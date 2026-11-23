<?php

namespace Tests\\Feature\\Api;

use App\\Contracts\\MobileOtpSender;
use App\\Models\\Shop;
use App\\Models\\User;
use Illuminate\\Foundation\\Testing\\RefreshDatabase;
use Tests\\TestCase;

class MobileOtpAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mobile_otp.sender' => FakeMobileOtpSender::class,
            'mobile_otp.token_expiry_days' => 30,
        ]);
    }

    public function test_request_and_verification_issue_a_sanctum_token_and_verify_mobile(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'mobile' => '9876543210',
            'is_active' => true,
            'mobile_verified_at' => null,
        ]);

        $sender = new FakeMobileOtpSender;
        $this->app->instance(MobileOtpSender::class, $sender);

        $this->postJson('/api/auth/mobile/request-otp', ['mobile' => $user->mobile])
            ->assertAccepted()
            ->assertJsonMissingPath('otp');

        $this->assertNotNull($sender->code);
        $this->assertDatabaseHas('mobile_otp_challenges', [
            'mobile' => $user->mobile,
            'consumed_at' => null,
        ]);

        $response = $this->postJson('/api/auth/mobile/verify-otp', [
            'mobile' => $user->mobile,
            'otp' => $sender->code,
        ])->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id);

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotNull($user->fresh()->mobile_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($response->json('token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('shop.id', $shop->id);
    }

    public function test_unknown_mobile_gets_generic_response_without_creating_challenge(): void
    {
        $sender = new FakeMobileOtpSender;
        $this->app->instance(MobileOtpSender::class, $sender);

        $this->postJson('/api/auth/mobile/request-otp', ['mobile' => '9876543210'])
            ->assertAccepted()
            ->assertJsonMissingPath('otp');

        $this->assertNull($sender->code);
        $this->assertDatabaseCount('mobile_otp_challenges', 0);
    }

    public function test_inactive_user_cannot_receive_or_verify_mobile_otp(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'mobile' => '9876543210',
            'is_active' => false,
        ]);

        $sender = new FakeMobileOtpSender;
        $this->app->instance(MobileOtpSender::class, $sender);

        $this->postJson('/api/auth/mobile/request-otp', ['mobile' => $user->mobile])
            ->assertAccepted();

        $this->assertNull($sender->code);
        $this->assertDatabaseCount('mobile_otp_challenges', 0);
    }

    public function test_suspended_shop_cannot_receive_mobile_otp(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_SUSPENDED]);
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'mobile' => '9876543210',
            'is_active' => true,
        ]);

        $sender = new FakeMobileOtpSender;
        $this->app->instance(MobileOtpSender::class, $sender);

        $this->postJson('/api/auth/mobile/request-otp', ['mobile' => $user->mobile])
            ->assertAccepted();

        $this->assertNull($sender->code);
        $this->assertDatabaseCount('mobile_otp_challenges', 0);
    }

    public function test_wrong_otp_increments_attempts_and_never_issues_a_token(): void
    {
        $user = $this->createEligibleUser();
        $sender = $this->requestOtp($user);

        $this->postJson('/api/auth/mobile/verify-otp', [
            'mobile' => $user->mobile,
            'otp' => $sender->code === '000000' ? '000001' : '000000',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('mobile_otp_challenges', [
            'mobile' => $user->mobile,
            'attempts' => 1,
            'consumed_at' => null,
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_otp_is_single_use_and_logout_revokes_current_token(): void
    {
        $user = $this->createEligibleUser();
        $sender = $this->requestOtp($user);

        $tokenResponse = $this->postJson('/api/auth/mobile/verify-otp', [
            'mobile' => $user->mobile,
            'otp' => $sender->code,
        ])->assertOk();

        $plainTextToken = $tokenResponse->json('token');

        $this->postJson('/api/auth/mobile/verify-otp', [
            'mobile' => $user->mobile,
            'otp' => $sender->code,
        ])->assertUnprocessable();

        $this->withToken($plainTextToken)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_resend_is_blocked_during_the_cooldown(): void
    {
        $user = $this->createEligibleUser();
        $sender = $this->requestOtp($user);
        $firstCode = $sender->code;

        $this->postJson('/api/auth/mobile/request-otp', ['mobile' => $user->mobile])
            ->assertAccepted();

        $this->assertSame($firstCode, $sender->code);
        $this->assertDatabaseCount('mobile_otp_challenges', 1);
    }

    public function test_five_incorrect_attempts_consume_the_challenge(): void
    {
        $user = $this->createEligibleUser();
        $sender = $this->requestOtp($user);
        $wrongCode = $sender->code === '000000' ? '000001' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/mobile/verify-otp', [
                'mobile' => $user->mobile,
                'otp' => $wrongCode,
            ])->assertUnprocessable();
        }

        $this->assertDatabaseHas('mobile_otp_challenges', [
            'mobile' => $user->mobile,
            'attempts' => 5,
        ]);
        $this->assertNotNull(
            \\Illuminate\\Support\\Facades\\DB::table('mobile_otp_challenges')
                ->where('mobile', $user->mobile)
                ->value('consumed_at')
        );
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expired_otp_cannot_be_verified(): void
    {
        $user = $this->createEligibleUser();
        $sender = $this->requestOtp($user);

        $this->travel(6)->minutes();

        $this->postJson('/api/auth/mobile/verify-otp', [
            'mobile' => $user->mobile,
            'otp' => $sender->code,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verify_rejects_invalid_mobile_format(): void
    {
        $this->postJson('/api/auth/mobile/verify-otp', [
            'mobile' => 'invalid-number',
            'otp' => '123456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('mobile');
    }

    public function test_token_expiry_is_applied_to_the_sanctum_token(): void
    {
        config(['mobile_otp.token_expiry_days' => 12]);
        $user = $this->createEligibleUser();
        $sender = $this->requestOtp($user);

        $this->postJson('/api/auth/mobile/verify-otp', [
            'mobile' => $user->mobile,
            'otp' => $sender->code,
        ])->assertOk();

        $this->assertEquals(
            now()->addDays(12)->toDateTimeString(),
            \\Illuminate\\Support\\Facades\\DB::table('personal_access_tokens')
                ->where('tokenable_id', $user->id)
                ->value('expires_at')
        );
    }

    public function test_mobile_request_rate_limit_is_enforced(): void
    {
        $user = $this->createEligibleUser();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/auth/mobile/request-otp', ['mobile' => $user->mobile])
                ->assertAccepted();
        }

        $this->postJson('/api/auth/mobile/request-otp', ['mobile' => $user->mobile])
            ->assertTooManyRequests();
    }

    private function createEligibleUser(): User
    {
        $shop = Shop::factory()->create();

        return User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'mobile' => '9876543210',
            'is_active' => true,
        ]);
    }

    private function requestOtp(User $user): FakeMobileOtpSender
    {
        $sender = new FakeMobileOtpSender;
        $this->app->instance(MobileOtpSender::class, $sender);

        $this->postJson('/api/auth/mobile/request-otp', ['mobile' => $user->mobile])
            ->assertAccepted();

        return $sender;
    }
}

class FakeMobileOtpSender implements MobileOtpSender
{
    public ?string $code = null;

    public function send(string $mobile, string $code): void
    {
        $this->code = $code;
    }
}

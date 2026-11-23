<?php

namespace Tests\Feature\Api;

use App\Contracts\MobileOtpSender;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

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
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
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
        $shop = Shop::factory()->create();
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'mobile' => '9876543210',
            'is_active' => true,
        ]);

        $sender = new FakeMobileOtpSender;
        $this->app->instance(MobileOtpSender::class, $sender);

        $this->postJson('/api/auth/mobile/request-otp', ['mobile' => $user->mobile])->assertAccepted();

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
        $shop = Shop::factory()->create();
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'mobile' => '9876543210',
            'is_active' => true,
        ]);

        $sender = new FakeMobileOtpSender;
        $this->app->instance(MobileOtpSender::class, $sender);

        $this->postJson('/api/auth/mobile/request-otp', ['mobile' => $user->mobile])->assertAccepted();

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
}

class FakeMobileOtpSender implements MobileOtpSender
{
    public ?string $code = null;

    public function send(string $mobile, string $code): void
    {
        $this->code = $code;
    }
}

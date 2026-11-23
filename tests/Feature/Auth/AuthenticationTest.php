<?php

namespace Tests\Feature\Auth;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_registration_creates_shop_and_user_atomically_and_logs_in(): void
    {
        $response = $this->post(route('register'), [
            'shop_name' => 'Shree Kisan Seva',
            'owner_name' => 'Ramesh Gurjar',
            'mobile' => '9876543210',
            'email' => 'owner@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'shop_id' => 999,
            'role' => User::ROLE_PLATFORM_OWNER,
        ]);

        $shop = Shop::query()->where('name', 'Shree Kisan Seva')->firstOrFail();
        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame($shop->id, $user->shop_id);
        $this->assertSame(User::ROLE_OWNER, $user->role);
        $this->assertTrue(Hash::check('StrongPass123', $user->password));
        $this->assertDatabaseCount('shops', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_rejects_duplicate_owner_mobile(): void
    {
        $shop = Shop::factory()->create();
        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'mobile' => '9876543210',
        ]);

        $response = $this->from(route('register'))->post(route('register'), [
            'shop_name' => 'Another Shop',
            'owner_name' => 'Another Owner',
            'mobile' => '9876543210',
            'email' => 'another@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('mobile');
        $this->assertDatabaseCount('shops', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_user_can_login_and_logout_with_session_authentication(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'email' => 'login@example.com',
            'password' => Hash::make('StrongPass123'),
            'is_active' => true,
        ]);

        $this->post(route('login'), [
            'email' => 'login@example.com',
            'password' => 'StrongPass123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard'))->assertOk();

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_shop_users_cannot_login(): void
    {
        $shop = Shop::factory()->create(['status' => Shop::STATUS_SUSPENDED]);
        User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'email' => 'suspended@example.com',
            'password' => Hash::make('StrongPass123'),
            'is_active' => true,
        ]);

        $this->from(route('login'))->post(route('login'), [
            'email' => 'suspended@example.com',
            'password' => 'StrongPass123',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_tenant_login_rejects_users_without_a_shop(): void
    {
        User::factory()->create([
            'shop_id' => null,
            'role' => User::ROLE_PLATFORM_OWNER,
            'email' => 'platform@example.com',
            'password' => Hash::make('StrongPass123'),
            'is_active' => true,
        ]);

        $this->from(route('login'))->post(route('login'), [
            'email' => 'platform@example.com',
            'password' => 'StrongPass123',
        ])->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}

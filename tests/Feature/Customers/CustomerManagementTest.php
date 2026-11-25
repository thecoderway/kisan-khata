<?php

namespace Tests\Feature\Customers;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\ShopSetting;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function seedVillage(): void
    {
        DB::table('villages')->insert([
            'villageCode' => '1001', 'villageNameEnglish' => 'Lotiya', 'villageNameLocal' => 'लोटिया',
            'subdistrictCode' => '201', 'subdistrictNameEnglish' => 'Sarangpur', 'subdistrictNameLocal' => 'सारंगपुर',
            'districtCode' => '301', 'districtNameEnglish' => 'Rajgarh', 'districtNameLocal' => 'राजगढ़',
            'stateCode' => '23', 'stateNameEnglish' => 'Madhya Pradesh', 'stateNameLocal' => 'मध्य प्रदेश',
            'data_gov_update_date' => '21-04-2026',
        ]);
    }

    private function makeUser(string $role = User::ROLE_OWNER, ?Shop $shop = null): User
    {
        $shop ??= Shop::factory()->create();

        return User::factory()->create([
            'shop_id' => $shop->id, 'role' => $role, 'is_active' => true,
        ]);
    }

    private function customerPayload(array $overrides = []): array
    {
        return array_merge([
            'name_original' => 'रामलाल', 'name_original_language' => 'hi',
            'name_hi' => 'रामलाल', 'name_en' => 'Ramlal', 'mobile' => '9876543210',
            'state_code' => null, 'district_code' => null, 'subdistrict_code' => null, 'village_code' => null,
            'address_original' => 'मुख्य मार्ग', 'address_original_language' => 'hi',
            'address_hi' => 'मुख्य मार्ग', 'address_en' => 'Main Road', 'notes' => 'पहला ग्राहक',
        ], $overrides);
    }

    public function test_owner_can_create_customer_with_independent_lgd_location_and_audit_record(): void
    {
        $this->seedVillage();
        $owner = $this->makeUser();

        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload([
            'state_code' => '23', 'district_code' => '301', 'subdistrict_code' => '201', 'village_code' => '1001',
        ]))->assertRedirect();

        $customer = DB::table('customers')->where('shop_id', $owner->shop_id)->first();
        $this->assertNotNull($customer);
        $this->assertSame($owner->id, $customer->created_by);
        $this->assertSame('1001', $customer->village_code);
        $this->assertSame('रामलाल', $customer->name_original);
        $this->assertSame('Ramlal', $customer->name_en);

        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $owner->shop_id, 'user_id' => $owner->id,
            'action' => 'customer.created', 'entity_type' => 'customer', 'entity_id' => $customer->id,
        ]);
    }

    public function test_manager_and_staff_can_create_and_edit_customer_profiles(): void
    {
        foreach ([User::ROLE_MANAGER, User::ROLE_STAFF] as $role) {
            $shop = Shop::factory()->create();
            $user = $this->makeUser($role, $shop);

            $this->actingAs($user)->post(route('customers.store'), $this->customerPayload([
                'name_original' => 'ग्राहक '.$role,
            ]))->assertRedirect();

            $customer = DB::table('customers')->where('shop_id', $shop->id)->first();
            $this->assertNotNull($customer);

            $this->actingAs($user)->put(route('customers.update', $customer->id), $this->customerPayload([
                'name_original' => 'अपडेट '.$role,
            ]))->assertRedirect();

            $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name_original' => 'अपडेट '.$role]);
        }
    }

    public function test_customer_name_is_required_and_location_hierarchy_is_validated(): void
    {
        $this->seedVillage();
        $owner = $this->makeUser();

        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload([
            'name_original' => '',
        ]))->assertSessionHasErrors('name_original');

        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload([
            'state_code' => '23', 'district_code' => '301', 'subdistrict_code' => '201', 'village_code' => '9999',
        ]))->assertSessionHasErrors('village_code');

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_customer_location_defaults_to_shop_location_and_can_be_cleared(): void
    {
        $this->seedVillage();
        $shop = Shop::factory()->create();
        DB::table('shops')->where('id', $shop->id)->update([
            'state_code' => '23', 'district_code' => '301', 'subdistrict_code' => '201', 'village_code' => '1001',
        ]);
        $owner = $this->makeUser(User::ROLE_OWNER, $shop);

        $this->actingAs($owner)->get(route('customers.create'))
            ->assertOk()->assertSee('1001')->assertSee('201');

        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload())->assertRedirect();
        $this->assertDatabaseHas('customers', [
            'shop_id' => $shop->id, 'village_code' => null, 'state_code' => null,
        ]);
    }

    public function test_customer_location_does_not_change_when_shop_location_changes(): void
    {
        $this->seedVillage();
        $owner = $this->makeUser();
        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload([
            'state_code' => '23', 'district_code' => '301', 'subdistrict_code' => '201', 'village_code' => '1001',
        ]))->assertRedirect();
        $customer = DB::table('customers')->where('shop_id', $owner->shop_id)->first();

        DB::table('shops')->where('id', $owner->shop_id)->update([
            'state_code' => null, 'district_code' => null, 'subdistrict_code' => null, 'village_code' => null,
        ]);

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'village_code' => '1001']);
    }

    public function test_customer_search_matches_original_translated_names_and_mobile(): void
    {
        $owner = $this->makeUser();
        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload())->assertRedirect();
        $this->post(route('customers.store'), $this->customerPayload([
            'name_original' => 'Mohan', 'name_original_language' => 'en',
            'name_hi' => 'मोहन', 'name_en' => 'Mohan', 'mobile' => '9123456780',
        ]))->assertRedirect();

        $this->get(route('customers.index', ['q' => 'राम']))->assertOk()->assertSee('रामलाल')->assertDontSee('Mohan');
        $this->get(route('customers.index', ['q' => '987654']))->assertOk()->assertSee('रामलाल');
        $this->get(route('customers.index', ['q' => 'Mohan']))->assertOk()->assertSee('Mohan');
    }

    public function test_customer_audit_history_records_old_and_new_values(): void
    {
        $owner = $this->makeUser();
        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload())->assertRedirect();
        $customer = DB::table('customers')->where('shop_id', $owner->shop_id)->first();

        $this->put(route('customers.update', $customer->id), $this->customerPayload([
            'name_original' => 'रामलाल सिंह',
        ]))->assertRedirect();

        $audit = DB::table('audit_logs')->where('entity_id', $customer->id)
            ->where('action', 'customer.updated')->first();
        $this->assertNotNull($audit);
        $this->assertSame('रामलाल', json_decode($audit->old_values, true)['name_original']);
        $this->assertSame('रामलाल सिंह', json_decode($audit->new_values, true)['name_original']);
    }

    public function test_customer_records_are_isolated_between_shops(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();
        $ownerA = $this->makeUser(User::ROLE_OWNER, $shopA);
        $ownerB = $this->makeUser(User::ROLE_OWNER, $shopB);

        $this->actingAs($ownerA)->post(route('customers.store'), $this->customerPayload([
            'name_original' => 'Private Customer', 'name_original_language' => 'en',
        ]))->assertRedirect();
        $customer = DB::table('customers')->where('shop_id', $shopA->id)->first();

        $this->actingAs($ownerB)->get(route('customers.show', $customer->id))->assertNotFound();
        $this->get(route('customers.index'))->assertOk()->assertDontSee('Private Customer');
    }

    public function test_api_customer_endpoints_use_authenticated_shop_and_role(): void
    {
        $owner = $this->makeUser();
        Sanctum::actingAs($owner, ['app']);

        $this->postJson('/api/v1/customers', $this->customerPayload([
            'shop_id' => 999999,
        ]))->assertCreated()->assertJsonPath('data.name_original', 'रामलाल')
            ->assertJsonMissingPath('data.shop_id');

        $this->assertDatabaseHas('customers', ['shop_id' => $owner->shop_id, 'name_original' => 'रामलाल']);
    }

    public function test_customer_bulk_delete_is_blocked_to_preserve_lifecycle_and_audit_rules(): void
    {
        $owner = $this->makeUser();
        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload([
            'name_original' => 'Bulk Delete Guard',
        ]))->assertRedirect();
        $customer = DB::table('customers')->where('shop_id', $owner->shop_id)->first();

        app(CurrentShop::class)->set($owner->shop);

        try {
            Customer::query()->whereKey($customer->id)->delete();
            $this->fail('Bulk deletion of soft-deletable customers must be rejected.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Bulk deletion is disabled', $exception->getMessage());
        }

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'shop_id' => $owner->shop_id,
            'deleted_at' => null,
        ]);
    }

    public function test_customer_bulk_delete_requires_a_current_shop_context(): void
    {
        $owner = $this->makeUser();
        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload())->assertRedirect();
        $customer = DB::table('customers')->where('shop_id', $owner->shop_id)->first();

        app(CurrentShop::class)->clear();

        try {
            Customer::query()->whereKey($customer->id)->delete();
            $this->fail('Tenant-owned bulk deletion must require a current shop.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('current shop context is required', $exception->getMessage());
        }

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]);
    }

    public function test_bulk_delete_remains_available_for_tenant_models_without_soft_deletes(): void
    {
        $owner = $this->makeUser();
        app(CurrentShop::class)->set($owner->shop);

        $setting = ShopSetting::query()->create([
            'setting_key' => 'temporary.test',
            'setting_value' => 'value',
        ]);

        $deleted = ShopSetting::query()->whereKey($setting->id)->delete();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('shop_settings', ['id' => $setting->id]);
    }

    public function test_customer_deletion_is_not_exposed_or_authorized(): void
    {
        $owner = $this->makeUser();
        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload([
            'name_original' => 'Protected',
        ]))->assertRedirect();
        $customerRow = DB::table('customers')->where('shop_id', $owner->shop_id)->first();
        $customer = (new Customer)->newFromBuilder((array) $customerRow);

        $this->assertFalse(Route::has('customers.destroy'));
        $this->assertFalse(Gate::forUser($owner)->allows('delete', $customer));
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]);
    }

    public function test_owner_and_manager_can_deactivate_and_reactivate_with_audit_history(): void
    {
        foreach ([User::ROLE_OWNER, User::ROLE_MANAGER] as $role) {
            $shop = Shop::factory()->create();
            $actor = $this->makeUser($role, $shop);
            $this->actingAs($actor)->post(route('customers.store'), $this->customerPayload())->assertRedirect();
            $customer = DB::table('customers')->where('shop_id', $shop->id)->first();

            $this->post(route('customers.deactivate', $customer->id), ['reason' => 'Customer requested pause'])
                ->assertRedirect();

            $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'inactive', 'deleted_at' => null]);
            $this->assertDatabaseHas('audit_logs', [
                'shop_id' => $shop->id, 'user_id' => $actor->id,
                'action' => 'customer.deactivated', 'entity_id' => $customer->id,
                'reason' => 'Customer requested pause',
            ]);

            $this->post(route('customers.reactivate', $customer->id), ['reason' => 'Customer returned'])
                ->assertRedirect();

            $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'active', 'deleted_at' => null]);
            $this->assertDatabaseHas('audit_logs', [
                'shop_id' => $shop->id, 'user_id' => $actor->id,
                'action' => 'customer.reactivated', 'entity_id' => $customer->id,
            ]);
        }
    }

    public function test_staff_cannot_run_customer_lifecycle_actions(): void
    {
        $shop = Shop::factory()->create();
        $staff = $this->makeUser(User::ROLE_STAFF, $shop);
        $this->actingAs($staff)->post(route('customers.store'), $this->customerPayload())->assertRedirect();
        $customer = DB::table('customers')->where('shop_id', $shop->id)->first();

        $this->post(route('customers.deactivate', $customer->id), ['reason' => 'Not authorized'])
            ->assertForbidden();

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'active']);
        $this->assertDatabaseMissing('audit_logs', [
            'entity_id' => $customer->id, 'action' => 'customer.deactivated',
        ]);
    }

    public function test_customer_deactivation_requires_a_nonblank_reason_and_valid_state(): void
    {
        $owner = $this->makeUser();
        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload())->assertRedirect();
        $customer = DB::table('customers')->where('shop_id', $owner->shop_id)->first();

        $this->post(route('customers.deactivate', $customer->id), ['reason' => '   '])
            ->assertSessionHasErrors('reason');
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'status' => 'active']);

        $this->post(route('customers.deactivate', $customer->id), ['reason' => 'Valid reason'])->assertRedirect();
        $this->post(route('customers.deactivate', $customer->id), ['reason' => 'Duplicate transition'])
            ->assertSessionHasErrors('status');
    }

    public function test_customer_soft_delete_fails_closed_when_financial_dependencies_are_not_verifiable(): void
    {
        $owner = $this->makeUser();
        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload())->assertRedirect();
        $customer = DB::table('customers')->where('shop_id', $owner->shop_id)->first();

        $this->delete(route('customers.soft-delete', $customer->id), ['reason' => 'Requested cleanup'])
            ->assertSessionHasErrors('customer');

        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('audit_logs', [
            'entity_id' => $customer->id, 'action' => 'customer.soft_deleted',
        ]);
    }

    public function test_restore_preserves_inactive_status_and_creates_audit_event(): void
    {
        $owner = $this->makeUser();
        $this->actingAs($owner)->post(route('customers.store'), $this->customerPayload())->assertRedirect();
        $customer = DB::table('customers')->where('shop_id', $owner->shop_id)->first();

        DB::table('customers')->where('id', $customer->id)->update([
            'status' => 'inactive', 'deleted_at' => now(),
        ]);

        $this->post(route('customers.restore', $customer->id))->assertRedirect();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id, 'status' => 'inactive', 'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $owner->shop_id, 'user_id' => $owner->id,
            'action' => 'customer.restored', 'entity_id' => $customer->id,
        ]);
    }

}

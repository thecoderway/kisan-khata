<?php

namespace Tests\Feature\Shops;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopProfileTest extends TestCase
{
    use RefreshDatabase;

    private function seedVillageHierarchy(): void
    {
        DB::table('villages')->insert([
            [
                'villageCode' => '1001',
                'villageNameEnglish' => 'Lotiya',
                'villageNameLocal' => 'लोटिया',
                'subdistrictCode' => '201',
                'subdistrictNameEnglish' => 'Sarangpur',
                'subdistrictNameLocal' => 'सारंगपुर',
                'districtCode' => '301',
                'districtNameEnglish' => 'Rajgarh',
                'districtNameLocal' => 'राजगढ़',
                'stateCode' => '23',
                'stateNameEnglish' => 'Madhya Pradesh',
                'stateNameLocal' => 'मध्य प्रदेश',
                'data_gov_update_date' => '21-04-2026',
            ],
            [
                'villageCode' => '1002',
                'villageNameEnglish' => 'Other Village',
                'villageNameLocal' => 'अन्य गाँव',
                'subdistrictCode' => '202',
                'subdistrictNameEnglish' => 'Other Tehsil',
                'subdistrictNameLocal' => 'अन्य तहसील',
                'districtCode' => '302',
                'districtNameEnglish' => 'Other District',
                'districtNameLocal' => 'अन्य जिला',
                'stateCode' => '23',
                'stateNameEnglish' => 'Madhya Pradesh',
                'stateNameLocal' => 'मध्य प्रदेश',
                'data_gov_update_date' => '21-04-2026',
            ],
            [
                'villageCode' => '1003',
                'villageNameEnglish' => 'Third Village',
                'villageNameLocal' => 'तीसरा गाँव',
                'subdistrictCode' => '203',
                'subdistrictNameEnglish' => 'Third Tehsil',
                'subdistrictNameLocal' => 'तीसरी तहसील',
                'districtCode' => '301',
                'districtNameEnglish' => 'Rajgarh',
                'districtNameLocal' => 'राजगढ़',
                'stateCode' => '23',
                'stateNameEnglish' => 'Madhya Pradesh',
                'stateNameLocal' => 'मध्य प्रदेश',
                'data_gov_update_date' => '21-04-2026',
            ],
            [
                'villageCode' => '1004',
                'villageNameEnglish' => 'New State Village',
                'villageNameLocal' => 'नया राज्य गाँव',
                'subdistrictCode' => '401',
                'subdistrictNameEnglish' => 'New State Tehsil',
                'subdistrictNameLocal' => 'नई तहसील',
                'districtCode' => '401',
                'districtNameEnglish' => 'New State District',
                'districtNameLocal' => 'नया जिला',
                'stateCode' => '24',
                'stateNameEnglish' => 'Other State',
                'stateNameLocal' => 'अन्य राज्य',
                'data_gov_update_date' => '21-04-2026',
            ],
        ]);
    }

    public function test_shop_owner_can_update_profile_and_lgd_location(): void
    {
        $this->seedVillageHierarchy();
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->put(route('shop-profile.update'), [
            'name' => 'Lotiya Kisan Store',
            'owner_name' => 'Pawan Gurjar',
            'mobile' => '9999999999',
            'email' => 'shop@example.com',
            'address' => 'Main Road',
            'pincode' => '465677',
            'state_code' => '23',
            'district_code' => '301',
            'subdistrict_code' => '201',
            'village_code' => '1001',
        ])->assertRedirect(route('shop-profile.edit'))->assertSessionHas('status');

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'name' => 'Lotiya Kisan Store',
            'village_code' => '1001',
            'subdistrict_code' => '201',
            'district_code' => '301',
            'state_code' => '23',
        ]);
    }

    public function test_shop_profile_is_forbidden_to_manager_and_staff(): void
    {
        $shop = Shop::factory()->create();

        foreach ([User::ROLE_MANAGER, User::ROLE_STAFF] as $role) {
            $user = User::factory()->create([
                'shop_id' => $shop->id,
                'role' => $role,
                'is_active' => true,
            ]);

            $this->actingAs($user)->get(route('shop-profile.edit'))->assertForbidden();
            $this->actingAs($user)->put(route('shop-profile.update'), [
                'name' => 'Tampered Shop',
                'owner_name' => 'Unauthorized User',
                'mobile' => '9999999999',
            ])->assertForbidden();
        }
    }

    public function test_changing_state_clears_omitted_descendant_codes(): void
    {
        $this->seedVillageHierarchy();
        $shop = Shop::factory()->create();
        DB::table('shops')->where('id', $shop->id)->update([
            'state_code' => '23',
            'district_code' => '301',
            'subdistrict_code' => '201',
            'village_code' => '1001',
        ]);
        $shop->refresh();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->put(route('shop-profile.update'), [
            'name' => $shop->name,
            'owner_name' => 'Owner',
            'mobile' => '9999999999',
            'state_code' => '24',
        ])->assertRedirect(route('shop-profile.edit'));

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'state_code' => '24',
            'district_code' => null,
            'subdistrict_code' => null,
            'village_code' => null,
        ]);
    }

    public function test_changing_district_clears_omitted_subdistrict_and_village(): void
    {
        $this->seedVillageHierarchy();
        $shop = Shop::factory()->create();
        DB::table('shops')->where('id', $shop->id)->update([
            'state_code' => '23',
            'district_code' => '301',
            'subdistrict_code' => '201',
            'village_code' => '1001',
        ]);
        $shop->refresh();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->put(route('shop-profile.update'), [
            'name' => $shop->name,
            'owner_name' => 'Owner',
            'mobile' => '9999999999',
            'state_code' => '23',
            'district_code' => '302',
        ])->assertRedirect(route('shop-profile.edit'));

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'district_code' => '302',
            'subdistrict_code' => null,
            'village_code' => null,
        ]);
    }

    public function test_changing_subdistrict_clears_omitted_village(): void
    {
        $this->seedVillageHierarchy();
        $shop = Shop::factory()->create();
        DB::table('shops')->where('id', $shop->id)->update([
            'state_code' => '23',
            'district_code' => '301',
            'subdistrict_code' => '201',
            'village_code' => '1001',
        ]);
        $shop->refresh();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->put(route('shop-profile.update'), [
            'name' => $shop->name,
            'owner_name' => 'Owner',
            'mobile' => '9999999999',
            'state_code' => '23',
            'district_code' => '301',
            'subdistrict_code' => '203',
        ])->assertRedirect(route('shop-profile.edit'));

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'subdistrict_code' => '203',
            'village_code' => null,
        ]);
    }

    public function test_location_update_rejects_a_village_outside_selected_hierarchy(): void
    {
        $this->seedVillageHierarchy();
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->put(route('shop-profile.update'), [
            'name' => 'Lotiya Kisan Store',
            'owner_name' => 'Pawan Gurjar',
            'mobile' => '9999999999',
            'state_code' => '23',
            'district_code' => '301',
            'subdistrict_code' => '201',
            'village_code' => '1002',
        ])->assertSessionHasErrors('village_code');

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'name' => $shop->name,
            'village_code' => null,
        ]);
    }

    public function test_location_lookup_returns_distinct_states_and_filtered_districts(): void
    {
        $this->seedVillageHierarchy();
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $this->actingAs($owner)->getJson(route('shop-profile.locations.states'))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.code', '23');

        $this->actingAs($owner)->getJson(route('shop-profile.locations.districts', ['state' => '23']))
            ->assertOk()->assertJsonCount(2, 'data');

        $this->getJson(route('shop-profile.locations.subdistricts', [
            'state' => '23',
            'district' => '301',
        ]))->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonFragment(['code' => '201', 'name' => 'Sarangpur']);

        $this->getJson(route('shop-profile.locations.villages', [
            'state' => '23',
            'district' => '301',
            'subdistrict' => '201',
        ]))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', '1001');
    }
}

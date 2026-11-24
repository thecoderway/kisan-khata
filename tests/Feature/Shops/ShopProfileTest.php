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
        $manager = User::factory()->create([
            'shop_id' => $shop->id,
            'role' => User::ROLE_MANAGER,
            'is_active' => true,
        ]);

        $this->actingAs($manager)->get(route('shop-profile.edit'))->assertForbidden();
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
    }
}

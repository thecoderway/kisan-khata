<?php

namespace Tests\Feature\Financial;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinancialVillageFilterViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_payments_and_reports_render_searchable_shop_village_filters(): void
    {
        $shop = Shop::factory()->create();
        $owner = User::factory()->create([
            'shop_id' => $shop->getKey(),
            'role' => User::ROLE_OWNER,
            'is_active' => true,
        ]);

        $villages = [
            ['480058', 'Lotia', 'लोटिया', '3567', 'Sarangpur', 'सारंगपुर'],
            ['480059', 'Biaora', 'ब्यावरा', '3568', 'Biaora', 'ब्यावरा'],
            ['480060', 'Narsinghgarh', 'नरसिंहगढ़', '3569', 'Narsinghgarh', 'नरसिंहगढ़'],
        ];

        foreach ($villages as [$code, $english, $local, $subdistrictCode, $subdistrictEnglish, $subdistrictLocal]) {
            DB::table('villages')->insert([
                'villageCode' => $code,
                'villageNameEnglish' => $english,
                'villageNameLocal' => $local,
                'subdistrictCode' => $subdistrictCode,
                'subdistrictNameEnglish' => $subdistrictEnglish,
                'subdistrictNameLocal' => $subdistrictLocal,
                'districtCode' => '422',
                'districtNameEnglish' => 'Rajgarh',
                'districtNameLocal' => 'राजगढ़',
                'stateCode' => '23',
                'stateNameEnglish' => 'Madhya Pradesh',
                'stateNameLocal' => 'मध्य प्रदेश',
                'data_gov_update_date' => '21-04-2026',
            ]);

            DB::table('customers')->insert([
                'shop_id' => $shop->getKey(),
                'name_original' => $english.' Customer',
                'name_original_language' => 'en',
                'name_en' => $english.' Customer',
                'village_code' => $code,
                'status' => Customer::STATUS_ACTIVE,
                'created_by' => $owner->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($owner);

        foreach (['sales', 'payments'] as $section) {
            $response = $this->get(route('financial.workspace', ['section' => $section]));

            $response->assertOk()
                ->assertSee('data-village-filter-search', false)
                ->assertSee('data-village-select="financial-village"', false)
                ->assertSee('data-search="Lotia, Sarangpur, Rajgarh"', false)
                ->assertSee('लोटिया, सारंगपुर, राजगढ़')
                ->assertSee('ब्यावरा, ब्यावरा, राजगढ़')
                ->assertSee('नरसिंहगढ़, नरसिंहगढ़, राजगढ़')
                ->assertSeeInOrder([
                    'for="financial-search"',
                    "@include('financial.partials.village-filter'",
                    'for="financial-from">तारीख से',
                    'for="financial-to">तारीख तक',
                ], false);

            $this->assertSame(1, substr_count($response->getContent(), 'id="financial-create"'));
        }

        $ledgerResponse = $this->get(route('financial.workspace', ['section' => 'ledger']));
        $ledgerResponse->assertOk();
        $this->assertSame(1, substr_count($ledgerResponse->getContent(), 'id="financial-ledger-print"'));

        $this->get(route('financial.workspace', ['section' => 'reports']))
            ->assertOk()
            ->assertSee('data-village-filter-search', false)
            ->assertSee('data-village-select="report-village"', false)
            ->assertSee('लोटिया, सारंगपुर, राजगढ़')
            ->assertSee('ब्यावरा, ब्यावरा, राजगढ़')
            ->assertSee('नरसिंहगढ़, नरसिंहगढ़, राजगढ़');
    }
}

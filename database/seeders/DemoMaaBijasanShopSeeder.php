<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Modules\Customers\Application\SaveCustomer;
use App\Modules\Financial\Application\PaymentPostingService;
use App\Modules\Financial\Application\ReconciliationService;
use App\Modules\Financial\Application\SalePostingService;
use App\Tenancy\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoMaaBijasanShopSeeder extends Seeder
{
    private const SHOP_NAME = 'Maa Bijasan Krishi Sewa Kendra';

    private const SHOP_EMAIL = 'demo-maa-bijasan@kisan-khata.test';

    private const CUSTOMER_COUNT = 40;

    public function run(): void
    {
        $password = (string) env('DEMO_SHOP_OWNER_PASSWORD');

        if ($password === '') {
            throw new RuntimeException(
                'Set DEMO_SHOP_OWNER_PASSWORD in the local environment before running the demo seeder.'
            );
        }

        DB::transaction(function () use ($password): void {
            $existingShop = Shop::query()
                ->where('name', self::SHOP_NAME)
                ->where('email', self::SHOP_EMAIL)
                ->first();

            if ($existingShop !== null) {
                $customerCount = DB::table('customers')->where('shop_id', $existingShop->getKey())->count();
                $saleCount = DB::table('sales')->where('shop_id', $existingShop->getKey())->count();
                $paymentCount = DB::table('payments')->where('shop_id', $existingShop->getKey())->count();

                if ($customerCount >= self::CUSTOMER_COUNT && $saleCount > 0 && $paymentCount > 0) {
                    $this->command?->info('Maa Bijasan demo shop is already seeded; no duplicate data was created.');

                    return;
                }

                throw new RuntimeException(
                    'A partial Maa Bijasan demo shop already exists. Inspect it before attempting another seed.'
                );
            }

            $shop = Shop::factory()->create([
                'name' => self::SHOP_NAME,
                'owner_name' => 'Demo Shop Owner',
                'email' => self::SHOP_EMAIL,
                'address' => 'Main Market Road',
                'village' => 'Sarangpur',
                'tehsil' => 'Sarangpur',
                'district' => 'Rajgarh',
                'state' => 'Madhya Pradesh',
                'pincode' => '465697',
                'timezone' => 'Asia/Kolkata',
                'preferred_language' => 'hi',
                'status' => Shop::STATUS_ACTIVE,
            ]);

            $owner = User::factory()->create([
                'shop_id' => $shop->getKey(),
                'name' => 'Maa Bijasan Demo Owner',
                'email' => (string) env('DEMO_SHOP_OWNER_EMAIL', 'maa-bijasan-owner@example.test'),
                'mobile' => fake()->unique()->numerify('9#########'),
                'password' => $password,
                'role' => User::ROLE_OWNER,
                'preferred_language' => 'hi',
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $currentShop = app(CurrentShop::class);
            $previousShop = $currentShop->get();
            $currentShop->set($shop);

            try {
                $this->createDefaultInterestPolicy($shop, $owner);

                $names = [
                    ['रामलाल', 'Ramlal'], ['मोहनलाल', 'Mohanlal'], ['कैलाश', 'Kailash'],
                    ['गोपाल', 'Gopal'], ['भेरूलाल', 'Bherulal'], ['शंकरलाल', 'Shankarlal'],
                    ['राधेश्याम', 'Radheshyam'], ['कमल', 'Kamal'], ['नारायण', 'Narayan'],
                    ['लक्ष्मण', 'Lakshman'], ['दिनेश', 'Dinesh'], ['सुरेश', 'Suresh'],
                    ['प्रहलाद', 'Prahlad'], ['बद्रीलाल', 'Badrilal'], ['मंगल', 'Mangal'],
                    ['राजेश', 'Rajesh'], ['गोविंद', 'Govind'], ['अर्जुन', 'Arjun'],
                    ['केशव', 'Keshav'], ['धर्मेंद्र', 'Dharmendra'],
                ];
                $villages = ['लोटिया', 'सारंगपुर', 'पचोर', 'पड़ाना', 'मलावर', 'तलेन', 'भैंसवा', 'काचरी'];
                $customerService = app(SaveCustomer::class);
                $saleService = app(SalePostingService::class);
                $paymentService = app(PaymentPostingService::class);
                $reconciliation = app(ReconciliationService::class);

                for ($index = 0; $index < self::CUSTOMER_COUNT; $index++) {
                    [$nameHi, $nameEn] = $names[$index % count($names)];
                    $sequence = $index + 1;
                    $customer = $customerService->create($owner, [
                        'customer_code' => sprintf('MBK-%04d', $sequence),
                        'name_original' => $nameHi,
                        'name_original_language' => 'hi',
                        'name_hi' => $nameHi,
                        'name_en' => $nameEn.' '.$sequence,
                        'mobile' => fake()->unique()->numerify('9#########'),
                        'address_original' => $villages[$index % count($villages)].', तहसील सारंगपुर, जिला राजगढ़',
                        'address_original_language' => 'hi',
                        'address_hi' => $villages[$index % count($villages)].', सारंगपुर, राजगढ़',
                        'address_en' => $villages[$index % count($villages)].', Sarangpur, Rajgarh',
                        'notes' => 'Synthetic demo customer '.$sequence.'; not a real person.',
                    ], null, null);

                    $baseDate = CarbonImmutable::parse('2026-05-15')->addDays($index % 20);
                    $paidAmount = (string) fake()->numberBetween(1200, 6500).'.00';
                    $creditAmount = (string) fake()->numberBetween(3000, 16000).'.00';

                    $saleService->post(
                        $owner,
                        $customer,
                        Sale::PAYMENT_CHOICE_PAID,
                        $baseDate,
                        $paidAmount,
                        'बीज / खाद / कृषि सामग्री — नकद बिक्री',
                        'demo-maa-bijasan-paid-sale-'.$sequence,
                    );

                    $creditSale = $saleService->post(
                        $owner,
                        $customer,
                        Sale::PAYMENT_CHOICE_UNPAID,
                        $baseDate->addDays(18),
                        $creditAmount,
                        'उधार कृषि सामग्री — खरीफ तैयारी',
                        'demo-maa-bijasan-credit-sale-'.$sequence,
                    );

                    $paymentAmount = number_format(((float) $creditAmount) * fake()->randomFloat(2, 0.20, 0.45), 2, '.', '');

                    $paymentService->post(
                        $owner,
                        $customer,
                        $paymentAmount,
                        CarbonImmutable::parse('2026-07-20')->addDays($index % 15),
                        ['CASH', 'UPI', 'BANK_TRANSFER', 'CHEQUE'][$index % 4],
                        'MBK-DEMO-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT),
                        'आंशिक भुगतान — demo data',
                        'demo-maa-bijasan-payment-'.$sequence,
                    );

                    $report = $reconciliation->reconcileCustomer($customer);

                    if (! $report['consistent']) {
                        throw new RuntimeException(
                            'Demo financial reconciliation failed for customer '.$customer->getKey().': '
                            .json_encode($report['discrepancies'], JSON_UNESCAPED_UNICODE)
                        );
                    }

                    if ((int) $creditSale->customer_id !== (int) $customer->getKey()) {
                        throw new RuntimeException('Demo Sale/customer ownership validation failed.');
                    }
                }

                $this->command?->info('Maa Bijasan demo shop seeded: 1 shop, 1 owner, 40 customers, 80 sales and 40 normal payments.');
            } finally {
                if ($previousShop !== null) {
                    $currentShop->set($previousShop);
                } else {
                    $currentShop->clear();
                }
            }
        }, 3);
    }

    private function createDefaultInterestPolicy(Shop $shop, User $owner): void
    {
        $policyId = DB::table('interest_policies')->insertGetId([
            'shop_id' => $shop->getKey(),
            'policy_code' => 'DEFAULT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('interest_policy_versions')->insert([
            'shop_id' => $shop->getKey(),
            'interest_policy_id' => $policyId,
            'version_number' => 1,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'monthly_rate_percent' => '1.50000000',
            'grace_days' => 30,
            'capitalization_enabled' => false,
            'payment_waterfall' => 'PRINCIPAL_FIRST',
            'annual_settlement_month_1' => 3,
            'annual_settlement_day_1' => 30,
            'annual_settlement_month_2' => 10,
            'annual_settlement_day_2' => 30,
            'created_by' => $owner->getKey(),
            'created_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature\Database;

use App\Models\Customer;
use App\Models\LedgerEntry;
use App\Models\Shop;
use App\Models\User;
use App\Tenancy\CurrentShop;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class GroupFourLedgerSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ledger_and_snapshot_tables_use_innodb_and_required_keys(): void
    {
        foreach (['ledger_entries', 'account_balance_snapshots'] as $table) {
            $engine = DB::table('information_schema.TABLES')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->value('ENGINE');

            $this->assertSame('InnoDB', $engine);
        }

        $this->assertContains(
            'le_shop_transaction_sequence_unique',
            collect(Schema::getIndexes('ledger_entries'))->pluck('name')
        );
        $this->assertContains(
            'abs_shop_customer_date_unique',
            collect(Schema::getIndexes('account_balance_snapshots'))->pluck('name')
        );
    }

    public function test_ledger_rejects_zero_or_two_sided_entries(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->fixture();

        try {
            $this->ledger($shop, $customer, $transaction, debit: '0.00', credit: '0.00');
            $this->fail('Expected zero-sided ledger entry to be rejected.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        $this->ledger($shop, $customer, $transaction, debit: '10.00', credit: '10.00');
    }

    public function test_snapshot_total_must_equal_component_balances(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->fixture();
        $ledgerId = $this->ledger($shop, $customer, $transaction);

        $this->expectException(QueryException::class);
        DB::table('account_balance_snapshots')->insert([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'as_of_date' => now()->toDateString(),
            'principal_balance' => '100.00',
            'interest_balance' => '5.00',
            'total_payable' => '99.00',
            'source_ledger_entry_id' => $ledgerId,
            'calculated_at' => now(),
            'created_at' => now(),
        ]);
    }

    public function test_ledger_model_populates_required_created_at_automatically(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->fixture();
        app(CurrentShop::class)->set($shop);

        $entry = LedgerEntry::create([
            'customer_id' => $customer->id,
            'financial_transaction_id' => $transaction,
            'component' => 'PRINCIPAL',
            'entry_kind' => 'SALE',
            'debit_amount' => '100.00',
            'credit_amount' => '0.00',
            'effective_date' => now()->toDateString(),
            'sequence_number' => 1,
        ]);

        $this->assertNotNull($entry->created_at);
        $this->assertDatabaseHas('ledger_entries', [
            'id' => $entry->id,
            'shop_id' => $shop->id,
        ]);
    }

    public function test_ledger_entries_are_append_only(): void
    {
        [$shop, $customer, $actor, $transaction] = $this->fixture();
        $id = $this->ledger($shop, $customer, $transaction);
        app(CurrentShop::class)->set($shop);

        $entry = LedgerEntry::withoutGlobalScopes()->findOrFail($id);

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    private function fixture(): array
    {
        $shop = Shop::factory()->create();
        $actor = User::factory()->create(['shop_id' => $shop->id, 'role' => User::ROLE_OWNER]);
        $id = (int) DB::table('customers')->insertGetId([
            'shop_id' => $shop->id,
            'created_by' => $actor->id,
            'name_original' => 'Ledger Customer',
            'name_original_language' => 'en',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customer = Customer::withoutGlobalScopes()->findOrFail($id);
        $transaction = (int) DB::table('financial_transactions')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'transaction_number' => 'G4-TXN-00000001',
            'transaction_type' => 'SALE_POSTING',
            'effective_date' => now()->toDateString(),
            'occurred_at' => now(),
            'created_by' => $actor->id,
            'posted_by' => $actor->id,
            'posted_at' => now(),
            'created_at' => now(),
        ]);

        return [$shop, $customer, $actor, $transaction];
    }

    private function ledger(
        Shop $shop,
        Customer $customer,
        int $transaction,
        string $debit = '100.00',
        string $credit = '0.00'
    ): int {
        static $sequence = 0;
        $sequence++;

        return (int) DB::table('ledger_entries')->insertGetId([
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'financial_transaction_id' => $transaction,
            'component' => 'PRINCIPAL',
            'entry_kind' => 'SALE',
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'effective_date' => now()->toDateString(),
            'sequence_number' => $sequence,
            'created_at' => now(),
        ]);
    }
}

<?php

namespace App\\Modules\\Financial\\Application;

use App\\Models\\AccountBalanceSnapshot;
use App\\Models\\Customer;
use App\\Models\\CustomerAccount;
use App\\Models\\FinancialTransaction;
use App\\Models\\LedgerEntry;
use App\\Models\\Sale;
use App\\Tenancy\\CurrentShop;
use Brick\\Math\\BigDecimal;
use DomainException;
use Illuminate\\Support\\Facades\\DB;
use LogicException;

class BalanceProjectionService
{
    public function __construct(
        private readonly CurrentShop $currentShop,
        private readonly CustomerAccountLockService $accountLock,
    ) {}

    public function rebuildForCustomer(Customer $customer): CustomerAccount
    {
        return DB::transaction(function () use ($customer): CustomerAccount {
            $account = $this->accountLock->lockForCustomer($customer);

            return $this->rebuildLocked($account, $customer);
        }, 3);
    }

    /**
     * Rebuild the current account projection from immutable ledger entries.
     * Callers that already own the customer/account lock use this method inside
     * their existing financial transaction.
     */
    public function rebuildLocked(
        CustomerAccount $account,
        Customer $customer,
        ?string $changedFromDate = null,
        ?int $sourceTransactionId = null,
    ): CustomerAccount {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('A balance projection rebuild requires an active financial transaction.');
        }

        $shopId = (int) $this->currentShop->require()->getKey();

        if (
            (int) $customer->shop_id !== $shopId
            || (int) $account->shop_id !== $shopId
            || (int) $account->customer_id !== (int) $customer->getKey()
        ) {
            throw new DomainException('The customer account projection must belong to the current Shop and Customer.');
        }

        $balances = $this->ledgerBalances($customer);

        if (BigDecimal::of($balances['principal_balance'])->isNegative()
            || BigDecimal::of($balances['interest_balance'])->isNegative()) {
            throw new DomainException('Ledger projection contains a negative principal or interest balance.');
        }

        $lastFinancialEventAt = DB::table('financial_transactions')
            ->where('shop_id', $shopId)
            ->where('customer_id', $customer->getKey())
            ->max('occurred_at');

        $account->forceFill([
            'principal_balance' => $balances['principal_balance'],
            'interest_balance' => $balances['interest_balance'],
            'total_payable' => $balances['total_payable'],
            'last_financial_event_at' => $lastFinancialEventAt,
            'projection_version' => (int) $account->projection_version + 1,
        ])->save();

        $this->rebuildSalePaymentStatuses($customer);
        $this->refreshSnapshots($customer, $changedFromDate, $sourceTransactionId);

        return $account->refresh();
    }

    /**
     * @return array{principal_balance:string,interest_balance:string,total_payable:string}
     */
    public function ledgerBalances(Customer $customer, ?string $asOfDate = null): array
    {
        $query = LedgerEntry::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey());

        if ($asOfDate !== null) {
            $query->where('effective_date', '<=', $asOfDate);
        }

        $principalEntries = (clone $query)->where('component', 'PRINCIPAL');
        $interestEntries = (clone $query)->where('component', 'INTEREST');

        $principal = BigDecimal::of((string) ((clone $principalEntries)->sum('debit_amount') ?: '0.00'))
            ->minus((string) ((clone $principalEntries)->sum('credit_amount') ?: '0.00'))
            ->toScale(2);
        $interest = BigDecimal::of((string) ((clone $interestEntries)->sum('debit_amount') ?: '0.00'))
            ->minus((string) ((clone $interestEntries)->sum('credit_amount') ?: '0.00'))
            ->toScale(2);

        return [
            'principal_balance' => (string) $principal,
            'interest_balance' => (string) $interest,
            'total_payable' => (string) $principal->plus($interest)->toScale(2),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function salePaymentStatuses(Customer $customer): array
    {
        $statuses = [];

        foreach (Sale::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey())
            ->whereNotNull('posted_at')
            ->orderBy('id')
            ->get() as $sale) {
            $entries = LedgerEntry::query()
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('sale_id', $sale->getKey());

            $balance = BigDecimal::of((string) ((clone $entries)->sum('debit_amount') ?: '0.00'))
                ->minus((string) ((clone $entries)->sum('credit_amount') ?: '0.00'))
                ->toScale(2);
            $paymentCredits = (string) LedgerEntry::query()
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('sale_id', $sale->getKey())
                ->where('entry_kind', 'PAYMENT')
                ->sum('credit_amount');
            $reversalDebits = (string) LedgerEntry::query()
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('sale_id', $sale->getKey())
                ->where('entry_kind', 'REVERSAL')
                ->sum('debit_amount');
            $netPayment = BigDecimal::of($paymentCredits ?: '0.00')
                ->minus($reversalDebits ?: '0.00');

            $statuses[(int) $sale->getKey()] = $balance->isZero()
                ? Sale::PAYMENT_STATUS_PAID
                : ($netPayment->isPositive()
                    ? Sale::PAYMENT_STATUS_PARTIALLY_PAID
                    : Sale::PAYMENT_STATUS_UNPAID);
        }

        return $statuses;
    }

    private function rebuildSalePaymentStatuses(Customer $customer): void
    {
        foreach ($this->salePaymentStatuses($customer) as $saleId => $status) {
            DB::table('sales')
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('id', $saleId)
                ->update(['payment_status' => $status]);
        }
    }

    private function refreshSnapshots(
        Customer $customer,
        ?string $changedFromDate,
        ?int $sourceTransactionId,
    ): void {
        $base = DB::table('account_balance_snapshots')
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey());

        if ($changedFromDate !== null) {
            $base->where('as_of_date', '>=', $changedFromDate);
        }

        $dates = $base->pluck('as_of_date')
            ->map(fn ($date): string => (string) $date)
            ->all();

        $newEntries = LedgerEntry::query()
            ->where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->getKey());

        if ($sourceTransactionId !== null) {
            $newEntries->where('financial_transaction_id', $sourceTransactionId);
        } elseif ($changedFromDate !== null) {
            $newEntries->where('effective_date', '>=', $changedFromDate);
        }

        $dates = array_values(array_unique([
            ...$dates,
            ...$newEntries->distinct()->orderBy('effective_date')->pluck('effective_date')
                ->map(fn ($date): string => (string) $date)->all(),
        ]));

        if ($changedFromDate === null && $sourceTransactionId === null) {
            $allDates = LedgerEntry::query()
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->distinct()
                ->orderBy('effective_date')
                ->pluck('effective_date')
                ->map(fn ($date): string => (string) $date)
                ->all();
            $dates = array_values(array_unique([...$dates, ...$allDates]));
        }

        sort($dates, SORT_STRING);

        foreach ($dates as $date) {
            $balances = $this->ledgerBalances($customer, $date);
            $sourceLedgerEntryId = LedgerEntry::query()
                ->where('shop_id', $customer->shop_id)
                ->where('customer_id', $customer->getKey())
                ->where('effective_date', '<=', $date)
                ->orderByDesc('effective_date')
                ->orderByDesc('id')
                ->value('id');

            $snapshot = AccountBalanceSnapshot::query()->firstOrNew([
                'shop_id' => $customer->shop_id,
                'customer_id' => $customer->getKey(),
                'as_of_date' => $date,
            ]);
            $snapshot->forceFill([
                ...$balances,
                'source_ledger_entry_id' => $sourceLedgerEntryId,
                'calculated_at' => now(),
            ])->save();
        }
    }
}

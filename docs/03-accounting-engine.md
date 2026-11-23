
# Kisan Khata — Accounting Engine Architecture

**Document Version:** 1.0

---

## 1. Purpose

This document defines the architecture of the financial calculation engine.

The accounting engine is responsible for:

- Recording financial transactions
- Maintaining customer balances
- Allocating payments
- Calculating interest
- Processing settlements
- Producing ledger entries
- Preserving financial history

The accounting engine must be independent of the web interface.

## 2. Core Principles

1. Financial records must be auditable.
2. Posted transactions must be immutable.
3. Principal and interest must remain distinguishable.
4. Every balance must be explainable.
5. Financial calculations must be deterministic.
6. Financial operations must be transactional.
7. Duplicate requests must not create duplicate financial entries.
8. Historical calculations must preserve the policy used.

## 3. Financial Data Types

Use MySQL DECIMAL for financial values.

Suggested initial types:

- Money: DECIMAL(15,2)
- Interest calculation precision: DECIMAL(20,8)
- Rate precision: DECIMAL(12,8)

These are proposed precision choices and should be validated against expected business limits.

Never use PHP float for authoritative financial calculations.

Use a suitable decimal arithmetic library or carefully designed integer minor-unit arithmetic.

## 4. Financial Concepts

### Principal

The original amount owed due to credit sales, less principal repayments and approved adjustments.

### Interest

The interest amount calculated under an approved policy.

### Payment

Money received from a customer.

### Allocation

The distribution of a payment across outstanding financial components.

### Settlement

An accounting event that closes or carries forward an account according to an approved settlement policy.

### Reversal

A compensating transaction that offsets an earlier posted transaction.

## 5. Suggested Transaction Model

Use a financial transaction header with associated entries.

Conceptual transaction types:

- OPENING_BALANCE
- CREDIT_SALE
- PAYMENT
- INTEREST_CHARGE
- SETTLEMENT
- ADJUSTMENT
- REVERSAL

Every transaction should include:

- Unique ID
- Shop ID
- Customer ID
- Transaction number
- Transaction type
- Effective date
- Created timestamp
- Posted timestamp
- Status
- Description
- Created by
- Reversal reference where applicable

## 6. Ledger Entry Model

A transaction may produce one or more ledger entries.

Each entry should identify:

- Financial component
- Debit amount
- Credit amount
- Effective date
- Transaction reference
- Source reference

Financial components may include:

- PRINCIPAL
- INTEREST

The ledger must support a deterministic running balance.

For customer receivables:

- Credit sale increases principal receivable.
- Payment reduces the allocated receivable.
- Interest charge increases interest receivable.
- Reversal offsets the original entry.
- Settlement follows its approved accounting treatment.

The final debit/credit presentation must be defined consistently throughout the application.

## 7. Payment Allocation Engine

The payment allocation engine determines how received money reduces outstanding balances.

Possible strategies:

- Interest first
- Principal first
- Oldest invoice first
- Manual allocation

The policy is not approved yet.

Suggested service interface:

`PaymentAllocationService`

Responsibilities:

1. Load eligible outstanding items.
2. Resolve the effective allocation policy.
3. Calculate allocations using decimal arithmetic.
4. Validate that allocations do not exceed payment amount.
5. Persist allocation records.
6. Update account projections safely.
7. Record audit information.

Do not implement a default allocation order without product owner approval.

## 8. Interest Engine

Suggested service:

`InterestCalculationService`

Responsibilities:

1. Load the approved interest policy.
2. Identify eligible principal balances.
3. Determine the interest-free period.
4. Determine the calculation interval.
5. Apply the approved rate.
6. Apply the day-count convention.
7. Apply partial-period rules.
8. Apply rounding rules.
9. Produce an explainable calculation result.
10. Persist a charge only when the posting workflow authorizes it.

### Interest Calculation Input

Conceptual input:

- Shop ID
- Customer ID
- Principal balance or eligible invoice balances
- Calculation start date
- Calculation end date
- Interest policy version
- Payment and adjustment history

### Interest Calculation Output

- Eligible principal
- Applicable rate
- Eligible period
- Number of days or months
- Calculation method
- Unrounded interest
- Rounded interest
- Policy version
- Explanation

The exact formula must be determined after policy approval.

Do not assume that a monthly rate should be divided by 30.

## 9. Interest Calculation History

Every posted interest charge should retain:

- Principal basis
- Rate
- Start date
- End date
- Day count or period count
- Formula identifier
- Rounding method
- Policy version
- Calculated amount
- Posting transaction ID

This makes the calculation reproducible and explainable.

## 10. Interest Policy Versioning

Interest policies must be versioned.

If a shop changes its rate or calculation method:

- New policy version must be created.
- Effective date must be recorded.
- Historical posted charges must not be rewritten.
- The system must define how open balances transition to the new policy.

Policy changes must be authorized.

## 11. Settlement Engine

Suggested service:

`SettlementService`

Responsibilities:

1. Load customer account state.
2. Calculate outstanding principal.
3. Calculate outstanding interest.
4. Validate settlement date.
5. Record amount received.
6. Apply payment allocation policy.
7. Calculate remaining amount.
8. Apply approved carry-forward treatment.
9. Record settlement transaction.
10. Preserve all previous entries.

The engine must support reversal or correction of settlements without destroying history.

## 12. Ledger Query Engine

Suggested service:

`CustomerLedgerService`

Responsibilities:

- Fetch chronological transactions.
- Apply date filters.
- Calculate opening balance.
- Calculate running balance.
- Calculate closing balance.
- Separate principal and interest.
- Return printable ledger data.

Use a stable transaction ordering.

When two entries have the same effective date, use a deterministic secondary ordering such as posting sequence or transaction ID.

## 13. Concurrency and Database Transactions

Financial posting must use database transactions.

Where necessary:

- Lock the customer account or relevant balance rows.
- Prevent concurrent over-allocation.
- Enforce unique transaction references.
- Use idempotency keys for retryable requests.
- Validate account state again after locking.

Never assume two simultaneous payment requests cannot occur.

## 14. Immutability

Once posted:

- Do not silently edit financial amounts.
- Do not physically delete transactions.
- Use reversal transactions.
- Preserve original records.
- Store correction reasons.
- Maintain audit information.

Draft records may be edited before posting according to permissions.

## 15. Balance Projection

A customer balance summary may be stored for performance.

However:

- The transaction ledger remains the source of truth.
- Projections must be rebuildable.
- Projection updates must happen transactionally.
- Periodic reconciliation must detect discrepancies.

Never trust a manually editable balance column as the accounting source of truth.

## 16. Suggested Application Services

- FinancialTransactionService
- LedgerPostingService
- PaymentAllocationService
- InterestCalculationService
- InterestPostingService
- SettlementService
- CustomerLedgerService
- AccountBalanceService
- FinancialReconciliationService

These are conceptual service boundaries, not mandatory class names.

## 17. Required Automated Tests

### Credit Sales

- Valid credit sale increases principal.
- Invalid sale amount is rejected.
- Cross-tenant sale is rejected.
- Reversal offsets original sale.

### Payments

- Valid payment is recorded.
- Payment cannot be allocated twice.
- Payment allocation follows the approved policy.
- Concurrent payments do not corrupt balances.
- Reversed payment restores the appropriate outstanding amount.

### Interest

- Interest-free period boundary.
- Rate changes.
- Partial period.
- Leap year.
- Month-end.
- Payment during interest period.
- Rounding.
- Repeated calculation.
- Historical policy version.

### Settlements

- Full settlement.
- Partial settlement.
- Remaining balance.
- Interest treatment.
- Settlement reversal.
- Repeated settlement request.

Tests requiring unresolved policies must remain pending until the relevant decisions are approved.

## 18. Accounting Engine Acceptance Criteria

The accounting engine is acceptable only when:

- Every balance can be traced to posted entries.
- Principal and interest are separately identifiable.
- Every payment allocation is recorded.
- Historical interest calculations are explainable.
- Reversals preserve history.
- Concurrent operations remain consistent.
- Cross-tenant access is prevented.
- Automated financial tests pass.

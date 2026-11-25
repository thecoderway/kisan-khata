# Kisan Khata — Accounting Engine Architecture

**Document Version:** 2.0  
**Status:** Approved  
**Related Documents:** `docs/01-product-requirements.md`, `docs/02-business-rules.md`

---

## 1. Purpose

This document defines the technical architecture of the Kisan Khata financial calculation engine.

The engine is responsible for recording financial transactions, maintaining consolidated customer balances, processing payments, calculating interest, maintaining customer advances, processing crop-season settlements, producing customer ledgers, preserving correction history and reproducing historical calculations.

The engine must be independent of the web interface. All authoritative financial calculations must run in backend services. The frontend and future Android application must never independently calculate authoritative balances or interest.

## 2. Core Architecture Principles

1. Financial calculations must be deterministic and decimal-safe.
2. Every balance must be explainable and reproducible.
3. Financial transaction history and correction audit history must remain traceable.
4. Owner corrections preserve previous values and immutable audit history.
5. Payments reduce consolidated customer payable; they are not allocated to individual sale IDs.
6. Interest follows approved calculation triggers.
7. Duplicate requests must not create duplicate financial effects.
8. Financial operations must be database-transactional.
9. Historical calculations preserve the policy used.
10. Balance projections must be rebuildable.
11. Every operation enforces tenant isolation.

## 3. Financial Data Types

Use MySQL DECIMAL for financial values.

Proposed initial types, subject to validation against expected business limits:

- Money: `DECIMAL(15,2)`
- Interest calculation precision: `DECIMAL(20,8)`
- Rate precision: `DECIMAL(12,8)`

Never use PHP floating-point arithmetic for authoritative financial calculations. Use a suitable decimal arithmetic library or carefully designed integer minor-unit arithmetic.

Rounding must be centralized in the financial calculation layer. Exact rounding precision and mode remain pending Product Owner approval.

## 4. Financial Concepts

### 4.1 Credit Sale

A credit sale increases customer payable. Every sale retains its own effective date because interest eligibility depends on that date.

### 4.2 Payment

A payment represents money received from a customer. It reduces the customer's complete payable amount rather than being allocated to individual sales or interest components.

### 4.3 Consolidated Outstanding

The consolidated outstanding is the amount carried forward after processing eligible sales, interest and payments. It has a calculation-cycle start date. After a payment, the remaining consolidated outstanding starts a new cycle from that payment date.

### 4.4 Sale-Level Interest Basis

New sales after the previous payment retain their individual sale dates. They must not inherit the calculation-cycle start date of an older consolidated outstanding balance.

### 4.5 Customer Advance

An advance represents money received beyond the customer's complete payable amount. It must be separately identifiable and must not be represented as a negative sale.

### 4.6 Interest and Settlement

Interest follows the shop or customer policy, preserving calculation basis, effective dates, rate and policy version. A settlement records full or partial settlement and any balance carried forward. Detailed settlement accounting follows separately approved rules.

## 5. Financial Transaction Model

Use a financial transaction model with associated transaction details and audit history.

Conceptual transaction types:

- OPENING_BALANCE
- CREDIT_SALE
- PAYMENT
- INTEREST_CALCULATION
- INTEREST_POSTING
- CUSTOMER_ADVANCE
- SETTLEMENT
- ADJUSTMENT

The exact type structure may be refined during database architecture design.

Every financial transaction should include:

- Unique ID, Shop ID and Customer ID.
- Transaction number and type.
- Effective date.
- Created and posted timestamps, where applicable.
- Status and description.
- Created by and updated by, where applicable.
- Version or revision reference.

Every transaction must belong to the correct tenant and customer.

## 6. Consolidated Customer Account Model

Maintain one consolidated account per customer per shop. It must represent existing carried-forward outstanding, new credit sales, calculated interest, payments, customer advances, settlements and approved adjustments.

Preserve the distinction between:

1. Consolidated carried-forward balance.
2. Individual new sales not yet incorporated into a payment calculation.
3. Separately maintained interest, where the selected policy requires it.
4. Customer advance balance.

Prevent the same sale or interest amount from being counted twice.

### 6.1 Calculation Cycle

A calculation cycle identifies the period for which a balance component is eligible for interest. Retain its start date, end date, eligible balance, applicable rate, policy version, calculated interest and calculation reference.

After a payment, the remaining consolidated outstanding starts a new cycle from the payment date. New sales retain their own effective sale dates.

## 7. Payment Processing Engine

Suggested service: `CustomerPaymentService`.

Responsibilities:

1. Validate shop and customer ownership.
2. Validate payment amount and effective date.
3. Lock or safely serialize the customer financial account.
4. Identify current outstanding and eligible new sales.
5. Calculate eligible interest through the payment date.
6. Determine complete payable amount.
7. Subtract the received payment.
8. Carry forward the remaining outstanding.
9. Record excess as customer advance, if applicable.
10. Update account projections.
11. Record transaction and audit history.
12. Return an explainable payment result.

### 7.1 No Sale-Level Allocation

The payment engine must not allocate payment to oldest or newest sale, allocate interest-first or principal-first, or require payment allocation records against sale IDs. Payment is applied to consolidated payable.

### 7.2 Multiple Payments on the Same Date

The engine must ensure the same elapsed period is not charged interest twice. For subsequent same-date payments, no additional elapsed-day interest is generated for the already calculated period. The received amount reduces remaining consolidated outstanding. Each payment and updated balance is recorded independently.

### 7.3 Excess Payment

If payment exceeds complete payable, outstanding becomes zero and excess becomes a separately stored customer advance, traceable in the ledger. Automatic use of advances against future sales requires a separately approved business rule.

## 8. Interest Calculation Engine

Suggested service: `InterestCalculationService`.

Responsibilities:

1. Load applicable shop or customer interest policy.
2. Identify eligible balance components.
3. Identify calculation start and end dates.
4. Apply grace period.
5. Calculate actual elapsed days.
6. Apply monthly interest rate.
7. Apply approved capitalization policy.
8. Apply approved rounding rules.
9. Produce an explainable calculation result.
10. Return a result suitable for payment processing or Auto Settlement posting.

### 8.1 Approved Formula

**Interest = Eligible Outstanding × (Monthly Rate / 100) × (Actual Days / 30)**

Use decimal-safe arithmetic.

### 8.2 Calculation Triggers

Interest calculation is event-driven. The two approved triggers are:

1. Customer payment.
2. Either of the two annual Auto Settlement dates configured by the Shop Owner.

There must be no automatic daily or monthly interest-posting process.

### 8.3 Calculation Inputs

Conceptual inputs:

- Shop ID and Customer ID.
- Calculation effective date.
- Existing consolidated outstanding.
- Consolidated balance cycle start date.
- New sale records and their effective dates.
- Applicable interest rate.
- Grace-period configuration.
- Capitalization policy.
- Previous calculation references.
- Policy version.

### 8.4 Calculation Output

- Eligible balance components.
- Applicable rate.
- Start and end dates.
- Actual elapsed days.
- Formula identifier.
- Capitalization policy.
- Unrounded and rounded interest.
- Policy version.
- Explanation of the result.

### 8.5 New Sales After Payment

Calculate interest independently on carried-forward consolidated outstanding from its applicable cycle start date and on each new sale from its own eligible sale date. Do not duplicate previously calculated interest or count a sale amount twice.

## 9. Interest Calculation History

Every calculation must preserve:

- Shop ID and Customer ID.
- Calculation date, start date and end date.
- Actual elapsed days.
- Eligible balance or balance components.
- Applicable monthly rate.
- Grace-period treatment.
- Formula identifier.
- Capitalization policy and policy version.
- Unrounded and rounded result.
- Posting or payment reference.
- Calculation status.

Historical calculation records remain available for audit and explanation.

## 10. Interest Policy Versioning

Interest policies must be versioned. A policy may contain monthly rate, customer-specific rate, grace period, capitalization policy, effective date and policy version.

When a policy changes:

1. Create a new policy version.
2. Preserve the previous version.
3. Record effective date and acting user.
4. Preserve historical calculations under their original policy.
5. Apply the new policy according to its effective-date rules.

Transition behavior for existing outstanding balances must be finalized before implementation.

## 11. Annual Auto Settlement Engine

Suggested service: `AutoSettlementService`.

Responsibilities:

1. Load configured annual Auto Settlement dates.
2. Identify eligible customer accounts.
3. Determine the last applicable calculation boundary.
4. Calculate interest through the configured date.
5. Prevent duplicate calculations.
6. Record calculation results.
7. Update account projections.
8. Record execution history.
9. Support safe retries after failures.

### 11.1 Idempotency

Repeated execution for the same customer and effective period must not create duplicate interest. If payment and Auto Settlement occur on the same date, the same elapsed period must be calculated only once. Exact execution ordering and recovery behavior must be finalized before implementation.

## 12. Settlement Engine

Suggested service: `SettlementService`.

Responsibilities:

1. Load customer account state.
2. Calculate payable amount under approved settlement rules.
3. Record settlement date and amount received.
4. Determine full or partial settlement.
5. Determine remaining balance.
6. Apply approved carry-forward treatment.
7. Preserve historical financial records.
8. Update account projections.
9. Record settlement and audit history.

Support settlement correction without destroying previous financial history. Detailed settlement accounting must not be invented before Product Owner approval.

## 13. Customer Ledger Engine

Suggested service: `CustomerLedgerService`.

Responsibilities:

- Fetch chronological customer financial activity.
- Apply date filters.
- Calculate opening, running and closing balances.
- Display credit sales, payments, interest, advances and settlements.
- Explain calculation history.
- Return printable ledger data.

Use stable transaction ordering. When two transactions share an effective date, use a deterministic secondary ordering such as posting sequence or transaction ID. The ledger must distinguish original transaction history from subsequent corrections.

## 14. Transaction Corrections and Audit History

The Shop Owner can directly edit posted sales and payments.

When a posted transaction is edited:

1. Preserve previous transaction values.
2. Store updated transaction values.
3. Create an immutable audit record.
4. Record acting user, correction timestamp and reason.
5. Recalculate affected financial results.
6. Update account projections transactionally.
7. Preserve a traceable link between original version and correction.

Managers and Staff cannot edit posted financial transactions. Physical deletion of posted financial history is prohibited. The current transaction state may be updated by an authorized Owner, but audit history must not be silently modified.

The exact strategy for historical backdated corrections and recalculation must be finalized before implementation.

## 15. Concurrency and Database Transactions

Financial posting must use database transactions. Where necessary:

- Lock customer account or relevant balance rows.
- Serialize concurrent financial operations for the same customer.
- Prevent duplicate payment processing.
- Enforce unique transaction references.
- Use idempotency keys for retryable requests.
- Validate account state after locking.
- Update transaction history and projections atomically.

Never assume simultaneous payment requests cannot occur. A failed operation must not leave a partially updated customer account.

## 16. Balance Projection and Reconciliation

A customer balance summary may be stored for performance, but:

1. Financial transaction history remains the source of truth.
2. Balance projections must be rebuildable.
3. Projection updates must occur transactionally.
4. Reconciliation must detect discrepancies.
5. A projection must never be treated as authoritative accounting history.
6. Corrections must update affected projections safely.

Provide a reconciliation mechanism to compare calculated account state with stored projections.

## 17. Suggested Application Services

Conceptual service boundaries:

- FinancialTransactionService
- LedgerPostingService
- CustomerPaymentService
- InterestCalculationService
- InterestPostingService
- AutoSettlementService
- SettlementService
- CustomerLedgerService
- AccountBalanceService
- FinancialReconciliationService
- FinancialAuditService

These are architectural boundaries, not mandatory class names. Implementation may refine names and responsibilities without changing approved business behavior.

## 18. Required Automated Tests

### 18.1 Credit Sales

- Valid sale increases customer payable.
- Invalid sale amount is rejected.
- Cross-tenant sale is rejected.
- Sale retains its effective date.
- New sale uses its own interest eligibility date.
- Owner correction preserves audit history.
- Manager and Staff cannot edit posted sales.

### 18.2 Payments

- Valid payment reduces consolidated payable.
- Payment is not allocated to individual sale IDs.
- Payment cannot be processed twice.
- Same-date payments do not duplicate interest.
- Payment after new sales calculates eligible interest correctly.
- Excess payment creates customer advance.
- Concurrent payments do not corrupt balances.
- Owner correction preserves previous values and recalculates affected results.
- Manager and Staff cannot edit posted payments.

### 18.3 Interest

- Actual Days / 30 formula.
- Zero elapsed days.
- Grace-period boundary.
- Customer-specific and shop default rates.
- New sale after payment.
- Existing consolidated outstanding after payment.
- Multiple sales with different effective dates.
- Capitalized and separate interest policies.
- Repeated calculation does not duplicate interest.
- Same-date payment and Auto Settlement.
- Rate and policy version changes.
- Leap year and month-end.
- Decimal precision and rounding.
- Historical calculation reproducibility.

Tests depending on unresolved policies remain pending until those policies are approved.

### 18.4 Settlements

- Full settlement.
- Partial settlement.
- Remaining balance carry-forward.
- Settlement history preservation.
- Repeated settlement request.
- Settlement correction.
- Settlement interest behavior according to approved policy.

### 18.5 Audit and Concurrency

- Owner correction records previous and updated values.
- Audit history cannot be silently modified.
- Unauthorized correction is rejected.
- Duplicate requests are idempotent.
- Concurrent financial operations remain consistent.
- Cross-tenant access is rejected.
- Balance projections can be rebuilt.

## 19. Accounting Engine Acceptance Criteria

The engine is acceptable only when every customer balance is explainable; consolidated payments follow approved rules; no sale-level allocation is required; same-date payments do not duplicate interest; new sales retain their own interest dates; advances are separately identifiable; interest uses Actual Days / 30 and approved triggers; historical calculations are reproducible; Owner corrections preserve immutable audit history; concurrent operations remain consistent; projections are rebuildable; tenant isolation is enforced; and automated financial tests pass.

## 20. Implementation Dependencies

The following decisions must be finalized before implementing the corresponding behavior:

1. Interest capitalization default and transition behavior.
2. Rounding precision and rounding mode.
3. Interest effective-date and day-boundary conventions.
4. Grace-period eligibility boundary.
5. Same-date payment and Auto Settlement execution ordering.
6. Crop-season settlement accounting.
7. Customer advance adjustment against future sales.
8. Backdated correction and recalculation behavior.
9. Final financial database schema and ledger representation.

No implementation may silently assume these unresolved rules.


---

## 21. Product Owner-approved Accounting Rules — Phase 5 (2026-11-25)

This addendum supersedes conflicting statements elsewhere in this architecture document. ADR-0012 is the controlling decision.

### 21.1 Sale and same-day payment posting

- Sale creation requires Paid/Unpaid.
- Paid creates an exactly equal, sale-linked automatic payment atomically with the sale. It has zero net effect on older customer balances and is excluded from future payable/interest calculations.
- Sale-day automatic payment is not an interest calculation trigger.
- Original sale amount remains immutable. Paid sales remain visible as Already Paid.
- Payment status and Interest Settlement Status are separate.

### 21.2 Normal payment sequence

Within one database transaction and customer-account lock:

1. Resolve the customer and tenant context from authenticated membership.
2. Calculate and persist eligible interest through the payment effective date for all earlier eligible unpaid components.
3. Mark the relevant interest calculation checkpoint Settled (not the Sale Paid).
4. Recompute total payable as outstanding principal plus posted unpaid interest.
5. Reject payment if amount exceeds total payable.
6. Apply payment internally using FIFO across oldest eligible unpaid sale/components.
7. Within FIFO, apply the Shop's effective-dated waterfall: Principal First or Interest First.
8. Persist immutable allocation rows and consolidated payment/ledger postings.
9. Reconcile the resulting account projection and commit atomically.

Same-day subsequent normal payments must not recalculate the same elapsed interval. A Paid sale's automatic payment is excluded from this sequence.

### 21.3 FIFO allocation and original amounts

- Customer-facing payment remains consolidated at customer-account level; internal FIFO allocation is required.
- Original sale amounts are never changed to represent remaining balances.
- Remaining per-sale/component balances are derived from original postings minus immutable applied allocations and reversals.
- Allocation records identify payment, sale/component, applied principal, applied posted interest, FIFO sequence and effective timestamp.
- Fully paid sales are excluded from later allocations.
- Sale-day automatic payment is allocated only to its originating sale.
- Reversal reverses the relevant allocation records; it does not delete them.

### 21.4 Payment waterfall and capitalization

- Shop setting selects Principal First or Interest First; record effective-dated policy version on every allocation.
- Customer-specific capitalization may override Shop capitalization; otherwise inherit Shop setting.
- Capitalized interest joins the future interest-bearing base from the policy effective date; separate interest remains outside that base.
- Capitalization changes do not retroactively reclassify posted interest.

### 21.5 Interest calculation conventions

- Monthly rate: Shop default 0%; optional customer-specific override.
- Grace period: Shop default 30 calendar days; Owner-configurable; Sale Date is Day 0.
- Customer-specific grace-period override is not approved.
- Two annual Interest Auto Settlement dates are Owner-configured.
- Formula uses Actual Days / 30.
- Posted interest is rounded to 2 decimal places using HALF_UP; calculations use decimal-safe arithmetic.
- Calculation interval is [start date, end date): start inclusive, end exclusive.
- Events: normal customer payment and the two annual Auto Settlement dates only; no daily/monthly posting job.
- Payment precedes Auto Settlement on the same date, without duplicate interval accrual.

### 21.6 Correction and immutability

- Posted Sales are not physically deleted. Corrections retain immutable revisions, before/after values, actor, timestamp and reason.
- Sales effective before a posted later Payment or annual Interest Auto Settlement are locked against ordinary editing.
- Posted Payments are not deleted or directly overwritten; use auditable reversal and optional replacement entries, reversing their allocations.
- Exceptional correction of a locked historical sale is not authorized by this addendum and requires a separately approved workflow.
- All postings, allocations and reversals are atomic, idempotent, tenant-scoped and concurrency-safe.

### 21.7 No advance balance

- Customer payment cannot exceed outstanding principal plus posted unpaid interest.
- Excess payment is rejected. No customer advance balance or automatic future-sale adjustment is part of the approved model.


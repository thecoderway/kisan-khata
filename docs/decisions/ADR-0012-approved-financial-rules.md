# ADR-0012 — Approved Financial Rules: Sale, Payment, Interest, FIFO Allocation and Settlement

**Status:** ACCEPTED  
**Date:** 2026-11-25  
**Decision owner:** Product Owner

## Context

The initial financial architecture specified consolidated customer payments without sale-level allocations and left several interest/payment parameters unresolved. Product Owner review finalized sale-day Paid behavior, internal FIFO allocation, payment waterfall, interest policy defaults, settlement semantics, correction locks and the no-advance rule.

## Decision

### 1. Sale payment state

- Sale creation requires Paid or Unpaid.
- Unpaid adds the full sale amount to outstanding principal.
- Paid creates a separate automatic Payment equal to the sale amount in the same atomic transaction.
- That automatic Payment is linked only to the originating Sale, has no effect on older customer balances, and does not trigger interest calculation for older outstanding.
- Paid Sales remain visible in the ledger as Already Paid and never re-enter payable/interest calculations.
- Original sale amount is immutable.

### 2. Consolidated customer payment with internal FIFO

- Normal customer payments are recorded as one consolidated customer-account transaction.
- Internally, payment is applied FIFO across oldest eligible unpaid Sales/components.
- FIFO allocation is an internal accounting record; it does not change the consolidated customer-facing payment model.
- Original Sale amounts never change. Remaining balances are derived from original postings, allocations and reversals.
- Sale-day automatic payment is allocated only to its own Sale.
- Fully paid Sales are excluded from later allocations.
- Shop Setting chooses the allocation waterfall: Principal First or Interest First.
- Every allocation retains payment, sale/component, principal amount, posted-interest amount, FIFO sequence, effective timestamp and applicable policy version.
- Allocation history is immutable; reversals are separate entries.

### 3. Payment eligibility and no advances

- Before a normal payment, calculate and persist eligible interest through the payment effective date for all earlier eligible unpaid Sales/components.
- Mark the corresponding interest calculation checkpoint Settled. Settled means interest calculation is complete; it does not mean the Sale is fully paid.
- Payment must not exceed outstanding principal plus posted unpaid interest.
- Reject excess payment. Customer advances and automatic future-sale advance adjustment are not supported.
- Recheck payable under customer-account lock inside the posting transaction.

### 4. Payment and Sale correction

- Never physically delete posted Sales or Payments.
- Sale revisions preserve before/after values, actor, timestamp and mandatory reason.
- Sales effective before a later posted normal Payment or annual Interest Auto Settlement are locked against ordinary editing.
- Posted Payment corrections use reversal and optional replacement records; never silently overwrite the original.
- Exceptional correction of a locked historical Sale is not yet approved and requires a separate Product Owner decision and workflow design.
- Manager and Staff cannot correct posted financial transactions; Owner permissions are server-enforced.

### 5. Interest policy and calculation

- Shop default monthly interest rate: 0%; Owner can change it in Shop Settings.
- Customer may override the Shop monthly rate; otherwise Shop default applies.
- Shop default grace period: 30 calendar days; Owner can change it. Sale Date is Day 0. No Customer-specific grace override.
- Two annual Interest Auto Settlement dates are configured by the Shop Owner.
- Interest formula: Eligible Principal × (Monthly Rate / 100) × (Actual Eligible Days / 30).
- Posted interest is rounded to 2 decimal places using HALF_UP; use decimal-safe arithmetic.
- Interest period is [start date inclusive, end date exclusive].
- Capitalization is configurable at Shop and Customer level. If Customer override is absent, inherit Shop setting.
- Rate and capitalization changes are effective-dated and prospective only; do not rewrite prior periods/postings.
- Capitalization ON adds eligible posted interest to future interest-bearing base from the effective date. OFF keeps interest separate and excludes prior accrued interest from future interest-bearing principal.
- Interest calculation events are normal customer payment and the two annual Owner-configured Auto Settlement dates only. No daily/monthly posting job.
- On a date shared by normal Payment and annual Auto Settlement, process Payment first and prevent duplicate accrual.
- Sale-day automatic payment is not an interest event.

### 6. Interest settlement and ledger semantics

- Payment Status: Unpaid, Partially Paid, Paid.
- Interest Settlement Status: Pending, Settled.
- Settled means the relevant interest calculation through the event date is completed and recorded, not that the Sale is Paid.
- Paid Sales and their automatic payment remain visible in the ledger with Already Paid status and zero net outstanding contribution.
- Normal customer Payment remains one consolidated ledger transaction; internal FIFO allocations support accounting and reconciliation.
- Ledger is append-only; corrections use reversal/replacement entries.
- Crop-season settlement remains distinct from annual Interest Auto Settlement.

## Consequences

- ADR-0004's prohibition on internal Sale-wise allocation is superseded. A tenant-safe internal FIFO allocation table/record is required.
- Database schema and tests must represent sale-day automatic payments, payment allocations, payment waterfall, separate payment/interest statuses, policy versioning and reversals.
- The accounting engine must derive balances from immutable financial records; original Sale amounts are not reduced.
- Shop/Customer policy versions and effective dates are required.
- No advance table/workflow is required for the approved initial model.
- FIFO ordering, interest/principal waterfall, concurrency and same-day event ordering require MySQL 8.4 integration tests.

## Related Documents

docs/02-business-rules.md  
docs/03-accounting-engine.md  
docs/04-database-architecture.md  
docs/06-security.md  
docs/09-testing-strategy.md  
docs/10-development-roadmap.md  
docs/PROJECT-STATUS.md  
docs/decisions/ADR-0004-consolidated-customer-ledger-and-payments.md  
docs/decisions/ADR-0006-financial-correction-and-audit.md  
docs/decisions/ADR-0007-interest-calculation-events.md

## Reconsideration

Any change to these approved rules requires explicit Product Owner approval and a superseding ADR.


## Product Owner clarification — finalized initial values (2026-11-25)

The previously open initial values are now decided:
- **Capitalization initial default: OFF** at Shop level. Customer-level override is supported; if no override is set, the Shop's OFF value applies.
- **Annual Interest Auto Settlement dates: 30 March and 30 October** each year as the initial Shop dates. The Owner may configure the Shop's two dates through Shop Settings.


## Clarification: detailed date and rounding conventions (2026-11-25)

The Product Owner approved Actual Days / 30 and two-decimal posted interest. The following were proposed during documentation drafting but have not been explicitly confirmed by the Product Owner: HALF_UP rounding mode, [start inclusive, end exclusive) interval convention, and Sale Date as grace-period Day 0. These are recommendations only until separately confirmed. Implementation must not treat them as final approved policy yet.

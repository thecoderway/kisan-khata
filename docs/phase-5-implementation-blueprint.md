# Kisan Khata — Phase 5 Implementation Blueprint

**Version:** 1.1  
**Date:** 2026-11-25  
**Status:** Updated design proposal; implementation not authorized  
**Controlling financial business decision:** [ADR-0012 — Approved Financial Rules](decisions/ADR-0012-approved-financial-rules.md)
**Migration sequence authority:** [Phase 5 Migration Readiness & Data-Safety Plan](phase-5-migration-readiness-data-safety-plan.md). Its migration groups supersede any older delivery-slice grouping in this blueprint.  

## 1. Purpose and scope

Phase 5 establishes the financial foundation for subsequent Sales, Payments, Customer Ledger, Interest and Settlement workflows. This blueprint defines logical architecture, posting flows, data requirements, integrity controls, test gates and delivery slices. It does not authorize application code, migrations, database changes or production financial workflows.

Existing Laravel code and migrations must be audited read-only before final physical schema design. Any conflict with existing architecture must be reported for approval rather than silently changed.

## 2. Binding Product Owner decisions

### Sales
- Sale creation requires Paid or Unpaid.
- Unpaid Sale adds the full original Sale amount to outstanding principal.
- Paid Sale creates an exactly equal linked automatic Payment atomically with the Sale.
- The automatic Payment is allocated only to its originating Sale; it does not reduce older customer balances and does not trigger interest settlement for older balances.
- Paid Sales remain visible as Already Paid, have zero net outstanding contribution and never re-enter future payable/interest calculations.
- Original Sale amount is immutable. No physical deletion.

### Normal Payments
- Normal Payment is one consolidated customer-account transaction in the customer-facing ledger.
- Before posting it, calculate and persist eligible interest through the Payment effective date.
- Then allocate internally FIFO across oldest eligible Sales first.
- Within each Sale, apply the Shop's configured Principal First or Interest First waterfall before moving to the next Sale.
- One Payment may allocate to multiple Sales. Fully paid and sale-day Paid Sales are excluded.
- Original Sale amounts are never reduced to represent remaining balance.
- Payment cannot exceed outstanding principal plus posted unpaid interest. Reject excess; no advances.
- Payment posting rechecks payable under a locked customer account inside one atomic transaction.

### Interest
- Shop default monthly rate is 0%; optional Customer-specific rate overrides it.
- Shop grace period defaults to 30 calendar days; Owner can configure it. No Customer-level grace override.
- Sale Date is Day 0. With a 30-day grace period, the first eligible interest day follows 30 complete calendar days.
- Formula: Eligible Principal × (Monthly Rate / 100) × (Actual Eligible Days / 30).
- Posted INR interest uses HALF_UP rounding to two decimal places. Use decimal-safe arithmetic with sufficient intermediate precision.
- Interest interval is [start inclusive, end exclusive).
- Capitalization initial Shop default is OFF. Customer may override; absent override inherits Shop setting.
- Shop and Customer rates/capitalization are effective-dated. Changes are prospective only.
- Interest events are normal customer Payment and two annual Interest Auto Settlement dates only. No daily/monthly posting job.
- Initial annual dates are 30 March and 30 October, Owner-configurable.
- On a shared date, normal Payment is processed before annual Auto Settlement; do not duplicate elapsed interest.

### Correction and status
- Payment Status: Unpaid / Partially Paid / Paid.
- Interest Settlement Status: Pending / Settled. Settled means calculation checkpoint complete, not Sale paid.
- Posted Sales and Payments are never physically deleted. Payment correction is reversal and optional replacement.
- Sales effective before a later Payment or annual Interest Auto Settlement are locked against ordinary edits.
- There is no exceptional correction path for a locked Sale in the approved scope. Stop and require separate explicit Product Owner approval and a separately designed workflow.
- Ledger is append-only; all postings, allocations and reversals are tenant-scoped, auditable, idempotent and atomic.

## 3. Proposed architecture

Controllers and API requests validate input and authorization, then invoke domain application services. Controllers must not calculate or directly mutate financial balances.

Logical services:
- SalePostingService
- PaymentPostingService
- InterestCalculationService
- FifoAllocationService
- PaymentWaterfallService
- LedgerPostingService
- SettlementService
- FinancialReversalService
- BalanceProjectionService
- ReconciliationService
- IdempotencyService

All financial amounts use MySQL DECIMAL and decimal-safe PHP arithmetic. Never use PHP binary floats as authoritative money values.

## 4. Logical data model

Existing schema must be reconciled before migrations. Proposed logical tables:

### Core
- sales
- sale_revisions
- payments
- payment_revisions
- payment_allocations
- payment_allocation_items
- financial_transactions
- customer_accounts
- ledger_entries
- account_balance_snapshots
- idempotency_keys

### Interest and policy
- interest_policies
- interest_policy_versions
- interest_calculation_runs
- interest_calculation_items
- interest_lots
- settlements
- settlement_items

No customer_advances table/workflow is required by the approved rules.

### Allocation record contract
Each allocation header should identify shop, customer, consolidated payment, Sale, FIFO Sale sequence, effective timestamp, policy version and reversal relationship. Allocation items should identify the component/source lot, applied principal, applied non-capitalized interest, within-Sale waterfall sequence and reversal relationship. Original allocation records are immutable; reversals are separate records.

Composite tenant-safe constraints (or equivalent robust enforcement) must ensure payment, Sale, customer and Shop references belong together.

### Interest record contract
Each calculation run/item should preserve event identity, Shop/Customer, Sale/component/source lot, interval start, exclusive interval end, eligible day count, grace evaluation, rate and policy version, principal basis, unrounded result, HALF_UP posted result, capitalization policy and ledger posting reference.

### Policy versions
Effective-dated versions are required for:
- Shop default monthly rate (initial 0%)
- optional Customer monthly rate override
- Shop grace period (initial 30 days)
- Shop capitalization default (initial OFF)
- optional Customer capitalization override
- Shop payment waterfall
- two annual settlement calendar dates (initial 30 March and 30 October)

Customer-level absent override inherits Shop setting. Historical policy versions must never be overwritten.

## 5. Payment allocation semantics

Allocation is a two-level deterministic algorithm:

1. Sort eligible Sales by Sale effective date and stable unique tie-breaker.
2. For the oldest Sale, calculate its outstanding components as of Payment effective time.
3. Apply the Shop waterfall within that Sale:
   - Principal First: reduce that Sale's principal-bearing components first, then its posted non-capitalized interest.
   - Interest First: reduce that Sale's posted non-capitalized interest first, then its principal-bearing components.
4. Continue to the next Sale only after the previous Sale's payable components are exhausted or the Payment is exhausted.
5. Persist allocation header and component-level items, including policy version and both ordering sequences.

### Capitalized interest interaction
When capitalization is ON, posted interest is transferred into the future interest-bearing principal base from the policy effective date. Preserve its origin as capitalized-interest provenance, but classify it as principal-bearing for subsequent accrual and payment waterfall. When capitalization is OFF, posted interest remains a separate non-capitalized interest component. The selected waterfall applies to the current accounting classification at the Payment effective time.

This preserves historical traceability without charging the same interest both as separate interest and as principal. Capitalization transfers must be ledgered/reclassified exactly once and must not duplicate receivable value.

### Example
Sale A: principal 5,000; Sale B: principal 3,000; Sale C: principal 2,000. A normal Payment of 4,000 is allocated to Sale A first. If Sale A also has posted non-capitalized interest, the selected per-Sale waterfall determines whether that Sale's interest or principal is reduced first. Only after Sale A's eligible payable components are exhausted does allocation proceed to Sale B. The Sale's original amount remains unchanged.

## 6. Posting workflows

### Unpaid Sale
- Validate authenticated Shop membership and Customer ownership.
- Lock/recheck required account state as appropriate.
- Create Sale, financial transaction, ledger debit and audit record atomically.
- Mark Payment Status Unpaid and Interest Settlement Status Pending.

### Paid Sale
- In one transaction create Sale, linked sale-day automatic Payment, sale-restricted allocation, ledger debit/credit and audit records.
- Enforce idempotency and exactly-equal amounts.
- Failure at any posting step rolls back all related records.
- Do not trigger interest calculation for older customer balances.

### Normal Payment
1. Resolve Shop/Customer from trusted authenticated membership; never trust client shop_id.
2. Lock customer_accounts row.
3. Validate idempotency.
4. Calculate/persist eligible interest through the Payment effective date.
5. Mark relevant interest checkpoint Settled.
6. Recompute outstanding principal plus posted unpaid interest.
7. Reject amount above payable.
8. Apply Sale FIFO, then per-Sale waterfall.
9. Create consolidated Payment, allocation header/items, ledger entries and audit records.
10. Reconcile/update balance projection and commit atomically.

### Reversal
- Preserve original Payment and allocation records.
- Create reversal references and compensating ledger entries.
- Reverse associated allocations with separate immutable records.
- Recalculate affected projections and future eligible interest using a separately designed, auditable reversal process.
- Require Owner authorization and mandatory reason.

### Annual Auto Settlement
- Run on configured 30 March and 30 October dates.
- Calculate eligible interest once per unique Shop/Customer/event interval.
- Persist run/items, ledger postings, policy versions and idempotency identity.
- On same-day normal Payment, payment processing precedes annual settlement.
- Repeated job execution must not duplicate postings.

## 7. Ledger and balance invariants

- Sale and Interest postings increase customer receivable.
- Payments decrease customer receivable.
- Paid Sale plus its linked automatic Payment nets to zero.
- Customer-facing normal Payment appears once; internal allocation is supporting detail.
- Original Sale amounts never change.
- Ledger entries are append-only; corrections use compensating entries.
- Balance snapshots are projections, not the source of truth.
- Reconciliation must be able to rebuild/check balances from immutable postings, allocation items and reversals.
- Capitalization is a classification transfer, not a second receivable charge.

## 8. Security, tenancy and concurrency

- Resolve tenant from authenticated membership; do not trust request-supplied shop_id or role.
- Enforce Shop/Customer/Sale/Payment/Allocation ownership in every query and relationship.
- Owner-only policy changes and financial corrections; Manager/Staff cannot bypass via web, API, jobs or direct IDs.
- Lock customer account for payment/interest posting and recheck payable inside transaction.
- Use unique idempotency identities scoped to Shop and operation.
- Atomically commit financial entries, allocations, projections and audit records.
- No deletion of posted financial records.
- Sale lock is enforced server-side; no exceptional bypass in current scope.

## 9. Testing and acceptance

MySQL 8.4 integration tests are mandatory for database locking, foreign keys, tenant constraints and concurrent posting. SQLite-only tests are insufficient evidence.

Required tests:
- Paid Sale creates one equal linked automatic Payment; rollback if any linked posting fails.
- Paid Sale has zero net outstanding and does not trigger older-balance interest.
- FIFO across multiple Sales; per-Sale Principal First and Interest First.
- Capitalized interest is not double-counted and retains provenance.
- HALF_UP exact half-cent rounding boundaries.
- [start, end) adjacent periods do not double-count days.
- Sale Date Day 0; first eligible day after 30 complete calendar days.
- Rate/capitalization effective-date changes preserve history.
- Reject excess payments; no advance.
- Concurrent payments cannot overdraw.
- Payment before annual settlement on same date; no duplicate interest.
- Idempotent annual settlement.
- Locked Sale correction rejected.
- Payment reversal preserves original records and reverses allocations.
- Cross-Shop references rejected.
- Ledger and balance projections reconcile.
- Failure at any posting stage rolls back all associated financial writes.

Run tests only against an independently isolated test database. Never point destructive test setup at development data.

## 10. Delivery slices

The [Phase 5 Migration Readiness & Data-Safety Plan](phase-5-migration-readiness-data-safety-plan.md) controls migration group boundaries and order. The following blueprint workstreams are aligned to that plan:

1. **Group 1 — Idempotency, account anchor and operation header:** decimal strategy, idempotency_keys, customer_accounts, financial_transactions and their tenant-safe constraints. ledger_entries is not part of Group 1.
2. **Group 2 — Sales and Payments:** sales, sale_revisions, payments and payment_revisions.
3. **Group 3 — Payment allocation:** allocation header/items, FIFO Sale ordering, per-Sale waterfall and reversal-aware history.
4. **Group 4 — Ledger and snapshots:** append-only ledger foundation, account_balance_snapshots and rebuildable projections.
5. **Group 5 — Effective-dated policy:** Shop/Customer policy aggregates, versions and inheritance.
6. **Group 6 — Interest calculations and lots:** calculation runs/items, interest lots and events.
7. **Group 7 — Annual Interest Auto Settlement:** 30 March/30 October event processing, same-day ordering and settlement run/items.
8. **Group 8 — Final constraints and reconciliation:** deferred constraints, supporting indexes, MySQL 8.4 integration acceptance and reconciliation.

The logical data model in Section 4 describes the full Phase 5 target and is not a statement that all listed tables belong in Group 1. Each migration slice requires a separately reviewed PR and acceptance evidence. Pint is mandatory for PHP changes. Do not claim local tests, Pint, CI or database verification passed without observed results.

## 11. Explicitly outside current approval

- Exceptional correction/recalculation of locked historical Sales.
- Customer advances.
- Daily/monthly interest posting jobs.
- Customer-specific grace period.
- Any retroactive policy rewrite.
- Crop-season settlement accounting details (separate from annual Interest Auto Settlement).
- Physical migration implementation until read-only code/schema audit and blueprint approval.

## 12. Implementation gates

1. Review and merge the latest documentation PR #18.
2. Complete read-only existing-code audit, including models, migrations, tenant constraints, authorization, audit logging, routes/services and tests.
3. Review and approve the physical schema mapping against existing code.
4. Approve the detailed blueprint and any remaining design clarifications.
5. Only then authorize implementation PR 5.1.

**No application code, migration or database change is authorized by this blueprint.**

# Financial Integrity Rules

The approved sources are docs/02-business-rules.md and docs/03-accounting-engine.md v2.0.

## Money
Never use PHP float or JavaScript floating-point arithmetic for authoritative financial calculations. Use MySQL DECIMAL and decimal-safe arithmetic. Do not invent rounding rules.

## Consolidated payments
Kisan Khata has no sale-level payment allocation. Do not create payment_allocations, allocation records, oldest/newest sale allocation, or payment splits between principal and interest. At payment time calculate eligible interest through payment date, determine consolidated customer payable, subtract the payment and carry the remainder forward from that date. Same-day subsequent payments accrue no duplicate interval. New sales retain their own sale dates and interest start dates. Excess payment is customer advance.

## Interest
Interest calculation events are customer payments and two Owner-configured annual Auto Settlement dates. No daily/monthly posting job. Use Actual Days / 30. Do not invent exact annual dates, rounding, date-boundary inclusivity, capitalization defaults or unresolved grace-period behavior. Payment and Auto Settlement on the same date must not calculate the same interval twice. Preserve policy version and traceability.

## Corrections and audit
Owner may directly edit posted Sales and Payments. Manager and Staff cannot. Preserve immutable before/after values, actor, timestamp, reason and transaction identity. Never physically erase financial history or audit evidence. Recalculate affected balances/projections safely. Unspecified backdated correction rules must be escalated, not guessed.

## Posting controls
Use database transactions, appropriate row locks, idempotency and explicit services. Validate tenant ownership at service boundaries. Never trust client-calculated balances. Keep ledger reconciliation verifiable.

## Tests
Cover decimal precision, same-day multiple payments, interest through payment date, carried-forward cycle start, new sale dates, excess advance, Owner vs Manager/Staff permissions, immutable audit history, recalculation, duplicate requests, concurrency, tenant isolation and reconciliation. Do not assert results for unresolved policies.


## Product Owner-approved Phase 5 rules (2026-11-25)

For any conflict with older text in this file, ADR-0012 and the latest approved sections of docs/02, docs/03 and docs/04 take precedence.

- Sale form requires Paid/Unpaid. Paid creates an equal linked automatic payment atomically; it has no effect on older balance and is not an interest event.
- Sale original amounts are immutable. No physical deletion. Sale revision requires before/after, actor, timestamp and reason. Sales before a later posted normal Payment or annual Interest Auto Settlement are locked against ordinary edits.
- Normal customer payments remain consolidated in the customer-facing account but are internally allocated FIFO across oldest eligible unpaid sale/components. Do not modify original sale amounts.
- Shop Settings choose Principal First or Interest First. Persist effective-dated policy versions and immutable allocation records.
- Before a normal payment, calculate and persist eligible interest through payment date for all earlier eligible unpaid components; Interest Settlement Status Settled does not mean Payment Status Paid.
- Payment cannot exceed outstanding principal plus posted unpaid interest. Reject excess; no advances.
- Posted Payment cannot be deleted or directly overwritten. Correct by reversal and optional replacement, retaining immutable allocation and audit history.
- Shop monthly rate defaults to 0%; customer-specific rate override is allowed. Shop grace period defaults to 30 days and is Owner-configurable; Sale Date is Day 0. No customer-specific grace override.
- Shop and Customer capitalization settings are supported; Customer override inherits Shop setting when absent.
- Formula uses Actual Days / 30; posted interest is 2 decimals HALF_UP; interval is [start inclusive, end exclusive].
- Two annual Interest Auto Settlement dates are Owner-configured. Normal Payment precedes annual Auto Settlement on the same date. No daily/monthly posting job.
- All financial operations remain decimal-safe, tenant-scoped, atomic, idempotent, concurrency-safe and auditable.


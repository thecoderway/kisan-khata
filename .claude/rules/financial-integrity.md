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

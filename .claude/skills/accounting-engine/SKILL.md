---
name: accounting-engine
description: Implement and review Kisan Khata consolidated customer accounting, payment events, interest calculations and settlements.
---

# Accounting Engine Skill

Read docs/02-business-rules.md, docs/03-accounting-engine.md, docs/04-database-architecture.md and .claude/rules/financial-integrity.md.

## Non-negotiable rules
- Never allocate a payment to individual sales. Do not create allocation tables or records.
- At payment, calculate eligible interest through payment date, subtract payment from consolidated payable and start the carried-forward balance cycle on that date.
- Same-day subsequent payments must not duplicate interest.
- New sales keep their individual sale dates and accrue interest from those dates.
- Excess payment remains customer advance.
- Interest events are payment and two Owner-configured annual Auto Settlement dates; no daily/monthly posting job.
- Actual Days / 30. Do not invent unresolved rounding, date-boundary, capitalization or exact annual-date rules.
- Owner may edit posted Sales/Payments; Manager and Staff may not. Preserve immutable before/after audit history.

## Workflow
1. Identify the financial event and relevant customer/shop.
2. Read the approved rule and determine opening state.
3. Document calculation inputs, effective dates, ledger effect and expected closing state.
4. Identify transaction, audit and idempotency requirements; no sale-level allocation records.
5. Implement with decimal-safe arithmetic, DB transactions, locks and tenant validation.
6. Reconcile ledger and projections.
7. Test same-day payments, independent new-sale dates, carried-forward balance dates, advance, corrections, concurrency and unresolved boundaries.
8. Use accounting reviewer for high-risk changes.

If an expected result depends on an unresolved business decision, stop and ask the product owner. Report exact tests and remaining risks.

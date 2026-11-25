# ADR-0007 — Interest Calculation Events

**Status:** ACCEPTED — specified scope only; unresolved parameters remain pending  
**Date:** 2026-11-23

## Context
Interest must be calculated consistently without daily or monthly posting jobs, and payment-date calculations must not be duplicated.

## Decision
Calculate interest at (1) customer payment events, through payment date, and (2) two annual Auto Settlement dates configured by the shop Owner. Use Actual Days / 30 with the approved monthly-rate model. Do not create daily/monthly interest-posting jobs. Payment and Auto Settlement on the same date must not calculate the same interval twice. Preserve calculation policy/version and traceability.

## Consequences
Calculations are event-driven and auditable. Exact Auto Settlement dates, rounding, date-boundary inclusivity, capitalization default and unresolved grace-period edge behavior are not decided by this ADR and must not be guessed.

## Security and Financial Integrity
Use idempotent calculation events, transaction safety and tenant scoping. Carried-forward balance cycle starts on payment date; new sales retain their own dates.

## Related Docs
docs/02-business-rules.md, docs/03-accounting-engine.md, docs/09-testing-strategy.md.

## Reconsideration
Open parameters require explicit product-owner decision; changing accepted event triggers requires a superseding ADR.# ADR-0007 — Interest Calculation Events

**Status:** ACCEPTED — parameters finalized by ADR-0012  
**Date:** 2026-11-23  
**Clarified:** 2026-11-25

## Context

Interest must be calculated consistently without daily or monthly posting jobs, and payment-date calculations must not be duplicated.

## Decision

Interest calculation occurs only:
1. On a normal customer payment, after calculating eligible interest through the payment effective date and before applying the payment.
2. On either of the two annual Interest Auto Settlement dates configured by the Shop Owner.

A sale-day automatic payment created for a Paid sale is not a normal customer payment event and does not trigger interest calculation for older outstanding.

Use Actual Days / 30. A normal Payment is processed before annual Auto Settlement on the same date, and the same elapsed interval must not be calculated twice. Preserve calculation policy/version and traceability.

The exact parameters are finalized by ADR-0012:
- Shop default monthly rate: 0%; optional customer-specific rate override.
- Shop default grace period: 30 calendar days; Owner-configurable; Sale Date is Day 0.
- Interest period: start inclusive, end exclusive.
- Posted interest rounding: 2 decimal places, HALF_UP.
- Capitalization: Shop setting with optional Customer override; absent Customer override inherits Shop setting.
- Rate and capitalization policy changes are effective-dated and apply prospectively only.

No ordinary daily/monthly interest-posting job is permitted.

## Consequences

Calculations are event-driven and auditable. Annual settlement dates are configured by the Owner. Every calculation retains eligible components, effective dates, rate/policy version, day count, precision and posted amount. Repeated event execution is idempotent.

## Security and Financial Integrity

Use idempotent calculation events, atomic transactions, customer-account locking and tenant scoping. A carried-forward balance and each new Sale retain their own interest eligibility dates.

## Related Docs

docs/02-business-rules.md, docs/03-accounting-engine.md, docs/04-database-architecture.md, docs/09-testing-strategy.md, docs/decisions/ADR-0012-approved-financial-rules.md.

## Reconsideration

Changing accepted event triggers or finalized parameters requires explicit Product Owner approval and a superseding ADR.

# ADR-0007 — Interest Calculation Events

**Status:** ACCEPTED — parameters finalized by ADR-0012  
**Date:** 2026-11-23  
**Clarified:** 2026-11-25

## Context

Interest must be calculated consistently without daily or monthly posting jobs, and payment-date calculations must not be duplicated.

## Decision

Interest calculation occurs only:
1. On a normal customer Payment, after calculating eligible interest through the Payment effective date and before applying the Payment.
2. On either of the two annual Interest Auto Settlement dates configured by the Shop Owner (initial defaults: 30 March and 30 October).

A sale-day automatic Payment created for a Paid Sale is not a normal customer Payment event and does not trigger interest calculation for older outstanding.

Use Actual Days / 30. A normal Payment is processed before annual Auto Settlement on the same date, and the same elapsed interval must not be calculated twice.

The exact parameters are finalized by ADR-0012:
- Shop default monthly rate: 0%; optional Customer-specific rate override.
- Shop default grace period: 30 calendar days; Owner-configurable; Sale Date is Day 0.
- Interest interval: start inclusive, end exclusive.
- Posted interest rounding: 2 decimal places, HALF_UP.
- Capitalization: Shop setting defaults OFF, with optional Customer override; absent Customer override inherits Shop setting.
- Rate and capitalization policy changes are effective-dated and apply prospectively only.

No ordinary daily/monthly interest-posting job is permitted.

## Consequences

Calculations are event-driven and auditable. Every calculation retains eligible components, effective dates, rate/policy version, day count, precision and posted amount. Repeated event execution is idempotent.

## Security and Financial Integrity

Use idempotent calculation events, atomic transactions, customer-account locking and tenant scoping. A carried-forward balance and each new Sale retain their own interest eligibility dates.

## Related Docs

docs/02-business-rules.md, docs/03-accounting-engine.md, docs/04-database-architecture.md, docs/09-testing-strategy.md, docs/decisions/ADR-0012-approved-financial-rules.md.

## Reconsideration

Changing accepted event triggers or finalized parameters requires explicit Product Owner approval and a superseding ADR.

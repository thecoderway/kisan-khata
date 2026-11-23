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
Open parameters require explicit product-owner decision; changing accepted event triggers requires a superseding ADR.
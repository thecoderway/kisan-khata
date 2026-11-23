# ADR-0004 — Consolidated Customer Ledger and Payments

**Status:** ACCEPTED  
**Date:** 2026-11-23

## Context
Payments are recorded against the customer's consolidated payable, not allocated to individual sales. New sales still retain their own dates for interest calculations.

## Decision
Do not allocate payments to individual sales, split payments into sale-level principal/interest components, or create payment allocation records. At payment, calculate eligible interest through that date, determine consolidated payable, subtract payment and carry the remainder forward from that date. Same-day subsequent payments must not accrue the same interval twice. New sales retain their own dates. Excess payment is retained as customer advance.

## Consequences
The calculation engine tracks the carried-forward balance cycle start separately from each new sale date. Advance application details remain subject to approved business rules.

## Security and Financial Integrity
Use decimal-safe arithmetic, transactions, locks, idempotency, tenant scoping and audit history. No payment_allocations table or equivalent allocation workflow is permitted.

## Related Docs
docs/02-business-rules.md, docs/03-accounting-engine.md, docs/04-database-architecture.md, docs/09-testing-strategy.md.

## Reconsideration
Requires explicit product-owner approval and a superseding ADR.
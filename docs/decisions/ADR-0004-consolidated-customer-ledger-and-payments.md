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
Requires explicit product-owner approval and a superseding ADR.# ADR-0004 — Consolidated Customer Ledger and Payments

**Status:** SUPERSEDED by ADR-0012  
**Date:** 2026-11-23  
**Superseded:** 2026-11-25

## Context

The initial decision recorded customer-level consolidated payments without sale-level allocations. Subsequent Product Owner review established a need for internal FIFO allocation while preserving the consolidated customer-facing payment model.

## Original decision (historical)

The original ADR prohibited sale-level payment allocation and a `payment_allocations` table. This remains historical context only and is no longer the controlling rule.

## Current controlling decision

See **ADR-0012 — Approved Financial Rules: Sale, Payment, Interest, FIFO Allocation and Settlement**.

The current model is:

- Customer-facing normal payments remain consolidated at customer-account level.
- Internally, normal payments are allocated FIFO across oldest eligible unpaid sales/components.
- A Paid sale creates a same-day automatic payment linked only to that sale; it cannot reduce older balances.
- Original sale amounts are immutable; outstanding is derived from immutable postings, allocations and reversals.
- Shop Settings choose Principal First or Interest First payment waterfall.
- Excess payment is rejected; customer advances are not supported.
- Interest calculation and settlement behavior follows ADR-0012.

## Reconsideration

Any further change requires explicit Product Owner approval and a superseding ADR.

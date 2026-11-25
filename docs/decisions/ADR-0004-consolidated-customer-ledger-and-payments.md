# ADR-0004 — Consolidated Customer Ledger and Payments

**Status:** SUPERSEDED by ADR-0012  
**Date:** 2026-11-23  
**Superseded:** 2026-11-25

## Historical context

The original decision recorded consolidated customer-level payments without sale-level allocations. It prohibited a `payment_allocations` table and retained excess payments as customer advances.

## Current controlling decision

ADR-0012 supersedes the original allocation and excess-payment rules. The current model is:

- Customer-facing normal payments remain consolidated at customer-account level.
- Internally, normal payments are allocated FIFO across oldest eligible unpaid Sales/components.
- Allocation order is FIFO Sale first, then the selected Principal First / Interest First waterfall within that Sale.
- A Paid Sale creates a same-day automatic Payment linked only to that Sale; it cannot reduce older balances.
- Original Sale amounts are immutable; outstanding is derived from immutable postings, allocations and reversals.
- Excess payment is rejected; customer advances are not supported.

See ADR-0012 and the current financial rules in docs/02, docs/03 and docs/04.

## Reconsideration

Any further change requires explicit Product Owner approval and a superseding ADR.

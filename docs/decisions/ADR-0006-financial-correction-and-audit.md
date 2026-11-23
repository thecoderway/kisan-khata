# ADR-0006 — Financial Transaction Correction and Audit

**Status:** ACCEPTED  
**Date:** 2026-11-23

## Context
The Shop Owner must be able to correct posted Sales and Payments while retaining a complete audit trail.

## Decision
Owner may directly edit posted Sales and Payments. Manager and Staff cannot edit posted financial transactions. Preserve prior and updated values, actor, timestamp, reason and transaction reference in immutable audit history. Do not physically erase financial history or audit evidence. Recalculate affected balances and projections according to approved rules. If backdated correction behavior is unspecified, stop and request a decision.

## Consequences
Authorized corrections are possible with traceability. Backdated recalculation needs careful design and tests; unspecified details remain pending.

## Security and Financial Integrity
Enforce Owner-only permission server-side. Audit history must not be mutable or erasable by ordinary application roles. Reconcile affected ledger projections.

## Related Docs
docs/02-business-rules.md, docs/03-accounting-engine.md, docs/04-database-architecture.md, docs/06-security.md, docs/09-testing-strategy.md.

## Reconsideration
Requires explicit product-owner approval and a superseding ADR.
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
Requires explicit product-owner approval and a superseding ADR.# ADR-0006 — Financial Transaction Correction and Audit

**Status:** ACCEPTED — constrained by ADR-0012  
**Date:** 2026-11-23  
**Clarified:** 2026-11-25

## Context

Posted financial transactions require traceable correction without erasing the audit trail. Product Owner subsequently specified a lock for historical Sales after later Payment or annual Interest Auto Settlement, and reversal-based Payment correction.

## Decision

- Posted Sales are never physically deleted.
- A permitted Sale correction creates immutable before/after revision data with actor, timestamp and mandatory reason.
- A Sale effective before a later posted customer Payment or annual Interest Auto Settlement is locked against ordinary editing.
- Exceptional correction of a locked historical Sale is not yet approved; stop and request a separately approved correction/recalculation workflow.
- Posted Payments are never physically deleted or directly overwritten. Correct them through an auditable reversal and, if appropriate, a replacement Payment.
- Preserve original and reversal/replacement records, allocation reversals, actor, timestamp, mandatory reason and references.
- Manager and Staff cannot correct posted financial transactions; Owner-only permissions must be enforced server-side.

## Consequences

Historical records remain reproducible. The application must distinguish ordinary correction eligibility from exceptional historical correction. No implementation may bypass a Sale lock or silently rewrite an existing Payment.

## Security and Financial Integrity

Use atomic transactions, idempotency, customer-account locking, tenant scoping and immutable audit records. Reconcile all affected projections after an authorized reversal or correction.

## Related Docs

docs/02-business-rules.md, docs/03-accounting-engine.md, docs/04-database-architecture.md, docs/06-security.md, docs/09-testing-strategy.md, docs/decisions/ADR-0012-approved-financial-rules.md.

## Reconsideration

Requires explicit Product Owner approval and a superseding ADR.

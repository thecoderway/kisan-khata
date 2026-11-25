# ADR-0006 — Financial Transaction Correction and Audit

**Status:** ACCEPTED — constrained by ADR-0012  
**Date:** 2026-11-23  
**Clarified:** 2026-11-25

## Context

Posted financial transactions require traceable correction without erasing the audit trail. ADR-0012 subsequently specified Sale edit locks and reversal-based Payment correction.

## Decision

- Posted Sales are never physically deleted.
- A permitted Sale correction creates immutable before/after revision data with actor, timestamp and mandatory reason.
- A Sale effective before a later posted normal Payment or annual Interest Auto Settlement is locked against ordinary editing.
- Exceptional correction of a locked historical Sale is not approved in the current scope. Stop; require separate explicit Product Owner approval and a separately designed workflow.
- Posted Payments are never physically deleted or directly overwritten. Correct them through an auditable reversal and, if appropriate, a replacement Payment.
- Preserve original and reversal/replacement records, allocation reversals, actor, timestamp, mandatory reason and references.
- Manager and Staff cannot correct posted financial transactions; Owner-only permissions must be enforced server-side.

## Consequences

Historical records remain reproducible. No implementation may bypass a Sale lock or silently rewrite an existing Payment.

## Security and Financial Integrity

Use atomic transactions, idempotency, customer-account locking, tenant scoping and immutable audit records. Reconcile all affected projections after an authorized Payment reversal.

## Related Docs

docs/02-business-rules.md, docs/03-accounting-engine.md, docs/04-database-architecture.md, docs/06-security.md, docs/09-testing-strategy.md, docs/decisions/ADR-0012-approved-financial-rules.md.

## Reconsideration

Requires explicit Product Owner approval and a superseding ADR.

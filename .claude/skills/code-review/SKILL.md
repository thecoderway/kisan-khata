---
name: code-review
description: Review Kisan Khata changes for correctness, maintainability, financial integrity, tenant security and regressions.
---

# Code Review Skill

Read CLAUDE.md, applicable .claude/rules/, docs/09-testing-strategy.md and relevant product docs.

Review:
1. Requirement and actual behavior.
2. Validation, authorization, state transitions and errors.
3. Decimal arithmetic and ledger reconciliation.
4. Consolidated payment model: no sale-level allocations; interest through payment date; same-day idempotency; separate new-sale dates; carried-forward balance date; advance.
5. Owner-only posted Sale/Payment corrections and immutable audit history.
6. Tenant context, ownership, policies, files, cache and API.
7. Migrations, constraints, indexes, data preservation and performance.
8. Tests, including negative, boundary, concurrency and tenant cases.
9. Actual verification commands and results.

Severity: CRITICAL, HIGH, MEDIUM, LOW. Report file/location, impact, recommended fix, tests and verdict. Do not claim unexecuted tests passed or invent defects. Do not approve unresolved business assumptions.
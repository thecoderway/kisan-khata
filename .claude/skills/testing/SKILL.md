---
name: testing
description: Design and execute Kisan Khata tests for financial workflows, tenancy, authorization and APIs.
---

# Testing Skill

Read docs/09-testing-strategy.md, docs/02-business-rules.md, docs/03-accounting-engine.md and test-reviewer agent.

1. Identify approved business rule, role, shop, input, expected behavior and error paths.
2. Inspect current tests, factories and helpers; avoid contradictory duplicate tests.
3. Choose unit, feature, integration, API or browser tests appropriately.
4. Use isolated synthetic data and multiple shops for tenant tests.
5. Verify both success and invalid/unauthorized/cross-tenant/duplicate paths.
6. Financial coverage: decimal precision, approved rounding only, interest through payment date, same-day multiple payments without duplicate interest, carried-forward balance start date, individual new-sale dates, excess advance, no sale-level allocation, Owner vs Manager/Staff correction permissions, immutable before/after audit, recalculation and ledger reconciliation.
7. Cover concurrency and idempotency. SQLite does not prove MySQL locking behavior.
8. Run tests and inspect actual output. Report command, environment, pass/fail/skip and known failures.
9. Never invent expected values for unresolved policies or claim tests passed unless executed.
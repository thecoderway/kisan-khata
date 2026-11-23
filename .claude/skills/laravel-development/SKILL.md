---
name: laravel-development
description: Implement Kisan Khata features using approved Laravel conventions, validation, services, policies, migrations and tests.
---

# Laravel Development Skill

Approved target stack: PHP 8.3, Laravel 13, MySQL 8.4. Inspect actual environment/manifests and existing patterns; do not upgrade/install packages without approval.

1. Read CLAUDE.md, relevant rules, product docs and accepted ADRs.
2. Identify user story, role, tenant, input, expected result and errors.
3. Inspect existing code and tests; reuse valid patterns.
4. Plan migrations, models, relationships, requests, policies, services, controllers, routes, views/API and tests.
5. Use migrations, foreign keys, constraints, indexes and DECIMAL.
6. Keep business logic out of controllers; validate tenant ownership at critical service boundaries.
7. Enforce authorization server-side.
8. Follow approved Hindi/English UI conventions and preserve original customer text separately from translations.
9. For payments, implement consolidated payable reduction only; no sale-level allocation. Apply interest through payment date, avoid duplicate same-day interest, preserve new-sale dates and carry-forward cycle start.
10. Owner-only posted Sale/Payment edits must retain immutable before/after audit history; Manager/Staff cannot edit.
11. Write success, validation, authorization, tenant, financial boundary and concurrency tests.
12. Run relevant tests/formatting/static analysis and report exact results and remaining risks.

Stop and ask if behavior depends on unresolved policy.
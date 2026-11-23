---
name: database-design
description: Design and review Kisan Khata MySQL schemas, tenant constraints, indexes and migrations.
---

# Database Design Skill

Read docs/04-database-architecture.md, docs/05-multi-tenancy.md, docs/03-accounting-engine.md and applicable security/financial rules.

1. Identify entities, relationships, ownership, lifecycle, retention and query patterns.
2. Inspect existing migrations/models before proposing tables.
3. Define cardinality, tenant ownership, constraints and deletion behavior.
4. Use shop_id for tenant-owned records and validate same-shop relationships; use composite constraints where practical.
5. Use DECIMAL for money, DATE/DATETIME appropriately, bounded strings and JSON only where justified. Never FLOAT/DOUBLE for authoritative amounts.
6. Do not create payment_allocations or any sale-level payment allocation model. Payments reduce consolidated customer payable.
7. Preserve each new sale's effective date separately from the carried-forward balance's calculation-cycle start date.
8. Plan safe migrations, indexes, query performance and data preservation.
9. Test constraints, foreign keys, duplicate references, cross-tenant relations and migration behavior.

Output schema, relationships, indexes, tenant analysis, migration plan, risks and tests. Do not invent unresolved accounting policy.
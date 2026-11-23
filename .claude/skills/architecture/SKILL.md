---
name: architecture
description: Plan and review Kisan Khata modules and architecture changes against approved docs and accepted ADRs.
---

# Architecture Skill

Read CLAUDE.md, docs/01-product-requirements.md, docs/04-database-architecture.md, docs/05-multi-tenancy.md, docs/08-api-architecture.md and docs/decisions/README.md.

1. Identify business objective, role, tenant, entities, behavior and acceptance criteria.
2. Inspect existing Laravel version, modules, models, services, policies, migrations, routes and tests.
3. Define module ownership, interfaces, data ownership and dependencies.
4. Trace request through authentication, tenant context, authorization, validation, service/domain logic, database and response.
5. Assess tenant isolation, financial integrity, security, performance, API compatibility and migration risks.
6. List files, schema changes, services, policies, routes, tests and docs affected.
7. If proposal conflicts with approved docs or accepted ADRs, explain and obtain approval before implementation.
8. Implement only the approved scope; run relevant tests and report evidence.

Do not introduce sale-level payment allocation, multi-shop user membership or a different tenancy architecture without a new approved decision.
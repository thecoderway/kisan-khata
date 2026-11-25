# Kisan Khata — Phase 5.1-A Group 0 Existing Database Prerequisites

**Version:** 1.0  
**Date:** 2026-11-25  
**Status:** Design proposal for review — implementation not authorized  
**Target:** Laravel 13 / PHP 8.3 / MySQL 8.4 / InnoDB  
**Controlling documents:**  
- [Phase 5 Migration Readiness & Data-Safety Plan](phase-5-migration-readiness-data-safety-plan.md)
- [Phase 5 Physical Database Schema](phase-5-physical-database-schema.md)
- [Phase 5 Implementation Blueprint](phase-5-implementation-blueprint.md)
- [ADR-0012 — Approved Financial Rules](decisions/ADR-0012-approved-financial-rules.md)
- [ADR-0005 — User, Shop and Role Relationship](decisions/ADR-0005-user-shop-role-relationship.md)
- [ADR-0010 — User Lifecycle and Role Permissions](decisions/ADR-0010-user-lifecycle-and-role-permissions.md)

> This document is a migration design proposal only. It authorizes no code changes, migration execution, database mutation, data backfill, test execution, or production deployment. Implementation requires separate explicit Product Owner approval after review.

## 1. Purpose

Group 0 prepares the existing `users` and `customers` tables to act as tenant-safe parent tables for future financial records.

The design has two parts:

1. Add the composite unique keys required by future composite foreign keys.
2. Replace the existing Customer creator FK with a same-Shop composite FK, subject to the separate review recorded in this document.

No financial tables are created in Group 0.

## 2. Readiness decision

**Decision: design-ready, implementation-gated.**

The repository and target database review support the proposed parent keys. The existing Customer creator relationship can also be strengthened to enforce same-Shop ownership because the reviewed data and application lifecycle are consistent. That second change is explicitly identified as a change to a pre-existing relationship and must not be treated as an automatic consequence of adding the indexes.

The merged migration-readiness plan requires read-only preflight before strengthening an existing relationship. That preflight and lifecycle review have now been completed for the inspected development database and source baseline. Every persistent target environment must repeat the same checks immediately before its migration.

## 3. Reviewed baseline

### 3.1 Repository baseline

The design is based on `main` at merge commit:

```text
dbe6ee0c2ae708dfdcce06ca44683fe22f6eb3e2
```

Relevant existing schema:

- `users.id`: BIGINT UNSIGNED primary key.
- `users.shop_id`: nullable BIGINT UNSIGNED FK to `shops.id`.
- Existing User index: `(shop_id, role)`; no `(shop_id, id)` unique key.
- `customers.id`: BIGINT UNSIGNED primary key.
- `customers.shop_id`: required BIGINT UNSIGNED FK to `shops.id`.
- `customers.created_by`: required BIGINT UNSIGNED FK to `users.id`.
- Existing Customer indexes include `(shop_id, customer_code)`, `(shop_id, mobile)`, and name/status indexes; no `(shop_id, id)` unique key.
- Existing creator FK name: `customers_created_by_foreign`.
- Existing creator FK delete rule: RESTRICT / NO ACTION.

Both parent tables use InnoDB in the inspected database.

### 3.2 Read-only target database observations

The user-provided read-only preflight returned:

| Check | Observed result |
|---|---:|
| Database | `kisan_khata` |
| MySQL | `8.4.13` |
| Applied migrations | 12 |
| Pending migrations | 0 |
| Users | 12 |
| Shops | 1 |
| Customers | 25 |
| Users without Shop | 0 |
| Customer creator/Shop mismatches | 0 |
| Existing financial domain tables | 0 |

The inspected FK rules for `customers` were:

- `customers_shop_id_foreign` → `shops.id`, RESTRICT.
- `customers_created_by_foreign` → `users.id`, RESTRICT.
- `customers_village_code_foreign` → `villages.villageCode`, RESTRICT.

These are observations from the inspected database, not a guarantee about another environment. Do not use them as a substitute for a fresh target-environment preflight.

### 3.3 Application lifecycle review

The inspected source establishes these relevant protections:

- `Customer` uses `BelongsToCurrentShop` and `SoftDeletes`.
- The Customer creating hook assigns `shop_id` from server-resolved `CurrentShop`; it rejects a different requested Shop.
- Customer update/delete hooks verify current ownership and prevent changing `shop_id`.
- `CustomerRequest` does not accept `shop_id`; Customer mass assignment excludes both `shop_id` and `created_by`.
- `SaveCustomer` assigns `created_by` from the authenticated actor.
- `CustomerPolicy` requires an active shop user and same-Shop ownership.
- `User` rejects changes to `shop_id` and blocks account deletion.
- `UserBuilder` blocks unsafe bulk reassignment and deletion paths.
- Shop-user deactivation changes `is_active`, revokes credentials/sessions, and retains the User row and audit history. Reactivation restores the same row.
- The approved one-User/one-Shop/one-Role model remains in force.

The database FK will enforce membership identity only. It will not replace active-user checks, role authorization, or tenant-scoped application services.

## 4. Proposed schema changes

### 4.1 Parent unique keys

Add the following named unique indexes:

| Table | Columns | Constraint name |
|---|---|---|
| `users` | `(shop_id, id)` | `users_shop_id_id_unique` |
| `customers` | `(shop_id, id)` | `customers_shop_id_id_unique` |

Rationale:

- Future child FKs need a matching referenced composite unique key in MySQL.
- The globally unique `id` alone does not provide the explicitly tenant-qualified parent key required by the approved schema design.
- `shop_id` remains the leading column to align with tenant-scoped access patterns.
- Existing primary keys and ID types remain unchanged.

**Do not make `users.shop_id` NOT NULL.** Platform-level users may retain NULL Shop membership under the existing architecture. Financial actor child rows will use mandatory non-NULL `shop_id` and actor IDs, so a platform-only user cannot satisfy a same-Shop composite FK.

### 4.2 Customer creator same-Shop FK

After the parent unique keys exist, replace:

```text
customers.created_by -> users.id
```

with:

```text
customers.(shop_id, created_by)
    -> users.(shop_id, id)
```

Proposed FK name:

```text
customers_shop_creator_foreign
```

Delete and update behaviour: RESTRICT / NO ACTION. No cascade.

This guarantees that a Customer row cannot reference a creator belonging to a different Shop, even if application-level checks are bypassed.

This is a strengthening of an existing relationship, not a new business rule. It is recommended because:

- the target preflight found no creator/Shop mismatches;
- Customer creation derives both tenant and creator from trusted server context;
- User Shop reassignment is prohibited in model and builder safeguards;
- User deactivation retains the referenced User row;
- existing deletion restrictions preserve creator references.

**Approval gate:** The composite creator FK must be reviewed as an explicit part of the Group 0 implementation PR. If the Product Owner does not approve changing this legacy relationship, implement only the two parent unique keys and leave the simple creator FK unchanged. Do not silently choose between these options.

## 5. Migration delivery design

Use two small ordered migrations rather than mixing parent-index creation with the legacy FK replacement.

### Migration A — parent supporting keys

Suggested filename:

```text
2026_11_25_000001_add_shop_id_id_unique_indexes_to_users_and_customers.php
```

Operations:

1. Add `users_shop_id_id_unique` on `users(shop_id, id)`.
2. Add `customers_shop_id_id_unique` on `customers(shop_id, id)`.

No row values are updated or backfilled.

### Migration B — same-Shop Customer creator FK

Suggested filename:

```text
2026_11_25_000002_enforce_same_shop_customer_creator_foreign_key.php
```

Preconditions:

1. Migration A has completed.
2. The target-environment integrity query in Section 6 returns zero rows.
3. The existing FK name and column definitions match the reviewed baseline.
4. The Product Owner has approved the relationship change in the implementation PR.

Operation:

- Replace `customers_created_by_foreign` with `customers_shop_creator_foreign` over the two-column relationship.
- Preserve RESTRICT / NO ACTION semantics.
- Prefer a single MySQL 8.4 InnoDB ALTER operation that drops the old FK and adds the new FK together, so the table is not left without creator referential integrity between separate DDL statements. Verify the actual SQL emitted/executed by the selected Laravel migration implementation before merge.

If the exact current constraint differs from the reviewed baseline, stop and revise the migration design; do not guess a constraint name or drop an unknown FK.

## 6. Mandatory target preflight

Run these checks read-only against each persistent target immediately before Migration A/B.

### 6.1 Environment and migration identity

Confirm:

- connection driver is MySQL;
- database name and host are the intended target;
- server version is supported MySQL 8.4;
- `users` and `customers` are InnoDB;
- the migration history matches the expected baseline;
- both existing tables and the named FK match the expected definitions;
- a verified backup and restore procedure exist for any persistent non-CI environment.

Do not expose credentials in logs or chat.

### 6.2 Creator integrity

```sql
SELECT
    c.id AS customer_id,
    c.shop_id AS customer_shop_id,
    c.created_by,
    u.shop_id AS creator_shop_id
FROM customers AS c
LEFT JOIN users AS u
    ON u.id = c.created_by
WHERE u.id IS NULL
   OR u.shop_id IS NULL
   OR c.shop_id <> u.shop_id;
```

Expected result: zero rows.

If any row is returned, stop. Do not rewrite historical `created_by`, move a Customer, reassign a User, or invent a mapping. Escalate the exact records for a separately approved data decision.

### 6.3 Supporting-key and FK verification

Before running migrations, verify that:

- the proposed unique index names do not already exist with different definitions;
- equivalent indexes do not exist under unexpected names;
- the old creator FK exists exactly once and references `users(id)`;
- no unexpected financial tables or foreign keys depend on the current relationship.

If an equivalent key already exists, inspect and reconcile rather than adding a duplicate index. If an unexpected financial table exists, stop and inspect it; do not adopt, rename, overwrite or drop it.

## 7. Data preservation and lifecycle guarantees

The migrations must not:

- update or rewrite any User or Customer row;
- change `users.shop_id` nullability;
- change User roles, active status, or lifecycle semantics;
- change Customer status, `deleted_at`, or SoftDeletes behaviour;
- change `created_by` values;
- change existing Shop, village, audit or user-audit data;
- add cascading deletes;
- alter User or Customer primary keys;
- create financial tables or seed financial data.

Deactivated users remain valid creator references because their rows are retained. The FK must not reference an "active users only" subset or attempt to encode active status.

## 8. Rollback and recovery design

MySQL DDL may implicitly commit. Do not assume the migration is transactionally reversible.

Migration B rollback, if permitted:

1. Verify no dependent financial composite FKs remain.
2. Replace the composite creator FK with the original simple `customers.created_by -> users.id` FK using RESTRICT / NO ACTION.
3. Do not alter any row values.

Migration A rollback, if permitted:

1. Verify no child FK depends on either parent composite key.
2. Drop only the two named supporting unique indexes.
3. Preserve primary keys and all rows.

If later financial migrations or posted financial data depend on these parent keys, rollback must stop until those dependent migrations are safely handled under the approved financial rollback policy. Never drop a parent key merely to make a rollback command succeed.

After any partial DDL failure:

- inspect actual schema and migration history read-only;
- do not blindly rerun or manually edit the migrations table;
- reconcile the exact completed DDL before a reviewed recovery action.

## 9. Verification plan for the implementation PR

The implementation PR must include tests and observable verification for:

### Schema assertions

- both named composite unique indexes exist with exact column order;
- the new creator FK references `users(shop_id, id)`;
- old simple creator FK is absent after Migration B;
- RESTRICT / NO ACTION semantics are preserved;
- `users.shop_id` remains nullable;
- existing primary keys and ID types are unchanged.

### MySQL 8.4 integration cases

- same-Shop Customer/creator pair is accepted;
- cross-Shop Customer/creator pair is rejected by the database;
- creator with NULL `users.shop_id` cannot be assigned to a tenant Customer;
- deactivating a creator does not invalidate existing Customer rows;
- deleting a referenced creator remains restricted;
- existing Customer soft-delete and restore operations remain unaffected;
- existing User deactivation/reactivation remains unaffected;
- no existing User or Customer row is rewritten by either migration.

SQLite-only tests are not sufficient evidence for MySQL composite FK behaviour. CI must use the dedicated `kisan_khata_test` MySQL 8.4 database. Preserve `tests/bootstrap.php` safety checks. Do not run local tests against the development MySQL database; local PHPUnit must remain on SQLite `:memory:` unless a separate isolated MySQL test database is independently verified.

## 10. Acceptance criteria

Group 0 is complete only when:

- [ ] Target preflight passes in the intended environment.
- [ ] `users(shop_id, id)` unique key exists.
- [ ] `customers(shop_id, id)` unique key exists.
- [ ] Product Owner has explicitly approved or declined the creator-FK strengthening.
- [ ] If approved, same-Shop creator FK exists with RESTRICT / NO ACTION.
- [ ] If declined, the original creator FK remains unchanged.
- [ ] Existing User and Customer records and values are unchanged.
- [ ] User Shop nullability and User/Customer lifecycle behaviour are unchanged.
- [ ] MySQL 8.4 CI verifies composite FK enforcement.
- [ ] Migration rollback/recovery behaviour is reviewed.
- [ ] No financial domain tables or financial data are introduced by Group 0.

## 11. Out of scope

- Sales, Payments, Ledger, Interest, Allocation or Settlement migrations.
- CustomerDeletionEligibility registry correction (to be delivered with the real `ledger_entries` table).
- Composer dependency changes.
- Application model/service/policy changes.
- User/Customer backfills or data cleanup.
- Production deployment.
- Any financial posting workflow.

## 12. Required next approval

Review this design document first. The next implementation PR must be separately authorized and must contain only the approved Group 0 migrations, associated schema/integration tests, and any narrowly required migration support. No financial module implementation is included.

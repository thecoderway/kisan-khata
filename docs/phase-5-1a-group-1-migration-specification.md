# Kisan Khata — Phase 5 Group 1 Migration Specification

**Version:** 1.0  
**Date:** 2026-11-25  
**Status:** Engineering specification for Product Owner review; implementation not authorized  
**Target:** Laravel 13 / PHP 8.3 / MySQL 8.4 / InnoDB

**Controlling documents:**
- [Phase 5 Migration Readiness & Data-Safety Plan](phase-5-migration-readiness-data-safety-plan.md)
- [Phase 5 Physical Database Schema](phase-5-physical-database-schema.md)
- [Phase 5 Implementation Blueprint](phase-5-implementation-blueprint.md)
- [ADR-0012 — Approved Financial Rules](decisions/ADR-0012-approved-financial-rules.md)
- [ADR-0010 — User Lifecycle and Role Permissions](decisions/ADR-0010-user-lifecycle-and-role-permissions.md)

> This document defines Group 1 only. It authorizes no code changes, Composer changes, migrations, tests against local databases, database mutation, backfills or production deployment. Implementation requires separate explicit Product Owner authorization.

## 1. Scope lock

Group 1 creates exactly these three tables:

1. `idempotency_keys`
2. `customer_accounts`
3. `financial_transactions`

It may add only the supporting indexes and constraints required by these tables.

**Explicitly out of scope:** Sales, Sale revisions, Payments, Payment revisions, allocations, ledger entries, balance snapshots, interest policies, interest calculations, lots, settlement, customer-deletion eligibility changes, application posting services and UI.

The latest Migration Readiness Plan controls the group boundary. In particular, `ledger_entries` and `account_balance_snapshots` are Group 4, not Group 1.

Group 0 is already applied and locally verified. Group 1 relies on:
- `customers_shop_id_id_unique` on `customers(shop_id, id)`;
- `users_shop_id_id_unique` on `users(shop_id, id)`;
- the existing `shops.id` primary key.

Re-verify these on every target environment; local observations are not a substitute for target preflight.

## 2. Shared physical conventions

- Engine: InnoDB; charset/collation follow the existing project standard.
- IDs: BIGINT UNSIGNED, matching existing Laravel IDs.
- Every Group 1 row has a mandatory, non-null `shop_id`.
- Every Customer reference is tenant-qualified: `(shop_id, customer_id) -> customers(shop_id, id)`.
- Financial actor references are tenant-qualified: `(shop_id, actor_id) -> users(shop_id, id)`. Platform users with NULL `users.shop_id` cannot be financial actors.
- Shop, Customer, User and idempotency references use RESTRICT / NO ACTION. No cascade deletion.
- Business dates use DATE. Event timestamps use DATETIME(6) in UTC by application convention.
- Monetary projections use MySQL DECIMAL, never FLOAT/DOUBLE. PHP floats are prohibited at financial domain boundaries.
- Status/type columns use VARCHAR plus MySQL 8.4 CHECK constraints where appropriate, not MySQL ENUM.
- No existing Shop, User, Customer, Village or audit row is updated, backfilled or recreated.

## 3. Table A — idempotency_keys

### Columns

| Column | Type | Null | Contract |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | Standard Laravel ID |
| shop_id | BIGINT UNSIGNED | No | FK to shops.id |
| operation_type | VARCHAR(40) | No | Stable operation namespace, e.g. SALE_POSTING / PAYMENT_POSTING |
| key_hash | CHAR(64) | No | SHA-256 of client idempotency key; never store raw key |
| request_hash | CHAR(64) | No | SHA-256 of canonical validated request payload |
| status | VARCHAR(20) | No | IN_PROGRESS / COMPLETED / FAILED |
| resource_type | VARCHAR(64) | Yes | Result type metadata; not a polymorphic FK |
| resource_id | BIGINT UNSIGNED | Yes | Result ID metadata; not a polymorphic FK |
| response_code | SMALLINT UNSIGNED | Yes | Stored result status where applicable |
| created_at | DATETIME(6) | No | UTC |
| updated_at | DATETIME(6) | No | UTC |

### Keys and constraints

- UNIQUE `(shop_id, operation_type, key_hash)`.
- UNIQUE `(shop_id, id)` to support tenant-qualified references.
- INDEX `(shop_id, status, created_at)`.
- FK `shop_id -> shops.id`, RESTRICT.
- CHECK `status IN ('IN_PROGRESS','COMPLETED','FAILED')`.

### Required semantics

- Same Shop + operation type + key hash + same request hash: return the previously committed result; do not repeat the operation.
- Same key identity with a different request hash: reject as an idempotency conflict; never overwrite the original request hash.
- Canonical request hashing occurs after validation and normalization. Decimal values must be normalized as decimal strings; include the operation type, Customer identity, effective date and all business fields that affect the result.
- Concurrent duplicate requests must serialize on the unique key. A losing request reads/locks the winning record and returns its completed result or a deterministic in-progress response.
- Idempotency record, financial operation and COMPLETED result commit atomically. A rolled-back operation must not leave a false COMPLETED record.
- `resource_type/resource_id` are descriptive result metadata only. Do not use them as foreign keys.
- Do not expire/reuse a key while a delayed retry could duplicate a financial operation. Default implementation recommendation: retain committed idempotency records for the lifetime of the referenced financial operation; any archival policy requires a separate reviewed design.
- FAILED is reserved for a deliberately committed terminal outcome. Unexpected exceptions roll back the operation and its uncommitted idempotency row; they must not leave a misleading FAILED or COMPLETED record.

## 4. Table B — customer_accounts

One row per Shop/Customer. This is a serialization/locking anchor and a rebuildable projection, not the authoritative financial history.

### Columns

| Column | Type | Null | Contract |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | Standard Laravel ID |
| shop_id | BIGINT UNSIGNED | No | FK to shops.id |
| customer_id | BIGINT UNSIGNED | No | Composite FK to customers |
| principal_balance | DECIMAL(15,2) | No | Non-negative projection; default 0.00 |
| interest_balance | DECIMAL(15,2) | No | Non-negative projection; default 0.00 |
| total_payable | DECIMAL(15,2) | No | Projection; default 0.00 |
| last_interest_cutoff_date | DATE | Yes | Last completed exclusive calculation boundary |
| last_financial_event_at | DATETIME(6) | Yes | UTC ordering/audit aid |
| projection_version | BIGINT UNSIGNED | No | Start at 0; increment on each projection update |
| created_at | DATETIME(6) | No | UTC |
| updated_at | DATETIME(6) | No | UTC |

### Keys and constraints

- UNIQUE `(shop_id, customer_id)`: one account per Customer per Shop.
- UNIQUE `(shop_id, id)`: parent key for future tenant-safe child references.
- FK `shop_id -> shops.id`, RESTRICT.
- FK `(shop_id, customer_id) -> customers(shop_id, id)`, RESTRICT.
- CHECK principal, interest and total are non-negative.
- CHECK `total_payable = principal_balance + interest_balance` where supported and verified on MySQL 8.4; otherwise enforce in the projection service and reconciliation tests.
- No cascade delete.

### Lifecycle and concurrency

- Create the account lazily on the first accepted financial operation for that Customer. Do not create accounts for every existing Customer and do not backfill.
- Concurrent first requests use the unique key as the arbiter: attempt insert, handle duplicate-key race by re-reading the existing row, then lock it `FOR UPDATE`. Do not rely on an unprotected check-then-insert.
- All later posting, reversal, interest and projection operations lock the same account row first, following one global lock order.
- No direct balance editing through UI, API mass assignment or generic CRUD.
- Group 1 creates the projection structure only. Until later groups introduce authoritative posting/ledger records, these zero-initialized fields are not evidence of a customer's real-world balance and must not be presented as a live accounting balance.
- The projection becomes rebuildable from authoritative records when the ledger/allocation/lot groups are implemented. Group 1 must not invent balances.

## 5. Table C — financial_transactions

One immutable operation header for a financial business operation. A Paid Sale may later have one header covering both the Sale and its linked sale-day automatic Payment. A normal Payment is a separate operation. Typed child FKs in Group 2 will be authoritative.

### Columns

| Column | Type | Null | Contract |
|---|---|---:|---|
| id | BIGINT UNSIGNED PK | No | Standard Laravel ID |
| shop_id | BIGINT UNSIGNED | No | FK to shops.id |
| customer_id | BIGINT UNSIGNED | No | Composite FK to customers |
| transaction_number | VARCHAR(64) | No | Unique within Shop; generated server-side |
| transaction_type | VARCHAR(32) | No | SALE_POSTING / PAYMENT_POSTING / INTEREST_POSTING / CAPITALIZATION / REVERSAL |
| effective_date | DATE | No | Shop business date |
| occurred_at | DATETIME(6) | No | Actual UTC event instant |
| source_type | VARCHAR(64) | Yes | Optional diagnostic metadata only |
| source_id | BIGINT UNSIGNED | Yes | Optional diagnostic metadata only |
| idempotency_key_id | BIGINT UNSIGNED | Yes | Tenant-qualified FK to idempotency_keys; see decision gate below |
| created_by | BIGINT UNSIGNED | No | Authenticated same-Shop actor |
| posted_by | BIGINT UNSIGNED | No | Authenticated same-Shop posting actor |
| posted_at | DATETIME(6) | No | UTC posting instant |
| created_at | DATETIME(6) | No | UTC |

### Keys and constraints

- UNIQUE `(shop_id, transaction_number)`.
- UNIQUE `(shop_id, id)`.
- UNIQUE `(shop_id, idempotency_key_id)` to prevent one idempotency identity from owning multiple operation headers; MySQL permits multiple NULLs, so internal operations without a client key remain possible if approved.
- INDEX `(shop_id, customer_id, effective_date, occurred_at, id)`.
- INDEX `(shop_id, transaction_type, effective_date)`.
- FK `shop_id -> shops.id`, RESTRICT.
- FK `(shop_id, customer_id) -> customers(shop_id, id)`, RESTRICT.
- FK `(shop_id, created_by) -> users(shop_id, id)`, RESTRICT.
- FK `(shop_id, posted_by) -> users(shop_id, id)`, RESTRICT.
- FK `(shop_id, idempotency_key_id) -> idempotency_keys(shop_id, id)`, RESTRICT, nullable only if the approved internal-operation exception applies.
- No FK on `source_type/source_id`. These are optional diagnostic metadata; do not make them reciprocal polymorphic references. Group 2 typed Sale/Payment-to-header FKs are authoritative.
- Posted operation headers are immutable. No cascade delete.

### Authorization and integrity

- Resolve Shop, Customer and actor from authenticated server-side context; ignore client-supplied Shop/actor identity.
- Composite FKs prove same-Shop identity only. Application authorization must additionally verify active membership and allowed role at posting time.
- Require `created_by` and `posted_by` to be valid same-Shop users. A platform user with NULL Shop cannot satisfy these FKs.
- `transaction_number` generation and collision retry are server-side responsibilities; never accept it from the client.
- Do not create Sale or Payment records in Group 1. Do not add reciprocal source FKs or ledger dependencies.

## 6. Migration order

Use three ordered migrations in PR 5.1-A:

1. Create `idempotency_keys`, including Shop FK, unique identity, supporting `(shop_id,id)` key and status/index constraints.
2. Create `customer_accounts`, including Shop/Customer composite FKs, one-account unique key, projection checks and supporting `(shop_id,id)` key.
3. Create `financial_transactions`, after the idempotency and account tables exist; add tenant-qualified Customer, actor and idempotency FKs and all indexes.

No existing-table alteration is expected in Group 1. Do not add Sales, Payments or Ledger tables to satisfy a forward reference. No foreign-key checks are disabled.

Exact migration filenames, Laravel schema-builder syntax and any raw MySQL DDL must be included in the implementation PR and reviewed before execution.

## 7. Decimal arithmetic gate

Before any financial service is implemented:
- verify the locked `brick/math` package version and metadata;
- add `brick/math` as an explicit direct Composer dependency in a separately reviewed implementation change;
- use immutable Money/Decimal domain value objects;
- INR posting scale is 2 decimals; intermediate precision is at least 8 decimals;
- centralize HALF_UP rounding at the posting boundary;
- reject PHP float inputs and emit decimal strings or integer minor units in JSON/API output;
- test large values, small fractions, repeating division, zero and rounding boundaries against independent fixtures.

No Composer change belongs in this documentation specification. Do not silently rely on brick/math remaining transitively installed.

## 8. Read-only preflight before any persistent migration

Immediately before Group 1 implementation/execution, verify:
1. Target host, database name, connection driver and MySQL 8.4 version.
2. Target is not production unless a separate deployment plan is approved.
3. Existing migration history matches the expected baseline; Group 0 migrations are applied.
4. The two Group 0 unique indexes exist with exact column order and uniqueness.
5. `shops`, `customers` and `users` are InnoDB and the referenced FK definitions match.
6. No Group 1 or later financial table already exists outside migration history. If one exists, stop and inspect; do not adopt, rename or drop it.
7. No schema drift or conflicting index/constraint names exist.
8. A verified backup and restore procedure exists for any persistent non-CI target.

Preflight is read-only. Any mismatch is a stop condition, not a prompt for automatic repair. Do not update, delete, backfill or rewrite existing business rows.

## 9. Rollback and recovery

MySQL DDL may implicitly commit; Laravel migration transactions are not an atomic rollback guarantee.

- Test fresh install and reverse-order rollback only on the dedicated isolated MySQL 8.4 CI database.
- A down migration must refuse to drop any Group 1 table if it contains rows. This includes idempotency records and account anchors; preserving retry identity and financial references is safer than destructive rollback.
- Empty disposable CI schema may be dropped in reverse dependency order.
- After any persistent environment has accepted an operation header or idempotency identity, use a forward corrective migration or a separately approved backup restore; do not improvise `migrate:rollback`.
- After partial DDL failure, inspect actual tables, indexes, constraints and Laravel migration history before retrying. Never fake migration history or disable FK checks to force completion.

## 10. Required MySQL 8.4 acceptance tests

### Schema and tenant isolation
- All three tables are InnoDB and every row requires non-null Shop ID.
- Same-Shop Customer references succeed; cross-Shop Customer references fail at DB level.
- Same-Shop active actor references succeed; cross-Shop and NULL-Shop platform actor references fail.
- Cross-Shop idempotency-key reference fails.
- RESTRICT prevents referenced Shop, Customer, User or idempotency rows from being deleted.
- Unique constraints reject duplicate account per Shop/Customer, duplicate transaction number per Shop, duplicate idempotency identity and duplicate operation header per idempotency identity.
- CHECK constraints reject negative projections and inconsistent total where enforced.

### Idempotency behavior
- Same key + same canonical request returns the original committed result and creates no duplicate operation.
- Same key + different request hash is rejected without mutating the original record.
- Concurrent duplicate requests serialize correctly.
- Injected exception rolls back operation and does not leave a false COMPLETED record.
- Retried transaction after rollback can safely execute once.

### Account anchor
- Two concurrent first requests create exactly one account row.
- Both callers obtain and lock the same row before proceeding.
- Account projections remain non-negative and reconcile to component totals.
- No direct balance update path or existing-Customer backfill is introduced.

### Operation header and regression
- A Paid Sale can later be represented by one operation header with multiple typed child effects (verified when Group 2 lands).
- Same operation header cannot be linked to two idempotency identities or two keys.
- Actor Shop membership is enforced in database and active-role authorization in application tests.
- Existing Auth, Shop, Customer, tenancy, role, audit and soft-delete regression suites remain green.

SQLite is not evidence for MySQL composite-FK, CHECK, locking or concurrency behavior.

## 11. Test and execution safety

- CI uses only the dedicated `kisan_khata_test` MySQL 8.4 service with `CI=true`.
- Preserve `tests/bootstrap.php` safety guard.
- **Do not run local PHPUnit against the development MySQL database.** Local test execution remains prohibited until isolation is independently verified.
- Do not run migrations, destructive test setup or database mutation as part of this review.
- No local PHPUnit, Pint, migration or database command is claimed as run for this document.

## 12. Decisions required before implementation

These are the only Group 1 design choices left for Product Owner confirmation:

1. **Idempotency-key requirement:** Recommended: mandatory for user-initiated financial posting requests; nullable only for trusted internal operations that have their own deterministic event identity. Confirm this split or require a key for every operation header.
2. **Transaction number format:** Recommended: server-generated per-Shop sequence/number with collision-safe unique constraint; never derive it from client input. Exact format can be selected during implementation without changing financial semantics.
3. **FAILED idempotency status:** Recommended: persist FAILED only for deterministic terminal outcomes intentionally committed without a financial posting. Unexpected exceptions roll back the whole transaction and leave no idempotency row. Confirm this behavior.

All other table contracts and migration order above are engineering translations of the already approved architecture and ADRs, not requests to reopen business rules.

## 13. Approval gate

After Product Owner review:
1. Confirm the three decisions in Section 12.
2. Freeze this specification.
3. Re-fetch current `main` and audit exact migration/model conventions.
4. Prepare PR 5.1-A with only these three tables, required indexes/FKs, direct decimal dependency and focused CI tests.
5. Keep PR unmerged and migrations unexecuted until a separate explicit implementation approval.

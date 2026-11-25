# Kisan Khata — Phase 5 Migration Readiness & Data-Safety Plan

**Version:** 1.0  
**Date:** 2026-11-25  
**Status:** Engineering readiness proposal; no implementation authorization  
**Controlling business decision:** [ADR-0012](decisions/ADR-0012-approved-financial-rules.md)  
**Physical schema:** [Phase 5 Physical Database Schema](phase-5-physical-database-schema.md)  
**Implementation blueprint:** [Phase 5 Implementation Blueprint](phase-5-implementation-blueprint.md)  
**Target:** Laravel 13 / PHP 8.3 / MySQL 8.4 / InnoDB

> Documentation only. This plan authorizes no application-code changes, dependency changes, migrations, tests, database operations, data backfills, or production work. PR 5.1 requires separate explicit Product Owner authorization.

## 1. Purpose and readiness decision

This plan converts the physical schema proposal and read-only repository review into a safe, ordered migration strategy.

### 1.1 Document authority and migration-scope precedence

This plan is the controlling document for Phase 5 migration grouping, migration order, data-safety gates and implementation-slice boundaries. Where an older physical-schema proposal or implementation-blueprint delivery slice conflicts with this plan, follow this plan for migration sequencing and scope; retain the other documents for their respective logical and physical design details unless a specific conflict is explicitly resolved here.

The approved Group 1 / PR 5.1-A migration scope is exactly:
- `idempotency_keys`;
- `customer_accounts`;
- `financial_transactions`;
- the supporting tenant-safe indexes/constraints required by those tables;
- the selected decimal-arithmetic dependency, after its separate implementation review.

`ledger_entries` and `account_balance_snapshots` are Group 4, not Group 1. Sales and Payments are Group 2. This ordering does not remove those tables from the overall Phase 5 logical/physical target model; it only assigns them to their later migration groups.

The controlling financial business rules remain [ADR-0012](decisions/ADR-0012-approved-financial-rules.md). The application-wide right-side drawer standard is a separate UI decision and is indexed as ADR-0014.

**Readiness decision: conditionally ready for a migration implementation design, not ready for execution.**

The repository currently provides:
- shops, users, shop_settings, customers, and append-only audit_logs;
- Laravel BIGINT UNSIGNED primary keys and MySQL/InnoDB-oriented migrations;
- Customer tenant scoping and SoftDeletes;
- a dedicated CI database safety contract in tests/bootstrap.php;
- no financial Sales, Payments, Ledger, Interest, Allocation, or Settlement tables.

The current Composer lock contains brick/math as a transitive package. It is not declared as a direct application dependency in composer.json. It is a strong decimal-arithmetic candidate, but not yet an approved dependency decision.

No existing financial data migration or backfill is expected because the financial domain tables do not yet exist. Existing Shop, User, Customer, village reference and audit data must remain untouched.

## 2. Repository baseline reviewed

The review used main at:

    09f1d7d28a53501dba767cc327da40dcf232300c

Relevant existing contracts:

| Object | Current contract | Migration implication |
|---|---|---|
| shops | BIGINT UNSIGNED ID; timezone defaults to Asia/Kolkata | Financial business dates must be interpreted in Shop timezone |
| users | BIGINT UNSIGNED ID; nullable shop_id; role and active fields | Composite same-Shop actor references need a supporting unique key |
| customers | BIGINT UNSIGNED ID; required shop_id, required created_by, SoftDeletes | Add supporting (shop_id, id) unique key before tenant-safe child FKs |
| shop_settings | Unique (shop_id, setting_key) | Not a historical financial policy source |
| audit_logs | Nullable Shop/User IDs; append-only; created_at uses normal timestamp precision | Reuse it; do not make it the financial ordering source |
| CustomerDeletionEligibility | Checks sales, payments, udhaar_entries, customer_ledger_entries | Reconcile ledger table name to ledger_entries when that table is introduced |
| composer.json | No direct decimal arithmetic package requirement | Declare selected library directly before use |
| composer.lock | Contains brick/math 1.0.0 transitively; PHP ^8.2 | Verify package metadata and add direct requirement in a reviewed implementation change |

This is a source-repository review, not a live production database inspection. Before implementation, inspect the actual target database migration state and schema read-only. Do not assume the deployed database exactly matches checked-in migrations.

## 3. Confirmed technical decisions and recommendations

### 3.1 Parent unique keys for composite foreign keys

Before creating tenant-owned financial tables, add supporting unique keys:
- customers(shop_id, id);
- users(shop_id, id);
- later, financial parent keys such as sales(shop_id, id), sales(shop_id, customer_id, id), payments(shop_id, id), payments(shop_id, customer_id, id), and financial_transactions(shop_id, id), as required by child references.

Although id is already globally unique, MySQL composite foreign keys require a matching referenced unique key/index.

For users, shop_id is nullable for platform-level accounts. A composite unique index still supports references where both child values are non-NULL. Financial actor references must always include a non-NULL Shop ID and a same-Shop user ID. Platform-only users must not be accepted as financial posting actors.

Do not add a same-Shop composite FK to the existing customers.created_by relationship automatically. First run read-only consistency queries against the target database and verify the current customer-creation lifecycle. Any change to that pre-existing relationship must be separately reviewed if historical rows or platform workflows make it unsafe.

### 3.2 Financial actor ownership

For every financial row with an actor:
- shop_id is mandatory;
- created_by is mandatory for a user-initiated operation;
- posted_by is mandatory once the row is posted;
- actor references should be constrained by (shop_id, actor_id) -> users(shop_id, id) where practical;
- the application must additionally require an active user, authenticated Shop membership, and an authorized role at posting time;
- platform Owner accounts with NULL users.shop_id cannot directly post Shop financial transactions.

A database FK proves Shop membership identity, not current active status or role. Those checks remain mandatory in application authorization and tests. Preserve actor references after user deactivation; do not cascade-delete historical actors.

For financial audit writes, use the authenticated Shop and actor resolved by the server. Reuse audit_logs; do not add a second generic audit table. Keep audit_logs.created_at as operational audit time. Authoritative financial chronology comes from business effective_date, UTC occurred_at DATETIME(6), ledger sequence and immutable source references, not from the lower-precision generic audit timestamp.

### 3.3 Avoid circular polymorphic source dependencies

The physical proposal gives sales and payments a financial_transaction_id, while financial_transactions also has polymorphic source_type/source_id fields.

Recommended contract:
- financial_transactions is the operation header and is created first;
- sales.financial_transaction_id and payments.financial_transaction_id are the authoritative typed links to that header;
- a Paid Sale and its sale-day automatic Payment may reference the same operation header;
- do not make source_type/source_id a required reciprocal FK;
- if retained for diagnostics, treat polymorphic source fields as non-authoritative metadata, validated in the application and never as a substitute for typed FKs.

This avoids an unnecessary circular FK and permits one financial operation to contain the Sale and its linked automatic Payment.

### 3.4 Allocation-item deterministic uniqueness

Do not use nullable lot_sequence as part of the only uniqueness guarantee. MySQL permits multiple NULL values in a unique index.

Recommended contract:
- make lot_sequence NOT NULL;
- use sequence 0 for a component slice that has no interest-lot source;
- use positive, deterministic sequence numbers for lot-backed slices;
- retain sale_fifo_sequence and component_sequence;
- add a unique key that includes the non-NULL sequence values.

Keep allocation amounts positive. A reversal is represented by an explicit reversal direction/type and a reference to the original allocation item, not by a negative amount that conflicts with positive-amount constraints.

The source-lot rule must be enforced: a non-capitalized-interest or capitalized-interest-principal allocation item requires its source lot; original Sale principal does not.

### 3.5 Effective-dated policy versions

Use a serialized policy-version write:
1. lock the Shop's interest_policies aggregate row FOR UPDATE;
2. validate the proposed effective range against all existing ranges;
3. close the previous open range as a controlled version transition;
4. insert the next immutable version;
5. commit the change and its audit event atomically.

MySQL has no general exclusion constraint for date-range overlap. The lock plus transactional service validation is the required initial strategy. Add concurrent policy-update integration tests.

For Customer override versions, reject a row where both monthly_rate_percent and capitalization_enabled are NULL. A NULL individual field may still mean inherit that one setting from the Shop.

### 3.6 Event idempotency identity

Avoid uniqueness based on nullable trigger columns.

Use a canonical, non-NULL event identity, for example:
- event_key_hash CHAR(64);
- unique (shop_id, event_key_hash);
- include trigger type, Customer, exclusive cutoff and source event identity in the canonical hash input.

The same pattern can be used for calculation-run identity. Keep readable trigger/source fields for querying and audit. A retry with the same identity and same request hash returns the original result; a conflicting payload is rejected.

### 3.7 Recurring annual dates

Store the two annual dates as month/day pairs rather than full one-time DATE values:
- initial pair 1: month 3, day 30;
- initial pair 2: month 10, day 30.

Use small unsigned columns and application validation for month-specific calendar validity. The initial dates avoid leap-day ambiguity. Shop timezone determines the business date of the scheduled event.

### 3.8 Decimal arithmetic dependency

**Recommendation: use Brick\Math BigDecimal as the authoritative decimal arithmetic primitive.**

Evidence from the current repository:
- brick/math is already present in composer.lock as a transitive package;
- its locked metadata identifies it as an arbitrary-precision arithmetic library and PHP ^8.2 compatible;
- the project targets PHP 8.3.

Before implementation:
1. verify the locked package version and source metadata against the current Composer lock;
2. add brick/math as an explicit direct composer.json requirement rather than relying on another package to keep it installed;
3. lock the exact compatible version through Composer;
4. define a Money/Decimal domain wrapper so controllers and services do not pass floats;
5. define explicit scale, division precision and HALF_UP rounding at the posting boundary;
6. add independent test vectors for interest, allocation, capitalization, reversal and balance reconciliation.

Do not introduce a second money library unless Brick\Math cannot satisfy a documented requirement. Do not implement money arithmetic with PHP float, native floating-point operators, or SQL floating-point columns.

No Composer file has been changed and no dependency has been installed as part of this plan.

## 4. Migration dependency order

The following order is the recommended implementation sequence. Each group should be a small, reviewable migration slice. Exact migration filenames and SQL are implementation deliverables, not part of this document.

### Group 0 — Existing-table prerequisites

1. Add customers_shop_id_id_unique on customers(shop_id, id).
2. Add users_shop_id_id_unique on users(shop_id, id).
3. Add supporting composite keys to newly created financial parent tables with those tables.
4. Do not alter existing primary keys or change ID types.
5. Do not change generic audit timestamp precision in this phase unless a concrete ordering requirement proves it necessary.
6. Before strengthening any existing relationship, perform read-only target-database preflight checks and stop if historical rows violate the proposed constraint.

**Acceptance:** supporting unique indexes exist; no data rows are rewritten; existing user/customer relationships remain valid.

### Group 1 — Idempotency, account anchor and operation header

Create:
- idempotency_keys;
- customer_accounts;
- financial_transactions.

Requirements:
- every row includes mandatory shop_id;
- Customer references use composite Shop/Customer FK;
- operation header references Customer and idempotency identity;
- one account per Shop/Customer;
- account row is created safely when the first financial operation occurs;
- lazy account creation handles concurrent first requests (unique key, insert-or-ignore/retry strategy, then lock the row);
- projections are rebuildable and never directly user-editable.

### Group 2 — Sales and Payments

Create:
- sales;
- sale_revisions;
- payments;
- payment_revisions.

Requirements:
- Sales and Payments reference the operation header using typed FKs;
- sale-day automatic Payment references exactly one source Sale and has a unique Shop/source-Sale constraint;
- source Sale, Customer and Shop must match;
- payment and interest statuses remain separate;
- posted originals are immutable;
- reversal/replacement relationships are explicit and tenant-safe;
- actor columns use same-Shop references and server-side active-role authorization.

### Group 3 — Payment allocation

Create:
- payment_allocations;
- payment_allocation_items.

Requirements:
- one allocation header per Payment;
- normal Payment remains consolidated at the customer-facing layer;
- internal allocation items retain Sale FIFO, component waterfall and lot sequence;
- sequence columns used in uniqueness are non-NULL;
- positive amount plus explicit reversal reference/direction;
- source-lot ownership is tenant/customer/Sale consistent;
- reversal items preserve original references and never mutate originals.

If allocation items need a foreign key to interest_lots, add the column first as nullable and add the FK in a later migration after the Interest Lot table exists. Do not leave a permanently unenforced reference.

### Group 4 — Ledger and snapshots

Create:
- ledger_entries;
- account_balance_snapshots.

Requirements:
- one-sided debit/credit invariant;
- positive decimal amounts;
- append-only posting and compensating reversal entries;
- tenant/customer-safe references to operation, Sale and Payment;
- stable sequence within each financial operation;
- component-level balances reconcile to allocation items;
- snapshots remain rebuildable projections.

The existing CustomerDeletionEligibility registry must be changed from customer_ledger_entries to ledger_entries in the same reviewed release that introduces the new table. Keep the checker fail-closed during deployment; never create a dummy compatibility table. Its other future dependencies must remain fail-closed until their real modules exist.

### Group 5 — Effective-dated policy

Create:
- interest_policies;
- interest_policy_versions;
- customer_interest_policy_versions.

Requirements:
- one Shop policy aggregate per Shop;
- version numbers unique per aggregate;
- effective intervals use inclusive start/exclusive end;
- policy changes are prospective only;
- initial defaults follow ADR-0012;
- no Customer grace-period override;
- concurrent version writes serialize on the policy aggregate;
- reject empty Customer override rows.

### Group 6 — Interest calculations and lots

Create:
- interest_calculation_runs;
- interest_calculation_items;
- interest_lots;
- interest_lot_events.

Requirements:
- calculation run identity is non-NULL and idempotent;
- trigger Payment references the same Shop/Customer;
- every item stores exact policy version and half-open interval;
- posted result uses decimal-safe HALF_UP rounding to two places;
- lot source and original amount are immutable;
- remaining lot value is derived from events;
- oldest lot order is lot_date ASC, lot_id ASC;
- no positive ledger posting for a zero interest result;
- any deferred FK to allocation items is added after allocation tables exist.

### Group 7 — Annual Interest Auto Settlement

Create:
- interest_settlement_runs;
- interest_settlement_items.

Requirements:
- annual interest settlement is distinct from crop-season settlement;
- unique Shop/Customer/business-date identity;
- exclusive settlement cutoff;
- same-day normal Payment precedes settlement;
- run resumes from previous completed exclusive checkpoint;
- retry cannot duplicate interest;
- all run/item references are tenant-safe.

### Group 8 — Final constraints and reconciliation

Add or verify deferred FKs, CHECK constraints and supporting indexes that could not be created earlier because of dependency order.

Provide a reconciliation command/service in a separately scoped implementation delivery. It must compare:
- account projections to ledger totals;
- Sale remaining components to postings, allocations and reversals;
- interest-lot remaining amounts to lot events;
- Payment amount to allocation totals;
- Paid Sale to its linked automatic Payment;
- all Shop/Customer ownership edges.

Do not add a true company-wide General Ledger in this scope.

## 5. Circular references and deferred constraints

The physical schema contains references that may require staged FK creation. Resolve them deliberately:

| Relationship | Recommended approach |
|---|---|
| Sale/Payment -> financial transaction | Create operation header first; typed child FK points to it |
| Financial transaction -> source Sale/Payment | Do not require reciprocal polymorphic FK; use typed child references as authority |
| Allocation item -> interest lot | Add nullable source column initially; add FK after lot table exists |
| Interest lot event -> allocation item | Add nullable reference initially; add FK after allocation table exists |
| Calculation run -> annual settlement run | Create nullable settlement reference; add FK after settlement-run table exists |
| Calculation item -> ledger entry | Create ledger before calculation items or add FK after ledger exists |
| Reversal -> original record | Same-table composite self-FK with Shop/Customer consistency |

Avoid disabling foreign-key checks to force a migration through. Avoid weakening a tenant-safe FK to a simple ID FK merely to satisfy creation order.

## 6. Data-safety and preflight plan

Before the first migration PR is run against any persistent database:

### 6.1 Read-only environment verification

- Confirm database host, database name, connection driver and server version.
- Confirm the target is not production unless a separately approved deployment plan exists.
- Confirm current migration status.
- Confirm actual schema matches the checked-in baseline.
- Confirm backups and restore procedure for any persistent non-CI environment.
- Do not expose database credentials in logs or chat.

### 6.2 Read-only integrity preflight

At minimum, inspect:
- duplicate or inconsistent Shop/User membership pairs;
- Customer rows whose created_by user is not in the same Shop (before any attempt to strengthen that legacy FK);
- missing Shop or Customer parents;
- existing migration history and any manually created financial tables;
- existing shop_settings keys that may collide with future financial settings;
- whether any test or local database contains data that must be retained.

The expected financial table baseline is no financial module tables. If any such table exists outside migrations, stop and inspect it; do not overwrite, rename, drop or adopt it without an explicit decision.

### 6.3 Data preservation rules

- Never truncate, refresh, drop, or recreate existing users, shops, customers, villages or audit_logs.
- Do not backfill invented financial balances.
- Do not infer historical Sales, Payments or Interest from Customer notes or unrelated records.
- Do not convert SoftDeleted Customers to active or remove their deleted_at.
- Do not cascade-delete existing records.
- No production migration or deployment is included in PR 5.1.

## 7. Rollback and recovery strategy

MySQL DDL may implicitly commit. A Laravel migration transaction must not be treated as a guarantee that all DDL changes can be rolled back atomically.

For each migration:
1. document its exact forward change;
2. document dependency-aware reverse order;
3. test fresh install and rollback on an isolated MySQL 8.4 CI database;
4. ensure rollback never touches pre-existing business rows;
5. make rollback refuse to drop financial tables once posted financial records exist;
6. use forward corrective migrations for any environment that has accepted financial postings.

Recommended policy:
- Empty, newly created financial schema may be rolled back in reverse FK order in disposable CI.
- Once any posted Sale, Payment, Ledger, Interest, Allocation or Settlement record exists, destructive rollback is prohibited.
- A failed deployment after financial posting requires a forward fix or restore from a verified backup under a separate incident plan, never an improvised migrate:rollback.

Migration down() methods must check for financial records before dropping tables. A refusal with a clear exception is safer than silently destroying accounting history.

## 8. Decimal arithmetic implementation gate

Before implementing any financial service:
- declare Brick\Math as a direct dependency after version verification;
- define immutable Money/Decimal value objects;
- reject float input at domain boundaries;
- define currency scale (INR 2 decimals), intermediate scale (at least 8 decimals), division precision and rounding mode;
- centralize HALF_UP posting rounding;
- test large values, tiny fractions, repeating division, negative reversal direction, zero and boundary rounding;
- compare expected values with independently calculated fixtures;
- ensure JSON/API serialization emits decimal strings or integer minor units, never binary floats.

The package recommendation is an engineering choice for evaluation, not a new business rule. If compatibility or API review rejects it, stop and present an alternative before adding any dependency.

## 9. CI and test safety plan

### Mandatory CI database

- MySQL 8.4 dedicated database name: kisan_khata_test.
- CI must set CI=true, DB_CONNECTION=mysql, and DB_DATABASE=kisan_khata_test.
- Preserve the existing tests/bootstrap.php guard.
- Verify CI credentials point only to the dedicated test service.
- Never run the MySQL integration suite against the local development database.

### Local safety

The current test bootstrap forces non-CI PHPUnit to SQLite :memory:, but the prior accidental development-database wipe means local test execution remains prohibited until the exact command/environment and database isolation are independently verified. Do not ask the user to run migrate:fresh, RefreshDatabase or destructive test setup against the development MySQL connection.

### Test layers

1. Unit: Money/Decimal, interest day-count, grace period, interval boundaries, FIFO ordering and policy resolution.
2. MySQL schema: composite FKs, CHECK constraints, unique NULL behavior, RESTRICT deletes and FK creation order.
3. Feature: authorized Sale/Payment posting, tenant isolation, status transitions, audit records and customer deletion guard.
4. Transaction: injected failure at each posting step leaves no partial financial records.
5. Concurrency: simultaneous first-account creation, Payments, retries and policy version changes.
6. Reconciliation: projections, ledger, allocations and interest lots agree.
7. Regression: existing Auth, Shop, Customer, tenancy, roles, audit and CI safety tests remain green.

SQLite may support selected isolated unit/feature tests, but it cannot be accepted as evidence for MySQL 8.4 locking, composite-FK or CHECK behavior.

No tests were run while preparing this plan.

## 10. Delivery slices and approval gates

| Slice | Scope | Required approval |
|---|---|---|
| Readiness plan (this document) | Migration order, data safety, engineering recommendations | Review this document |
| PR 5.1-A | Supporting indexes, idempotency, account anchor, operation header, decimal dependency and minimal core | Separate explicit implementation authorization |
| PR 5.1-B | Sales and Payments schema + safe deletion-eligibility name reconciliation | Review after 5.1-A |
| PR 5.1-C | Allocation and ledger schema | Review after 5.1-B |
| PR 5.1-D | Policy and Interest schema | Review after 5.1-C |
| PR 5.1-E | Annual settlement, reconciliation and final constraints | Review after 5.1-D |

Each slice must:
- be implemented on a dedicated branch;
- include migrations, models/services only for the approved scope, and focused tests;
- run Pint for PHP changes;
- pass CI on the dedicated MySQL 8.4 test database;
- include a migration/rollback report and exact test evidence;
- be submitted as a reviewable PR;
- remain unmerged until explicit Product Owner approval.

Do not combine the entire 20-table schema into one large migration PR.

## 11. Stop conditions

Stop and request Product Owner direction if:
- the live target schema differs from the repository baseline;
- a composite FK would invalidate existing data;
- any financial table or financial records already exist unexpectedly;
- a required business behavior is missing or conflicts with ADR-0012;
- decimal package compatibility cannot be established;
- MySQL 8.4 CI isolation cannot be demonstrated;
- migration rollback would destroy posted history;
- a requested implementation would change approved business rules.

Do not solve a stop condition by silently dropping a constraint, inventing a backfill, or changing a business rule.

## 12. Implementation authorization boundary

This plan recommends the migration order and engineering safeguards. It does not authorize:
- editing application code;
- editing composer.json or composer.lock;
- creating migrations or running Artisan migration commands;
- running tests locally or against any persistent database;
- seeding, backfilling, truncating, dropping or altering live data;
- deploying or merging implementation PRs.

After review of this plan, the next step is to request separate explicit authorization for the first implementation slice. The first slice must re-fetch main, models, migrations, Composer metadata and actual target schema before making any changes.

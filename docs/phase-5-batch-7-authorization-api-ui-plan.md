# Phase 5 — Batch 7: Authorization, APIs & Financial UI

**Status:** Historical planning specification; Batch 7 implementation was subsequently merged through PR #43. This document preserves the intended authorization/API/UI contract and is retained for traceability, not as a claim that Batch 7 remains unimplemented. Current delivery and acceptance state is recorded in `docs/PROJECT-STATUS.md` and `docs/phase-5-batch-8-financial-integrity-fixes.md`.  
**Base branch:** `main`  
**Original planning baseline:** Batch 6 merged at `d73c8c103889b6aa777f3701f280945afdc8227e`  
**Current documentation baseline:** `main` at `62419de9872f84a38a6d8d422e11dbadca73a1ca` (2026-11-27)  
**Controlling decisions:** ADR-0010, ADR-0012, ADR-0013, ADR-0014  
**Stack:** Laravel 13, PHP 8.3, MySQL 8.4, Blade, Tabler, Alpine.js, Vite, Sanctum

## 1. Objective

Expose the already implemented Phase 5 financial domain through secure, tenant-scoped web workflows and versioned APIs. Batch 7 connects existing financial application services to Owner, Manager and Staff experiences without moving accounting rules into controllers or the browser.

Batch 7 must deliver:
- server-side role authorization for every financial read and write;
- stable `/api/v1` endpoints for web and future Android clients;
- responsive financial screens for Sales, Payments, Customer Ledger, Interest Settings and reconciliation;
- auditable Owner-only configuration, correction and repair operations;
- automated feature, authorization, tenant-isolation and MySQL 8.4 integration tests.

Batch 7 does not change the approved accounting formula, allocation algorithm, ledger semantics or financial history.

## 2. Verified starting point

The current `main` branch already contains:
- Sales, Payments, Payment allocations, immutable LedgerEntry, CustomerAccount and AccountBalanceSnapshot models;
- SalePostingService, PaymentPostingService, PaymentReversalService, SaleRevisionService;
- InterestCalculationService, InterestPolicyResolver and AnnualInterestSettlementService;
- BalanceProjectionService and ReconciliationService from merged Batch 6;
- Customer and User management web/API routes;
- a single financial API controller for Payment correction;
- role policies for Customer, User Management and User Audit Log;
- shared Action Drawer component and its approved UI standards.

**Historical statement at the time this plan was drafted:** the financial presentation/API layer was incomplete. That statement is superseded by subsequent merged work. On the current `main` branch, PR #43 delivered the Batch 7 financial authorization, API and web workspace foundation. `routes/web.php` exposes the shared `/financial/{section}` workspace for Sales, Payments, Customer Ledger, Interest Settings, Reconciliation and financial audit. `routes/api.php` exposes tenant-scoped `/api/v1` Sales, Payments, Customer Ledger, Reconciliation/repair, Interest Policy and financial-audit endpoints, alongside Payment correction. The controllers/services are under `app/Modules/Financial`; the principal web workspace is `FinancialWorkspaceController` and `resources/views/financial/workspace.blade.php`.

Subsequent PRs #53–#56 completed customer dropdown loading, brought Sales/Payments tables and drawers into the approved UI contract, corrected drawer layout/pagination presentation, and fixed pagination navigation/sidebar behavior. The Product Owner has manually confirmed the Sales/Payments listing, drawer layout, pagination navigation and sidebar behavior. This does not constitute full Phase 5 browser acceptance: new-Shop registration, tenant isolation, role-based allowed/denied paths and complete financial workflows remain part of the outstanding acceptance gate.

Accordingly, Sections 3–12 below are the original Batch 7 target contract and traceability checklist. They must not be treated as proof that every listed item has passed end-to-end browser acceptance. For current implementation gaps and acceptance evidence, consult the live Project Status and Batch 8 release-gate documents.

No Batch 7 migration is assumed. Reuse the existing schema unless the implementation audit proves a specific missing persisted requirement.

## 3. Binding role and authorization matrix

All decisions are enforced on the server through Policies/Gates and domain service checks. UI visibility is supplementary only.

| Capability | Owner | Manager | Staff |
|---|---|---|---|
| View shop financial overview | Yes | Yes, permitted operational view | Shop-wide operational financial view only where explicitly exposed; no Owner-only administration/audit |
| Create credit Sale | Yes | Yes | Yes |
| View Sales | All shop Sales | Shop Sales | Own created Sales |
| Correct/revise an eligible unlocked Sale | Yes, controlled workflow | No | No |
| Record customer Payment | Yes | Yes | Yes |
| View Payments | All shop Payments | Shop Payments | Own recorded Payments |
| Reverse/replace Payment | Yes, mandatory reason + idempotency | No | No |
| View Customer Ledger | All shop Customers | Shop-wide operational customer access | All Customers and ledger entries within assigned Shop (ADR-0015) |
| Configure Shop interest policy | Yes | No | No |
| Configure Customer interest overrides | Yes | No | No |
| View interest calculations/settlements | All shop | Permitted operational read | Own work/customer context only |
| Run annual auto-settlement manually | No ad-hoc/manual posting in this batch | No | No |
| View reconciliation report | Yes | No | No |
| Explicitly repair a projection | Yes, audited and confirmed | No | No |
| View financial audit history | Yes | No | No |

Additional constraints:
- Active Shop membership and Shop context must be resolved from the authenticated user; never trust request `shop_id`, role, creator ID or actor ID.
- Manager and Staff cannot edit or delete posted Sales or Payments.
- Only Owner can initiate Payment reversal/replacement and eligible Sale revision; locked Sale corrections remain rejected under ADR-0012.
- Under accepted ADR-0015, active Staff may read all Customer profiles, posted Sales, Payments and Customer Ledger entries within their assigned Shop, regardless of creator. Staff cannot access Owner-only financial audit history, interest administration, reconciliation/repair or correction actions.
- Do not introduce custom per-user permissions, multi-shop membership or ownership transfer.
- If existing ADR-0010/roadmap language conflicts with a domain-specific rule, ADR-0010 and the approved domain rule take precedence; document any ambiguity rather than silently broadening access.

## 4. Functional scope

### 4.1 Sales

**Web**
- Paginated, searchable Sales list with date, Customer and payment-status filters.
- Sale detail with original amount, effective date, status, creator, audit context and linked sale-day Payment where applicable.
- Add Sale through the shared right-side drawer.
- Display Paid/Unpaid selection and approved optional crop/season tag, description and private bill attachment only if the existing schema/service supports them.
- Owner-only controlled revision action for eligible unlocked Sales. Never expose direct edit/delete of posted financial records.

**API**
- `GET /api/v1/sales`
- `POST /api/v1/sales`
- `GET /api/v1/sales/{sale}`
- Owner-only controlled revision endpoint only if the existing SaleRevisionService contract supports it.

The controller validates and authorizes, then calls SalePostingService/SaleRevisionService. It must not calculate balances or write ledger entries directly.

### 4.2 Payments

**Web**
- Paginated Payment list and detail.
- Record consolidated customer Payment through the shared drawer.
- Show payment method/reference/description and resulting backend-confirmed balance.
- Owner-only correction drawer captures mandatory reason and optional replacement details.
- Display reversal/replacement linkage; original Payment remains immutable.

**API**
- `GET /api/v1/payments`
- `POST /api/v1/payments`
- `GET /api/v1/payments/{payment}`
- `POST /api/v1/customers/{customer}/payments/{payment}/correction` (existing route retained and brought under the complete authorization contract).

Payment writes require a valid `Idempotency-Key`. The service remains responsible for interest-through-payment-date, payable recheck under lock, excess-payment rejection, FIFO/waterfall allocation, ledger posting and projection reconciliation.

### 4.3 Customer Ledger

**Web**
- Customer-scoped ledger page with date range, opening/closing balance, transaction chronology, transaction type, debit, credit and deterministic running balance.
- Show consolidated customer-facing Payments once; do not present internal Sale allocation items as separate customer payments.
- Clearly distinguish principal, non-capitalized interest, capitalization/reclassification, Payment, reversal and settlement entries.
- Print-friendly view can be included only if it does not expand into the later Reports/Exports phase.

**API**
- `GET /api/v1/customers/{customer}/balance`
- `GET /api/v1/customers/{customer}/ledger`

Ledger data must be derived from authorized backend queries and ledger records; never accept a client-provided balance.

### 4.4 Interest Settings and calculation transparency

**Owner web settings**
- Shop default monthly interest rate (initial default 0%).
- Shop grace period (initial default 30 calendar days).
- Shop capitalization default (initial OFF).
- Shop payment waterfall (Principal First / Interest First).
- Two annual auto-settlement dates (initial 30 March and 30 October).
- Customer-level monthly-rate and capitalization overrides, with absent values inheriting Shop defaults.
- Effective-from date and clear explanation that policy changes are prospective only.

Grace period remains Shop-level only; no Customer grace override. Historical policy versions must not be overwritten.

**API**
- `GET /api/v1/interest/policy`
- `PUT /api/v1/interest/policy` (Owner only; creates a new effective-dated version, never updates historical versions).
- `GET /api/v1/customers/{customer}/interest-policy`
- `PUT /api/v1/customers/{customer}/interest-policy` (Owner only).
- `GET /api/v1/customers/{customer}/interest/calculations`
- `GET /api/v1/interest/settlements` and settlement detail/read endpoints as supported by existing run/item models.

No endpoint may accept an arbitrary rate override for a calculation request or directly post interest. Interest calculations remain event-driven by Payment and the two scheduled annual settlement dates.

### 4.5 Reconciliation and projection repair

**Owner-only**
- Read-only reconciliation screen with Customer search, status and structured discrepancy details.
- Explicit repair action for one Customer, with a confirmation summary and mandatory reason.
- Show before/after expected projection and fresh reconciliation result.
- Write an append-only audit record for actor, Shop, Customer, reason, timestamp and outcome.
- Never silently repair during page load, list query or ordinary financial posting.

**API**
- `GET /api/v1/reconciliation/customers/{customer}` — read-only.
- `POST /api/v1/reconciliation/customers/{customer}/repair` — Owner only, explicit confirmation, mandatory reason and idempotency key.

The Batch 6 `reconcileCustomer` read path and `rebuildAndReconcile` repair path are the domain boundary. Controller code must not duplicate ledger aggregation. Repair must not create financial transactions, compensating postings or mutate ledger history.

### 4.6 Financial audit trail

Provide an Owner-only, tenant-scoped financial audit history view/API for:
- Sale and Payment creation;
- Sale revision and Payment correction/reversal/replacement;
- Shop and Customer interest policy changes;
- annual settlement outcomes where already recorded;
- explicit reconciliation repair.

Use existing audit infrastructure where it meets the required append-only and tenant-safe semantics. Do not log passwords, OTPs, tokens, secrets or unnecessary personal data. If the existing FinancialAuditService does not cover an event, extend it in the relevant domain transaction; do not create a parallel audit framework without evidence.

## 5. Route, response and API conventions

- All new mobile-facing routes live under the existing `auth:sanctum`, `abilities:app`, `tenant` and `/api/v1` middleware structure.
- Web routes require authenticated, password-changed and tenant middleware.
- Route model binding/query resolution must not disclose cross-Shop records; inaccessible foreign records should follow the established 404/authorization policy.
- Use Form Requests or equivalent dedicated validation, Policies, API Resources and consistent JSON responses.
- Collections use server-side pagination, validated page size (10/20/50/100; maximum 100), server-side filters and stable ordering.
- Financial amount input is a decimal string with strict precision; no PHP/JS floating-point money arithmetic.
- Write endpoints return server-confirmed resource state and resulting balance projection.
- All mutating financial operations use service-level idempotency where supported. Do not blindly retry ambiguous writes.
- Maintain API v1 backward compatibility; document any intentional response contract.

## 6. UI/UX implementation contract

- Use the approved Blade + Tabler + Alpine.js + Vite stack. Do not add Tailwind or jQuery.
- Reuse the existing shared Right-Side Action Drawer for routine Add, Edit, correction and policy forms.
- All drawer forms use the shared asynchronous Fetch/Alpine submission contract from ADR-0013: preserve values and keep the drawer open on errors; close only after recognized server-confirmed success.
- Use dedicated pages for Sales/Payments listings, Customer Ledger and Reconciliation workspace where the page is primarily a table/report or complex workspace.
- Production tables must be horizontally scrollable with only the first column sticky, server-side paginated, and must not cause page-level horizontal overflow.
- Text search debounce is 350–400 ms; discrete filters apply on change; stale requests must not overwrite newer results.
- Support Hindi and English, the approved Green/White/Slate palette, keyboard access, focus management, responsive small Android widths and accessible status labels.
- Do not hardcode financial values or calculate authoritative balances in JavaScript.

## 7. Delivery sequence

### 7.0 Contract and implementation audit
- Inspect service method signatures, model relationships, audit contracts, attachment handling, transaction boundaries and idempotency support.
- Map every role/action to a Policy and route.
- Verify schema sufficiency; record any required migration separately for approval.
- Produce endpoint/resource contract before controllers.

### 7.1 Authorization foundation
- Add/extend Sale, Payment, Customer Ledger, Interest Policy, Reconciliation and Financial Audit policies.
- Ensure policies check active membership, role, Shop ownership and record ownership.
- Apply the same rules to web and API.
- Add negative tests for every forbidden role/action and cross-Shop ID.

### 7.2 Financial APIs
- Implement versioned endpoints and API Resources for Sales, Payments, balance/ledger, interest policy/history, settlement reads and reconciliation.
- Keep controllers thin and delegate all mutations to application services.
- Add request validation, pagination, filters, response contracts and idempotency handling.

### 7.3 Financial web workflows
- Implement Sales and Payments listings/details/create flows.
- Implement Customer Ledger page.
- Implement Owner Interest Settings and Customer override workflows.
- Implement Owner Reconciliation view and explicit repair drawer.
- Implement financial audit history.
- Reuse shared drawers, toast, table and filter patterns.

### 7.4 Audit and operational safety
- Verify audit event completeness and append-only behavior.
- Ensure policy changes are effective-dated and prospective.
- Ensure repair is explicit, idempotent and separately auditable.
- Verify private attachments and authorization on download if attachments are included.

### 7.5 Verification and documentation
- Run Pint, targeted tests, full Laravel suite, Vite production build and MySQL 8.4 CI.
- Add API contract documentation/OpenAPI only using the project's adopted convention; do not add a new documentation dependency without approval.
- Update roadmap, endpoint documentation and Batch 7 status with actual observed test/CI evidence.
- Submit one reviewable PR with a clear scope, migrations called out separately and CI evidence.

## 8. Required test matrix

### Authorization and tenancy
- Unauthenticated requests are rejected.
- Inactive users are rejected.
- Owner/Manager/Staff allowed actions match the matrix.
- Manager/Staff cannot revise Sales, correct Payments, configure interest, view financial audit logs, reconcile or repair.
- Staff can view Shop-wide Sales/Payments/Ledger read data under ADR-0015; cross-Shop access and Owner-only administration/correction remain forbidden.
- Cross-Shop Customer/Sale/Payment/policy/reconciliation IDs cannot be accessed or mutated.
- Client-supplied Shop ID, role, actor ID and creator ID cannot change authorization context.

### Financial correctness
- Sale and Payment controllers invoke existing services and preserve atomic posting.
- Duplicate Idempotency-Key behavior is deterministic.
- Excess Payment is rejected without partial writes.
- FIFO, per-Sale waterfall, same-day interest and annual settlement ordering remain unchanged.
- Payment correction requires reason and idempotency and preserves original records.
- Locked Sale revision is rejected.
- Ledger running balances agree with authoritative ledger projection.
- Read-only reconciliation has no writes.
- Repair requires explicit Owner action and reason, restores projection/snapshots/statuses and does not mutate ledger history.
- Policy changes create prospective versions and never rewrite history.

### UI and API
- Validation errors return the documented 422 structure.
- Drawer retains values on validation/authorization/server/network errors and closes only on confirmed success.
- Paginated filters, stable sort and stale-request handling work.
- Hindi/English labels, accessibility and responsive layouts pass.
- Vite production build succeeds.

### Database and concurrency
- MySQL 8.4 tests cover foreign keys, composite tenant constraints, row locks and concurrent financial writes.
- All financial writes and audit records commit or roll back atomically.
- Tests use an isolated test database only.

## 9. Explicit exclusions

- No changes to ADR-0012 formulas, grace-period semantics, FIFO/waterfall, capitalization or settlement dates' initial values.
- No retroactive policy rewrite or historical interest restatement.
- No exceptional correction of locked Sales.
- No Customer advance balance.
- No daily/monthly interest posting job.
- No crop-season settlement accounting.
- No subscription/billing implementation.
- No reports/export suite beyond the Customer Ledger's own print-friendly view.
- No Android client implementation or offline sync.
- No ownership transfer, custom roles or multi-Shop membership.
- No production migration, backfill, deployment or production data repair.
- No merge without separate explicit Product Owner authorization.

## 10. Database and migration gate

The current schema appears to contain the financial policy, ledger, allocation, audit and snapshot foundations required for this batch. A new migration is not part of the plan by default.

If implementation audit identifies a genuine schema gap:
1. stop the affected workstream;
2. document the exact missing invariant and proposed migration;
3. assess existing data impact and rollback/recovery;
4. request explicit Product Owner approval before creating or executing the migration.

Never run migrations or backfills against production as part of Batch 7.

## 11. Definition of Done

Batch 7 is complete only when:
- every in-scope role/action is enforced server-side and tested;
- all planned APIs are tenant-scoped, versioned, validated and documented;
- web financial workflows work through the approved UI patterns;
- interest policy changes are effective-dated and Owner-only;
- reconciliation is read-only by default and repair is explicit, Owner-only and audited;
- no financial invariant or historical ledger entry is weakened;
- all targeted and full automated tests pass, including MySQL 8.4;
- Pint and Vite build pass;
- documentation reflects actual implementation and verification;
- a reviewable PR is opened.

PR merge, production migrations, backfill and deployment remain separate explicit approvals.

## 12. Approval checkpoints and open clarifications

This document is a planning artifact, not authorization to implement Batch 7. The Product Owner has requested the plan. Before coding, confirm only unresolved product-level details discovered during the 7.0 audit:
- Staff financial read visibility is resolved by accepted ADR-0015: Shop-wide read access for all Customers, posted Sales, Payments and Customer Ledger entries; no further clarification is required.
- whether Sale revision is required in this batch or can be limited to the existing approved revision service contract;
- whether financial audit history should reuse the current shared audit screen or have a dedicated financial view;
- whether bill/receipt attachment upload is already supported end-to-end and should be included now.

Do not block the plan on speculative new features. Resolve these points from approved documents and existing code first; ask the Product Owner only where the repository leaves a material ambiguity.

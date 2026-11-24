# Kisan Khata — Project Status

**Last Updated:** 2026-11-24  
**Status Snapshot Base:** Phase 4B branch `feature/phase-4b-customer-management` at `8c78012` (2026-11-24), stacked on the Phase 4A branch.  
**Project Status:** Phases 2 and 3 are accepted on their feature branch. Phase 4A Shop Profile/LGD location editing is implemented and manually smoke-tested on Draft PR #10. Phase 4B Customer Management is implemented and CI-verified on Draft PR #11; Phase 4 remains in progress pending review and remaining translation/product decisions. No production deployment is claimed.  
**Release:** Not released; no production deployment is claimed.

## 1. Source of truth and mandatory project rules

The authoritative baseline is the approved **Docs 01–10** at their recorded document versions, together with **ADR-0001 through ADR-0010** in `docs/decisions/`. The roadmap in `docs/10-development-roadmap.md` defines the official phase numbering and sequence. This status file records evidence-based progress; it does not replace or silently amend approved requirements.

These rules are mandatory for all future work, including AI-assisted work:

1. **Document-first:** Before planning or changing code, read the relevant approved PRD, Business Rules, Accounting Engine, Database, Multi-Tenancy, Security, UI/UX, API and Testing sections, the applicable roadmap phase, and all relevant ADRs.
2. **Inspect before implementing:** Audit the existing code, migrations, routes, policies, tests and dependencies. Do not assume a feature is absent or complete from a status label alone.
3. **No silent scope or policy changes:** Do not invent business rules, defaults, permissions, pricing, calculations or architecture. If a requirement is missing, ambiguous or conflicts with an ADR/document, stop the affected work, explain the conflict and obtain Product Owner approval. Update the authoritative document/ADR before implementation.
4. **Small, reviewable delivery:** Work on a dedicated feature/documentation branch, implement one independently verifiable scope at a time, and submit a PR. Never merge to `main` without the Product Owner's explicit approval.
5. **Security and tenancy by design:** Derive tenant context from authenticated membership; never trust client-supplied `shop_id` or role. Enforce authorization server-side and test cross-tenant access for every tenant-owned feature.
6. **Financial integrity:** Follow Docs 02/03/04 and accepted ADRs exactly. Use decimal-safe arithmetic and MySQL DECIMAL; never PHP floats for authoritative money. Financial posting must be transactional, idempotent, concurrency-safe and auditable. Do not introduce sale-level payment allocation.
7. **Tests are evidence:** Add relevant automated tests and actually run them. Never claim tests, builds, migrations or acceptance criteria passed without observed output.
8. **Review all changes:** Report changed files, migrations, security implications, test output and known limitations. Preserve existing working functionality unless explicitly approved otherwise.
9. **Documentation stays synchronized:** Update the relevant technical document and this status file in the same delivery whenever implementation changes documented behavior or verified progress. Distinguish implemented, tested, accepted and production-configured states.
10. **Approval gates:** Ask before significant architecture/business changes, PR creation when not already authorized, and every merge. SMS provider integration is explicitly deferred to the final integration/release-readiness stage by Product Owner instruction.

If any future instruction conflicts with these rules or approved documentation, do not guess; surface the conflict and ask for a decision.

## 2. Approved baseline

| Document | Subject | Baseline |
|---|---|---|
| 01 | Product Requirements | Approved v2.0 |
| 02 | Business Rules | Approved v2.0 |
| 03 | Accounting Engine | Approved v2.0 |
| 04 | Database Architecture | Approved v2.0 |
| 05 | Multi-Tenancy | Approved v2.0 |
| 06 | Security | Approved v2.0 |
| 07 | UI/UX Guidelines | Approved v2.0 |
| 08 | API Architecture | Approved v2.0 |
| 09 | Testing Strategy | Approved v2.0 |
| 10 | Development Roadmap | Approved v2.0 |
| 11 | Geographic Master Data and Import Runbook | Added 2026-11-24 |

Accepted ADRs: ADR-0001 Modular Monolith; ADR-0002 Shared Database Multi-Tenancy; ADR-0003 Web-First, API-Ready Architecture; ADR-0004 Consolidated Customer Ledger and Payments; ADR-0005 User, Shop and Role Relationship; ADR-0006 Financial Transaction Correction and Audit; ADR-0007 Interest Calculation Events (specified scope only; parameters remain open); ADR-0008 Bilingual Customer Data; ADR-0009 Shop-Level Subscriptions; ADR-0010 User Lifecycle and Role Permissions; ADR-0011 Geographic Master Data and Shop/Customer Location Defaults.

See [approved docs](https://github.com/thecoderway/kisan-khata/tree/main/docs) and [ADR index](https://github.com/thecoderway/kisan-khata/blob/main/docs/decisions/README.md).

## 3. Executive summary

The initial Docker/modular-monolith foundation and initial shop identity/authentication/tenant-context implementation are present. The work merged into `main` includes:

| Delivery label used during implementation | Scope | Evidence-based status |
|---|---|---|
| Package 1 | Shop tenancy database foundation | Implemented; merged |
| Package 2A | Owner/shop registration and email/password authentication | Implemented; merged |
| Package 2B | CurrentShop context and tenant middleware | Initial foundation implemented; merged; broader Phase 2 acceptance remains |
| Package 2C | Mobile OTP API and Sanctum bearer tokens | Implemented; merged; real SMS delivery not configured |
| Package 2D | No separate approved implementation package has been defined in Roadmap v2.0 | Historical conversational label only; see official Phase 3 below |

**Important numbering clarification:** The approved roadmap defines **Phase 3 — Roles and User Management**. It does not define a Package 2D. If the team informally uses “Package 2D” for the next work, it must be explicitly treated as a delivery alias for the approved Phase 3 scope, not as a new or competing roadmap phase. The official roadmap numbering always takes precedence.

## 4. Official roadmap phase status

| Official phase | Scope | Current status |
|---|---|---|
| Phase 0 | Existing Project Audit and Foundation | Complete, based on recorded initial audit/foundation evidence |
| Phase 1 | Authentication and Shop Foundation | Partially implemented; core registration/authentication exists; user profile and shop profile deliverables/acceptance are not yet verified complete |
| Phase 2 | Multi-Tenancy Foundation | **Complete on `feat/tenant-isolation-foundation`**; request/job tenant context, fail-closed tenant queries, tenant-safe bulk writes, User/UserAuditLog ownership, private storage paths, cache-key namespacing, web/API role boundaries and regression coverage are implemented. Verified by successful MySQL 8.4 CI run #17; future business modules still require resource-specific isolation tests. |
| Phase 3 | Roles and User Management | **Complete — accepted on `feat/tenant-isolation-foundation`**. Owner-only user administration, Manager read-only Staff directory, capacity enforcement, lifecycle/session/token revocation, append-only audit, Web/API authorization and regression coverage verified by successful MySQL 8.4 CI run #19. Cross-domain Customer/Sales/Payment permission enforcement remains a requirement for those later domain phases, not implemented ahead of them. |
| Phase 4 | Customer Management | **In progress.** Phase 4A Shop Profile/LGD selectors are implemented and manually smoke-tested on Draft PR #10. Phase 4B customer CRUD, search, bilingual fields, LGD location defaults and versioned API are implemented and CI-verified on Draft PR #11. Translation automation/provider, customer lifecycle decisions and final Phase 4 acceptance remain open. |
| Phase 5 | Financial Foundation and Accounting Engine | Not started; critical prerequisite for financial workflows |
| Phase 6 | Credit Sales | Not started |
| Phase 7 | Payments | Not started |
| Phase 8 | Customer Ledger | Not started |
| Phase 9 | Interest Engine | Not started; unresolved business parameters must be approved first |
| Phase 10 | Crop-Season Settlement | Not started |
| Phase 11 | Reports, Printing and Exports | Not started |
| Phase 12 | Subscription System | Not started |
| Phase 13 | API Finalization and Android Readiness | Partial groundwork only; full versioned API contract is not complete |
| Phase 14 | Production Readiness | Not started |
| Phase 15 | Android Application | Not started |

The roadmap's five release milestones remain:
- **A — Secure Shop & Customer Foundation:** Phases 1–4.
- **B — Basic Financial Ledger:** Phases 5–8.
- **C — Financial Automation:** Phases 9–11.
- **D — Commercial SaaS Release:** Phases 12–14.
- **E — Mobile Expansion:** Phases 13 and 15.

No milestone is declared complete merely because some component packages have merged.

## 5. Implemented work and known gaps

### Phase 0 — Existing Project Audit and Foundation

Recorded as complete:
- Docker Compose environment with PHP 8.3, MySQL 8.4, Nginx, phpMyAdmin and frontend container.
- Modular Monolith structure and module namespaces.
- Environment/setup workflow, Composer test/lint scripts and Docker-based frontend build workflow.
- Persistent MySQL volume.

### Phase 1 — Authentication and Shop Foundation

**Implemented:**
- Shops and shop settings tables; user-shop relationship foundation.
- User role/mobile/language/active-state fields.
- Transactional Owner and Shop registration.
- Email/password login/logout, remember-me, password reset and login throttling.
- Inactive/suspended account checks.
- Registration does not trust client-supplied shop ID or role.
- Mobile OTP request/verification service; cryptographically generated six-digit OTP with hash-only storage.
- Five-minute expiry, single-use verification, five-attempt limit, resend cooldown and rate limiting.
- Generic unknown-mobile response.
- Sanctum token issuance after successful verification, token expiry and current-token logout.
- Daily OTP challenge-pruning command and scheduler registration.
- OTP feature tests and `docs/mobile-otp-auth.md`.

**Not yet verified complete against Phase 1 acceptance:**
- Full user profile workflow.
- Full shop profile workflow.
- End-to-end real SMS delivery (explicitly deferred).
- Complete phase-level acceptance review.

### Phase 2 — Multi-Tenancy Foundation

**Status: Accepted — CI verified**

**Implemented on `feat/tenant-isolation-foundation`:**
- Central `CurrentShop` service registered with request scope; tenant middleware derives shop only from the authenticated user, rejects inactive users/shops and platform-level roles, and clears context after request completion.
- Web and API business routes require tenant middleware; logout remains available to suspended shops.
- `ShopSetting` uses fail-closed tenant query scoping and a custom builder that protects bulk update/delete/increment/upsert/insert paths even when the global scope is explicitly bypassed.
- `UserBuilder` prevents shop reassignment, blocks unsafe bulk insert/upsert/delete/truncate paths, scopes bulk updates and increments to the current shop, and fails closed for unscoped bulk mutations. Controlled model-instance persistence remains available for authentication and lifecycle operations.
- `UserAuditLog` now has current-shop global query scoping, fail-closed behavior, tenant-validated model inserts and append-only mutation protection. The lifecycle service's raw insert remains a controlled system operation using the locked and validated shop/actor/target.
- Tenant cache-key helper prefixes keys with the current shop ID.
- Dedicated `tenant_private` filesystem disk and tenant-prefixed private-path helper reject traversal and absolute paths.
- `TenantAwareJob` contract and job middleware establish an active shop context and clear it after success or failure.
- Web/API route, cross-tenant model, bulk-mutation, platform-role, audit-history, cache-key, private-path and job-context regression tests.

**Infrastructure boundary review:**
- Current tenant-owned Eloquent data models are `ShopSetting` and `UserAuditLog`; shop users are protected through authenticated shop resolution, explicit shop-bound directory/lifecycle queries and `UserBuilder` mutation safeguards.
- No Customer, Sales, Payments, Ledger, tenant upload/download, tenant-specific cache consumer, tenant business job or export module exists yet. The Phase 2 primitives are ready for those modules; each module must still add its own resource ownership, authorization and cross-tenant tests before release.
- The current raw OTP query-builder path targets only the system-level `mobile_otp_challenges` table. The user-lifecycle audit writer is restricted to its validated shop context. Any future raw SQL/query-builder access to tenant-owned data requires explicit review and tenant filtering.
- No production deployment or tenant-specific file/cache/job feature is claimed.

**Verification evidence — GitHub Actions Laravel CI run #17:** [run `45352021969`](https://github.com/thecoderway/kisan-khata/actions/runs/45352021969), commit `4cf1d128a4c166f8bf3dcd9b6dc6a3dc50baacba`, completed successfully on MySQL 8.4:
- Pint: **89 files PASS**.
- Composer validation: passed.
- Vite production build: passed.
- MySQL row-lock integration: **1 passed / 2 assertions**.
- Remaining Laravel suite: **131 passed / 387 assertions**.
- Combined Laravel result: **132 tests passed / 389 assertions / 0 failures / 0 skipped**.

Phase 2 is accepted as the multi-tenancy foundation. Future modules remain subject to the tenant-isolation requirements in Docs 04/05/06/09 and ADR-0002/ADR-0005; this acceptance does not pre-approve their implementation or release.

### Phase 3 — Roles and User Management

**Status: COMPLETE — accepted on `feat/tenant-isolation-foundation`**

**Implemented and reviewed:**

- **P3.1 Authorization foundation:** `UserManagementPolicy`, `UserAuditLogPolicy`, Gate registration, server-side role authorization and tenant-boundary checks.
- **P3.2 User creation and first login:** Owner-only Manager/Staff creation, server-derived shop membership, temporary password hashing, forced password change in Web and Mobile flows, restricted first-login token.
- **P3.3 Capacity enforcement:** exactly 1 active Owner, maximum 2 active Managers, maximum 5 active Staff and 8 active users total; deactivated Manager/Staff release capacity; transactional shop-row lock and atomic role/reactivation checks.
- **P3.4 User lifecycle:** Owner-only deactivate/reactivate/role change; sole Owner/self-protection; retained accounts; Sanctum token revocation and database-session invalidation in the same application DB transaction. Deactivation fails closed unless `SESSION_DRIVER=database` and session connection matches the application connection.
- **P3.5 Audit history:** append-only `UserAuditLog`, Owner-only tenant-scoped paginated Web/API history, actor/target/action/old/new/reason/timestamp, Eloquent instance and bulk mutation protections.
- **Web UI and navigation:** Owner user directory, capacity summary, create form, lifecycle and role controls, audit-history page; Manager sees only Staff name and active/deactivated status; Staff has no user-management navigation or page access.
- **API security:** Owner-only lifecycle routes, role authorization, tenant-bound target lookup, paginated audit endpoint, minimal response projection that excludes email/mobile/password/token/tenant internals.
- **Regression coverage:** Owner/Manager/Staff/platform-role authorization, cross-shop isolation, user creation and forced password change, capacity limits and reactivation, role transitions, token/session revocation, append-only audit, Web and API lifecycle success/denial paths.

**Authoritative ADR-0010 rules verified:** Owner is the only shop-user administrator; Manager is read-only for Staff identity/status; Staff cannot access User Management; Owner cannot be ordinarily deactivated/demoted; ownership transfer remains a separate future process.

**Cross-domain boundary (not a Phase 3 blocker):** Customer profile CRUD and posted Sales/Payments correction endpoints do not exist yet; their modules belong to later official phases. ADR-0010 role boundaries remain authoritative and must be enforced by Customer/Sales/Payment policies and covered by their own authorization tests when those modules are implemented. No Phase 4/5/6/7 functionality was added in this Phase 3 delivery.

**CI verification — GitHub Actions Laravel CI run #19:** [run `45353098904`](https://github.com/thecoderway/kisan-khata/actions/runs/45353098904), implementation/test commit `d9512f3370bb65f30232e329b745fa2d393a2974`, completed successfully on MySQL 8.4:
- Pint: **90 files PASS**.
- Composer validation: passed.
- Vite production build: passed.
- MySQL row-lock integration: **1 passed / 2 assertions**.
- Remaining Laravel suite: **138 passed / 430 assertions**.
- Combined Laravel result: **139 tests passed / 432 assertions / 0 failures / 0 skipped**.

Phase 3 is accepted as the complete Roles and User Management phase. This is feature-branch acceptance, not production deployment/release approval. No merge to `main` is claimed.

### Phase 4 — Customer Management

**Status: In progress — Phase 4A and Phase 4B implementation evidence recorded; overall Phase 4 not yet accepted.**

**Phase 4A — Shop Profile and LGD selectors**
- Implemented on branch `feature/phase-4-shop-profile-location`, Draft PR #10: https://github.com/thecoderway/kisan-khata/pull/10.
- Owner-only shop location update; cascading MP LGD state/district/subdistrict/village selectors; server-side hierarchy validation; nullable location codes.
- Product Owner manually confirmed the shop-profile location selection, save and reload smoke checks passed.
- GitHub Actions Laravel CI run #75 (`45433670407`) passed for commit `cbfeac90b3a4e4554590703594de2ba0cee80acd`, including formatting, frontend build, MySQL row-lock integration and Laravel suite.
- PR #10 remains Draft/open/unmerged.

**Phase 4B — Customer Management**
- Implemented on branch `feature/phase-4b-customer-management`, Draft PR #11: https://github.com/thecoderway/kisan-khata/pull/11. PR #11 is stacked on the Phase 4A branch so its diff contains only Phase 4B changes.
- Tenant-scoped customer records and web CRUD/list/detail/edit; name/mobile search and pagination; Owner/Manager/Staff profile permissions; independent customer LGD location with shop-location preselection on create; Hindi/English original and translation fields; shared cascading lookup; versioned API endpoints at `/api/v1/customers`.
- Customer create/update writes append-only, tenant-scoped audit events. Customer deletion is not exposed or authorized.
- MySQL 8.4 CI run `45440619824` passed on commit `8c78012c2a5e99538062d4ab871d35be0a52a307`: Pint **110 files PASS**; Composer validation and Vite build passed; MySQL row-lock integration **1 test / 2 assertions passed**; remaining Laravel suite **156 tests / 518 assertions passed**. Combined result: **157 tests / 520 assertions, zero failures**.
- No migration was run against the Product Owner's local/development database. CI migrations/tests ran only against the dedicated ephemeral `kisan_khata_test` MySQL service.
- Explicit boundaries: customer code remains nullable (no invented generation format); automatic translation/provider integration is not enabled; customer deactivation/deletion lifecycle is not implemented pending approved rules; no sales, payments or ledger behavior is included.
- PR #11 remains Draft/open/unmerged. Phase 4 is not declared complete until review, unresolved requirements and remaining acceptance checks are closed.


### Geographic master data — implemented and imported (2026-11-24)

- Added the MP LGD `villages` table through migration `2026_11_24_000003_create_villages_table.php`.
- Added `lgd:import-villages`, a validation-first Artisan import command with dry-run default, selected-snapshot filtering, duplicate/required-field checks, expected-count validation, source SHA-256 stability checks, chunked inserts and transaction rollback on failure.
- Imported the 21-04-2026 snapshot: **57,484 Madhya Pradesh village records**.
- Verified total row count and unique `villageCode` count are both 57,484; all rows have snapshot date `21-04-2026` and state code `23`.
- Source CSV `imports/mp_villages.csv` is intentionally ignored by Git; the dataset itself is not committed.
- Import provenance, schema, selection rationale, command usage and verification procedure are documented in [Geographic Master Data and Import Runbook](11-geographic-master-data.md).
- This is shared reference data, not tenant data. Shop Profile and Customer location workflows are not yet implemented.

## 6. Remaining official phases — scope summary

1. **Phase 4 — Customer Management:** customer CRUD, search, LGD-backed location selectors and filters, shop-location defaults for new customer forms, independently saved customer location, required/optional fields, preserve original entered text and store Hindi/English translations separately; correction workflow and tenant-scoped tests.
2. **Phase 5 — Financial Foundation:** decimal-safe money, consolidated outstanding, ledger posting, advances, immutable audit history, idempotency, transaction atomicity and concurrency protection. This is a prerequisite for production sales/payments.
3. **Phase 6 — Credit Sales:** sale creation/posting/list/details, optional crop/season tag and private bill attachment, Owner-only corrections with before/after audit.
4. **Phase 7 — Payments:** consolidated payment processing, interest through payment date, excess as advance, idempotency and concurrency protection; no sale-level allocation.
5. **Phase 8 — Customer Ledger:** complete running account, sales/payments/interest/settlements/advance/corrections, filters and printable Hindi/English ledger.
6. **Phase 9 — Interest Engine:** policy management/versioning, actual-days/30 basis, grace period, capitalization and calculation history. Finalize unresolved date, rounding and capitalization parameters with Product Owner before implementation.
7. **Phase 10 — Crop-Season Settlement:** full/partial settlement, carry-forward, history, audit and duplicate prevention.
8. **Phase 11 — Reports, Printing and Exports:** tenant-scoped reports, PDF and Excel/CSV, totals reconciled to ledger.
9. **Phase 12 — Subscription System:** shop-level plans, trial, monthly/annual billing, renewal/expiry, three-day grace period, read-only enforcement and Owner data export. Do not invent pricing/entitlements.
10. **Phase 13 — API Finalization and Android Readiness:** complete approved versioned REST API, auth/customer/sales/payment/ledger/interest/settlement/file endpoints, documentation and integration tests. Current auth routes are under `/api`; approved architecture specifies `/api/v1`, so versioning remains outstanding.
11. **Phase 14 — Production Readiness:** hosting, HTTPS, secure configuration, encrypted off-site backup, restore tests, monitoring, logging, security/performance review and deployment/recovery procedures.
12. **Phase 15 — Android Application:** native Android workflows using the same backend business logic, secure token storage and integration tests. Offline synchronization is not in initial scope and requires separate approval.

## 7. Explicitly deferred integration

**Production SMS provider integration is deferred until the final integration/release-readiness stage**, by explicit Product Owner instruction. Do not select a provider, request credentials, add provider-specific dependencies or implement live delivery during Phases 3–15 unless the Product Owner changes this instruction.

The existing provider-independent sender contract and fail-closed null sender remain. Current OTP tests use a fake sender and do not prove real SMS delivery.

## 8. Unresolved product/business decisions

Do not invent defaults. Obtain Product Owner approval before implementing affected behavior:
- Interest capitalization policy.
- Interest rounding precision and rounding mode.
- Interest effective-date/day-boundary conventions and grace-period boundary.
- Exact two annual Auto Settlement dates and same-date payment/settlement behavior.
- Detailed backdated correction recalculation.
- Advance application rules.
- Crop-season settlement edge cases.
- Translation provider and correction workflow.
- Subscription plan pricing and entitlements.
- Payment gateway.
- Hosting region and operational data-retention parameters.

If a relevant document or ADR conflicts or is ambiguous, stop and report the conflict before coding.

## 9. Key financial and security invariants

- No sale-level payment allocation.
- Payments reduce consolidated customer payable after eligible interest is calculated through the payment date.
- Same-day subsequent payments must not duplicate interest.
- New sales retain their own sale dates; carried-forward balance has its own calculation-cycle start date.
- Excess payment is customer advance.
- Interest events are customer payment and two Owner-configured annual Auto Settlement dates; no ordinary daily/monthly posting job.
- Owner may edit posted Sales/Payments with immutable before/after audit history; Manager/Staff may not.
- Use decimal-safe arithmetic and MySQL DECIMAL for authoritative money values; never PHP floats for financial calculations.
- Resolve tenant context from authenticated membership; never trust client-supplied `shop_id`.
- Every tenant-owned query, relation, file, cache entry, export and background job must enforce tenant ownership.
- Financial posting must be transactional, idempotent, concurrency-safe and auditable.

## 10. Verified technical evidence

**Execution date:** 2026-11-23 (baseline) and 2026-11-24 (Phase 3)  
**Environment:** local Docker Compose development environment  
**Verified by Product Owner after merging PR #4:**
- Local `main` fast-forwarded to `origin/main` at merge commit `73617d1`.
- Working tree clean and local branch up to date at that time.
- `php artisan migrate:status`: all six listed migrations reported `Ran`.
- `php artisan test`: **30 passed, 113 assertions**.
- `vendor/bin/pint --test`: **53 files PASS**.
- Earlier frontend Vite production build was verified during Package 2B.

After PR #5, the Product Owner separately confirmed local `main` synced to `13ad942`, working tree clean and branch divergence `0 0`. No new application test run was reported after the documentation-only PR #5. The test results above remain the latest reported application verification, not a claim of a fresh run at `13ad942`.

**Additional feature-branch verification reported by Product Owner (2026-11-23):**
- Feature branch: `feat/tenant-isolation-foundation`, local fast-forward to commit `9cd5cc4`.
- Working tree was clean before the test run.
- `vendor/bin/pint --test`: **57 files PASS**.
- `TenantMiddlewareIsolationTest`: **10 passed, 25 assertions**.
- `TenantOwnedModelIsolationTest`: **17 passed, 26 assertions**.
- Full `php artisan test`: **57 passed, 164 assertions, 0 failures**.
- The cache/session/storage/jobs/database-access audit was also reported; findings are recorded in the Phase 2 section above.

**Phase 3 verification evidence (2026-11-24):**
- Earlier feature-branch CI run at `7c59921` passed: Pint, Composer validation, frontend production build, and Laravel tests (**113 passed / 346 assertions / 1 skipped**). The skipped test was the MySQL/InnoDB row-lock integration test because that run used SQLite, so it did not verify MySQL concurrency.
- The first MySQL 8.4 run (#9, `45348114561`) exposed a migration rollback foreign-key/index ordering defect and shared test-process migration-state interference. These were corrected in commit `b7de55665f02fc010a754e38b88b46ceedda0c4c` by fixing rollback order and isolating the MySQL row-lock group in its own CI process.
- **GitHub Actions Laravel CI run #10:** [run `45349066353`](https://github.com/thecoderway/kisan-khata/actions/runs/45349066353), commit `b7de55665f02fc010a754e38b88b46ceedda0c4c`, completed successfully against **MySQL 8.4**. Pint passed for **83 files**; Composer validation passed; Vite production build passed; MySQL row-lock integration test passed (**1 test / 2 assertions**); remaining Laravel suite passed (**117 tests / 360 assertions**). Combined Laravel result: **118 tests passed, 362 assertions, 0 failed, 0 skipped**.
- Run #10 is historical MySQL 8.4 CI evidence for the earlier Phase 3 implementation state. Formal Phase 2 and Phase 3 feature-branch acceptance is recorded in the official roadmap and the Phase 2/3 sections above; Phase 3's later CI evidence is run #19. Neither CI run is a production deployment verification.

No production deployment or release is claimed.


**Latest targeted regression verification (2026-11-24):**
- Branch: `feat/tenant-isolation-foundation`, commit `2d8c848`.
- The Product Owner fast-forwarded the local branch to this commit.
- Docker Compose `app` container executed:
  `php artisan test --compact tests/Feature/UserManagement/CreateShopUserTest.php tests/Feature/Auth/AuthenticationTest.php`.
- Result: **15 tests passed / 70 assertions / 0 failures** (2.73 seconds).
- This run specifically verifies the latest CreateShopUser authorization corrections, stale-actor regression coverage and initial Owner registration audit coverage. It is a targeted local test run, not a full-suite or CI run.

## 11. Git and delivery workflow

- Stable branch: `main`.
- Latest Product Owner-confirmed feature-branch sync: `2d8c848` on `feat/tenant-isolation-foundation`; targeted user-management/authentication tests passed as recorded above. The stable `main` branch has not been changed by this delivery.
- All feature and documentation changes use dedicated branches and PR review.
- No merge to `main` without explicit Product Owner approval.
- Update status only from verified repository, test or Product Owner evidence.
- Keep this document and relevant technical documentation synchronized with accepted implementation.

## 12. Immediate next steps

- Phase 4 is in progress on stacked Draft PRs #10 and #11. Continue Phase 4 only within its approved customer/location scope; resolve open translation and customer-lifecycle decisions before implementing those behaviors.
- Keep PR #10 and PR #11 Draft/unmerged until the Product Owner explicitly approves their merge.
- Keep real SMS provider integration deferred until final integration/release readiness.

This status file is a progress record. Approved product/technical documents and accepted ADRs remain authoritative.

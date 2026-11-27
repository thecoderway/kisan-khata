# Kisan Khata — Project Status

**Last Updated:** 2026-11-27  
**Status Snapshot Base:** `main` at `d1d36622c866ccb2b4113d254f62d7dcdec65a56` (2026-11-27), including merged PR #73 Phase 9 Interest History and Transparency.  
**Documentation update:** Phase 9 remains accepted; Phase 10 is explicitly deferred to a future release; Phase 11 has been reviewed and planned but not started. This documentation update is delivered through a dedicated PR.
**Project Status:** Phases 5–9 are accepted for their delivered scopes. Phase 9 PR #73 is merged, its exact-head CI passed, and the Product Owner confirmed the Interest History UI, calculation breakdown, filters and pagination tests. The live browser read-only before/after database comparison was not independently performed; automated read-only regression coverage passed. Phase 10 Crop-Season Settlement is explicitly deferred to a future release at the Product Owner's direction. Phase 11 Reports, Printing and Exports has been reviewed and planned but has not started and requires explicit authorization. No production deployment or release is claimed.
**Release:** Not released; no production deployment is claimed.

## 1. Source of truth and mandatory project rules

The authoritative baseline is the approved **Docs 01–10** together with accepted ADRs in `docs/decisions/`; ADR-0012 is the controlling Product Owner decision for Phase 5 financial rules and supersedes conflicting earlier statements. The roadmap in `docs/10-development-roadmap.md` defines the official phase numbering and sequence. This status file records evidence-based progress; it does not replace or silently amend approved requirements.

These rules are mandatory for all future work, including AI-assisted work:

1. **Document-first:** Before planning or changing code, read the relevant approved PRD, Business Rules, Accounting Engine, Database, Multi-Tenancy, Security, UI/UX, API and Testing sections, the applicable roadmap phase, and all relevant ADRs.
2. **Inspect before implementing:** Audit the existing code, migrations, routes, policies, tests and dependencies. Do not assume a feature is absent or complete from a status label alone.
3. **No silent scope or policy changes:** Do not invent business rules, defaults, permissions, pricing, calculations or architecture. If a requirement is missing, ambiguous or conflicts with an ADR/document, stop the affected work, explain the conflict and obtain Product Owner approval. Update the authoritative document/ADR before implementation.
4. **Small, reviewable delivery:** Work on a dedicated feature/documentation branch, implement one independently verifiable scope at a time, and submit a PR. Standing Product Owner authorization permits merge when CI is green, GitHub reports the PR mergeable, and no unresolved blocker or hold exists; otherwise stop and report.
5. **Security and tenancy by design:** Derive tenant context from authenticated membership; never trust client-supplied `shop_id` or role. Enforce authorization server-side and test cross-tenant access for every tenant-owned feature.
6. **Financial integrity:** Follow Docs 02/03/04 and accepted ADRs exactly. Use decimal-safe arithmetic and MySQL DECIMAL; never PHP floats for authoritative money. Financial posting must be transactional, idempotent, concurrency-safe and auditable. Customer-facing payments remain consolidated; internal FIFO allocation across Sales is required under ADR-0012.
7. **Tests are evidence:** Add relevant automated tests and actually run them. Never claim tests, builds, migrations or acceptance criteria passed without observed output.
8. **Review all changes:** Report changed files, migrations, security implications, test output and known limitations. Preserve existing working functionality unless explicitly approved otherwise.
9. **Documentation stays synchronized:** Update the relevant technical document and this status file in the same delivery whenever implementation changes documented behavior or verified progress. Distinguish implemented, tested, accepted and production-configured states.
10. **Approval gates:** Ask before significant architecture/business changes and PR creation when not already authorized. Under the standing merge authorization, merge only after green CI, mergeability and blocker review; respect any explicit hold/review-only instruction. SMS provider integration is explicitly deferred to the final integration/release-readiness stage by Product Owner instruction.

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

Accepted ADRs: ADR-0001 Modular Monolith; ADR-0002 Shared Database Multi-Tenancy; ADR-0003 Web-First, API-Ready Architecture; ADR-0004 Consolidated Customer Ledger and Payments (superseded in part by ADR-0012); ADR-0005 User, Shop and Role Relationship; ADR-0006 Financial Transaction Correction and Audit; ADR-0007 Interest Calculation Events; ADR-0008 Bilingual Customer Data; ADR-0009 Shop-Level Subscriptions; ADR-0010 User Lifecycle and Role Permissions; ADR-0011 Geographic Master Data and Shop/Customer Location Defaults; ADR-0012 Approved Financial Rules; ADR-0013 Asynchronous Action Drawer Form Submission; ADR-0014 Application-Wide Right-Side Drawer Standard; ADR-0015 Staff Shop-Wide Financial Read Access; ADR-0016 Customer Lifecycle Financial Visibility.

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
| Phase 2 | Multi-Tenancy Foundation | **Complete — merged into `main` via PR #7** (`8c670a1`). Tenant context, fail-closed tenant queries, tenant-safe bulk writes, User/UserAuditLog ownership, private storage paths, cache-key namespacing, web/API boundaries and job context are implemented. MySQL 8.4 CI evidence: run #17 (132 tests / 389 assertions, 0 failures, 0 skipped); PR #7 also records successful CI for its merged head. Future modules still require resource-specific isolation tests. |
| Phase 3 | Roles and User Management | **Complete — merged into `main` via PR #7**. Owner-only user administration, Manager read-only Staff directory, capacity enforcement, lifecycle/session/token revocation, append-only audit, Web/API authorization and regression coverage. MySQL 8.4 CI run #19: 139 tests / 432 assertions, 0 failures, 0 skipped. Cross-domain Customer/Sales/Payment permission enforcement remains required in those domain modules. |
| Phase 4 | Customer Management | **Implementation complete for merged scope — PRs #10–#14 merged into `main`.** Shop Profile/LGD selectors, customer CRUD/search, bilingual fields and location defaults, approved customer lifecycle policy, safe soft-delete builder behavior, and customer deactivate/reactivate/soft-delete/restore are merged and CI-verified. Product Owner manually smoke-tested Owner/Manager/Staff browser flows. Automatic translation provider integration remains explicitly deferred; no live provider integration is claimed. |
| Phase 5 | Financial Foundation and Accounting Engine | **COMPLETE — Product Owner accepted 2026-11-27.** Automated financial acceptance, Batch 7C combined lifecycle CI and local UI smoke acceptance recorded. No production release implied. |
| Phase 6 | Credit Sales | **COMPLETE — Product Owner accepted 2026-11-27** for the delivered scope. Sale posting/list/detail, controlled revision, audit, authorization and tenant safeguards delivered early in Phase 5 batches; no duplicate engine work. |
| Phase 7 | Payments | **COMPLETE — Product Owner accepted 2026-11-27** for the delivered scope. Consolidated posting, FIFO, interest integration, reversal/replacement, history, audit, authorization and concurrency safeguards delivered early in Phase 5 batches. |
| Phase 8 | Customer Ledger | **COMPLETE — Product Owner accepted 2026-11-27.** Batches 8A–8C merged through PRs #69–#71; exact-head CI passed; Product Owner confirmed all Batch 8D browser, print/PDF, ledger, balance, filter, pagination and regression checks passed. |
| Phase 9 | Interest Engine | **COMPLETE / ACCEPTED — 2026-11-27.** PR #73 merged as `6a9146c496b82143a7813683ed32a178eb60efb3`; exact-head CI passed. Product Owner confirmed UI, calculation breakdown, filters and pagination. Browser before/after DB comparison was not independently performed; automated read-only regression tests passed. |
| Phase 10 | Crop-Season Settlement | **DEFERRED TO FUTURE RELEASE — NOT STARTED.** Product Owner explicitly deferred this phase on 2026-11-27. Do not schedule or resume without explicit reactivation. |
| Phase 11 | Reports, Printing and Exports | **REVIEWED / PLANNED — NOT STARTED.** Existing list APIs, ledger print/PDF and interest history are reusable foundations; aggregate report APIs/UI and general CSV/XLSX exports are not present in inspected scope. Requires Product Owner authorization to begin. |
| Phase 12 | Subscription System | Not started |
| Phase 13 | API Finalization and Android Readiness | Versioned tenant-scoped financial API foundations exist from Phase 5 Batch 7; API contract finalization and Android readiness remain incomplete |
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
- Customer and financial modules now exist. Their resource ownership, role authorization and cross-Shop protections must be verified per resource and workflow; the Phase 2 foundation alone is not a substitute for domain-specific acceptance tests.
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

Phase 3 is accepted as the complete Roles and User Management phase and was merged into `main` through PR #7. This is feature acceptance, not production deployment/release approval.

### Phase 4 — Customer Management

**Status: Implementation complete for merged scope — all delivery PRs merged into `main`.**

#### Merged delivery history

| PR | Scope | Merge commit |
|---|---|---|
| [#10](https://github.com/thecoderway/kisan-khata/pull/10) | Phase 4A — Shop Profile and LGD location selectors | `c7c6b4640676a9a6d96602cd94af4c63d203361c` |
| [#11](https://github.com/thecoderway/kisan-khata/pull/11) | Phase 4B — Customer Management | `0d1c2abcf8473871134d2482a47c3712ae117d36` |
| [#12](https://github.com/thecoderway/kisan-khata/pull/12) | Approved customer lifecycle policy and acceptance criteria | `c6e0a0fcc080b2180d47abf2fbe6d84366c08025` |
| [#13](https://github.com/thecoderway/kisan-khata/pull/13) | Prevent unsafe bulk deletion of soft-deletable tenant records | `3fd8408a47663bcb485daff77d17d9499b9d2edf` |
| [#14](https://github.com/thecoderway/kisan-khata/pull/14) | Customer lifecycle implementation | `bc783ae23d33e6583dc7d1347a2d0408ba1835b3` |

GitHub confirms all five PRs are closed and merged into `main`; none remains Draft/open.

#### Delivered scope

- Owner-authorized Shop Profile location editing with cascading Madhya Pradesh LGD State/District/Subdistrict/Village selectors and server-side hierarchy validation.
- Tenant-scoped customer create, list, detail, edit, search and pagination; Hindi/English original and translation fields; versioned customer endpoints under `/api/v1/customers`.
- Customer location is stored independently; the shop location is only a default for new customer forms and can be overridden.
- Approved customer lifecycle: Active, Inactive and Soft Deleted; Owner/Manager deactivate/reactivate/soft-delete/restore permissions; mandatory reasons where specified; tenant-scoped append-only audit history; fail-closed deletion eligibility.
- Tenant-owned SoftDeletes bulk deletion safety guard.
- No Sales, Payments or Ledger workflows were introduced in Phase 4.

#### Verification and acceptance evidence

- **Phase 4A CI:** GitHub Actions run #75, `45433670407`, on commit `cbfeac90b3a4e4554590703594de2ba0cee80acd` — formatting, Composer validation, frontend build, MySQL row-lock integration and Laravel suite passed.
- **Phase 4B CI:** run `45440619824`, on commit `8c78012c2a5e99538062d4ab871d35be0a52a307` — Pint 110 files; Composer validation and Vite build passed; MySQL row-lock integration 1 test / 2 assertions passed; Laravel suite 157 tests / 520 assertions, zero failures.
- **Phase 4 lifecycle CI:** GitHub Actions Laravel CI run #131, `45556047948`, associated with lifecycle head commit `7b30fbe08429406ae540fbc15daa71144b4581fd` — PR #14 records 168 tests / 579 assertions, zero failures; Pint, Composer validation, frontend production build and MySQL 8.4 row-lock integration passed.
- **Product Owner browser smoke test:** Owner, Manager and Staff flows manually verified. This is manual smoke-test evidence, not a claim of comprehensive browser automation.
- No local development database migration or destructive database command was run for PR #14; CI used its isolated MySQL 8.4 test service.

#### Explicitly deferred / not claimed

- Automatic translation provider integration is not enabled. Provider selection and correction workflow remain an explicit Product Owner decision; do not select or integrate a provider without approval.
- Customer code remains nullable; no generation format has been invented.
- Customer soft-delete eligibility intentionally fails closed while financial dependency tables cannot be verified.
- No production deployment or release is claimed.

Phase 4 implementation and merged-scope verification are recorded as complete. The deferred translation provider decision must be resolved before claiming that live automatic translation is available; it does not authorize silent changes to the approved language-handling requirements.

### Geographic master data — implemented and imported (2026-11-24)

- Added the MP LGD `villages` table through migration `2026_11_24_000003_create_villages_table.php`.
- Added `lgd:import-villages`, a validation-first Artisan import command with dry-run default, selected-snapshot filtering, duplicate/required-field checks, expected-count validation, source SHA-256 stability checks, chunked inserts and transaction rollback on failure.
- Imported the 21-04-2026 snapshot: **57,484 Madhya Pradesh village records**.
- Verified total row count and unique `villageCode` count are both 57,484; all rows have snapshot date `21-04-2026` and state code `23`.
- Source CSV `imports/mp_villages.csv` is intentionally ignored by Git; the dataset itself is not committed.
- Import provenance, schema, selection rationale, command usage and verification procedure are documented in [Geographic Master Data and Import Runbook](11-geographic-master-data.md).
- This is shared reference data, not tenant data. Shop Profile and Customer location workflows are implemented and merged through Phase 4A/4B.

## 6. Remaining official phases — scope summary

1. **Phase 5 — Financial Foundation:** decimal-safe money, consolidated outstanding, ledger posting, excess-payment rejection, immutable audit history, idempotency, transaction atomicity and concurrency protection. This is a prerequisite for production sales/payments.
3. **Phase 6 — Credit Sales:** sale creation/posting/list/details, optional crop/season tag and private bill attachment, Owner-only corrections with before/after audit.
4. **Phase 7 — Payments:** consolidated payment processing, interest through payment date, internal FIFO allocation, excess-payment rejection, idempotency and concurrency protection.
5. **Phase 8 — Customer Ledger:** complete running account, sales/payments/interest/settlements/corrections, filters and printable Hindi/English ledger.
6. **Phase 9 — Interest Engine:** policy management/versioning, actual-days/30 basis, grace period, capitalization and calculation history. Finalize unresolved date, rounding and capitalization parameters with Product Owner before implementation.
7. **Phase 10 — Crop-Season Settlement:** full/partial settlement, carry-forward, history, audit and duplicate prevention. **Deferred to a future release by Product Owner instruction; do not start.**
8. **Phase 11 — Reports, Printing and Exports:** reviewed/planned but not started; tenant-scoped reports, PDF and CSV/Excel-compatible exports, totals reconciled to authoritative records. Requires explicit start authorization.
9. **Phase 12 — Subscription System:** shop-level plans, trial, monthly/annual billing, renewal/expiry, three-day grace period, read-only enforcement and Owner data export. Do not invent pricing/entitlements.
10. **Phase 13 — API Finalization and Android Readiness:** complete approved versioned REST API, auth/customer/sales/payment/ledger/interest/settlement/file endpoints, documentation and integration tests. Current auth routes are under `/api`; approved architecture specifies `/api/v1`, so versioning remains outstanding.
11. **Phase 14 — Production Readiness:** hosting, HTTPS, secure configuration, encrypted off-site backup, restore tests, monitoring, logging, security/performance review and deployment/recovery procedures.
12. **Phase 15 — Android Application:** native Android workflows using the same backend business logic, secure token storage and integration tests. Offline synchronization is not in initial scope and requires separate approval.

## 7. Explicitly deferred integration

**Production SMS provider integration is deferred until the final integration/release-readiness stage**, by explicit Product Owner instruction. Do not select a provider, request credentials, add provider-specific dependencies or implement live delivery during Phases 3–15 unless the Product Owner changes this instruction.

The existing provider-independent sender contract and fail-closed null sender remain. Current OTP tests use a fake sender and do not prove real SMS delivery.

## 8. Unresolved product/business decisions

Do not invent defaults. Obtain Product Owner approval before implementing affected behavior:
- Detailed crop-season settlement accounting.
- Backdated transaction correction/recalculation beyond approved Sale revision and Payment reversal/replacement.
- Subscription plan pricing and entitlements.
- Payment gateway.
- Hosting region and operational data-retention parameters.
- Translation provider and correction workflow.

Interest defaults, rounding/date conventions, grace-period boundary, annual settlement dates, same-date ordering, FIFO waterfall and the no-advance rule are finalized by ADR-0012 and are not unresolved.

If a relevant document or ADR conflicts or is ambiguous, stop and report the conflict before coding.

## 9. Key financial and security invariants

- Customer-facing payments are consolidated; internal FIFO allocation is required.
- Payments reduce consolidated customer payable after eligible interest is calculated through the payment date.
- Same-day subsequent payments must not duplicate interest.
- New sales retain their own sale dates; carried-forward balance has its own calculation-cycle start date.
- Excess payment is rejected; no customer advance balance exists.
- Interest events are customer payment and two Owner-configured annual Auto Settlement dates; no ordinary daily/monthly posting job.
- Posted Sales/Payments are immutable: eligible unlocked Sale corrections use revisions; Payment corrections use reversal and optional replacement. Manager/Staff cannot initiate corrections.
- Use decimal-safe arithmetic and MySQL DECIMAL for authoritative money values; never PHP floats for financial calculations.
- Resolve tenant context from authenticated membership; never trust client-supplied `shop_id`.
- Every tenant-owned query, relation, file, cache entry, export and background job must enforce tenant ownership.
- Financial posting must be transactional, idempotent, concurrency-safe and auditable.

## 10. Verified technical evidence

**Execution date:** 2026-11-23 to 2026-11-25 (recorded evidence; individual run dates are noted below)  
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

- Stable and sole permanent branch: `main`. Feature/documentation branches are temporary PR branches only and should be deleted after merge; do not maintain parallel `master` or other long-lived integration branches.
- Current verified `main` HEAD: `340f74302605899719514d2a6d1d39fe76db41b5` (PR #61 documentation synchronization; 2026-11-27).
- PRs #7 and #10–#14 are confirmed merged into `main`; no open Draft status is carried forward for these deliveries.
- All feature and documentation changes use reviewable PRs. Standing Product Owner authorization permits merge when CI is green, GitHub reports the PR mergeable, and no unresolved blocker or hold exists; otherwise stop and report.
- Update status only from verified repository, CI, test or Product Owner evidence.
- Keep this document and relevant technical documentation synchronized with accepted implementation.

## 12. Immediate next steps

- Phase 2 and Phase 3 are merged and accepted; Phase 4 implementation is merged and accepted for its recorded scope.
- **Current phase: Phase 5 implementation and automated acceptance through Batch 8.8 are merged. Product Owner reports the manual browser tests performed are passing, and the controlled Projection Mismatch/Repair test has passed; explicit final sign-off remains outstanding.**
- Product Owner financial rules are finalized in ADR-0012 and synchronized with docs/01, docs/02, docs/03, docs/04, docs/06, docs/09, docs/10 and the financial integrity guardrails.
- The detailed Phase 5 implementation blueprint is `docs/phase-5-implementation-blueprint.md`.
- PR #49 (Batch 8.7) and PR #50 (Batch 8.8) are merged; final feature-head CI passed. The post-merge `main` CI result is tracked separately in Section 27.
- Next product phase after Phase 5 sign-off is Phase 6 — Credit Sales. Do not mix later Phase 6–15 roadmap scope into Phase 5.
- No database migrations, local/production data changes or deployment without separate explicit Product Owner approval.
- Do not begin production Sales/Payment workflows until Phase 5 foundation passes its critical acceptance tests.
- Keep production SMS provider integration deferred until final integration/release readiness.

## 13. Phase 5 status — 2026-11-27 (updated after PR #60 and manual testing)

**Current delivery baseline:** Phase 5 financial implementation and automated acceptance through Batch 8.8 are merged into `main`; PRs #53–#56 and #60 add subsequent UI, customer lifecycle and legacy Interest Policy follow-ups. The current `main` HEAD is `340f74302605899719514d2a6d1d39fe76db41b5`. The Product Owner reports that all manual browser tests performed so far are passing. The controlled Projection Mismatch/Repair test has also passed in the local Demo Shop. Phase 5 remains OPEN only for explicit Product Owner final sign-off; production readiness is a separate later phase.

Controlling decision: `docs/decisions/ADR-0012-approved-financial-rules.md`.

The approved financial rules remain unchanged: consolidated customer-facing payments with internal FIFO allocation; per-Sale waterfall; excess-payment rejection and no advances; Shop/Customer interest policy; 30-day Shop grace period; Actual Days / 30; HALF_UP two-decimal rounding; [start inclusive, end exclusive) intervals; annual settlement dates defaulting to 30 March and 30 October; Payment-before-settlement ordering on shared dates; append-only, tenant-scoped, atomic, idempotent, concurrency-safe and auditable financial history.

**Merged delivery history:**
- Batch 5 — Annual Interest Auto Settlement: PR #40, merge commit `b30348130141ab6a43d2a2a56e151d389e3c38f9`.
- Batch 6 — Ledger Projection & Reconciliation: PR #41, merged.
- Batch 7 — Financial Authorization, APIs & UI: PR #43, merge commit `7b8b9791e04788569dc09935cc1854982732c717`.
- Batch 8.2 — projection repair lock ordering and recurring settlement calendar validation: PR #44, merge commit `a187480a4cdf132a1ffe6b3af4a8ac185b4b226d`.
- Batch 8.3 — lock-first, atomic repair through the actual authenticated API endpoint: PR #45, merge commit `bc9afb78811ca28d2238506bcba6ec1154ad05d7`.
- Batch 8.4 — release-gate documentation synchronization: PR #46, merge commit `a97cf7f9272e5a3d880a0a74ff23d43f633e9c3c`.
- Batch 8.5 — repair rollback/audit atomicity and reconciliation authorization regressions: PR #47, merge commit `5f98e0d4cdd3cf11eb3786004cac3a359aacb18c`.
- Batch 8.7 — complete financial workflow and correction-authorization acceptance: PR #49, merge commit `a67edfaacfc660ce0ecc9bae88ffa3e01307e5f4`.
- Batch 8.8 — real MySQL 8.4 concurrency acceptance for competing Payments, Sale-versus-Payment FIFO and same-day Payment/annual settlement: PR #50, merge commit `c740e44434071062c4cb8eac546f375a7e234126`.

**Batch 8.3 CI evidence:** GitHub Actions run [45868343441](https://github.com/thecoderway/kisan-khata/actions/runs/45868343441) completed successfully. All job steps passed, including MySQL row-lock integration, the remaining Laravel test suite, PHP formatting, Composer validation and Vite production build. This is CI evidence for the merged PR head; it is not production deployment or a full release acceptance sign-off.

**Batch 8.5 CI evidence:** GitHub Actions run [45872271027](https://github.com/thecoderway/kisan-khata/actions/runs/45872271027) completed successfully. All steps passed, including MySQL row-lock integration, remaining Laravel tests, Pint formatting, Composer validation and Vite production build. PR #47 adds failure-injection rollback coverage proving projection repair and its audit event roll back together, Manager/Staff repair denial and Owner cross-Shop customer denial, plus policy denial-matrix coverage. This is targeted regression evidence, not complete Phase 5 acceptance.

**Manual acceptance update (2026-11-27):** The Product Owner reports that the manual tests they performed across the remaining browser workflows are passing. The controlled Projection Mismatch/Repair test was completed in local Demo Shop ID 156 against synthetic Customer ID 26. Baseline reconciliation was consistent. A controlled ₹1 mismatch was introduced only in the derived customer_accounts projection (principal ₹9,431.00 and total ₹9,553.11); Ledger, Sales, Payments and snapshots were not modified. Reconciliation detected the principal and total mismatches. repairAndReconcile() returned repaired=true, consistent=true, and no discrepancies. Final read-only verification confirmed principal ₹9,432.00, interest ₹122.11, total ₹9,554.11, and projection version 6. All three snapshots were rebuilt with their original source ledger IDs (2, 3 and 5); Sale 1 remained PAID and Sale 2 remained PARTIALLY_PAID. Audit Log ID 316 records the before-state discrepancies and consistent after-state. Browser automation is not configured; this is Product Owner-reported local manual evidence, not CI evidence. Automated workflow, role/cross-Shop, rollback, MySQL 8.4 concurrency, formatting, Composer and Vite checks remain supported by their separately recorded CI runs.

**Separate later-stage release gates:** production environment configuration, backup/restore evidence, monitoring and deployment/recovery procedures belong to Phase 14 Production Readiness. Branch-protection and required-check settings remain unknown because the current GitHub integration cannot inspect repository administration settings; a repository administrator must verify them before release.

No production deployment, production data change, migration execution or release is claimed. Database migrations and deployment require separate explicit Product Owner approval.

## 29. Customer lifecycle financial visibility policy and implementation (2026-11-27)

ADR-0016 is ACCEPTED and indexed in `docs/decisions/README.md`; Business Rules are updated to v2.3. PR #58 (`1a573d813fe10d8113f1b316c5b67b448f3498a2`) merged the policy and implementation. Latest CI run #45970399456 (run #1400) completed successfully: PHP formatting, frontend unit tests, frontend build, MySQL row-lock integration and the remaining Laravel test suite all passed. The implementation separates Active-only Sale/Payment selectors, financial-history-only Ledger/Reconciliation selectors, and non-deleted customer selection for Owner-managed interest policy; preserves historical Sale/Payment customer identity and lifecycle labels for soft-deleted legacy records; restricts the deleted-customer directory to Owner/Manager; and enforces tenant/role authorization plus customer-first lock ordering. Automated regression coverage is merged. The Product Owner subsequently reports successful manual testing of the remaining browser workflows. The controlled Projection Mismatch/Repair scenario has since passed in local Demo Shop ID 156 / synthetic Customer ID 26; Phase 5 remains OPEN pending explicit final sign-off. PR #58 itself included no migrations, data backfills, production operations or deployment.

## Phase 5 — Historical Batch 5 and Batch 6 delivery note (superseded)

The original in-progress delivery notes below were superseded by the merged delivery baseline in Section 13. Batch 5 (PR #40) and Batch 6 (PR #41) are both merged into `main`; neither remains open. The current Phase 5 state and release gate are recorded in Section 13 and the current roadmap release-gate section.

## Phase 5 — Batch 7 and Batch 8 delivery correction (2026-11-26)

This delivery history is superseded by the current Phase 5 status in Section 13 above:
- Batch 5: merged via PR #40.
- Batch 6: merged via PR #41.
- Batch 7: merged via PR #43.
- Batch 8.2: merged via PR #44.
- Batch 8.3: merged via PR #45.
- Batch 8.4 is documentation synchronization only; it does not close the overall Phase 5 acceptance/release gate.
- Batch 8.5 is targeted rollback/audit atomicity and reconciliation authorization regression coverage; it does not close the overall Phase 5 acceptance/release gate.
- No production migration, production data change, deployment or release is included or authorized.


---

## 26. Phase 5 — Batch 8.7 financial workflow acceptance (2026-11-27)

Batch 8.7 was merged through PR #49 at `a67edfaacfc660ce0ecc9bae88ffa3e01307e5f4`. It adds a consolidated acceptance test for Sale, sale-day automatic Payment, normal Payment/FIFO, annual interest settlement and ledger reconciliation; failure-injection rollback checks for Paid Sale/automatic Payment and normal Payment; and authenticated Payment correction API checks for Owner, Manager, Staff and cross-Shop isolation.

The delivery also aligns `AGENTS.md`, `CLAUDE.md` and `.claude/rules/financial-integrity.md` with ADR-0012: customer-facing Payments remain consolidated while immutable internal FIFO allocation records are required. ADR-0012 supersedes the older ADR-0004 prohibition on internal Sale-wise allocation.

**Verification:** PR #49 feature-head CI runs [45876775514](https://github.com/thecoderway/kisan-khata/actions/runs/45876775514) and [45876767753](https://github.com/thecoderway/kisan-khata/actions/runs/45876767753) completed successfully, including MySQL 8.4 integration, remaining Laravel tests, Pint, Composer validation and Vite build. The post-merge `main` CI was also subsequently verified green in runs [45877841024](https://github.com/thecoderway/kisan-khata/actions/runs/45877841024) and [45877068509](https://github.com/thecoderway/kisan-khata/actions/runs/45877068509).

Browser automation is not configured; browser-level financial UI smoke acceptance remains a Product Owner manual sign-off item. No production database, production migration, backfill, deployment or release was performed or authorized. See [Batch 8.7 financial workflow acceptance](phase-5-batch-8-7-financial-workflow-acceptance.md).


## 27. Phase 5 — Batch 8.8 MySQL financial posting concurrency acceptance (2026-11-27)

Batch 8.8 is merged through PR #50 at merge commit `c740e44434071062c4cb8eac546f375a7e234126`. It adds `tests/Feature/Financial/FinancialPostingConcurrencyTest.php` with three MySQL 8.4 multi-connection/fork-based concurrency scenarios:

1. Two competing normal Payments: the first Payment holds the Customer lock; the second waits, re-reads the committed payable and is rejected rather than overdraw the account.
2. Sale-versus-Payment race: a Payment waits for an uncommitted Sale, then observes the committed Sale and allocates FIFO across both eligible Sales.
3. Payment-versus-same-day annual settlement: Payment posting and settlement serialize on the Customer lock; the settlement does not post duplicate interest and reconciliation remains consistent.

**Observed CI evidence:** [run 45879804264](https://github.com/thecoderway/kisan-khata/actions/runs/45879804264) completed successfully on head `5fd97786b7ca78bd22a1f200d36cebf551182be4`. Composer validation, Pint, Vite production build, MySQL 8.4 row-lock integration group and remaining Laravel test suite all passed. The three new concurrency tests passed as part of the MySQL row-lock group. No application behavior or schema changed; this batch adds test coverage only.

The post-merge `main` CI run [45880048532](https://github.com/thecoderway/kisan-khata/actions/runs/45880048532) completed successfully on merge commit `c740e44434071062c4cb8eac546f375a7e234126`; post-merge CI is green.

Detailed scenario setup, locking assertions, fixture cleanup and CI evidence are recorded in [Batch 8.8 MySQL financial posting concurrency acceptance](phase-5-batch-8-8-financial-posting-concurrency-acceptance.md).

**Scope and safety:** no production database, production migration, backfill, deployment or release was performed or authorized. Product Owner browser/UI smoke acceptance remains outstanding; Phase 14 production readiness and repository-admin branch-protection verification remain separate gates.


## 28. Phase 5 — Financial UI follow-up and partial browser acceptance (2026-11-27)

**Current main baseline:** `6506a4a01526cc54959712721b8f9d30465a88cd`.

Merged follow-ups after Batch 8.8:
- PR #53: customer directory loading for financial workflows and JS helper tests.
- PR #54: Sales/Payments server-side tables, auto filters, pagination and asynchronous action drawers.
- PR #55: Customer-style pagination presentation and improved drawer field layout.
- PR #56: working numbered pagination/Tabler arrows, independently scrollable sidebar with visible shop/logout footer, and Interest Settings moved into Settings.

**Manual evidence reported by Product Owner:** Sales/Payments listing and filters, customer dropdown, drawer layout, pagination navigation and sidebar behavior were checked in the local browser and confirmed working. Local frontend production build succeeded after pulling these changes. This is limited UI smoke evidence, not full Phase 5 acceptance.

**Still required:** create a fresh Shop through registration in a separate Incognito/browser profile; verify Shop A/Shop B isolation; verify Owner/Manager/Staff permitted and forbidden actions; exercise Sale, Payment, FIFO allocation, interest, ledger, reconciliation and explicit repair workflows; verify validation/error/success behavior. No new-Shop acceptance has yet been reported. Phase 5 remains OPEN.

**CI evidence:** post-merge `main` run [45961048236](https://github.com/thecoderway/kisan-khata/actions/runs/45961048236) completed successfully, including PHP formatting, automated tests and frontend build. CI does not replace browser acceptance.

**Next sequence:** complete fresh-Shop browser acceptance, record results, close Phase 5 acceptance, then begin Phase 6. Do not start Phase 6 implementation before Phase 5 sign-off.

## 30. Phase 5 — PR #60 legacy Interest Policy follow-up and manual acceptance update (2026-11-27)

**Current Git baseline:** PR #61 merged into `main` at `340f74302605899719514d2a6d1d39fe76db41b5`. The Product Owner ran `git fetch` and `git pull --ff-only origin main`; local `main` and `origin/main` are synchronized at this commit, and git status showed only the existing untracked pre-Phase-5 SQL backup. That backup must not be deleted, modified, staged or committed. GitHub branch inventory currently shows `main` plus temporary PR branches; the intended repository policy is one permanent branch (`main`) with merged temporary branches cleaned up.

**PR #60 scope:** supports legacy shops without a DEFAULT Interest Policy, initializes the approved zero-interest default during new shop registration, adds an idempotent backfill migration for existing shops, records policy initialization in the financial audit log, and moves Shop Interest Policy and Customer Interest Override forms into shared asynchronous right-side drawers. It also includes sidebar scrollbar/accessibility improvements and regression tests. PR: https://github.com/thecoderway/kisan-khata/pull/60.

**Local migration evidence (Product Owner-reported):** migration `2026_11_27_000001_backfill_default_interest_policy_for_existing_shops` was executed in the local development environment after explicit approval. The missing DEFAULT policy/version for legacy Shop ID 155 was verified with the approved defaults (0% monthly rate, 30 grace days, capitalization OFF, PRINCIPAL_FIRST, 30 March and 30 October settlement dates) and one audit entry. The Product Owner confirmed the Interest Settings UI works after migration. This is local-development evidence only; no production migration or deployment is claimed.

**Manual acceptance evidence (Product Owner-reported):** the Product Owner reports that the remaining manual tests they performed are passing. The demo Shop used for safe workflow testing is Maa Bijasan Krishi Sewa Kendra (Shop ID 156). The controlled Projection Mismatch/Repair test has passed for synthetic Customer ID 26: only the derived account projection was deliberately mismatched, reconciliation detected it, repair restored the ledger-derived account and snapshots, Sale statuses remained correct, and Audit Log ID 316 captured before/after evidence. The exact observed values and source IDs are recorded in Section 13 above.

**CI qualification:** the available commit-workflow and combined-status lookups did not return a CI run or status for `f9d12a28`. This is an inability to independently verify CI through the available lookup, not evidence of failure or success. Do not label PR #60 CI green without a matching successful run and job results.

**Acceptance boundary:** the controlled Projection Mismatch/Repair test is complete and passed. Phase 5 remains OPEN only until the Product Owner explicitly gives final sign-off. Phase 6 implementation must not begin before that sign-off. No production data changes, production migration, deployment or release are claimed.
## 31. Phase 5 — Controlled Projection Mismatch and Repair acceptance (2026-11-27)

**Status: PASS — local controlled scenario completed; formal Product Owner sign-off remains OPEN.**

The test used Demo Shop ID 156 (Maa Bijasan Krishi Sewa Kendra) and synthetic Customer ID 26 (account ID 1). The pre-test baseline was principal ₹9,432.00, interest ₹122.11 and total payable ₹9,554.11, with projection version 5. Baseline reconciliation returned consistent with no discrepancies.

A deliberate ₹1 mismatch was introduced only in the derived customer_accounts projection: principal ₹9,431.00 and total payable ₹9,553.11. No Ledger entry, Sale, Payment or snapshot was edited to create the mismatch. Read-only reconciliation correctly returned consistent=false with principal and total projection discrepancies.

The authorized local repairAndReconcile() operation returned repaired=true, consistent=true and an empty discrepancy list. Final read-only verification confirmed:
- Account projection restored to principal ₹9,432.00, interest ₹122.11 and total payable ₹9,554.11; projection version advanced to 6.
- All three snapshots were rebuilt correctly for 2026-05-15, 2026-06-02 and 2026-07-20, retaining source Ledger Entry IDs 2, 3 and 5.
- Posted Sale 1 remained PAID; posted Sale 2 remained PARTIALLY_PAID.
- Audit Log ID 316 records action financial.customer_projection_repaired, entity customer_projection / Customer 26, the pre-repair discrepancies and the consistent post-repair state.
- Final reconciliation returned consistent=true and discrepancies=[].

This is local development evidence only. It does not constitute production verification, production readiness or deployment approval. No production data, production migration, deployment or release was performed.

**Phase 5–7 acceptance:** Product Owner formally accepted Phases 5, 6 and 7 on 2026-11-27. Phase 5 local UI smoke checks were reported working fine. Phase 6/7 are accepted for the delivered scope; any deferred features or later-phase scope remain subject to the approved roadmap and explicit exclusions.

**Git branch policy:** main is the sole permanent integration branch. Other branches are temporary PR branches and should be deleted after their PRs are merged. Do not create a parallel master branch or retain long-lived feature/documentation branches.

## 26. Phase 5 — Batch 7B merged and Batch 7C combined acceptance (2026-11-27)

### Batch 7B — Payment reversal/replacement safeguards

- PR [#66](https://github.com/thecoderway/kisan-khata/pull/66) merged into `main`.
- Merge commit: `2651776c0698d14bb3c51743f4c541ac110ab114`.
- Exact PR head `3245b2a730221da0372f211b2c16b60a1dd7534d` passed GitHub Actions CI, including Pint, frontend tests/build, MySQL row-lock integration and the remaining Laravel suite.
- Protected builder safeguards cover 12 immutable/append-only financial record models. Reversal rollback, inactive Owner, later-Payment dependency, completed annual settlement restriction, idempotency and Payment Details history regression tests are included.
- No migration/schema or production changes.

### Batch 7C — Combined financial lifecycle acceptance

- Dedicated branch: `test/batch-7c-combined-financial-acceptance`.
- Acceptance specification: [Batch 7C Combined Financial Lifecycle Acceptance](phase-5-batch-7c-combined-financial-lifecycle-acceptance.md).
- Added a combined multi-Sale lifecycle acceptance test to `Batch87FinancialWorkflowAcceptanceTest`: two ₹5,000 unpaid Sales; ₹4,000 and ₹2,000 normal Payments; FIFO allocation across both Sales; reverse the latest Payment and post an equal replacement; verify Sale statuses, ₹4,000 final payable, ledger net, audit actor/reason, Payment Details revision history and reconciliation.
- Existing Batch 6, 7B, 8.7 and 8.8 tests are reused for projection repair, authorization/tenant isolation, rollback/idempotency, interest and MySQL 8.4 concurrency rather than reimplementing those engines.
- **Current state:** implementation branch prepared; exact-head CI and Product Owner manual UI acceptance are pending. Batch 7C is not yet marked complete.
- No migrations, schema changes, production DB operations, deployment or release. Permanent exclusions remain Crop/Season tagging, Sale Bill Attachment and Payment Receipt Attachment; no advance balances or physical deletion.



## 27. Product Owner acceptance — Phases 5, 6 and 7; Phase 8 next (2026-11-27)

### Formal acceptance decision

The Product Owner explicitly accepted **Phase 5 — Financial Foundation**, **Phase 6 — Credit Sales**, and **Phase 7 — Payments** as complete on 2026-11-27. This acceptance is recorded as a product/functional sign-off for the delivered scope.

- Phase 5: financial core, ledger posting, projections, reconciliation/repair, interest and annual settlement foundations, audit/idempotency/concurrency safeguards, and combined lifecycle acceptance are merged. Batch 7C local UI smoke acceptance was reported successful by the Product Owner.
- Phase 6: Sale creation/posting, listing/details, controlled Owner correction/revision, audit and authorization safeguards were delivered early within the Phase 5 financial batches and are accepted as the Phase 6 delivered scope.
- Phase 7: consolidated Payment posting, FIFO allocation, interest integration, excess-payment rejection, Payment details/history, Owner-only reversal/replacement, audit/idempotency/concurrency safeguards were delivered early within the Phase 5 batches and are accepted as the Phase 7 delivered scope.
- Do not recreate or duplicate these existing services, APIs, UI components, migrations or financial engines merely to follow the nominal phase sequence.
- Product Owner acceptance does not authorize production migration, production data mutation, deployment or release.

### Evidence and boundaries

- Current accepted main baseline at the time of sign-off: `e197205e6ac9df41efdc08b2bdf55b55553ed5d1`.
- PR #67 Batch 7C is merged. Pre-merge exact-head CI was green (runs #1549/#1550 as recorded in delivery history); post-merge main CI status is not asserted here.
- Product Owner confirmed local dashboard access and reported all requested local UI smoke checks working. No additional financial transactions were created for this smoke check.
- The local untracked backup `kisan_khata_before_phase5_2026-11-27_051801.sql` remains untouched.
- Previously approved exclusions remain: no advance balances, physical deletion, crop/season tagging, Sale bill attachment or Payment receipt attachment unless separately approved.

### Phase 8 — Customer Ledger: implementation report and work plan

**Objective:** deliver a complete, deterministic, consolidated customer account ledger using the existing ledger entries and financial accounting engine as the source of truth.

#### Reuse existing foundations (do not rebuild)

- Append-only ledger entries and authoritative financial posting services.
- Customer account balance projections and projection repair.
- Reconciliation service and its read-only report/controlled repair workflow.
- Existing Customer Ledger API/workspace foundations and tenant/role controls.
- Existing interest and annual settlement posting foundations.

#### Required read-only audit before implementation

1. Inspect ledger entry schema/model, entry types, source transaction links, indexes and tenant scoping.
2. Trace the ledger query/service used by Customer Ledger UI/API; confirm whether it reads authoritative ledger entries or derives from mutable projections.
3. Verify opening balance semantics and how carried-forward principal/interest is represented; do not invent an opening-balance posting rule.
4. Verify chronological ordering and deterministic tie-breakers for same-day Sales, Payments, interest and settlement entries.
5. Verify running balance arithmetic uses decimal-safe values and matches CustomerAccount projection and reconciliation.
6. Inspect existing filters, pagination, endpoint authorization, Owner/Manager/Staff visibility and cross-Shop denial.
7. Inspect printable/print CSS and Hindi/English font/rendering support; distinguish browser print from PDF export (PDF/export may be a later report-phase deliverable unless approved).
8. Inventory existing automated tests and UI acceptance gaps.

#### Phase 8 delivery scope

- Complete customer-wise chronological ledger view with opening/carry-forward balance clearly distinguished from current-period transactions.
- Show Sale, consolidated Payment, Interest, Settlement and approved adjustment/correction entries with original dates and human-readable descriptions.
- Show Payments as one consolidated customer payment entry; never display internal FIFO allocation rows as separate customer-facing payments.
- Deterministic running balance after each entry; preserve source transaction date and immutable history.
- Date range filters, pagination and customer navigation; totals must reconcile with the accounting engine.
- Responsive, readable ledger UI with clear debit/credit/balance columns.
- Hindi and English printable ledger layout, with correct glyph/font output.
- Tenant isolation, role permissions, audit/history integrity and API contract coverage.

#### Explicit non-goals / decisions to resolve before coding

- No new accounting engine, duplicate ledger tables, duplicate migrations or independent balance calculator.
- No changes to approved FIFO, interest, settlement or posting rules.
- No customer advance balance.
- Do not invent opening-balance import/adjustment behavior; identify existing semantics and request Product Owner decision if absent.
- Do not add PDF export, Excel/CSV export, crop/season settlement or new adjustment transaction types under Phase 8 without scope approval; align exports with Phase 11 and crop-season accounting with Phase 10.
- No production DB operations or deployment.

#### Acceptance criteria

- Ledger rows are tenant-scoped and authorized server-side.
- Displayed entries reconcile exactly to authoritative ledger totals and customer projection.
- Payments appear consolidated, not split into FIFO allocation rows.
- Ordering and running balance are deterministic for same-date entries.
- Date filters preserve opening/carry-forward balance semantics.
- Hindi and English print output is legible and complete.
- Automated tests cover ledger types, date boundaries, running balances, permissions, cross-Shop isolation and reconciliation.
- Pint passes every changed PHP file; full relevant CI including MySQL 8.4 passes; frontend build/tests pass.
- Product Owner completes browser UI and print acceptance before Phase 8 closure.

#### Read-only acceptance audit completed — 2026-11-27

The audit confirmed that the existing Customer Ledger API reads tenant-scoped authoritative `LedgerEntry` rows, applies deterministic `effective_date → sequence_number → id` ordering, supports date filters and pagination, calculates separate principal/interest running balances with `Brick\\Math\\BigDecimal`, and derives opening balances from prior entries. Existing role authorization and tenant scoping are present.

**Phase 8 acceptance gaps:**
1. `CustomerLedgerController` paginates raw ledger rows. `PaymentPostingService` creates one internal ledger entry per FIFO allocation item, so one customer-facing Payment can appear as multiple rows. Aggregate Payment display rows before pagination and running-balance calculation; preserve every underlying immutable ledger entry and all FIFO allocations.
2. The live financial workspace has no completed Hindi/English customer-ledger print workflow. The prototype HTML is not live acceptance evidence.
3. Automated regression coverage is not yet sufficient for consolidated Payment display, date boundaries, opening/carry-forward, pagination, same-day ordering, running balances and cross-Shop denial. Browser and print acceptance remain pending.

**Phase 9 acceptance gaps:**
1. Existing policy-version history is not calculation history. A read-only customer-facing API/UI for calculation runs and items is missing.
2. Calculation transparency is missing: policy version/rate, eligible interval and actual days, grace-period treatment, principal basis, posted interest and settlement linkage need to be explainable to the customer.
3. Scenario regression and formal acceptance remain incomplete for grace-period boundaries, customer overrides, policy transitions, capitalization ON/OFF, payment waterfall, date boundaries, rounding, same-date payment/settlement ordering, duplicate prevention and reconciliation.

#### Approved Phase 8 delivery batches

- **8A — Consolidated Ledger Entries (authorized; active):** group internal allocation-level ledger records into one customer-facing Payment row before pagination and running-balance calculation. Preserve the authoritative append-only ledger, internal FIFO allocations, source IDs, date ordering and decimal-safe arithmetic. No new accounting engine or financial posting changes.
- **8B — Ledger regression tests:** opening/carry-forward, date boundaries, pagination, running balances, deterministic same-day ordering, consolidated Payment display, authorization and tenant isolation.
- **8C — Printable Ledger:** live Hindi/English print layout with customer details, opening balance, transactions, debit/credit and closing balance.
- **8D — Automated, browser and print acceptance:** run relevant automated tests and CI; Product Owner verifies browser and print output before Phase 8 closure.

Phase 8 Batch 8A implementation is authorized after completion and review of the read-only audit. No database schema change, migration, production operation, or change to underlying FIFO/accounting rules is authorized by this batch.


---

## 28. Product Owner acceptance — Phase 8 Customer Ledger (2026-11-27)

The Product Owner confirmed **all Batch 8D browser acceptance checks passed**. Phase 8 — Customer Ledger is therefore **COMPLETE / ACCEPTED** for the delivered scope.

### Merged delivery and verification

| Batch | Scope | Pull request | Status |
|---|---|---|---|
| 8A | Consolidated customer-facing Payment ledger entries | [PR #69](https://github.com/thecoderway/kisan-khata/pull/69) | Merged; CI green |
| 8B | Ledger regression coverage and balance/date/pagination correctness | [PR #70](https://github.com/thecoderway/kisan-khata/pull/70) | Merged; CI green |
| 8C | Printable customer ledger statement | [PR #71](https://github.com/thecoderway/kisan-khata/pull/71) | Merged; CI green |
| 8D | Combined browser, print/PDF and financial ledger acceptance | Product Owner browser verification | Passed, user-confirmed |

- PRs #69, #70 and #71 are closed and merged into `main`.
- Local `main` and `origin/main` were verified at the same commit: `2e53191715edbd3293736586887965c9b53e5dca`.
- Docker app, database, frontend, Nginx and phpMyAdmin services were reported running; MySQL reported healthy.
- The Product Owner reported all checklist items passed, including consolidated payments, opening/carry-forward and running balances, date boundaries, pagination, same-day ordering, printable statement contents, multi-page output, and final regression checks.
- The untracked local backup `kisan_khata_before_phase5_2026-11-27_051801.sql` was preserved.
- No production migration, production data mutation, deployment or release is claimed.

### Phase 9 — Interest Engine: previously approved execution plan

Phase 9 is the next implementation phase. Preserve the existing approved interest calculation basis, business rules, ADRs and accounting behavior. Reuse existing interest policy, calculation, posting, capitalization, FIFO and annual settlement foundations; do not duplicate or silently alter them.

1. **Batch 9A — Calculation History API:** implement a secure, read-only API for interest runs and calculation items, including policy version, cutoff date and posted interest. Enforce tenant isolation and role authorization. Expose historical records without recalculating, posting, mutating or changing financial data.
2. **Batch 9B — Interest Transparency UI:** provide customer-wise interest calculation history and a clear calculation breakdown, including the source run/item and relevant policy/calculation details already persisted.
3. **Batch 9C — Regression Coverage:** add and run scenario tests for grace period, policy changes, capitalization, payment waterfall, rounding and same-day settlement. Preserve the approved calculation basis and deterministic financial behavior.
4. **Batch 9D — Acceptance:** complete automated tests, UI verification and financial reconciliation before Phase 9 is marked complete.

Phase 9 work must begin with a read-only audit of the existing interest models, calculation runs/items, policy versioning, calculation and posting services, cutoff semantics, ledger links, APIs, UI, authorization, tenant isolation and existing tests. Do not create a second interest engine or duplicate calculation history. No production operations or deployment are authorized by this plan.

---

## 32. Phase 9 completion; Phase 10 future deferral and Phase 11 planning review (2026-11-27)

### Phase 9 completion record

- **PR #73:** [Phase 9 — Interest calculation history and transparency](https://github.com/thecoderway/kisan-khata/pull/73), merged into `main`.
- **Feature branch:** `feature/phase-9-interest-history-transparency`.
- **Merge commit:** `6a9146c496b82143a7813683ed32a178eb60efb3`.
- **Exact PR head:** `04a0a60cca3ddcc4f5adbfe6a6d56a813b4cc91b`; its two GitHub check runs completed successfully.
- Read-only, tenant-scoped customer interest calculation history list and run-detail APIs reuse persisted calculation runs/items.
- Customer-wise Interest Transparency UI exposes persisted calculation breakdown and relevant policy, interval, principal, interest, capitalization, ledger and event linkage fields.
- API regression coverage includes authorization, tenant isolation, filters, pagination validation and read-only behavior.
- Financial regression coverage preserves existing calculation/posting behavior and covers historical policy segmentation and related interest invariants.
- No migration/schema change and no change to the calculation engine, financial posting, ledger or policy rules were introduced by PR #73.
- Product Owner confirmed the customer-wise Interest History UI, calculation breakdown, filters and pagination tests passed.
- The Product Owner's local interpretation of the displayed 30-day grace period and 22 eligible days was discussed and accepted for this test scenario. The display's day-count terminology must remain grounded in persisted fields; do not reinterpret or change calculation semantics without a separate audit and approval.
- **Read-only acceptance qualification:** automated tests cover read-only API behavior. A live browser before/after database comparison was not independently performed because interactive browser control was unavailable. Do not claim that this specific manual DB comparison was completed.
- No production migration, production data mutation, deployment or release is claimed.

### Phase 10 — Crop-Season Settlement: deferred to a future release

Phase 10 is explicitly deferred by the Product Owner and will not be developed in the current work plan. The following is retained only as a future scope reference; it is not authorization to audit further or implement:
- Settlement preparation and calculation.
- Full and partial settlement.
- Carry-forward of remaining outstanding to the next crop season.
- Settlement history and audit history.
- Settlement services, ledger entries, API, UI and automated tests.

On future reactivation, acceptance must establish that full settlement closes only the eligible balance; partial settlement retains the correct residual; carry-forward preserves outstanding and prior financial history; interest follows approved rules; duplicate settlement is prevented; and all settlement actions are auditable.

### Phase 10 reactivation gate (future, only if explicitly requested)

Before implementation, inspect existing code and persisted architecture for:
1. Crop/season master data and any existing Sale crop/season fields or tagging support (do not assume the roadmap's legacy Sale tagging text is already implemented).
2. Existing annual interest settlement models/services, settlement runs/items, ledger entry types, idempotency keys and financial audit events.
3. Customer account principal/interest balances, FIFO allocations, outstanding projections and reconciliation behavior before/after existing annual settlement.
4. Existing settlement-related API routes/controllers, workspace UI, authorization policies, tenant scoping and role visibility.
5. Existing migrations, indexes, foreign keys, uniqueness constraints and deletion/immutability safeguards.
6. Existing automated tests for annual settlement, same-date payment ordering, duplicate prevention, rollback, concurrency and ledger reconciliation.
7. Whether crop-season boundaries, eligible Sale selection, partial settlement allocation, carry-forward destination, treatment of posted/unpaid interest, and settlement correction/reversal semantics are explicitly defined in approved requirements/ADRs.

Only after explicit reactivation, perform a fresh read-only audit and business-rule review before implementation. Until then, no further Phase 10 work is authorized.

### Phase 10 future safety and approval boundaries

- Do not invent crop-season dates, crop eligibility, settlement allocation order, carry-forward semantics, interest treatment or correction rules.
- If a required business rule is absent or conflicts with ADR-0012 or another accepted ADR, stop and request Product Owner clarification; update approved documentation before coding.
- This deferral record authorizes no code changes, migrations, database writes, financial postings, production operations, deployment or release.
- Any implementation proposal must identify schema changes, transaction/locking/idempotency strategy, tenant/role authorization, audit requirements, regression tests and migration/data-safety implications before implementation approval.


---

## 33. Phase 10 deferral and Phase 11 review (2026-11-27)

### Product Owner direction — Phase 10

- Phase 10 Crop-Season Settlement is deferred to a future release.
- It is not the next phase and must not be started, scheduled for implementation, branched, or subjected to further design/audit work unless the Product Owner explicitly reactivates it.
- Existing annual-interest settlement is not crop-season settlement.
- This is a roadmap sequencing decision; it does not remove the future scope placeholder from the roadmap.

### Phase 11 review findings

Inspected current `main` routes, financial controllers, workspace Blade view, ledger print JavaScript and Composer dependencies.

**Reusable foundations found:**
- Sales listing API: tenant-scoped, searchable, date/customer filters, stable sorting and pagination.
- Payments listing API: tenant-scoped, searchable, date/customer/payment-method filters, stable sorting and pagination.
- Customer Ledger API: consolidated customer-facing rows and principal/interest running balances.
- Customer Ledger UI already exposes a Print/PDF action; the print helper retrieves all ledger pages for the selected statement.
- Interest Calculation History API: customer-specific persisted run/item history, cutoff date filters and pagination.
- Existing financial workspace sections: Sales, Payments, Ledger, Interest, Reconciliation and Audit.
- Existing authorization policies and tenant middleware are the required baseline for report access.

**Gaps found in inspected scope:**
- No dedicated report centre or aggregate shop-wide report API/controller was found.
- No general CSV or XLSX export workflow was found.
- No server-side general PDF report-generation service was found; existing ledger output uses browser print/PDF.
- Composer currently has Laravel, Sanctum and Brick Math; no dedicated PDF or XLSX package is declared.
- No Crop-Season Settlement report can be built until Phase 10 is reactivated and delivered.

**Recommended Phase 11 delivery sequence:**
1. 11A — Approve report definitions, columns, totals, date-boundary rules, reversal handling, as-of balance semantics and role visibility.
2. 11B — Read-only tenant-scoped report APIs and report centre UI for outstanding, sales, payments and interest.
3. 11C — Hindi/English print layouts and PDF strategy decision; reuse ledger print conventions where appropriate.
4. 11D — CSV and Excel-compatible export with chunking/streaming, deterministic ordering, limits and formula-injection protection.
5. 11E — Financial reconciliation, tenant/role authorization, export parity, no-write verification, full regression and Product Owner browser acceptance.

**Phase 11 approval and safety gates:**
- This is a review and proposal only. Phase 11 has not started.
- Do not create code, migrations, export packages, endpoints, UI or branches for implementation until explicitly authorized.
- Do not introduce new ledger entries, payments, interest postings, projection repairs or other financial writes.
- Reuse existing financial source records and policies; do not duplicate accounting calculations.
- Any report metric without an authoritative, documented source or date-boundary definition must be clarified before coding.
- Settlement reporting is excluded while Phase 10 is deferred.

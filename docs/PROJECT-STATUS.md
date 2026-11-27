# Kisan Khata — Project Status

**Last Updated:** 2026-11-27  
**Status Snapshot Base:** `main` at `62419de9872f84a38a6d8d422e11dbadca73a1ca` (2026-11-27), including PRs #53–#56 financial UI follow-ups.  
**Project Status:** Phase 2–4 are merged and accepted for their recorded scopes. Phase 5 financial implementation and automated acceptance through Batch 8.8 are merged. PRs #53–#56 subsequently completed Sales/Payments customer dropdowns, approved table/filter/drawer behavior, pagination layout/navigation and sidebar usability. The Product Owner has manually confirmed the Sales/Payments listing, drawer layout, pagination navigation and sidebar behavior. Phase 5 remains OPEN pending fresh-Shop registration and tenant-isolation/role-based browser acceptance, plus complete workflow smoke checks across Sales, Payments, Customer Ledger, Interest Settings and Reconciliation; production-readiness and repository-admin checks remain separate later-stage gates. See [Batch 8 financial integrity and release gate](phase-5-batch-8-financial-integrity-fixes.md). No production deployment or release is claimed.  
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

Accepted ADRs: ADR-0001 Modular Monolith; ADR-0002 Shared Database Multi-Tenancy; ADR-0003 Web-First, API-Ready Architecture; ADR-0004 Consolidated Customer Ledger and Payments (superseded in part by ADR-0012); ADR-0005 User, Shop and Role Relationship; ADR-0006 Financial Transaction Correction and Audit; ADR-0007 Interest Calculation Events (specified scope only); ADR-0008 Bilingual Customer Data; ADR-0009 Shop-Level Subscriptions; ADR-0010 User Lifecycle and Role Permissions; ADR-0011 Geographic Master Data and Shop/Customer Location Defaults; ADR-0012 Approved Financial Rules; ADR-0013 Asynchronous Action Drawer Form Submission; ADR-0014 Application-Wide Right-Side Drawer Standard; ADR-0015 Staff Shop-Wide Financial Read Access.

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
| Phase 5 | Financial Foundation and Accounting Engine | Core implementation and automated acceptance through Batch 8.8 merged; PRs #53–#56 UI follow-ups merged. Partial Sales/Payments browser smoke confirmed; fresh-Shop tenant/role and full financial workflow acceptance remains OPEN |
| Phase 6 | Credit Sales | Formal phase acceptance not started. Sale posting/list/detail and related API/UI foundations were delivered early within Phase 5 Batch 7; reconcile remaining scope before implementation to avoid duplication |
| Phase 7 | Payments | Formal phase acceptance not started. Payment posting/list/detail/correction foundations were delivered early within Phase 5 Batch 7; reconcile remaining scope before implementation to avoid duplication |
| Phase 8 | Customer Ledger | Ledger projection/reconciliation and customer ledger API/workspace foundations were delivered within Phase 5; formal phase acceptance and print/export scope remain open |
| Phase 9 | Interest Engine | Interest calculation, effective-dated policy and annual settlement foundations were delivered within Phase 5 under ADR-0012; reconcile formal phase acceptance and remaining scope before duplicate work |
| Phase 10 | Crop-Season Settlement | Not started |
| Phase 11 | Reports, Printing and Exports | Not started |
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

- Stable branch: `main`.
- Current verified `main` HEAD: `62419de9872f84a38a6d8d422e11dbadca73a1ca` (PR #56 pagination/sidebar follow-up; 2026-11-27).
- PRs #7 and #10–#14 are confirmed merged into `main`; no open Draft status is carried forward for these deliveries.
- All feature and documentation changes use reviewable PRs. Standing Product Owner authorization permits merge when CI is green, GitHub reports the PR mergeable, and no unresolved blocker or hold exists; otherwise stop and report.
- Update status only from verified repository, CI, test or Product Owner evidence.
- Keep this document and relevant technical documentation synchronized with accepted implementation.

## 12. Immediate next steps

- Phase 2 and Phase 3 are merged and accepted; Phase 4 implementation is merged and accepted for its recorded scope.
- **Current phase: Phase 5 automated implementation and acceptance are complete through Batch 8.8; manual Product Owner browser/UI acceptance is the remaining Phase 5 sign-off.**
- Product Owner financial rules are finalized in ADR-0012 and synchronized with docs/01, docs/02, docs/03, docs/04, docs/06, docs/09, docs/10 and the financial integrity guardrails.
- The detailed Phase 5 implementation blueprint is `docs/phase-5-implementation-blueprint.md`.
- PR #49 (Batch 8.7) and PR #50 (Batch 8.8) are merged; final feature-head CI passed. The post-merge `main` CI result is tracked separately in Section 27.
- Next product phase after Phase 5 sign-off is Phase 6 — Credit Sales. Do not mix later Phase 6–15 roadmap scope into Phase 5.
- No database migrations, local/production data changes or deployment without separate explicit Product Owner approval.
- Do not begin production Sales/Payment workflows until Phase 5 foundation passes its critical acceptance tests.
- Keep production SMS provider integration deferred until final integration/release readiness.

## 13. Phase 5 status — 2026-11-27

**Current delivery baseline:** Phase 5 financial implementation and automated acceptance through Batch 8.8 are merged into `main`. Batch 8.7 merged in PR #49 (`a67edfaacfc660ce0ecc9bae88ffa3e01307e5f4`); Batch 8.8 merged in PR #50 (`c740e44434071062c4cb8eac546f375a7e234126`). This includes the financial core, tenant-safe posting and locking, FIFO allocation, effective-dated interest, annual auto settlement, ledger projection/reconciliation, financial APIs/UI, workflow/authorization regressions, rollback/audit atomicity checks and MySQL 8.4 concurrent Payment/Sale/settlement tests. Automated acceptance is CI-verified. Product Owner browser/UI smoke acceptance remains outstanding; production readiness is a separate later phase.

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

**Remaining Phase 5 acceptance:** Product Owner must complete browser/UI smoke acceptance for the financial Sales, Payments, Customer Ledger, Interest Settings and Reconciliation workflows, including Owner/Manager/Staff visibility and denial paths. Browser automation is not configured, so CI does not claim browser-level acceptance. The automated workflow, role/cross-Shop, rollback, MySQL 8.4 concurrency, formatting, Composer and Vite checks are complete and passed.

**Separate later-stage release gates:** production environment configuration, backup/restore evidence, monitoring and deployment/recovery procedures belong to Phase 14 Production Readiness. Branch-protection and required-check settings remain unknown because the current GitHub integration cannot inspect repository administration settings; a repository administrator must verify them before release.

No production deployment, production data change, migration execution or release is claimed. Database migrations and deployment require separate explicit Product Owner approval.

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

**Current main baseline:** `62419de9872f84a38a6d8d422e11dbadca73a1ca`.

Merged follow-ups after Batch 8.8:
- PR #53: customer directory loading for financial workflows and JS helper tests.
- PR #54: Sales/Payments server-side tables, auto filters, pagination and asynchronous action drawers.
- PR #55: Customer-style pagination presentation and improved drawer field layout.
- PR #56: working numbered pagination/Tabler arrows, independently scrollable sidebar with visible shop/logout footer, and Interest Settings moved into Settings.

**Manual evidence reported by Product Owner:** Sales/Payments listing and filters, customer dropdown, drawer layout, pagination navigation and sidebar behavior were checked in the local browser and confirmed working. Local frontend production build succeeded after pulling these changes. This is limited UI smoke evidence, not full Phase 5 acceptance.

**Still required:** create a fresh Shop through registration in a separate Incognito/browser profile; verify Shop A/Shop B isolation; verify Owner/Manager/Staff permitted and forbidden actions; exercise Sale, Payment, FIFO allocation, interest, ledger, reconciliation and explicit repair workflows; verify validation/error/success behavior. No new-Shop acceptance has yet been reported. Phase 5 remains OPEN.

**CI evidence:** post-merge `main` run [45961048236](https://github.com/thecoderway/kisan-khata/actions/runs/45961048236) completed successfully, including PHP formatting, automated tests and frontend build. CI does not replace browser acceptance.

**Next sequence:** complete fresh-Shop browser acceptance, record results, close Phase 5 acceptance, then begin Phase 6. Do not start Phase 6 implementation before Phase 5 sign-off.

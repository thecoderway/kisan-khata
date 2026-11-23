# Kisan Khata — Project Status

**Last Updated:** 2026-11-23  
**Status Snapshot Base:** `main` at `13ad942` (PR #5 merged; this document correction is proposed separately)  
**Project Status:** Foundation implementation in progress; Phase 3 is the next implementation phase.  
**Release:** Not released; no production deployment is claimed.

## 1. Source of truth and mandatory project rules

The authoritative baseline is the approved **Docs 01–10, Version 2.0**, together with **ADR-0001 through ADR-0009** in `docs/decisions/`. The roadmap in `docs/10-development-roadmap.md` defines the official phase numbering and sequence. This status file records evidence-based progress; it does not replace or silently amend approved requirements.

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

Accepted ADRs: ADR-0001 Modular Monolith; ADR-0002 Shared Database Multi-Tenancy; ADR-0003 Web-First, API-Ready Architecture; ADR-0004 Consolidated Customer Ledger and Payments; ADR-0005 User, Shop and Role Relationship; ADR-0006 Financial Transaction Correction and Audit; ADR-0007 Interest Calculation Events (specified scope only; parameters remain open); ADR-0008 Bilingual Customer Data; ADR-0009 Shop-Level Subscriptions.

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
| Phase 2 | Multi-Tenancy Foundation | Tenant context, tenant-owned builder protections, route-level web/API security coverage and immutable user-to-shop association implemented on the feature branch; prior local suite passed before the latest user-association change; re-verification and full acceptance remain pending |
| Phase 3 | Roles and User Management | Not started; next official phase |
| Phase 4 | Customer Management | Not started |
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

**Implemented initial foundation:**
- `CurrentShop` context resolved from authenticated user's shop.
- Tenant middleware and protected dashboard/API context.
- Client-supplied shop IDs are not trusted for tenant selection.
- Initial tests for active shop context and missing/suspended tenant access.

**Current feature-branch work (local execution reported by Product Owner):**
- Group tenant business routes under both authentication and tenant-resolution middleware; keep logout authenticated but outside tenant resolution so suspended shops can sign out.
- Add web/API feature coverage for middleware assignment, authenticated-user shop resolution despite forged query-string `shop_id`, inactive users, suspended shops, request-to-request tenant context resolution and logout behavior.
- Local verification: `pint --test` passed for 57 files; `TenantMiddlewareIsolationTest` passed 10 tests / 25 assertions; `TenantOwnedModelIsolationTest` passed 17 tests / 26 assertions; full suite passed 57 tests / 164 assertions.

**Infrastructure audit findings (feature branch):**
- No application cache/Redis usage was found under `app/`.
- Session usage is limited to authentication/session regeneration and invalidation.
- No upload/download implementation or Storage facade usage was found; `Shop.logo_path` is currently only a model attribute.
- No application jobs or queue dispatch usage was found under `app/`.
- Direct query-builder access in `MobileOtpService` targets the system-level `mobile_otp_challenges` table; registration uses a transaction to create the Shop and Owner User.
- The only current tenant-owned Eloquent model identified is `ShopSetting`; its tenant scope and guarded mutation paths have dedicated tests.

**Final acceptance review findings (2026-11-23):**
- Reviewed Docs 04/05/06/09, Roadmap Phase 2 and accepted ADR-0002/ADR-0005 against the current feature-branch implementation.
- Confirmed the current tenant-owned Eloquent data model is limited to `ShopSetting`; customer/financial/file/cache/export/job modules are not implemented yet. Their required controls and tests remain mandatory when those modules are introduced.
- Confirmed `CurrentShop` is registered as a Laravel scoped service, tenant middleware derives the active shop from the authenticated user, and authenticated business routes are tenant-protected while logout remains available for suspended shops.
- Added a `User` model update guard rejecting changes to `shop_id`, plus a regression test. This protects the approved one-user/one-shop association against ordinary Eloquent saves. Any future administrative reassignment workflow would require a separately reviewed, explicitly authorized system operation; it is not part of ordinary user management.
- Raw `DB::table()` / query-builder operations and `toBase()` are outside Eloquent model guards. Current raw OTP access is limited to the system-level `mobile_otp_challenges` table; tenant-owned raw SQL/query-builder access must be explicitly reviewed and tenant-filtered when added.
- No current application cache, upload/download, tenant-specific queue job or export implementation exists. These are deferred until their features exist, not considered implemented or tested.
- The newly added user reassignment regression test has not yet been executed by the Product Owner at the time of this status update. Earlier reported passing results predate this latest change and must not be presented as verification of the new test.

**Remaining Phase 2 acceptance work:**
- Complete a final review of all tenant-owned models, relationships, raw query paths and scope-bypass/system-operation boundaries.
- Add tenant-aware private file storage and authorized download tests when file features are introduced; no upload/download path currently exists to exercise.
- Add tenant-aware cache keys/tests when tenant-specific caching is introduced; no application cache usage currently exists.
- Establish explicit tenant context and lifecycle cleanup for tenant-specific jobs when such jobs are introduced; no application jobs or queue dispatch currently exist.
- Verify tenant-scoped exports and asynchronous work when implemented.
- Complete the Phase 2 acceptance review against Docs 04/05/06/09 and Roadmap Phase 2. Do not mark Phase 2 complete solely from the current passing suite.

Phase 2 must be accepted before exposing customer and financial modules to multiple shops.

### Phase 3 — Roles and User Management (next official phase)

Required scope from Roadmap v2.0:
- Owner management of shop users.
- Manager creation and Staff creation.
- Role-based access control.
- User activation/deactivation and account lifecycle.
- Backend enforcement of shop limits: 1 Owner, up to 2 Managers and up to 5 Staff.
- Policies/authorization rules and role-specific navigation.
- Automated tests for every role/action combination, privilege escalation, limits, inactive users and cross-tenant access.
- Owner-only editing of posted Sales/Payments; Manager and Staff must not edit posted financial transactions, as specified in the approved roadmap/business rules.

Before coding, audit the existing role representation, registration logic, routes/controllers and existing authorization. Produce a permission matrix from approved documents; do not infer unspecified Manager/Staff permissions.

## 6. Remaining official phases — scope summary

1. **Phase 4 — Customer Management:** customer CRUD, search, village filters, required/optional fields, preserve original entered text and store Hindi/English translations separately; correction workflow and tenant-scoped tests.
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

**Execution date:** 2026-11-23  
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

No production deployment or release is claimed.

## 11. Git and delivery workflow

- Stable branch: `main`.
- Latest Product Owner-confirmed local sync before this proposed documentation correction: `13ad942`; working tree clean; divergence `0 0`.
- All feature and documentation changes use dedicated branches and PR review.
- No merge to `main` without explicit Product Owner approval.
- Update status only from verified repository, test or Product Owner evidence.
- Keep this document and relevant technical documentation synchronized with accepted implementation.

## 12. Immediate next steps

1. Execute the in-progress Phase 2 route-level tenant security tests and full relevant test suite; record only observed results.
2. Complete the focused Phase 1/2 gap audit against Roadmap acceptance criteria and Docs 01/02/04/05/06/08/09 plus relevant ADRs. Record which gaps block safe Phase 3/4 delivery.
3. Complete remaining Phase 2 tenant-aware infrastructure and acceptance checks before exposing multi-tenant customer/financial workflows.
4. Prepare the official Phase 3 implementation plan: role-permission matrix, lifecycle workflows, shop-limit enforcement, UI/API scope, migration impact, security threats and acceptance tests. Ask for approval where approved documents do not specify behavior.
5. Implement Phase 3 in small PR-sized increments; test every role/action combination and tenant boundary.
6. Proceed to Phase 4, then Phase 5 before Credit Sales and Payments.
7. Keep production SMS provider integration deferred until final integration/release readiness.

This status file is a progress record. The approved v2.0 documents and accepted ADRs remain authoritative.

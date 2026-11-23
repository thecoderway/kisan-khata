# Kisan Khata — Project Status

**Last Updated:** 2026-11-23  
**Project Status:** Phase 2 — SaaS Core Architecture (foundation implemented; role/user management is next)  
**Current Main Commit:** `73617d14895cc972ae45d57080113402cfd76f6d`  
**Release:** Not yet released; no production deployment is claimed.

## Approved baseline

Docs 01–10 are Approved, Version 2.0. Accepted ADRs: ADR-0001 through ADR-0009. See `docs/decisions/README.md`.

The approved product and accounting rules remain authoritative. This status update records implementation progress; it does not approve or change unresolved business rules.

## Executive summary

The project has completed its initial Docker/modular-monolith foundation and the first SaaS identity/authentication/tenant-context packages.

The following implementation packages are merged into `main`:

| Package | Scope | Status |
|---|---|---|
| Package 1 | Shop tenancy database foundation | Complete |
| Package 2A | Owner/shop registration and email/password authentication | Complete |
| Package 2B | CurrentShop tenant context and tenant middleware | Complete |
| Package 2C | Mobile OTP authentication API and Sanctum bearer tokens | Complete |

**Current next milestone:** Phase 3 — Roles and User Management, beginning with backend authorization/policies and enforcing shop user limits.

Important distinction: role values and tenant foundations exist, but complete role-based user-management workflows, screens, policies and all shop limits have not yet been delivered.

## Implementation status

### Phase 0 — Existing project audit and foundation: COMPLETE

The initial project foundation is present:
- Docker Compose development environment with PHP 8.3, MySQL 8.4, Nginx, phpMyAdmin and a separate frontend container.
- Environment/security configuration and setup workflow.
- Modular Monolith structure and module namespaces.
- Composer test/lint scripts and Docker-based frontend build workflow.
- Persistent MySQL volume retained.

### Phase 1 — Authentication and Shop Foundation: CORE COMPLETE

**Package 1 — Shop tenancy database foundation**
- Shops and shop settings tables.
- Nullable user-to-shop relationship foundation.
- User role/mobile/language/active-state fields.
- Database constraints and relationships for the one-user/one-shop model.

**Package 2A — Owner registration and authentication**
- Transactional Owner and Shop registration.
- Email/password login and logout.
- Remember-me and password reset flows.
- Login throttling and inactive/suspended account checks.
- Registration does not trust client-supplied shop ID or role.

**Package 2C — Mobile OTP authentication**
- Provider-independent OTP request and verification services.
- Cryptographically generated six-digit OTP; only the hash is stored.
- Five-minute expiry, single-use verification, five-attempt limit and resend cooldown.
- Request/verification rate limiting and generic unknown-mobile response.
- Sanctum bearer token issuance only after successful verification.
- Token expiry and current-token logout.
- Daily challenge-pruning command and scheduler registration.
- Automated feature tests and `docs/mobile-otp-auth.md`.

**SMS delivery is intentionally deferred:** no production SMS provider is selected or configured. The default null sender fails closed. Keep the sender abstraction and fake sender for development/tests; integrate the real SMS provider in the final integration/release-readiness stage, not during the upcoming feature phases.

### Phase 2 — Multi-Tenancy Foundation: CORE COMPLETE; CONTINUOUS ENFORCEMENT REQUIRED

**Package 2B — CurrentShop and tenant middleware**
- Central CurrentShop tenant context resolved from the authenticated user.
- Tenant middleware and route protection.
- Dashboard/API tenant context protection.
- Client-provided shop IDs are not trusted for tenant selection.
- Automated tests cover active shop context and forbidden access for missing/suspended tenant contexts.

This completes the initial tenant-context foundation. Every new tenant-owned module must still enforce shop scoping, ownership validation and cross-tenant tests. File storage, cache, exports and background jobs must receive explicit tenant-aware handling when those features are introduced.

### Phase 3 — Roles and User Management: NOT STARTED / NEXT

Required scope:
- Backend Policies/Gates and role-permission matrix for Owner, Manager and Staff.
- Owner-managed Manager and Staff creation, activation/deactivation and account lifecycle.
- Enforce per-shop limits on the backend: 1 Owner, up to 2 Managers and up to 5 Staff.
- Prevent privilege escalation and unauthorized role changes.
- Role-aware navigation and user-management UI.
- Tests for every role/action combination, limits, inactive users and cross-tenant access.

Do not treat the existence of a role column or role constants as completion of role-based authorization.

### Later roadmap phases

Follow the approved sequence in `docs/10-development-roadmap.md`:

1. **Phase 4 — Customer Management:** customer CRUD/search, village filters, tenant isolation, preserve original text and separate Hindi/English translations.
2. **Phase 5 — Financial Foundation and Accounting Engine:** decimal-safe money handling, consolidated customer balance, ledger posting, advances, immutable audit history, idempotency and concurrency protection. This is a critical prerequisite.
3. **Phase 6 — Credit Sales:** sale workflows built on the approved financial services.
4. **Phase 7 — Payments:** consolidated payment processing; never introduce sale-level payment allocation.
5. **Phase 8 — Customer Ledger:** complete running account history and printable ledger.
6. **Phase 9 — Interest Engine:** only after the outstanding financial policy decisions are approved.
7. **Phase 10 — Crop-Season Settlement:** full/partial settlement and carry-forward according to approved rules.
8. **Phase 11 — Reports, Printing and Exports.**
9. **Phase 12 — Subscription System:** shop-level plans and enforcement; do not invent plan pricing or entitlements.
10. **API completion and native Android:** expand the current authentication API into the approved versioned API contract, then build the Android client against the same backend business logic. Offline synchronization remains a separately designed/approved capability.
11. **Final integration and release readiness:** configure and test the selected production SMS provider here, after the main product workflows and client integration are ready; then complete deployment, monitoring, backup and operational readiness work.

The current authentication routes are implemented under `/api`; the approved API architecture specifies a versioned `/api/v1` contract. Versioning and the remaining business endpoints are future API work, not completed functionality.

## Current technical verification

**Execution date:** 2026-11-23  
**Environment:** local Docker Compose development environment  
**Verified by project owner after merging PR #4:**

- Local `main` fast-forwarded to `origin/main` at merge commit `73617d1`.
- Git working tree clean; local branch up to date with `origin/main`.
- `php artisan migrate:status`: all six listed migrations report `Ran`.
- `php artisan test`: **30 passed, 113 assertions**.
- `vendor/bin/pint --test`: **53 files PASS**.
- Earlier frontend Vite production build was verified during Package 2B.

The OTP test suite uses a fake sender. These results do not mean live SMS delivery has been tested. No production deployment or release is claimed.

## Deferred integrations and unresolved product decisions

### Explicitly deferred until the final integration stage
- Production SMS provider selection, credentials, sender/template registration and delivery testing.

### Product/business decisions still requiring approval before affected features
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

Do not invent defaults for these decisions. Stop and ask the Product Owner when an implementation depends on an unresolved rule.

## Key financial and security invariants

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

## Git and delivery workflow

- Current stable branch: `main`.
- Current main commit at this status update: `73617d1` (merge of PR #4).
- Feature work must use a dedicated branch and be reviewed through a PR.
- Do not merge to `main` without the Product Owner's explicit approval.
- Update this document only from verified repository, test or Product Owner evidence.

## Immediate next implementation plan

Start with **Package 3A — Role Authorization and User Management Foundation**:
1. Audit the existing role representation and current route/controller authorization.
2. Translate the approved Owner/Manager/Staff permissions into a server-side policy matrix.
3. Implement backend policies/services and enforce the shop user limits transactionally.
4. Add Owner-only Manager/Staff lifecycle endpoints and validation.
5. Add feature tests for allowed/denied actions, role escalation, limits, inactive users and tenant isolation.
6. Implement the corresponding responsive Hindi/English UI after backend contracts are stable.
7. Run the full test suite, Pint and frontend build; update this status only with actual results.

Do not start Customer, Sales, Payment or Accounting workflows until the prerequisite identity, tenant and role authorization work is sufficiently established. Build the financial foundation before exposing Sales and Payments.


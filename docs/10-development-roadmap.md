# Kisan Khata — Development Roadmap

**Document Version:** 2.1  
**Status:** Approved  
**Delivery Approach:** Incremental Development  
**Architecture:** Modular Monolith  
**Backend:** Laravel 13  
**Database:** MySQL 8.4  
**PHP:** 8.3

---

## 1. Purpose

This roadmap defines the recommended development sequence for Kisan Khata.

Development must proceed in small, independently verifiable phases.

The application must be developed according to the approved product requirements, business rules, accounting engine, database architecture, multi-tenancy, security, UI/UX, API and testing documents.

Do not ask an AI coding agent to build the entire application in one operation.

Every development phase must have:

- Clear objectives.
- Defined scope.
- Dependencies.
- Database changes.
- Implementation tasks.
- Automated tests.
- Security considerations.
- Acceptance criteria.
- Completion status.

## 2. Development Strategy

Use the following development cycle:

1. Inspect the existing codebase.
2. Read relevant approved documentation.
3. Identify dependencies and existing implementation.
4. Prepare a small implementation plan.
5. Identify conflicts with approved architecture.
6. Obtain approval for significant changes.
7. Implement one manageable feature.
8. Write automated tests.
9. Run relevant tests.
10. Review security and tenant isolation.
11. Review database integrity.
12. Update documentation and project status.

### Important implementation rules

- Existing working functionality must not be removed without approval.
- Approved business rules must not be silently changed.
- Financial calculations must remain backend-controlled.
- Database changes must follow the approved database architecture.
- All tenant-owned data must remain isolated by shop.
- Every significant feature must have automated tests.
- Do not claim completion without executing the relevant tests.

## 3. Phase 0 — Existing Project Audit and Foundation

### Objectives

- Inspect the existing Laravel application.
- Confirm PHP, Laravel and MySQL versions.
- Review existing database migrations.
- Review installed packages.
- Review current authentication implementation.
- Review current application structure.
- Review Docker and development environment.
- Identify existing functionality and incomplete modules.
- Establish baseline testing and code quality checks.

### Deliverables

- Existing codebase audit.
- Architecture gap report.
- Development environment verification.
- Baseline test results.
- Initial implementation backlog.

### Acceptance criteria

- Application starts successfully.
- Database connection works.
- Existing migrations are understood.
- Existing tests have been executed.
- Existing functionality is documented.
- No existing feature is accidentally removed.

## 4. Phase 1 — Authentication and Shop Foundation

### Objectives

- Shop Owner registration.
- Shop creation.
- Mobile OTP authentication.
- Email/password authentication.
- User profile.
- Shop profile.
- Owner account setup.
- Authentication security.

### Business rules

- One user belongs to exactly one shop.
- One user has exactly one role.
- Roles are Owner, Manager and Staff.
- No multi-shop switching or membership model.
- Shop user limits:
  - 1 Owner.
  - 2 Managers.
  - 5 Staff.

### Deliverables

- Authentication module.
- Shop module.
- User module.
- OTP verification.
- Email/password login.
- User-shop relationship.
- Authentication tests.

### Acceptance criteria

- Users can authenticate using approved login methods.
- Shop registration creates the correct Owner relationship.
- A user cannot belong to multiple shops.
- User role is enforced.
- OTP expiration and rate limiting work.
- Unauthorized shop access is rejected.

## 5. Phase 2 — Multi-Tenancy Foundation

### Objectives

- Centralized authenticated shop context.
- Tenant-aware models.
- Tenant-aware database queries.
- Tenant authorization.
- Tenant-aware file storage.
- Tenant-aware cache keys.
- Tenant-aware background jobs.

### Deliverables

- CurrentShop or equivalent shop-context abstraction.
- Tenant middleware or equivalent request context.
- Tenant-scoped query mechanism.
- Tenant ownership validation.
- Cross-tenant security tests.

### Acceptance criteria

- Every tenant-owned record is scoped to the authenticated user's shop.
- Client-provided shop IDs cannot bypass authorization.
- Shop A cannot access Shop B data.
- Background jobs establish explicit tenant context.
- Tenant isolation tests pass.

**This phase must be completed before exposing customer and financial modules to multiple shops.**

## 6. Phase 3 — Roles and User Management

**Status: Complete — accepted on `feat/tenant-isolation-foundation` (2026-11-24).**

### Objectives

- Owner-only creation and administration of Manager and Staff accounts.
- Role-based access control for Owner, Manager and Staff.
- User activation, deactivation and reactivation.
- Owner-only Manager/Staff role changes.
- Active-user capacity enforcement and concurrency protection.
- Role-specific customer permissions and Staff visibility for Managers.
- Audit history for user lifecycle and role changes.

### Deliverables

- User management screens.
- User management services.
- Policies and authorization rules.
- Role-specific navigation.
- Role authorization tests.

### Acceptance criteria

- Owner has full permitted shop access and is the only role allowed to administer Manager/Staff accounts.
- Manager can view the Staff list and active/deactivated status only; Manager cannot create, deactivate, reactivate, or change user roles.
- Staff can view, add and edit customer profile information, but cannot delete customers or edit/delete posted financial transactions.
- Manager and Staff cannot edit posted financial transactions.
- Only Owner can edit posted sales and payments.
- Active capacity is limited to one Owner, two Managers, five Staff and eight shop users total.
- Deactivated Manager/Staff accounts release role and total active-user capacity while retaining their accounts and audit history.
- Reactivation is rejected when the target role or total active-user capacity is full.
- Concurrent creation, reactivation and role changes cannot exceed capacity.
- Only Owner can change Manager/Staff roles; role changes enforce destination capacity atomically and are audited.
- The sole active Owner cannot be deactivated or demoted through ordinary shop-user management; ownership transfer remains a separately authorized and audited process.
- User lifecycle and role-change authorization is enforced server-side for both web and API requests.

### Acceptance evidence and implementation boundary

- Owner-only account creation, deactivation, reactivation and Manager/Staff role changes are enforced in application services and policies for Web and API.
- Manager's Staff directory projection contains only `id`, `name`, `role` and `is_active`; Owner-only fields and controls are not exposed to Managers.
- Capacity checks are serialized by a MySQL shop-row lock and execute within the lifecycle transaction.
- Deactivation retains the account and audit history, revokes Sanctum tokens and database sessions atomically, and fails closed for unsupported session configuration.
- Audit history is append-only through Eloquent model and bulk mutation paths and is tenant-scoped and Owner-only.
- Customer-profile permissions and posted Sales/Payments correction permissions are the role contracts for later Customer/Sales/Payments modules. Those domain modules are not part of this Phase 3 implementation; they must enforce ADR-0010 server-side and provide domain-specific tests when introduced. Do not create those modules as part of Phase 3.
- Ownership transfer is expressly excluded from ordinary Phase 3 user administration.

**Verification:** GitHub Actions Laravel CI run #19, [run `45353098904`](https://github.com/thecoderway/kisan-khata/actions/runs/45353098904), commit `d9512f3370bb65f30232e329b745fa2d393a2974`, successful on MySQL 8.4: 90 Pint files; Composer validation and Vite build passed; MySQL row-lock test 1/2 assertions; remaining suite 138/430; combined 139 tests, 432 assertions, zero failures and zero skipped.

## 7. Phase 4 — Customer Management

### Objectives

- Customer creation.
- Customer editing.
- Customer listing.
- Customer search.
- LGD-backed State, District, Subdistrict and Village selectors.
- Shop Profile location editing by the Shop Owner.
- Preselect the shop's current location when creating a customer, while allowing the user to override it.
- Store customer location independently; shop location changes must not modify existing customers.
- Customer details.
- Hindi and English data handling.
- Automatic translation support.

### Customer fields

- Name — required.
- Mobile — optional.
- State, District, Subdistrict and Village LGD codes — optional.
- Address — optional.
- Notes — optional.

### Language requirements

- Preserve original entered text.
- Preserve original language.
- Store Hindi translation separately.
- Store English translation separately.
- Allow translation corrections.
- Never overwrite original text with automatic translation.

### Deliverables

- Customer database tables and tenant-scoped location fields.
- Shop Profile location fields and Owner-authorized edit workflow.
- Read-only geographic lookup services/API for cascading selectors.
- Customer model and services.
- Customer policies.
- Customer controllers and API endpoints.
- Customer UI with shop-location preselection and customer override.
- Translation integration.
- Customer and geographic hierarchy tests.

### Acceptance criteria

- Authorized users can manage customers.
- Shop Owner can change shop location using hierarchical selectors.
- New customer forms preselect the current shop location where available.
- Users can override or clear customer location.
- Customer location is saved independently and remains unchanged when shop location changes.
- Location hierarchy is validated against the LGD master data.
- Search works in Hindi and English.
- Original and translated text remain separate.
- Tenant isolation is enforced.
- Pagination and filters work.

## 8. Phase 5 — Financial Foundation and Accounting Engine

**This is one of the most critical development phases.**

### Objectives

- Implement financial transaction foundation.
- Implement decimal-safe money handling.
- Implement consolidated customer outstanding.
- Implement ledger entries.
- Implement financial posting.
- Reject excess payments; do not implement customer advance balances.
- Implement audit history.
- Implement idempotency.
- Implement concurrency protection.

### Accounting architecture

The accounting engine must support:

- Sales.
- Payments.
- Consolidated outstanding.
- Interest.
- Excess-payment rejection (no advance balance).
- Settlements.
- Corrections.
- Running customer ledger.

### Consolidated balance rules

- Customer-facing payments remain consolidated and are internally allocated FIFO across eligible Sales.
- A payment first triggers eligible interest calculation through the payment date.
- The received payment is deducted from total payable.
- Remaining balance is carried forward from the payment date.
- New sales preserve their own sale dates.
- Excess payment is rejected in full before posting.
- Multiple payments on the same date must not duplicate interest.

### Deliverables

- Financial transaction services.
- Ledger posting services.
- Consolidated balance calculation.
- Excess-payment rejection.
- Database migrations.
- Audit services.
- Idempotency mechanism.
- Concurrency protection.
- Financial unit and integration tests.

### Acceptance criteria

- Financial posting is atomic.
- Duplicate posting is prevented.
- Monetary calculations use safe decimal arithmetic.
- Consolidated outstanding is correct.
- Excess payments are rejected without creating financial postings.
- Ledger entries reconcile with financial transactions.
- Same-day multiple payments are handled correctly.
- Audit history is preserved.

**Do not begin full production sales and payment workflows until the financial foundation passes its critical tests.**

## 9. Phase 6 — Credit Sales

### Objectives

- Sale creation.
- Sale validation.
- Sale posting.
- Sale listing.
- Sale details.
- Crop/season tagging.
- Optional bill upload.
- Owner-authorized correction.

### Sale information

- Customer.
- Sale date.
- Amount.
- Description.
- Optional crop or season tag.

A product category or sale type is not mandatory.

### Deliverables

- Sales module.
- Sale migrations.
- Sale services.
- Sale controllers and API endpoints.
- Sale forms and listing.
- Private bill attachments.
- Audit integration.
- Sales tests.

### Acceptance criteria

- Authorized users can record sales.
- Posted sales update consolidated customer outstanding.
- Original sale date is retained.
- Owner can edit posted sales.
- Manager and Staff cannot edit or delete posted sales.
- Owner corrections retain old and new values in audit history.
- Corrections trigger required recalculation.
- Attachments are private.

## 10. Phase 7 — Payments

### Objectives

- Payment recording.
- Payment processing.
- Payment history.
- Payment receipt upload.
- Consolidated balance update.
- Excess-payment rejection; no advance balance.
- Owner-authorized correction.

### Payment processing flow

1. Validate payment details.
2. Lock or otherwise safely protect the customer's financial account during processing.
3. Calculate eligible interest through the payment date.
4. Determine total payable.
5. Deduct payment amount.
6. Reject the entire payment if its amount exceeds total payable; never create an advance.
7. Carry forward the remaining outstanding.
8. Preserve audit and ledger history.
9. Return the resulting balance.

### Deliverables

- Payments module.
- Payment services.
- Payment controllers and API endpoints.
- Receipt upload.
- Payment history.
- Idempotency integration.
- Audit integration.
- Payment tests.

### Acceptance criteria

- Payments are posted exactly once.
- Customer-facing payments remain consolidated and are internally allocated FIFO across eligible Sales.
- Same-day multiple payments do not duplicate interest.
- Excess payment is rejected; no advance balance is created.
- Concurrent payment requests preserve balance consistency.
- Only Owner can edit posted payments.
- Corrections preserve immutable audit history.
- Financial balances reconcile.

## 11. Phase 8 — Customer Ledger

### Objectives

- Customer account ledger.
- Opening balance.
- Sales display.
- Payment display.
- Interest entries.
- Settlement entries.
- No advance balance (excess payments are rejected).
- Adjustments and corrections.
- Running balance.
- Date filters.
- Printable ledger.

### Deliverables

- Customer ledger service.
- Ledger UI.
- Ledger API.
- Running balance calculation.
- Printable ledger layout.
- Ledger tests.

### Acceptance criteria

- Ledger displays the complete consolidated account.
- Payments are not shown as allocations against individual sales.
- Carried-forward balance is correct.
- New sales preserve their own dates.
- Running balances are deterministic.
- Ledger matches the accounting engine.
- Hindi and English printing work.

## 12. Phase 9 — Interest Engine

### Prerequisites

- Financial foundation completed.
- Consolidated payment model implemented.
- Interest business rules finalized.
- Interest date-boundary and rounding decisions approved.

### Objectives

- Interest policy management.
- Monthly interest rate.
- Grace period.
- Customer-specific interest rate.
- Capitalization policy.
- Actual-days/30 calculation.
- Calculation history.
- Annual auto settlement.
- Interest transparency.

### Approved calculation basis

`Interest = Eligible Base × (Monthly Rate / 100) × (Eligible Days / 30)`

### Interest calculation events

Interest calculation must occur on:

1. Customer payment.
2. Two configured annual auto-settlement dates.

It must not be repeatedly posted by an ordinary daily/monthly background interest job.

### Deliverables

- Interest policy models.
- Policy versioning.
- Interest calculation service.
- Interest posting service.
- Interest calculation history.
- Owner-only policy management.
- Interest API.
- Interest tests.

### Acceptance criteria

- Calculations are deterministic.
- Actual elapsed days are used.
- Grace period is respected.
- Historical policies are preserved.
- Capitalized and separate interest configurations work correctly.
- Same-date payment and auto settlement do not duplicate interest.
- Interest calculations are explainable.
- Boundary and rounding tests pass.

## 13. Phase 10 — Crop-Season Settlement

### Objectives

- Settlement preparation.
- Settlement calculation.
- Full settlement.
- Partial settlement.
- Carry-forward to next crop season.
- Settlement history.
- Audit history.

### Deliverables

- Settlement module.
- Settlement services.
- Settlement ledger entries.
- Settlement API.
- Settlement UI.
- Settlement tests.

### Acceptance criteria

- Full settlement is handled correctly.
- Partial settlement retains the remaining balance.
- Carry-forward preserves outstanding.
- Previous financial history remains intact.
- Interest is calculated according to approved rules.
- Duplicate settlements are prevented.
- Settlement operations are auditable.

## 14. Phase 11 — Reports, Printing and Exports

### Objectives

- Customer outstanding report.
- Sales report.
- Payment report.
- Interest report.
- Settlement report.
- Customer ledger print.
- PDF exports.
- Excel/CSV exports.
- Date and customer filters.

### Deliverables

- Reporting services.
- Report UI.
- PDF generation.
- Excel/CSV export.
- Report tests.

### Acceptance criteria

- All reports are tenant-scoped.
- Totals match authoritative ledger data.
- Role permissions are enforced.
- Hindi and English printing work.
- Large datasets are handled safely.
- Exported data is consistent with the accounting engine.

## 15. Phase 12 — Subscription System

### Objectives

- Subscription plans.
- Shop-level subscription.
- Free Trial.
- Basic, Standard and Premium plans.
- Monthly billing.
- Annual billing.
- Renewal.
- Expiry.
- Three-day grace period.
- Read-only access.
- Subscription history.

### Approved rules

- Subscription belongs to the shop, not individual users.
- All users of a shop share subscription status.
- Free Trial duration is configurable, initially three months.
- After expiry and three-day grace period, the shop becomes read-only.
- Owner retains access to data exports after expiry.

### Deliverables

- Subscription models.
- Plan configuration.
- Subscription services.
- Subscription enforcement middleware or equivalent.
- Subscription UI.
- Billing integration after provider selection.
- Subscription tests.

### Acceptance criteria

- Shop subscription status is enforced correctly.
- One shop's subscription status does not affect another.
- Expiry and grace period work.
- Read-only restrictions cannot be bypassed through API.
- Owner can export data after expiry.
- Subscription changes are auditable.

## 16. Phase 13 — API Finalization and Android Readiness

The API foundation should be implemented alongside the relevant backend modules. This phase verifies the complete API contract and Android readiness.

### Objectives

- Verify API versioning.
- Verify authentication.
- Verify customer APIs.
- Verify sales APIs.
- Verify payment APIs.
- Verify ledger APIs.
- Verify interest and settlement APIs.
- Verify file APIs.
- Verify API documentation.
- Verify Android-compatible token authentication.

### Deliverables

- Versioned REST API.
- API documentation.
- OpenAPI/Swagger specification, where adopted.
- API integration tests.
- Android integration readiness report.

### Acceptance criteria

- Web and Android clients use the same business logic.
- Financial calculations remain backend-controlled.
- API tenant isolation is verified.
- API authorization is verified.
- Idempotency works for financial endpoints.
- API tests pass.

## 17. Phase 14 — Production Readiness

### Objectives

- Production hosting.
- HTTPS.
- Environment configuration.
- Database backup.
- Encrypted off-site backup.
- Restore testing.
- Error monitoring.
- Application logging.
- Security review.
- Performance review.
- Deployment procedure.
- Recovery procedure.

### Deliverables

- Production environment.
- Deployment documentation.
- Backup automation.
- Restore procedure.
- Monitoring and alerting.
- Security review report.
- Release checklist.

### Acceptance criteria

- Production configuration is secure.
- Backups run successfully.
- Restore procedure has been tested.
- Tenant isolation is verified.
- Critical automated tests pass.
- Monitoring is operational.
- Deployment and recovery procedures are documented.

## 18. Phase 15 — Android Application

Android development begins after the backend API and financial behavior are stable.

### Objectives

- Android authentication.
- Customer management.
- Sales recording.
- Payments.
- Customer ledger.
- Interest information.
- Settlements.
- Reports.
- Bill and receipt uploads.
- Hindi and English UI.

### Deliverables

- Native Android application.
- API integration.
- Secure token storage.
- Mobile UI.
- Android integration tests.

### Acceptance criteria

- Android uses the same backend accounting engine.
- Android cannot independently modify authoritative balances.
- User permissions match backend roles.
- Tenant isolation remains enforced.
- Financial workflows work consistently across web and Android.

Offline synchronization is not part of the initial release. It requires a separate architecture and approval.

## 19. Indicative Development Schedule

The following estimates are planning estimates for one experienced Laravel developer. They are not delivery guarantees.

| Development area | Estimated duration |
|---|---:|
| Project audit and foundation | 3–5 days |
| Authentication and shop foundation | 5–8 days |
| Multi-tenancy | 4–7 days |
| Roles and user management | 3–5 days |
| Customer management | 4–7 days |
| Financial foundation | 7–12 days |
| Credit sales | 4–7 days |
| Payments | 5–8 days |
| Customer ledger | 4–7 days |
| Interest engine | 7–12 days |
| Crop-season settlement | 4–8 days |
| Reports and exports | 4–8 days |
| Subscription system | 5–8 days |
| API finalization | 4–7 days |
| Production readiness | 5–10 days |
| Android application | Separate estimate |

Some tasks can run in parallel after their dependencies are stable.

The actual duration depends on existing code quality, integration complexity, testing depth and unresolved business decisions.

## 20. AI-Assisted Development Rules

When using Claude Code or another AI coding agent:

1. Work on one phase at a time.
2. Provide the relevant approved documentation.
3. Ask the agent to inspect existing code before modifying it.
4. Require an implementation plan.
5. Review database migrations.
6. Review financial calculations independently.
7. Require automated tests.
8. Run tests before accepting implementation.
9. Review authorization and tenant isolation.
10. Do not allow silent changes to approved architecture.
11. Require a summary of changed files.
12. Require evidence of test execution.
13. Update project documentation after completion.

AI-generated code must be reviewed before merging.

## 21. Release Milestones

### Milestone A — Secure Shop and Customer Foundation

- Authentication.
- Shop registration.
- Multi-tenancy.
- Roles.
- Customer management.

### Milestone B — Basic Financial Ledger

- Financial foundation.
- Credit sales.
- Consolidated payments.
- Customer ledger.
- Advance handling.

### Milestone C — Financial Automation

- Interest engine.
- Interest policies.
- Annual auto settlement.
- Crop-season settlement.
- Financial reports.

### Milestone D — Commercial SaaS Release

- Subscription system.
- Data exports.
- Production hosting.
- Backup and recovery.
- Security and performance review.

### Milestone E — Mobile Expansion

- Finalized API.
- Android application.
- Mobile workflows.
- Future offline architecture, subject to separate approval.

## 22. Roadmap Completion Rules

A phase is complete only when:

- Approved scope is implemented.
- Relevant automated tests exist.
- Relevant tests have actually passed.
- Tenant isolation is verified.
- Authorization is verified.
- Financial integrity is verified where applicable.
- Security review is completed.
- Documentation is updated.
- Acceptance criteria are met.
- `docs/PROJECT-STATUS.md` is updated.

A phase must not be marked complete merely because code has been written.

## 23. Change Management

If implementation reveals a conflict or missing requirement:

1. Stop the affected implementation.
2. Document the conflict.
3. Identify impacted approved documents.
4. Explain the technical and business consequences.
5. Obtain approval for the proposed change.
6. Update the relevant documentation.
7. Resume implementation only after approval.

This prevents implementation decisions from silently changing the product.

---

## 24. Phase 5 Financial Rules and Delivery Baseline (2026-11-25)

ADR-0012 is the controlling Product Owner decision for Phase 5 financial behavior. This section and ADR-0012 supersede conflicting older roadmap statements.

### Phase 5 — Financial Foundation
- Decimal-safe INR money, customer account, append-only ledger and balance projections.
- Paid/Unpaid Sale posting; Paid creates an equal linked automatic Payment atomically.
- Consolidated normal Customer Payments with internal FIFO allocation across Sales.
- FIFO Sale order first, then Shop Principal First / Interest First waterfall within each Sale.
- Reject excess Payment; no customer advances.
- Effective-dated Shop/Customer interest policy, Shop capitalization default OFF, Customer override, 30-day Shop grace period and Sale Date Day 0.
- Actual Days / 30; HALF_UP two-decimal posted interest; [start inclusive, end exclusive) interval.
- Annual Interest Auto Settlement defaults 30 March and 30 October; Payment precedes settlement on a shared date.
- Immutable financial history, reversal-based Payment correction, Sale edit locks, audit, idempotency, tenant isolation and concurrency protection.
- MySQL 8.4 integration tests for transaction rollback, locking, FIFO, rounding, interval boundaries and reconciliation.

### Implementation sequence
1. Phase 5.1 Financial core and money/ledger foundation.
2. Phase 5.2 Tenant integrity, locking, idempotency, balance projection and reconciliation.
3. Phase 5.3 FIFO allocation and per-Sale waterfall.
4. Phase 5.4 Effective-dated interest policy and calculation foundation.
5. Phase 5.5 Annual settlement and full MySQL 8.4 acceptance suite.

### Explicit scope boundaries
- No advances, daily/monthly interest posting jobs or Customer-specific grace-period override.
- Locked historical Sales cannot be exceptionally corrected in the current approved scope; stop and seek separate approval and workflow design.
- Crop-season settlement accounting remains a separate design decision.
- Phase 5 implementation remains NOT STARTED until the read-only code/schema audit, physical schema review and separate implementation authorization are complete.


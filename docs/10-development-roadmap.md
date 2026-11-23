
# Kisan Khata — Development Roadmap

**Document Version:** 1.0  
**Delivery Approach:** Incremental Development  
**Architecture:** Modular Monolith

---

## 1. Purpose

This roadmap defines the recommended development sequence for Kisan Khata.

Development must proceed in small, verifiable phases.

Do not ask an AI coding agent to build the entire application in one operation.

Every phase must have:

- Clear objectives.
- Defined scope.
- Database changes.
- Implementation tasks.
- Tests.
- Acceptance criteria.
- Completion status.

## 2. Development Strategy

Use the following cycle:

1. Inspect existing code.
2. Read relevant project documentation.
3. Identify dependencies.
4. Prepare implementation plan.
5. Obtain approval for significant decisions.
6. Implement a small feature.
7. Write automated tests.
8. Run tests.
9. Review security.
10. Update documentation.

## 3. Phase 0 — Project Foundation

Objectives:

- Inspect existing Laravel application.
- Confirm PHP and Laravel versions.
- Confirm MySQL version.
- Review installed packages.
- Configure development environment.
- Configure environment variables.
- Establish code formatting standards.
- Establish testing framework.
- Configure logging.

Deliverables:

- Working Laravel application.
- Database connection.
- Passing baseline tests.
- Initial architecture documentation.

Acceptance criteria:

- Application starts successfully.
- Database connection works.
- Existing functionality is preserved.
- Baseline test suite passes.

## 4. Phase 1 — Authentication and Shop Management

Objectives:

- Authentication.
- Shop registration.
- Shop profile.
- Shop memberships.
- Shop selection.
- Roles and permissions foundation.

Deliverables:

- User management.
- Shop management.
- Membership model.
- Authorization foundation.

Acceptance criteria:

- Users can authenticate.
- Authorized users can access their shop.
- Unauthorized shop switching is rejected.
- Permission tests pass.

## 5. Phase 2 — Multi-Tenancy Foundation

Objectives:

- Centralized current shop context.
- Tenant-aware models.
- Tenant-aware queries.
- Tenant authorization.
- Tenant-specific storage structure.
- Tenant-specific cache keys.

Deliverables:

- CurrentShop abstraction.
- Tenant middleware or equivalent request context.
- Tenant ownership validation.
- Cross-tenant tests.

Acceptance criteria:

- Shop A cannot access Shop B data.
- Jobs establish explicit tenant context.
- Tenant isolation tests pass.

This phase must be completed before customer and financial modules are exposed to multiple shops.

## 6. Phase 3 — Customer Management

Objectives:

- Customer creation.
- Customer editing.
- Customer listing.
- Search.
- Filters.
- Customer detail page.

Deliverables:

- Customer migrations.
- Customer model.
- Customer services.
- Customer policies.
- Customer controllers.
- Customer views.
- Customer tests.

Acceptance criteria:

- Authorized users can manage customers.
- Search works in Hindi and English.
- Tenant isolation is enforced.
- Pagination works.

## 7. Phase 4 — Financial Foundation

Objectives:

- Define financial transaction model.
- Define ledger entry conventions.
- Implement decimal arithmetic.
- Implement transaction posting workflow.
- Implement reversal workflow.
- Implement idempotency.
- Implement concurrency protection.

Deliverables:

- Financial transaction service.
- Ledger posting service.
- Financial database migrations.
- Financial integrity tests.

Important:

Do not begin production interest calculations before the accounting foundation is stable.

Acceptance criteria:

- Transactions are auditable.
- Posted entries are immutable through ordinary workflows.
- Duplicate posting is prevented.
- Financial posting is atomic.

## 8. Phase 5 — Credit Sales

Objectives:

- Draft credit sale.
- Sale validation.
- Sale posting.
- Bill upload.
- Sale listing.
- Sale detail.
- Sale reversal.

Deliverables:

- Sales module.
- Sale attachments.
- Posting service integration.
- Sales tests.

Acceptance criteria:

- Posted sales update the ledger correctly.
- Customer and shop ownership are validated.
- Attachments are private.
- Reversals preserve history.

## 9. Phase 6 — Payments

Objectives:

- Draft payment.
- Payment posting.
- Payment methods.
- Receipt upload.
- Allocation records.
- Payment history.
- Payment reversal.

Important:

The product owner must approve payment allocation behavior before the authoritative allocation engine is implemented.

Deliverables:

- Payments module.
- Payment allocation service.
- Payment posting service.
- Payment tests.

Acceptance criteria:

- Payments are posted exactly once.
- Allocations are traceable.
- Account balances remain consistent.
- Reversal preserves history.

## 10. Phase 7 — Customer Ledger

Objectives:

- Chronological ledger.
- Opening balance.
- Debit and credit display.
- Running balance.
- Principal and interest separation.
- Date filters.
- Ledger printing.

Deliverables:

- Customer ledger service.
- Ledger UI.
- Printable ledger layout.
- Ledger tests.

Acceptance criteria:

- Ledger can be reconciled to posted entries.
- Running balances are deterministic.
- Hindi printing works.
- Closing balance is correct.

## 11. Phase 8 — Interest Engine

Prerequisite:

All required interest business decisions must be approved.

Objectives:

- Interest policy.
- Policy versioning.
- Calculation engine.
- Calculation history.
- Interest posting.
- Interest reports.

Deliverables:

- Interest policy models.
- Interest calculation service.
- Interest posting service.
- Interest calculation tests.
- Audit trail.

Acceptance criteria:

- Calculations are deterministic.
- Calculation basis is explainable.
- Historical policies are preserved.
- Boundary tests pass.
- No unapproved interest behavior exists.

## 12. Phase 9 — Crop-Season Settlement

Prerequisite:

All settlement and payment allocation decisions must be approved.

Objectives:

- Settlement preparation.
- Settlement calculation.
- Settlement posting.
- Carry-forward handling.
- Settlement reversal.
- Settlement history.

Deliverables:

- Settlement module.
- Settlement services.
- Settlement ledger entries.
- Settlement reports.
- Settlement tests.

Acceptance criteria:

- Previous financial history remains intact.
- Outstanding balances are correct.
- Carry-forward treatment is explainable.
- Reversals work.
- Duplicate settlements are prevented.

## 13. Phase 10 — Reports and Printing

Objectives:

- Outstanding report.
- Sales report.
- Payment report.
- Interest report.
- Settlement report.
- Customer ledger print.
- Date filters.

Acceptance criteria:

- Reports are tenant-scoped.
- Totals match ledger data.
- Print layouts work in Hindi and English.
- Large datasets are paginated or exported safely.

## 14. Phase 11 — Subscription System

Objectives:

- Subscription plans.
- Shop subscription.
- Trial configuration.
- Renewal.
- Expiry.
- Grace period.
- Suspension.
- Payment integration after provider selection.

Acceptance criteria:

- Shop subscription status is correctly enforced.
- One shop's suspension does not affect another.
- Subscription changes are auditable.

## 15. Phase 12 — Production Readiness

Objectives:

- Production hosting.
- HTTPS.
- Database backups.
- Restore testing.
- Error monitoring.
- Security review.
- Performance review.
- Deployment procedure.

Acceptance criteria:

- Production configuration is secure.
- Backup and recovery are tested.
- Critical tests pass.
- Tenant isolation is verified.
- Monitoring is operational.

## 16. Phase 13 — Android Application

Android development starts after the web application API and financial behavior are stable.

Objectives:

- Android authentication.
- Shop selection.
- Customer management.
- Sales.
- Payments.
- Ledger.
- Bill and receipt uploads.
- Hindi and English UI.

The Android client must use backend financial services.

Do not duplicate accounting logic in Android.

Offline synchronization requires separate approval and architecture design.

## 17. Indicative Schedule

An indicative estimate for one experienced Laravel developer:

| Area | Estimated Duration |
|---|---:|
| Foundation and architecture | 3–5 days |
| Authentication and tenancy | 5–8 days |
| Customers | 3–5 days |
| Financial foundation | 5–8 days |
| Sales | 4–6 days |
| Payments | 5–8 days |
| Ledger | 4–7 days |
| Interest engine | 5–10 days |
| Settlement | 4–7 days |
| Reports and printing | 4–7 days |
| Subscription and production readiness | 5–10 days |

These are planning estimates, not delivery guarantees.

Actual duration depends on existing codebase quality, requirements, testing depth and product decisions.

## 18. AI-Assisted Development Rules

When using Claude Code:

- Work on one phase at a time.
- Give it the relevant documentation.
- Ask it to inspect the existing project first.
- Require an implementation plan.
- Review database changes.
- Review financial calculations.
- Require tests.
- Run tests before accepting work.
- Do not let it silently change approved architecture.

## 19. Release Milestones

### Milestone A — Basic Customer Ledger

- Authentication.
- Shops.
- Customers.
- Credit sales.
- Payments.
- Basic ledger.

### Milestone B — Financial Automation

- Approved interest engine.
- Crop-season settlements.
- Financial reports.

### Milestone C — SaaS Commercial Release

- Subscriptions.
- Production deployment.
- Security review.
- Merchant onboarding.

### Milestone D — Mobile Expansion

- Versioned API.
- Android application.
- Mobile workflows.

## 20. Roadmap Completion Rules

A phase is complete only when:

- Scope is implemented.
- Relevant tests pass.
- Security is reviewed.
- Tenant isolation is tested.
- Documentation is updated.
- Acceptance criteria are met.

Update docs/PROJECT-STATUS.md after every completed phase.

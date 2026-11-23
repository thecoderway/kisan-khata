
# CLAUDE.md — Kisan Khata

## 1. Project Overview

Kisan Khata is a multi-tenant SaaS accounting and credit ledger application for small and medium-sized merchants in India.

The primary users are shop owners who sell products to farmers on credit and collect payments during harvest seasons.

The application must be simple enough for users with limited technical knowledge.

Read README_START_HERE.md and the documents inside docs/ before making architectural decisions.

## 2. Core Technology

Follow the existing project's installed versions.

Preferred initial architecture:

- Laravel
- PHP
- MySQL
- Blade
- Bootstrap 5
- Alpine.js
- Versioned REST API for future Android application

Do not upgrade framework or package versions without reviewing compatibility and obtaining approval.

## 3. Architecture Rules

Use a Modular Monolith architecture.

Keep business logic separated into logical modules.

Suggested modules:

- Authentication
- Shops and Tenancy
- Users and Permissions
- Customers
- Products (optional initial module)
- Credit Sales
- Payments
- Accounting Ledger
- Interest Calculation
- Crop Settlements
- Reports
- Subscription and Billing
- Audit Logs

Controllers must remain thin.

Business calculations must not be implemented directly inside controllers or Blade templates.

Use dedicated services, actions or domain classes where appropriate.

Do not introduce microservices without explicit approval.

## 4. Multi-Tenancy

Kisan Khata is a SaaS platform.

Multiple shops will use the same application and database.

Every tenant-owned record must be scoped to its shop.

Rules:

- Never trust shop_id supplied by the browser.
- Resolve the current shop from authenticated membership.
- Apply tenant isolation in queries and services.
- Use policies for authorization.
- Validate ownership of uploaded files.
- Prevent cross-tenant record access.
- Add automated cross-tenant security tests.

Never assume that hiding a record in the UI is sufficient security.

## 5. Financial Integrity

This is an accounting application.

Financial correctness has higher priority than convenience.

Never use PHP float or JavaScript floating-point arithmetic for authoritative financial calculations.

Use appropriate decimal arithmetic.

Store monetary values with suitable DECIMAL precision.

Keep these concepts separate:

- Original sale principal
- Payments
- Principal outstanding
- Accrued interest
- Posted interest
- Settlements
- Adjustments
- Reversals

Never silently overwrite a posted financial transaction.

Corrections must be auditable.

Use database transactions and appropriate row locking for operations that modify account balances.

Use idempotency protection where duplicate financial requests are possible.

Every financial transaction must have a clear source, date, amount and audit trail.

## 6. Interest Calculation

Interest calculation must be deterministic, explainable and testable.

Do not assume:

- Interest rate
- Interest-free period
- Calendar-month versus 30-day calculation
- Daily versus monthly calculation
- Partial-month treatment
- Compounding
- Payment allocation order
- Settlement treatment

Refer to docs/02-business-rules.md and docs/03-accounting-engine.md.

If any required rule is unresolved, ask the product owner.

Do not silently choose a financial policy.

## 7. Customer Management

Customer information includes:

- Customer name
- Father's name
- Mobile number
- Village
- Optional address
- Notes

Support searching by:

- Name
- Father's name
- Mobile number
- Village

Support Hindi and English text.

Do not assume Hindi and English names are interchangeable.

## 8. Credit Sales

A credit sale should support:

- Customer
- Sale amount
- Sale date and time
- Description
- Optional bill photo
- Notes
- Reference number

All sales must be associated with the correct shop and customer.

Editing posted financial records must follow the approved correction policy.

## 9. Payments

A payment should support:

- Customer
- Amount
- Date and time
- Payment method
- Reference number
- Description
- Optional receipt photo

Maintain payment history and allocation history.

Never lose the original payment record when a correction is required.

## 10. Customer Ledger

The customer ledger must provide a complete chronological financial history.

It should show:

- Opening balance
- Credit sales
- Payments
- Interest entries
- Settlements
- Adjustments
- Reversals
- Running balance
- Closing balance

Principal and interest must be separately identifiable.

The ledger must be printable in an Indian business-friendly format.

## 11. Bilingual User Interface

The application must support Hindi and English.

Use Laravel localization.

All interface text must be translatable.

Use clear terminology suitable for small-town shop owners.

Avoid unnecessary technical terminology in the UI.

Ensure Hindi text displays correctly in print and PDF output.

## 12. Security

Follow Laravel security best practices.

Validate all inputs.

Use CSRF protection.

Use authorization policies.

Protect file uploads.

Use private storage for financial documents.

Never expose uploaded bills or receipts through unrestricted public URLs.

Do not log passwords, authentication tokens or sensitive financial information unnecessarily.

## 13. Database Rules

Use foreign keys wherever appropriate.

Use indexes for frequent search and tenant filtering.

Use DECIMAL columns for financial amounts.

Use timestamps consistently.

Use soft deletes only where appropriate.

Do not physically delete posted financial transactions.

Use migrations for every schema change.

Never modify production data directly without an approved migration or controlled operation.

## 14. Testing Requirements

Write tests for:

- Authentication
- Tenant isolation
- Customer CRUD
- Credit sales
- Payments
- Payment allocations
- Interest calculations
- Settlements
- Ledger calculations
- Authorization
- File uploads
- Duplicate financial requests

Financial calculation tests must include boundary conditions and rounding cases.

A feature is not complete until relevant tests pass.

## 15. Development Workflow

Before implementing a feature:

1. Read relevant documentation.
2. Inspect the existing codebase.
3. Identify dependencies.
4. Explain the proposed implementation.
5. Identify database changes.
6. Implement in small steps.
7. Write tests.
8. Run tests.
9. Review security.
10. Update project documentation.

Do not rewrite existing working code unnecessarily.

Do not introduce unnecessary dependencies.

Do not make unrelated changes.

## 16. Future Android Application

The web application is Phase 1.

Android is Phase 2.

Keep business logic independent of Blade controllers.

Use API-ready service architecture.

The Android application must use the same accounting and business rules as the web application.

Never duplicate financial calculation logic in the Android client.

Offline synchronization is not included unless separately approved.

## 17. Documentation Discipline

Update relevant documentation whenever a business rule, architecture decision or implementation status changes.

Record significant architecture decisions as ADR documents inside docs/decisions/.

Record unresolved questions rather than guessing.

## 18. Definition of Done

A feature is complete only when:

- Implementation is complete.
- Validation is implemented.
- Authorization is implemented.
- Tenant isolation is verified.
- Automated tests pass.
- Financial integrity is preserved.
- Documentation is updated.
- No known critical security issue remains.

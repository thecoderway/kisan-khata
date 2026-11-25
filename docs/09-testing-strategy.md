# Kisan Khata — Testing Strategy

**Document Version:** 2.1  
**Status:** Approved  
**Testing Framework:** Pest (preferred) or PHPUnit, following the existing Laravel project  
**Backend:** Laravel 13  
**Primary Database:** MySQL 8.4  
**PHP:** 8.3

---

## 1. Purpose

This document defines the testing strategy for Kisan Khata.

Kisan Khata manages customer information, credit sales, payments, interest calculations, consolidated customer balances and financial settlements.

Testing must protect:

- Financial correctness.
- Tenant isolation.
- Role-based authorization.
- Data integrity.
- Audit history.
- Application security.
- API and UI consistency.

A feature is not complete merely because it works in a browser. Its business rules, failure scenarios and financial effects must also be tested.

## 2. Testing Principles

1. Test business behavior, not only implementation details.
2. Test financial calculations with independently verified expected results.
3. Test tenant isolation for every tenant-owned resource.
4. Test authorization independently of UI visibility.
5. Test failure scenarios and boundary conditions.
6. Test concurrent financial operations.
7. Use MySQL integration tests for database-sensitive behavior.
8. Never use floating-point assertions for authoritative money calculations.
9. Keep tests deterministic and repeatable.
10. Test audit history whenever financial data changes.
11. Do not silently approve unresolved business rules through test expectations.
12. Do not mark tests successful unless they have actually executed successfully.

## 3. Testing Layers

### 3.1 Unit Tests

Test isolated business logic without requiring HTTP requests.

Examples:

- Money and decimal arithmetic.
- Interest calculation components.
- Date-range calculations.
- Grace-period calculations.
- Consolidated outstanding calculations.
- Advance balance calculations.
- Ledger balance calculations.
- Policy version selection.
- Translation data handling.
- Subscription expiry calculations.

### 3.2 Feature Tests

Test complete application behavior.

Examples:

- Registration and login.
- Customer creation and updates.
- Credit sale creation.
- Payment recording.
- Customer ledger viewing.
- Interest policy management.
- Settlement processing.
- Transaction corrections.
- File uploads and downloads.
- Role-based permissions.
- Subscription restrictions.

### 3.3 Integration Tests

Test interactions between application components and the database.

Examples:

- Sale posting and customer balance updates.
- Payment processing and consolidated ledger updates.
- Interest calculation and balance carry-forward.
- Owner correction and financial recalculation.
- Settlement and customer balance updates.
- Database transaction rollback.
- Idempotency handling.
- Audit log creation.
- Concurrent transaction handling.

### 3.4 Security Tests

Test unauthorized access and data isolation.

Examples:

- Cross-tenant record access.
- Unauthorized financial posting.
- Unauthorized transaction correction.
- Private file access.
- Privilege escalation.
- Shop ID manipulation.
- Authentication token misuse.
- Subscription restriction bypass.

### 3.5 Browser Tests

Use browser automation where it provides meaningful end-to-end coverage.

Examples:

- Registration and login.
- Customer creation.
- Credit sale form.
- Payment form.
- Customer ledger.
- Interest settings.
- Settlement workflow.
- Owner transaction correction.
- Hindi and English UI.
- Printing and export.

Browser tests must not replace accounting unit and integration tests.

## 4. Test Environment

Requirements:

- Dedicated testing database.
- Test-specific environment variables.
- No production credentials.
- No production customer data.
- Predictable test fixtures.
- Database migrations available.
- Reliable cleanup between tests.
- Isolated external service dependencies.

MySQL 8.4 must be used for database integration tests.

SQLite may be used for selected isolated tests but must not replace MySQL integration tests involving financial transactions, constraints, locking or tenant isolation.

External services such as OTP providers, translation providers and payment gateways should be mocked or tested using dedicated sandbox environments.

## 5. Authentication and User Tests

Verify:

- Shop Owner can register a shop.
- Required verification is enforced.
- Mobile OTP login works.
- Email/password login works.
- Invalid credentials are rejected.
- Expired OTP is rejected.
- Repeated OTP attempts are rate-limited.
- Logout invalidates the applicable session or token.
- Disabled users cannot access protected endpoints.
- A user belongs to exactly one shop and one role.
- Unauthorized shop switching is impossible.
- Manager and Staff accounts can only be created by authorized users.
- Shop user limits are enforced: 1 Owner, 2 Managers and 5 Staff.

## 6. Customer Tests

Verify:

- Authorized users can create customers.
- Customer name is required.
- Mobile and village are optional.
- Invalid mobile values are rejected according to approved validation rules.
- Address and notes are optional.
- Hindi names are stored correctly.
- English names are stored correctly.
- Original entered text is preserved.
- Hindi and English translations are stored separately.
- Translation corrections do not overwrite original text.
- Search works with Hindi and English input.
- Customer belongs to the correct shop.
- A customer cannot be accessed by another shop.
- Unauthorized users cannot modify customer information.
- Customer location may be omitted.
- A new customer form receives the shop's current location as initial values where configured.
- A user can override the preselected customer location.
- Changing the shop location does not alter existing customer locations.
- Changing State clears District, Subdistrict and Village; changing District clears Subdistrict and Village; changing Subdistrict clears Village.
- Unknown, stale or unsupported LGD codes are rejected.
- Mismatched State/District/Subdistrict/Village combinations are rejected server-side.
- Duplicate village names remain distinguishable by their LGD codes and hierarchy.

### 6.1 Customer Lifecycle Tests

Verify all lifecycle behavior through Web and API requests, independently of UI visibility:

**State transitions**
- Active customer can be deactivated by Owner and Manager.
- Deactivation requires a non-empty reason; blank or missing reasons are rejected.
- Deactivation changes status to Inactive, retains the customer and all history, removes the customer from the default directory and prevents selection for new transactions.
- Inactive customer can be reactivated by Owner and Manager.
- Reactivation reason is optional and is retained when supplied.
- Reactivation changes status to Active and restores default-directory/new-transaction eligibility.
- Only Soft Deleted customers can be restored.
- Restore clears `deleted_at` and preserves the exact pre-deletion Active or Inactive status.
- Restoring an Inactive customer does not activate it.
- Invalid, repeated or stale state transitions are rejected safely.

**Role authorization**
- Owner and Manager can perform permitted customer lifecycle actions.
- Staff cannot deactivate, reactivate, soft-delete or restore customers, even by calling endpoints directly.
- Unauthenticated users and users without an active authorized shop context are denied.
- Authorization is enforced server-side for every lifecycle endpoint.

**Deletion eligibility and retention**
- Soft deletion requires a non-empty reason.
- Soft deletion is allowed only when no financial or transaction records are associated with the customer.
- Soft deletion is rejected when Sales, Payments, Udhaar/Outstanding or Ledger records exist.
- Inactive status does not make a customer eligible for deletion when financial history exists.
- The eligibility checker includes relationships introduced by future financial modules; unknown or unverifiable eligibility fails closed.
- Soft deletion sets `deleted_at`, never physically removes the customer, and preserves the prior Active/Inactive status.
- No ordinary Web/API route can permanently delete a customer.

**Tenant isolation**
- Owner/Manager of Shop A cannot deactivate, reactivate, soft-delete or restore Shop B customers.
- Cross-tenant identifiers and client-supplied `shop_id` values cannot bypass tenant scoping.
- Lifecycle lookup, dependency checks and audit writes remain within the authenticated shop.

**Audit and concurrency**
- Every successful lifecycle action creates exactly one append-only audit event with shop, actor, customer, action, previous/new state, timestamp and applicable reason.
- Failed operations create no misleading success event and do not partially change customer state.
- Audit history cannot be updated or deleted through ordinary model or bulk-query operations.
- Concurrent lifecycle requests cannot produce conflicting states or duplicate success events.
- Restore validates tenant ownership and applicable duplicate/uniqueness constraints.

## 7. Credit Sale Tests

Verify:

- Authorized users can record a credit sale.
- Customer is required.
- Sale date is validated.
- Sale amount must be positive.
- Description is supported.
- Crop or season tags are optional.
- Product category is not mandatory.
- Customer must belong to the authenticated user's shop.
- Successful posting updates the customer's consolidated outstanding.
- Sale date is preserved for future interest calculation.
- Duplicate posting is prevented.
- Unauthorized posting is rejected.
- Failed posting rolls back all related database changes.
- Manager and Staff cannot edit or delete posted sales.
- Owner can edit a posted sale.
- Owner correction preserves original and updated values in audit history.
- Owner correction triggers the required balance and interest recalculation.

## 8. Payment Tests

Payment tests must follow the consolidated customer account model.

Verify:

- Authorized users can record a payment.
- Payment amount must be positive.
- Customer must belong to the authenticated user's shop.
- Payment date is validated.
- Optional payment method and reference are supported.
- Successful payment calculates eligible interest through the payment date.
- Payment is subtracted from the consolidated payable balance.
- Remaining balance is carried forward from the payment date.
- Excess payment is recorded as customer advance.
- Duplicate payment posting is prevented.
- Failed payment processing rolls back all related changes.
- Concurrent payments do not corrupt customer balances.
- Manager and Staff cannot edit or delete posted payments.
- Owner can edit posted payments.
- Owner correction preserves immutable audit history.
- Owner correction triggers the required financial recalculation.

### 8.1 Multiple Payments on the Same Date

Test the following scenarios:

1. First payment calculates eligible interest through its payment date.
2. The first payment is deducted from the consolidated payable balance.
3. A second payment on the same date does not calculate interest again for the same elapsed period.
4. The second payment is deducted from the resulting outstanding balance.
5. Multiple same-day payments produce the same final balance as the equivalent combined payment, subject to transaction ordering and applicable advance handling.
6. No duplicate interest entries are created.

### 8.2 Excess Payment and Advance

Test:

- Payment exactly equal to total payable.
- Payment greater than total payable.
- Partial payment.
- Multiple payments creating an advance.
- Future transactions when an advance exists.
- Advance balance display in customer ledger.
- No accidental loss or duplication of advance.

## 9. Consolidated Interest Calculation Tests

Interest calculation is financially sensitive and must have independently verified expected results.

### 9.1 Calculation Basis

The approved calculation basis is:

`Interest = Eligible Base × (Monthly Rate / 100) × (Eligible Days / 30)`

Test:

- Monthly rate application.
- Actual elapsed days.
- Grace period.
- First eligible interest date.
- Partial month.
- Calendar month boundary.
- Month-end dates.
- February.
- Leap year.
- Zero elapsed days.
- Multiple sales with different sale dates.
- Customer-specific rate, where configured.

### 9.2 Consolidated Balance After Payment

Use the approved example as a regression test.

Initial sales:

| Sale | Amount |
|---|---:|
| X | ₹100 |
| Y | ₹500 |
| Z | ₹2,000 |
| Total | ₹2,600 |

Assume previously calculated interest is ₹500.

Total payable: ₹3,100.

Payment: ₹1,500.

Expected carried-forward outstanding: ₹1,600.

New sales after the payment:

| Sale | Amount |
|---|---:|
| A | ₹100 |
| B | ₹300 |

For the next interest calculation:

- The carried-forward ₹1,600 balance accrues interest from the previous payment/calculation date.
- Sale A accrues interest from its own sale date.
- Sale B accrues interest from its own sale date.
- The same elapsed period must not be charged twice.
- The new consolidated balance must include all applicable amounts and interest.

Test this scenario using exact dates and independently calculated expected values.

### 9.3 Interest Calculation Events

Interest calculation must be tested for the approved business events:

1. Customer payment.
2. Configured annual auto settlement.

Verify:

- Interest is not posted repeatedly for the same period.
- Payment and auto settlement on the same date do not duplicate interest.
- A second payment on the same date does not repeat the first payment's interest calculation.
- Interest calculation is reproducible for the same inputs and policy.
- Historical calculation records retain the applicable policy and date range.

### 9.4 Capitalization Policy

Test both supported policy configurations:

- Capitalized interest: interest is added to outstanding and subsequent calculations use the updated eligible balance.
- Separate interest: interest remains separately identifiable and is not included in the base for subsequent interest calculations.

The selected policy must be applied consistently.

The exact effective-date, rounding and date-boundary conventions must be finalized before their corresponding expected-value tests are treated as final acceptance tests.

### 9.5 Interest Policy Changes

Verify:

- Only Owner can change the interest policy.
- Monthly rate changes are recorded.
- Grace-period changes are recorded.
- Capitalization policy changes are recorded.
- Auto-settlement date changes are recorded.
- Policy history is preserved.
- Historical calculations remain reproducible using the policy applicable to them.
- Unauthorized policy changes are rejected.

## 10. Settlement Tests

Test the approved settlement scenarios:

- Full settlement.
- Partial settlement.
- Carry-forward to the next crop season.

Verify:

- Settlement amount and date.
- Interest calculation through the settlement date.
- Remaining outstanding after partial settlement.
- Advance treatment where applicable.
- Carry-forward balance.
- No duplicate settlement.
- No silent deletion of previous financial history.
- Audit history for settlement operations.
- Concurrent settlement attempts do not corrupt balances.

Detailed expected-value tests for unresolved settlement and date-boundary rules must be finalized when those rules are approved.

## 11. Customer Ledger Tests

Verify:

- Customer opening balance is correct.
- Sales increase outstanding.
- Payments reduce consolidated outstanding.
- Interest entries are included.
- Advances are represented correctly.
- Settlements are included.
- Corrections are reflected correctly.
- Running balance is deterministic.
- Date filters work.
- Same-date entries have stable ordering.
- Closing balance matches the sum of applicable entries.
- Carried-forward balance uses the correct calculation start date.
- New sales retain their own dates for interest calculations.
- Ledger values match backend accounting calculations.

The ledger must not depend on sale-level payment allocations.

## 12. Multi-Tenancy Tests

Create at least two shops with their respective Owners, Managers and Staff.

Verify:

- Shop A cannot list Shop B customers.
- Shop A cannot view Shop B sales.
- Shop A cannot view Shop B payments.
- Shop A cannot view Shop B ledgers.
- Shop A cannot modify Shop B records.
- Shop A cannot download Shop B attachments.
- Shop A cannot post transactions for Shop B customers.
- Shop A cannot access Shop B interest policies.
- Shop A cannot access Shop B settlements.
- A user cannot switch to another shop by modifying request parameters.
- Background jobs use the correct tenant context.
- Tenant-specific caches do not leak data.
- Reports and exports remain tenant-scoped.
- API resource identifiers cannot be used to bypass tenant isolation.

Every new tenant-owned module must include corresponding isolation tests.

Current foundation regression coverage also verifies:

- Tenant context is resolved from the authenticated user, rejects platform-level roles on shop routes, and is cleared after request completion.
- User bulk mutations cannot affect another shop and fail closed when no tenant context is available.
- Shop settings and user audit history are scoped to the current shop; audit history fails closed without context and rejects cross-shop inserts.
- Tenant cache keys are shop-namespaced.
- Private tenant paths are shop-prefixed and reject absolute paths and traversal segments.
- Tenant job middleware establishes the requested active shop context and clears it after both successful and failed execution.


## 13. Role Authorization Tests

Verify:

- Owner has full permitted shop access.
- Manager can view only Staff list and active/deactivated status.
- Staff cannot access user management.
- Owner can create, deactivate, reactivate and change Manager/Staff roles.
- Manager and Staff cannot create, deactivate, reactivate or change user roles.
- Owner cannot ordinarily deactivate or demote themselves.
- Ownership transfer is not available through ordinary user-management routes.
- Customer profile permissions and posted financial transaction permissions are enforced and tested by their respective domain modules when introduced.

### Phase 3 execution evidence — 2026-11-24

GitHub Actions Laravel CI run #19 completed successfully on MySQL 8.4:
- 90 Pint files passed.
- Composer validation and Vite production build passed.
- MySQL shop-row locking integration: 1 passed / 2 assertions.
- Remaining Laravel suite: 138 passed / 430 assertions.
- Combined: 139 tests / 432 assertions / 0 failures / 0 skipped.

Phase 3 coverage includes Web/API lifecycle success and denial paths, tenant isolation, response-field minimization, account retention, session/token revocation, capacity limits and append-only audit history. Customer/Sales/Payments domain-specific authorization remains required when those later modules are built.

## 14. File Upload and Export Tests

### Upload tests

Test:

- Valid JPEG upload.
- Valid PNG upload.
- Valid WebP upload.
- Valid PDF upload.
- Unsupported file rejection.
- Oversized file rejection.
- Spoofed MIME type rejection.
- Random storage filename.
- Private storage.
- Authorized download.
- Unauthorized download.
- Cross-tenant download rejection.

### Export tests

Verify:

- Customer ledger PDF.
- Financial report PDF.
- Excel/CSV exports.
- Correct date filters.
- Correct tenant-scoped data.
- Role-based export permissions.
- Owner data export access after subscription expiry.
- Exported values match authoritative ledger values.

## 15. Idempotency Tests

Test:

1. First valid request posts successfully.
2. Identical retry returns the original result.
3. Reuse with a different payload is rejected.
4. Concurrent requests with the same key do not create duplicate financial entries.
5. A failed request does not leave a misleading completed idempotency record.
6. Idempotency records are scoped to the correct tenant and user context.
7. Retried requests do not duplicate interest or ledger entries.

Apply these tests especially to sales, payments and settlements.

## 16. Concurrency Tests

Test concurrent operations affecting the same customer account.

Examples:

- Two simultaneous payments.
- Two simultaneous sale postings.
- A payment and sale at the same time.
- Payment and auto settlement on the same date.
- Duplicate interest calculation requests.
- Concurrent Owner correction attempts.
- Concurrent settlement attempts.

Use database transactions and row locking where required.

Verify:

- No lost updates.
- No duplicate financial postings.
- No negative or inconsistent balances caused by race conditions.
- No duplicate interest for the same period.
- Final ledger state is consistent.

## 17. Database Integrity Tests

Verify:

- Foreign keys work.
- Unique transaction numbers work within a shop.
- User-to-shop relationship constraints are enforced.
- Invalid tenant relationships are rejected.
- Financial records maintain valid customer and shop relationships.
- Audit history is preserved.
- Database rollback removes partial financial operations.
- Decimal precision is sufficient for monetary values.
- Required indexes support tenant-scoped queries.
- Ordinary application workflows cannot silently alter audit history.

## 18. Subscription Tests

Verify:

- Free Trial is created according to approved configuration.
- Trial duration is configurable.
- Monthly and annual subscription periods are handled correctly.
- Subscription belongs to the shop, not an individual user.
- All shop users share the shop's subscription status.
- Expiry is enforced.
- Three-day grace period is enforced.
- Read-only access begins after the grace period.
- Financial writes are blocked when subscription is read-only.
- Owner can still export shop data after expiry.
- Subscription restrictions cannot be bypassed through direct API requests.

## 19. Backup and Recovery Tests

Verify:

- Automated database backup runs successfully.
- Backup files are encrypted.
- Off-site backup storage is accessible only to authorized systems.
- Restore procedure works in a separate test environment.
- Restored financial records remain consistent.
- Audit history is retained.
- Recovery procedures are documented and periodically tested.

## 20. Test Data and Factories

Use factories for:

- Users.
- Shops.
- Customers.
- Sales.
- Payments.
- Ledger entries.
- Interest policies.
- Interest calculation records.
- Settlements.
- Subscription records.
- Audit events.

Factories must create valid tenant relationships and must not bypass business invariants.

Financial test fixtures should include realistic amounts, dates, partial payments, advance balances and multiple sales.

## 21. Continuous Integration

The CI pipeline should run:

1. Dependency installation.
2. Code formatting checks.
3. Static analysis, if configured.
4. Database migrations.
5. Unit tests.
6. Feature tests.
7. Integration tests.
8. Security-focused tests.
9. Relevant API tests.
10. Browser tests where configured.

Database-sensitive integration tests must use MySQL 8.4. Tests that manage the schema through Laravel's `DatabaseMigrations` trait must run in a separate PHPUnit process from tests using `RefreshDatabase` when their migration teardown would otherwise invalidate the shared in-process migration state. Keep these tests in a dedicated PHPUnit group and invoke that group in a separate CI test command; run the remaining suite in a fresh command/process. Do not remove or skip the MySQL test to work around migration-state conflicts.

Critical failures must block deployment.

Tests must run against the intended PHP and MySQL versions.

## 22. Definition of Done

A feature is complete only when:

- Relevant automated tests exist.
- Tests pass.
- Tenant isolation is verified.
- Role authorization is verified.
- Financial calculations are tested where applicable.
- Database integrity is preserved.
- Audit history is verified for sensitive operations.
- Error and failure scenarios are covered.
- API behavior is tested where applicable.
- Documentation is updated.
- Test execution results are recorded accurately.

## 23. Release Testing

Before production release:

- Run the complete automated test suite.
- Verify migrations on a production-like MySQL database.
- Test backup and restore.
- Test tenant isolation.
- Test Owner, Manager and Staff permissions.
- Test financial posting.
- Test multiple same-day payments.
- Test consolidated outstanding calculations.
- Test interest policy behavior.
- Test settlement behavior.
- Test Owner transaction corrections.
- Test ledger printing and exports.
- Test Hindi and English UI.
- Test subscription expiry and read-only behavior.
- Review application logs and error reporting.

## 24. Testing Status

Testing status must be recorded in `docs/PROJECT-STATUS.md`.

The status must distinguish:

- Tests planned.
- Tests implemented.
- Tests executed.
- Tests passed.
- Tests failed.
- Known gaps.

Do not claim a feature is tested unless the relevant test suite has actually been executed successfully.


---

## 25. Approved Phase 5 Financial Test Requirements (2026-11-25)

ADR-0012 defines expected behavior. Add deterministic unit, feature and MySQL 8.4 integration coverage for:

### Sale and automatic payment
- Unpaid Sale increases outstanding principal.
- Paid Sale creates exactly one equal linked automatic Payment atomically.
- Automatic payment failure rolls back the Sale.
- Paid Sale remains visible as Already Paid but contributes zero net outstanding.
- Sale-day automatic payment does not trigger interest settlement for older balances.
- Duplicate Sale submission cannot duplicate Sale or automatic Payment.

### Normal payment and FIFO
- Interest is calculated and persisted through payment effective date before normal payment application.
- Interest status Settled is independent of Payment status.
- FIFO applies payment to oldest eligible unpaid Sale/component first, then subsequent components.
- A single consolidated Payment can have multiple internal allocations.
- Original Sale amounts remain unchanged.
- Fully Paid and sale-day Paid Sales are excluded from later FIFO.
- Principal First and Interest First are tested independently.
- Payment cannot exceed principal plus posted unpaid interest; excess is rejected.
- Two concurrent payments cannot overdraw the same customer payable.
- Same-date subsequent payments do not duplicate interest.

### Interest policy and settlement
- Shop rate default 0%; customer rate override and fallback.
- Shop grace period default 30 days; Sale Date Day 0; exact first eligible day.
- Start-inclusive/end-exclusive adjacent intervals do not double-count.
- Actual Days / 30 formula and HALF_UP 2-decimal posted result.
- Shop capitalization default and customer override/inheritance.
- Effective-dated rate/capitalization changes do not rewrite prior postings.
- Two annual Owner-configured settlement dates; idempotent repeated execution.
- Normal Payment precedes annual Auto Settlement on the same date.
- Paid-sale automatic Payment is not an interest event.

### Revisions, reversals and integrity
- Sale lock after later Payment or annual Auto Settlement.
- Sale revision stores before/after, actor, timestamp and reason.
- Payment reversal reverses original allocations without deleting them.
- Tenant-crossing allocation/payment/sale references are rejected.
- Every failure rolls back financial, allocation, projection and audit writes together.
- Ledger/projection rebuild reconciles against immutable source postings.

MySQL 8.4 is mandatory for row locks, concurrent posting, foreign-key and tenant-integrity tests. SQLite-only results are not sufficient evidence for these behaviors. Run tests only against an independently isolated test database; never point destructive test setup at a development database.


### 25.1 Interest convention tests pending Product Owner confirmation

Tests for Actual Days / 30 and two-decimal posted interest are required. Tests asserting HALF_UP rounding, [start inclusive, end exclusive) date boundaries or Sale Date Day 0 must be finalized only after the Product Owner confirms those conventions. Until then, retain them as candidate test cases, not accepted expected outcomes.

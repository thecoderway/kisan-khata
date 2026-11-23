
# Kisan Khata — Testing Strategy

**Document Version:** 1.0  
**Testing Framework:** Pest or PHPUnit (follow the existing Laravel project)  
**Primary Database:** MySQL

---

## 1. Purpose

This document defines the testing strategy for Kisan Khata.

Kisan Khata handles customer information and financial transactions. Testing must protect financial correctness, tenant isolation, data integrity and application security.

A feature is not complete merely because it works in a browser.

## 2. Testing Principles

1. Test business behavior, not only implementation details.
2. Test financial calculations with exact expected results.
3. Test tenant isolation for every tenant-owned resource.
4. Test authorization independently of UI visibility.
5. Test failure scenarios and boundary conditions.
6. Test concurrent financial operations.
7. Use realistic database constraints in integration tests.
8. Never use floating-point assertions for authoritative money calculations.
9. Keep tests deterministic.
10. Do not silently approve unresolved business rules through test expectations.

## 3. Testing Layers

### 3.1 Unit Tests

Test isolated business logic.

Examples:

- Amount validation.
- Decimal arithmetic helpers.
- Interest calculation components.
- Date-range calculations.
- Payment allocation strategies.
- Ledger balance calculations.
- Policy version selection.

Unit tests should not require HTTP requests.

### 3.2 Feature Tests

Test complete application behavior.

Examples:

- Customer creation.
- Credit sale posting.
- Payment posting.
- Ledger viewing.
- Interest policy management.
- Settlement creation.
- File uploads.
- Permission enforcement.

### 3.3 Integration Tests

Test interactions between application components.

Examples:

- Payment posting and ledger entries.
- Sale posting and account balance projections.
- Interest calculation and interest posting.
- Settlement and payment allocation.
- Database transaction rollback.
- Idempotency handling.

### 3.4 Security Tests

Test unauthorized access and tenant isolation.

Examples:

- Cross-tenant record access.
- Unauthorized financial posting.
- Private file access.
- Privilege escalation.
- Invalid shop switching.

### 3.5 Browser Tests

Use browser automation only where it provides meaningful coverage.

Examples:

- Login workflow.
- Shop selection.
- Customer creation.
- Credit sale form.
- Payment form.
- Ledger filters.
- Print layout.

Do not make browser tests the only validation of accounting logic.

## 4. Test Environment

Requirements:

- Dedicated testing database.
- Test-specific environment variables.
- No production credentials.
- No production customer data.
- Predictable test data.
- Database migrations available.
- Reliable cleanup between tests.

Use the actual MySQL version intended for deployment when testing database-specific behavior.

SQLite may be useful for some isolated tests but must not replace MySQL integration tests for financial and tenant-sensitive behavior.

## 5. Customer Tests

Verify:

- Customer can be created by an authorized shop user.
- Required fields are validated.
- Mobile number validation works according to approved rules.
- Hindi customer names are stored correctly.
- English customer names are stored correctly.
- Search supports Hindi and English input.
- Customer belongs to the correct shop.
- Customer cannot be accessed by another shop.
- Unauthorized users cannot modify customers.

## 6. Credit Sale Tests

Verify:

- A valid draft sale can be created.
- A draft can be edited according to permissions.
- A posted sale creates the expected financial transaction.
- A posted sale creates the expected ledger entries.
- Sale amount must be positive.
- Customer must belong to the current shop.
- Duplicate posting is prevented.
- Unauthorized posting is rejected.
- Posted sale cannot be silently modified.
- Reversal preserves original history.
- Failed posting rolls back all related database changes.

## 7. Payment Tests

Verify:

- Valid payment can be created.
- Payment amount must be positive.
- Payment belongs to the correct customer and shop.
- Payment posting creates the expected financial records.
- Allocation total does not exceed the payment amount.
- Allocation cannot target another customer's transaction.
- Allocation cannot target another shop's transaction.
- Duplicate payment posting is prevented.
- Payment reversal preserves history.
- Failed posting rolls back all related changes.
- Concurrent payment requests do not corrupt account balances.

## 8. Payment Allocation Tests

Do not implement expected allocation results until the product owner approves the allocation policy.

After approval, test:

- Interest-first allocation, if selected.
- Principal-first allocation, if selected.
- Oldest-invoice-first allocation, if selected.
- Manual allocation, if selected.
- Partial payment.
- Exact settlement.
- Excess payment.
- Zero remaining allocation.
- Reversal and reallocation.
- Rounding.
- Multiple outstanding invoices.

The tests must prove that total allocated amount equals the amount intended for allocation, subject to the approved handling of unapplied funds.

## 9. Interest Tests

Interest calculations are financially sensitive.

Create tests for:

- Interest-free period start.
- Interest-free period end.
- First eligible interest date.
- Calendar month boundary.
- Month-end dates.
- February.
- Leap year.
- Partial month.
- Payment during an interest period.
- Rate change.
- Policy effective date.
- Policy version transition.
- Rounding.
- Repeated calculation.
- Historical calculation reproducibility.
- Reversal of posted interest.

Do not create arbitrary expected interest amounts while the interest policy remains unresolved.

Use approved policy examples with independently verified expected results.

## 10. Settlement Tests

After settlement rules are approved, test:

- Full payment of outstanding amount.
- Partial payment.
- Remaining principal.
- Remaining interest.
- Interest crystallization.
- Carry-forward treatment.
- New settlement period.
- Settlement effective date.
- Settlement reversal.
- Duplicate settlement request.
- Concurrent settlement attempts.

Verify that settlement does not silently erase previous financial history.

## 11. Ledger Tests

Verify:

- Opening balance is correct.
- Sales increase receivable according to the selected ledger convention.
- Payments reduce receivable according to recorded allocations.
- Interest entries are included.
- Reversals offset original entries.
- Settlement entries are included.
- Running balance is deterministic.
- Date filters work.
- Same-date entries have stable ordering.
- Closing balance matches the sum of included entries.
- Principal and interest remain separately identifiable.

Test ledger calculations against independently calculated expected values.

## 12. Multi-Tenancy Tests

Create at least two shops and multiple users.

Test:

- Shop A cannot list Shop B customers.
- Shop A cannot view Shop B sales.
- Shop A cannot view Shop B payments.
- Shop A cannot view Shop B ledger.
- Shop A cannot modify Shop B records.
- Shop A cannot download Shop B attachments.
- Shop A cannot post transactions for Shop B customers.
- Shop A cannot allocate payments to Shop B transactions.
- Shop A cannot access Shop B interest policies.
- Shop A cannot access Shop B settlements.
- Unauthorized shop switching is rejected.
- Background jobs use the correct tenant context.
- Tenant-specific caches do not leak data.

Every new tenant-owned module must add corresponding isolation tests.

## 13. Authorization Tests

Test permissions independently.

Examples:

- Read-only users cannot post sales.
- Sales employees cannot configure interest policies unless explicitly permitted.
- Unauthorized users cannot reverse payments.
- Unauthorized users cannot post settlements.
- Only authorized users can manage employees.
- Subscription permissions are enforced.
- Platform administrators do not automatically receive unrestricted tenant financial access.

## 14. File Upload Tests

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
- Deletion or archival behavior according to retention policy.

## 15. Idempotency Tests

Test:

1. First valid request posts successfully.
2. Identical retry returns the original result.
3. Reuse with a different payload is rejected.
4. Concurrent requests with the same key do not create duplicate financial entries.
5. A failed request does not leave a misleading completed idempotency record.
6. Idempotency records are scoped to the correct tenant and user context.

## 16. Concurrency Tests

Test concurrent operations affecting the same customer account.

Examples:

- Two simultaneous payments.
- Two simultaneous sale postings.
- Payment and settlement at the same time.
- Duplicate interest posting.
- Concurrent reversal attempts.

Use database transactions and row locking where required.

Verify that final ledger state is consistent.

## 17. Database Integrity Tests

Verify:

- Foreign keys work.
- Unique transaction numbers work within a shop.
- Duplicate memberships are rejected.
- Invalid tenant relationships are rejected.
- Posted ledger entries cannot be changed through ordinary application workflows.
- Database rollback removes partial financial operations.

## 18. Test Data and Factories

Use factories for:

- Users
- Shops
- Memberships
- Customers
- Sales
- Payments
- Financial transactions
- Ledger entries
- Interest policies
- Settlements

Factories must create valid tenant relationships.

Avoid unrealistic fixtures that bypass business invariants.

## 19. Continuous Integration

A CI pipeline should run:

1. Dependency installation.
2. Code formatting checks.
3. Static analysis, if configured.
4. Database migrations.
5. Unit tests.
6. Feature tests.
7. Security-focused tests.
8. Relevant integration tests.

Critical failures must block deployment.

## 20. Definition of Done

A feature is complete only when:

- Relevant tests exist.
- Tests pass.
- Tenant isolation is verified.
- Authorization is verified.
- Financial calculations are tested.
- Database integrity is preserved.
- Error scenarios are covered.
- Documentation is updated.

## 21. Release Testing

Before production release:

- Run the complete automated suite.
- Verify migrations on a production-like database.
- Test backup and restore.
- Test tenant isolation.
- Test financial posting.
- Test payment allocation.
- Test interest policy behavior.
- Test settlement behavior.
- Test ledger printing.
- Test Hindi and English UI.
- Review application logs.

## 22. Testing Status

Testing status must be recorded in docs/PROJECT-STATUS.md.

Do not claim a feature is tested unless the relevant test suite has actually been executed successfully.


---
name: testing
description: Design, implement and execute automated tests for Kisan Khata Laravel modules, financial operations, tenant isolation and API workflows.
---

# Testing Skill

## Objective

Ensure that every feature behaves correctly under normal, invalid and concurrent conditions where applicable.

## Required Reading

- docs/09-testing-strategy.md
- docs/02-business-rules.md
- docs/03-accounting-engine.md
- .claude/agents/test-reviewer.md

## Workflow

### Step 1: Understand Expected Behavior

Identify:

- Business rule.
- User role.
- Tenant.
- Input.
- Expected output.
- Error conditions.

### Step 2: Inspect Existing Tests

Check for existing:

- Unit tests.
- Feature tests.
- Factories.
- Fixtures.
- Test helpers.

Avoid duplicate or contradictory tests.

### Step 3: Select Test Type

Use:

- Unit tests for isolated calculations.
- Feature tests for HTTP behavior.
- Integration tests for database workflows.
- Browser tests for critical user journeys.

### Step 4: Create Test Data

Use isolated test data.

Include multiple shops when testing tenant behavior.

Never use real customer data.

### Step 5: Test Success Cases

Verify the intended business behavior.

### Step 6: Test Failure Cases

Verify:

- Invalid input.
- Unauthorized actions.
- Cross-tenant access.
- Invalid transaction state.
- Duplicate requests.

### Step 7: Test Financial Boundaries

Verify:

- Decimal precision.
- Rounding.
- Date boundaries.
- Ledger reconciliation.
- Reversal behavior.

### Step 8: Run Tests

Execute the relevant test suite.

Inspect actual output.

Do not claim a test passed unless it was executed successfully.

### Step 9: Diagnose Failures

Identify the root cause.

Do not modify correct production logic merely to satisfy a weak assertion.

### Step 10: Report

Provide:

- Tests created.
- Tests executed.
- Passed tests.
- Failed tests.
- Skipped tests.
- Remaining risks.

## Completion Criteria

The feature must have meaningful automated coverage and no unexplained test failures.

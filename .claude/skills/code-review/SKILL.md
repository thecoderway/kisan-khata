
---
name: code-review
description: Perform structured code reviews of Kisan Khata changes for correctness, maintainability, financial integrity, tenant security and regression risks.
---

# Code Review Skill

## Objective

Review code changes before they are considered complete.

## Required Reading

- CLAUDE.md
- .claude/rules/
- docs/09-testing-strategy.md
- Relevant module documentation.

## Workflow

### Step 1: Understand the Change

Identify:

- Requirement.
- Intended behavior.
- Changed files.
- Database changes.
- Affected modules.

### Step 2: Review Correctness

Check:

- Business logic.
- Validation.
- Error handling.
- State transitions.
- Edge cases.

### Step 3: Review Financial Integrity

Check:

- Decimal arithmetic.
- Immutable posting.
- Principal and interest separation.
- Payment allocations.
- Reversal behavior.
- Ledger reconciliation.

### Step 4: Review Tenant Security

Check:

- Tenant context.
- Ownership validation.
- Policies.
- Query scoping.
- File access.
- Cache isolation.

### Step 5: Review Maintainability

Check:

- Naming.
- Responsibility boundaries.
- Duplication.
- Unnecessary dependencies.
- Complex methods.
- Hidden side effects.

### Step 6: Review Database Impact

Check:

- Migrations.
- Foreign keys.
- Indexes.
- Constraints.
- Data preservation.
- Query performance.

### Step 7: Review Tests

Check:

- Relevant test coverage.
- Negative cases.
- Boundary cases.
- Financial tests.
- Tenant isolation tests.

### Step 8: Run Verification

Run relevant tests and inspect the actual results.

Do not report successful execution without evidence.

## Severity Levels

CRITICAL:
Major financial corruption, tenant compromise or severe security defect.

HIGH:
Serious functional, authorization or data integrity issue.

MEDIUM:
Meaningful correctness, performance or maintainability problem.

LOW:
Minor improvement.

## Required Review Output

### Summary

Describe the change.

### Findings

For each finding:

- Severity.
- File.
- Location.
- Problem.
- Impact.
- Recommended fix.

### Test Assessment

List tests reviewed and executed.

### Remaining Risks

Identify unresolved risks.

### Verdict

Choose:

- APPROVED
- APPROVED WITH CHANGES
- BLOCKED

## Review Principles

- Be specific.
- Explain the impact.
- Do not invent defects.
- Do not approve unverified behavior.
- Prioritize correctness and security.

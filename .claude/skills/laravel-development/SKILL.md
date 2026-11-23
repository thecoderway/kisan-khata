
---
name: laravel-development
description: Implement and maintain Kisan Khata features using the existing Laravel project conventions, validation, services, policies, migrations and tests.
---

# Laravel Development Skill

## Objective

Implement production-quality Laravel functionality without violating Kisan Khata architecture.

## Required Reading

- CLAUDE.md
- .claude/rules/laravel-conventions.md
- .claude/rules/tenant-security.md
- docs/06-security.md
- docs/09-testing-strategy.md

## Workflow

### Step 1: Inspect the Environment

Check:

- PHP version.
- Laravel version.
- Composer dependencies.
- Database configuration.
- Existing frontend stack.
- Existing tests.

Do not upgrade dependencies without approval.

### Step 2: Understand the Feature

Identify:

- User story.
- Permissions.
- Tenant ownership.
- Input fields.
- Expected result.
- Error cases.

### Step 3: Inspect Existing Code

Find similar functionality.

Follow established conventions unless they conflict with approved architecture.

### Step 4: Plan Changes

Identify:

- Migrations.
- Models.
- Relationships.
- Form Requests.
- Policies.
- Services or Actions.
- Controllers.
- Routes.
- Views or API Resources.
- Tests.

### Step 5: Implement Database Changes

Use migrations.

Add appropriate:

- Foreign keys.
- Unique constraints.
- Indexes.
- DECIMAL columns.

Never use floating-point database columns for money.

### Step 6: Implement Business Logic

Keep business logic outside controllers.

Validate tenant ownership inside critical service boundaries.

Use database transactions for multi-step financial workflows.

### Step 7: Implement Authorization

Use Policies or equivalent server-side authorization.

Never rely on frontend visibility.

### Step 8: Implement User Interface

Follow approved UI conventions.

Use localized labels.

Preserve Hindi and English support.

### Step 9: Write Tests

Include:

- Success cases.
- Validation failures.
- Authorization failures.
- Tenant isolation.
- Edge cases.

### Step 10: Verify

Run:

- Relevant automated tests.
- Code formatting.
- Static analysis if configured.
- Migration checks.

### Step 11: Report

Summarize:

- Files changed.
- Database changes.
- Tests executed.
- Results.
- Remaining risks.

## Completion Criteria

A feature is complete only when its implementation, authorization, validation, tests and documentation are verified.

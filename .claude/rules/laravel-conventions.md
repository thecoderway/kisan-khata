
# Laravel Development Conventions

These conventions apply to all Laravel development in Kisan Khata.

## 1. Existing Project First

Before writing code:

- Inspect the Laravel version.
- Inspect the PHP version.
- Inspect composer.json.
- Inspect package.json.
- Inspect the existing directory structure.
- Inspect installed packages.
- Inspect existing coding conventions.

Never assume the project uses the latest Laravel version.

Do not upgrade dependencies without approval.

## 2. Architecture

Use a Modular Monolith.

Keep modules logically separated.

Suggested business modules:

- Authentication
- Tenancy
- Customers
- Sales
- Payments
- Accounting
- Interest
- Settlements
- Reports
- Subscriptions
- Audit

Do not introduce microservices without explicit approval.

## 3. Thin Controllers

Controllers should handle HTTP concerns.

Controllers may:

- Validate requests through Form Requests.
- Invoke application services.
- Return views or API responses.

Controllers must not contain complex accounting calculations or large business workflows.

## 4. Form Requests

Use dedicated Form Request classes for complex validation.

Validate:

- Required fields.
- Data types.
- Lengths.
- Numeric ranges.
- Date formats.
- Uploaded files.
- Tenant ownership.
- Business constraints.

Never rely only on frontend validation.

## 5. Services and Actions

Keep business workflows in dedicated services or action classes.

Use clear, focused responsibilities.

Avoid enormous service classes that contain unrelated operations.

Do not create unnecessary abstraction layers for trivial operations.

## 6. Models

Use Eloquent relationships.

Define appropriate:

- Casts.
- Relationships.
- Scopes.
- Attribute behavior.

Do not put complex multi-step accounting workflows inside model event hooks.

Financial posting must remain explicit and testable.

## 7. Database

Use migrations for schema changes.

Use foreign keys and indexes appropriately.

Use DECIMAL for monetary fields.

Review query performance for large ledgers.

Do not modify production database structure manually.

## 8. Transactions

Use DB transactions for multi-step financial operations.

Use row locks when required.

Handle concurrent requests safely.

Do not perform external network calls inside a database transaction unless explicitly justified.

## 9. API

Use versioned API routes.

Use Laravel API Resources or equivalent response transformation.

Do not expose raw internal model structures without review.

Use consistent response and error formats.

## 10. Frontend

Follow the existing approved frontend stack.

The proposed initial stack is:

- Blade.
- Bootstrap 5.
- Alpine.js.

Do not introduce another frontend framework without approval.

Keep interface labels translatable.

## 11. Naming

Follow Laravel conventions.

Use:

- Singular model names.
- Plural table names.
- Descriptive service names.
- Clear method names.
- Explicit boolean names.

Avoid cryptic abbreviations.

## 12. Dependencies

Before adding a dependency:

1. Check whether Laravel or PHP already provides the functionality.
2. Evaluate maintenance status.
3. Review security and compatibility.
4. Explain why the dependency is required.

Do not install packages without justification.

## 13. Error Handling

Use appropriate Laravel exceptions and validation responses.

Do not suppress unexpected exceptions.

Never expose stack traces in production responses.

Log useful diagnostic information without exposing secrets.

## 14. Testing

Write automated tests for new functionality.

Follow docs/09-testing-strategy.md.

Do not mark a feature complete merely because the page loads.

## 15. Code Quality

Keep code readable and maintainable.

Avoid:

- Duplicate business logic.
- Unnecessary inheritance.
- Giant controllers.
- Hidden side effects.
- Unexplained magic numbers.
- Unused dependencies.
- Unnecessary refactoring.

## 16. Documentation

Update relevant documentation when behavior or architecture changes.

Follow the Definition of Done in CLAUDE.md.

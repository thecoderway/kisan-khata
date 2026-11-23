
# ADR-0001 — Modular Monolith Architecture

**Status:** PROPOSED  
**Date:** 2026-11-22

## Context

Kisan Khata is a multi-tenant SaaS application that requires several connected business modules.

These include:

- Authentication
- Shop management
- Customers
- Credit sales
- Payments
- Accounting ledger
- Interest calculations
- Crop-season settlements
- Reports
- Subscription management

The modules share financial data and business rules.

The initial development team and infrastructure budget should remain manageable.

## Decision Drivers

- Minimum initial infrastructure complexity.
- Maintainable Laravel codebase.
- Clear separation of business modules.
- Reliable financial transactions.
- Easy deployment.
- Future Android API support.
- Ability to scale when required.

## Options Considered

### Option A — Traditional Monolithic Application

All application logic is implemented without meaningful module boundaries.

Advantages:

- Simple initial setup.
- Straightforward deployment.

Disadvantages:

- Business logic can become tightly coupled.
- Difficult to maintain as modules grow.
- Increased risk of duplicated financial logic.

### Option B — Modular Monolith

A single Laravel application with clearly separated business modules and service boundaries.

Advantages:

- Single deployment.
- Shared database transactions.
- Clear module responsibilities.
- Lower infrastructure complexity.
- Easier testing and maintenance.

Disadvantages:

- Requires discipline to preserve module boundaries.
- Large codebases still require architectural reviews.

### Option C — Microservices

Separate services for customers, payments, accounting and other modules.

Advantages:

- Independent deployment.
- Independent scaling.
- Service-level isolation.

Disadvantages:

- Higher infrastructure cost.
- Distributed transaction complexity.
- More operational overhead.
- More difficult financial consistency.

## Decision

Propose a Modular Monolith architecture using Laravel.

Keep business modules logically separated.

Use dedicated services and domain classes for financial operations.

Do not introduce microservices in the initial release.

## Consequences

### Positive

- Lower operational complexity.
- Easier local development.
- Shared database transactions.
- Clear module responsibilities.
- Easier future refactoring.

### Negative

- Requires strict code organization.
- Modules share deployment lifecycle.
- Poor module boundaries can still create coupling.

## Security Impact

Each module must enforce authorization and tenant ownership.

Module separation does not replace security controls.

## Financial Data Impact

Financial operations should use shared database transactions where appropriate.

Interest calculation, payment allocation and ledger posting must have explicit service boundaries.

## Future Reconsideration

Reconsider the architecture if:

- Individual modules need independent deployment.
- Specific modules require substantially different scaling.
- Operational requirements justify distributed services.
- The application reaches a scale where a monolith becomes a verified bottleneck.

Any change requires a separate ADR.

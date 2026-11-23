
# ADR-0003 — Web-First Development with API-Ready Architecture

**Status:** PROPOSED  
**Date:** 2026-11-22

## Context

Kisan Khata will initially launch as a responsive web application.

A native Android application is planned for a later phase.

The initial development budget and complexity should remain controlled.

The backend should support both web and mobile clients without duplicating financial logic.

## Decision Drivers

- Faster initial release.
- Lower initial development cost.
- Responsive mobile web experience.
- Future Android support.
- Shared accounting logic.
- Stable API contracts.
- Maintainable backend architecture.

## Options Considered

### Option A — Web-Only Architecture

Build a Laravel application specifically for browser access.

Advantages:

- Simple initial development.
- Minimal API work.

Disadvantages:

- Future Android integration may require architectural changes.
- Business logic may become tightly coupled to web controllers.

### Option B — Web-First, API-Ready Architecture

Build the web application first while keeping business logic independent of presentation.

Introduce versioned APIs for mobile and other clients.

Advantages:

- Practical initial development.
- Shared backend services.
- Future Android integration.
- Avoids duplicate financial calculations.

Disadvantages:

- Requires discipline in service boundaries.
- API design and testing require additional work.

### Option C — API-First with Separate Frontend

Build a fully separate API backend and frontend application from the beginning.

Advantages:

- Strong client/server separation.
- Independent frontend development.

Disadvantages:

- Higher initial complexity.
- Additional frontend infrastructure.
- More development and deployment work.

## Decision

Propose Option B.

The initial product will use Laravel with a responsive web interface.

Business logic must remain in backend services.

Versioned REST API routes will be introduced according to implementation needs.

The future Android application will use the same backend services and financial rules.

## Consequences

### Positive

- Faster initial web delivery.
- Shared business logic.
- Easier Android integration.
- Centralized financial calculations.

### Negative

- Service boundaries must be maintained.
- API contracts require versioning and testing.
- Authentication flows may differ between browser and Android.

## Security Impact

Web and API clients must use appropriate authentication mechanisms.

Both clients must enforce the same tenant isolation and authorization policies.

The API must not expose unrestricted Eloquent model data.

## Financial Data Impact

Interest calculations, payment allocation, settlement processing and ledger generation must remain backend responsibilities.

Android must never be treated as the authoritative financial calculation engine.

## Future Reconsideration

Reconsider the frontend architecture if:

- A separate web frontend becomes necessary.
- Mobile requirements become substantially different.
- API traffic or scaling requirements justify independent services.

Any change requires a separate ADR.

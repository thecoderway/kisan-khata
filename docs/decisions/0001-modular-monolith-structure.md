# ADR 0001: Modular Monolith Structure

- Status: Accepted
- Date: 2026-11-23

## Context

Kisan Khata is a Laravel-based, multi-tenant SaaS application.
The codebase must support clear business-domain boundaries while
retaining a single deployable application and shared database.

## Decision

Use a Modular Monolith organized under `app/Modules`.

The initial domain modules are:

- Auth
- Shops
- Customers
- Sales
- Payments
- Interest
- Subscriptions

Each module follows four logical layers:

- `Domain`: Domain concepts and business rules.
- `Application`: Use cases and application workflows.
- `Infrastructure`: Persistence and external integration details.
- `Presentation`: HTTP-facing adapters and module interfaces.

Composer maps `App\Modules\` to `app/Modules/`.

## Architectural constraints

- Modules remain part of one Laravel application and deployment.
- Modules must not introduce independent databases or services by default.
- Cross-module interactions should use explicit application-level
  interfaces or contracts rather than reaching into another module's
  internal implementation.
- Tenant isolation must be enforced server-side. Client-provided
  `shop_id` values must not establish tenant context.
- Financial calculations and persistence must use transactional,
  decimal-safe approaches.
- This ADR establishes structure only; it does not implement business
  rules, tenant middleware, database schema, or module services.

## Consequences

The codebase has explicit domain boundaries and can evolve incrementally.
The additional structure requires discipline to prevent unnecessary
cross-module coupling and empty abstraction layers.


# ADR-0002 — Shared Database Multi-Tenancy

**Status:** PROPOSED  
**Date:** 2026-11-22

## Context

Kisan Khata will serve multiple independent merchants.

Each merchant must have isolated customer and financial data.

The platform should remain affordable and operationally simple during the initial SaaS launch.

## Decision Drivers

- Low initial hosting complexity.
- Cost-effective operation.
- Simple database management.
- Strong tenant isolation.
- Efficient reporting.
- Manageable backups.
- Ability to support many shops.

## Options Considered

### Option A — Separate Database Per Shop

Every shop receives its own database.

Advantages:

- Strong database-level separation.
- Independent backups and restoration.
- Easier tenant-specific database migration in some cases.

Disadvantages:

- More database administration.
- More connection management.
- Higher operational complexity.
- More difficult cross-tenant platform reporting.

### Option B — Shared Database with shop_id

All shops share the same database.

Tenant-owned records contain shop_id.

Advantages:

- Lower initial infrastructure complexity.
- Easier centralized migrations.
- Efficient platform management.
- Straightforward subscription management.

Disadvantages:

- Requires strict tenant isolation.
- A missing tenant filter can expose data.
- Tenant-aware indexing and query design are essential.

### Option C — Separate Database for Larger Tenants

Use a shared database initially and support dedicated databases for selected large tenants later.

Advantages:

- Flexible future isolation.
- Potentially useful for enterprise customers.

Disadvantages:

- Additional architectural complexity.
- Requires tenant routing and migration strategies.

## Decision

Propose Option B for the initial release.

Use a shared MySQL database with shop_id on tenant-owned records.

Use centralized tenant context, authorization policies, ownership validation and automated isolation tests.

## Required Safeguards

1. Never trust shop_id from the browser.
2. Resolve tenant context through authenticated membership.
3. Scope tenant-owned queries.
4. Validate related record ownership.
5. Protect private uploaded files.
6. Isolate tenant caches.
7. Establish tenant context in background jobs.
8. Test cross-tenant access.
9. Use database constraints where practical.

## Consequences

### Positive

- Lower initial infrastructure complexity.
- Simpler deployments.
- Centralized schema management.
- Easier subscription administration.

### Negative

- Tenant isolation becomes a critical application responsibility.
- Shared infrastructure can create noisy-neighbor concerns.
- Individual tenant restoration requires additional planning.

## Security Impact

This decision creates a high-priority requirement for tenant isolation.

A missing authorization or ownership check can expose another merchant's records.

Security review is mandatory for tenant-sensitive features.

## Financial Data Impact

Financial transactions, customer accounts and ledgers must always belong to the same shop as their associated customer.

Cross-tenant financial references must be rejected.

## Future Reconsideration

Consider dedicated databases when:

- Enterprise customers require stronger isolation.
- Tenant-specific data volume becomes unusually large.
- Regulatory or contractual requirements demand separation.
- A tenant needs independent backup and restoration.

A migration to hybrid tenancy requires a separate ADR and migration plan.

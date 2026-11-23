# Tenant Security Rules

Kisan Khata uses shared MySQL multi-tenancy. Every tenant-owned record is scoped to shop_id.

## Tenant context
- One User = One Shop = One Role.
- Resolve shop context from authenticated user server-side.
- Never trust shop_id from request body, query, URL, hidden fields, frontend state or uploaded metadata.
- Every tenant-sensitive service must require valid tenant context.
- Jobs must explicitly establish and clear tenant context.

## Query and relationship isolation
Scope all tenant-owned queries. Validate every related record belongs to the same shop before linking. A valid ID is not proof of ownership. Kisan Khata has no sale-level payment allocation records; do not add allocation-target checks or an allocation model.

## Authorization
Use Policies/Gates for authentication, shop ownership, role and resource-state checks. Owner-only posted Sale/Payment correction must be enforced server-side. Manager/Staff cannot edit posted financial transactions.

## Other boundaries
Protect files and exports; isolate tenant cache keys; scope queues; serialize API responses safely. Platform support access is a separate audited boundary.

## Tests
Prove authorized access succeeds and cross-tenant direct IDs, relationships, files, exports, API and background jobs are rejected. Never bypass policies or use unscoped tenant queries to work around errors.

# Tenant Security Rules

These rules are mandatory throughout Kisan Khata.

## 1. Tenant Isolation

Kisan Khata uses a shared database multi-tenant architecture.

Every tenant-owned record must belong to the correct shop.

The tenant identifier is shop_id.

A shop must never access another shop's data.

## 2. Never Trust Client Tenant IDs

Never trust shop_id received from:

- Request body.
- Query parameters.
- URL parameters.
- Hidden form inputs.
- Frontend state.
- Uploaded metadata.

Resolve the current shop through the authenticated user's authorized membership.

## 3. Tenant Context

Use the centralized tenant context implementation.

Do not scatter direct session lookups throughout controllers and services.

Every tenant-sensitive operation must have a valid tenant context.

Background jobs and scheduled tasks must establish their tenant context explicitly.

## 4. Query Isolation

All tenant-owned queries must be scoped to the current shop.

Use centralized tenant-aware query patterns.

Global scopes may provide additional protection but must not be the only authorization mechanism.

Never disable tenant filtering merely to make a query work.

## 5. Relationship Ownership

Before connecting records, validate ownership.

Examples:

- Customer belongs to current shop.
- Sale belongs to current shop.
- Payment belongs to current shop.
- Payment allocation target belongs to the same shop and customer.
- Settlement belongs to current shop.
- Uploaded file belongs to an authorized parent record.

A valid database ID does not prove ownership.

## 6. Authorization

Use Laravel Policies and Gates.

Every protected action must validate:

1. Authentication.
2. Active shop membership.
3. Tenant ownership.
4. Required permission.
5. Resource state.

Never rely on hidden frontend buttons as access control.

## 7. File Security

All financial documents must use private storage.

Every download must validate tenant ownership and user authorization.

Never expose private bills or receipts using unrestricted public URLs.

## 8. Cache Isolation

Tenant-specific cache keys must contain the shop identifier.

Never share financial cache entries between shops.

Clear or refresh tenant-specific caches when changing the active shop.

## 9. Queue Isolation

Every tenant-specific job must explicitly identify its shop.

Validate the shop before processing.

Clear tenant context after job execution.

Never depend on the original HTTP session inside a queue worker.

## 10. Platform Administration

Platform administration and shop administration are separate security boundaries.

Platform administrators must not automatically receive unrestricted access to shop financial records.

Any exceptional support access must be authorized and audited.

## 11. Security Tests

Every tenant-owned feature must include tests proving:

- Authorized shop access succeeds.
- Unauthorized shop access fails.
- Direct URL access fails when unauthorized.
- Cross-tenant relationships are rejected.
- Cross-tenant file downloads are rejected.

## 12. Prohibited Shortcuts

Never:

- Accept arbitrary shop_id from a client.
- Load tenant-owned records without ownership validation.
- Assume UUIDs prevent unauthorized access.
- Expose unrestricted financial exports.
- Use unscoped queries in ordinary tenant workflows.
- Bypass Policies to fix authorization errors.

Refer to docs/05-multi-tenancy.md and docs/06-security.md.

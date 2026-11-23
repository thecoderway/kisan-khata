
# Kisan Khata — Multi-Tenancy Architecture

**Document Version:** 1.0  
**Architecture:** Shared Database, Shared Application  
**Tenant Identifier:** shop_id

---

## 1. Purpose

Kisan Khata is a multi-tenant SaaS application.

Multiple independent merchants will use the same application infrastructure.

Each merchant operates within an isolated shop environment.

One shop must never access another shop's:

- Customers
- Sales
- Payments
- Financial transactions
- Ledgers
- Interest policies
- Settlements
- Reports
- Uploaded documents
- Employees and permissions

Tenant isolation is a critical security requirement.

## 2. Selected Architecture

Use a shared database and shared application architecture.

All shops use the same MySQL database.

Tenant-owned records contain a shop_id column.

Conceptual structure:

Application
    |
    |---- Shop A
    |       |---- Customers
    |       |---- Sales
    |       |---- Payments
    |       |---- Ledger
    |
    |---- Shop B
    |       |---- Customers
    |       |---- Sales
    |       |---- Payments
    |       |---- Ledger
    |
    |---- Shop C
            |---- Customers
            |---- Sales
            |---- Payments
            |---- Ledger

Data is separated logically using tenant ownership and authorization.

## 3. Tenant Identification

The current shop must be resolved from the authenticated user's authorized shop membership.

Do not trust shop_id submitted by the browser.

Do not allow an ordinary user to switch to a shop unless they have an active membership in that shop.

Conceptual flow:

1. User authenticates.
2. Application loads authorized shop memberships.
3. User selects an active shop if multiple memberships exist.
4. Application validates membership.
5. Current shop context is established.
6. Requests execute within that context.

The selected shop identifier may be stored in the session, but membership must be revalidated.

## 4. Tenant Context

Create a centralized tenant context abstraction.

Suggested class:

`App\Support\Tenancy\CurrentShop`

Responsibilities:

- Resolve the current shop.
- Expose the current shop ID.
- Verify active membership.
- Prevent unauthorized context changes.
- Provide a consistent tenant reference to services.

Avoid scattered session lookups throughout the application.

## 5. Tenant-Owned Models

Examples:

- Customer
- Sale
- Payment
- FinancialTransaction
- LedgerEntry
- InterestPolicy
- Settlement
- Subscription

Each tenant-owned model must have a shop relationship.

Example:

```php
public function shop()
{
    return $this->belongsTo(Shop::class);
}
```

Customer-owned financial records must also validate customer ownership.

## 6. Query Isolation

All tenant-owned queries must be tenant-scoped.

Preferred approach:

- Centralized tenant-aware query scopes.
- Repository or service methods that require a tenant context.
- Authorization policies.
- Database constraints where practical.

Do not rely on a developer remembering to add `where('shop_id', ...)` to every query.

Global scopes may be used as defense in depth, but must not be treated as the only security boundary.

Background jobs, CLI commands and scheduled tasks must establish an explicit tenant context.

## 7. Relationship Validation

When creating a sale:

- Customer must belong to the current shop.
- Sale must use the current shop ID.
- Client-supplied customer IDs must be validated.

When creating a payment:

- Customer must belong to the current shop.
- Payment must use the current shop ID.
- Allocation targets must belong to the same shop and customer.

When creating a settlement:

- Customer must belong to the current shop.
- All financial references must belong to the same shop and customer.

A valid foreign key does not automatically guarantee tenant ownership.

## 8. Tenant Isolation Layers

Apply defense in depth:

### Layer 1: Authentication

Only authenticated users may access protected shop functionality.

### Layer 2: Membership

User must have active membership in the selected shop.

### Layer 3: Query Scoping

Tenant-owned queries must be scoped to the current shop.

### Layer 4: Authorization

Policies must verify access to the requested resource.

### Layer 5: Service Validation

Business services must verify ownership before financial operations.

### Layer 6: File Authorization

Uploaded files must be checked against their parent tenant-owned record.

### Layer 7: Database Integrity

Use composite constraints where practical to prevent cross-tenant relationships.

## 9. Shop Switching

If a user belongs to multiple shops:

1. Show authorized shops.
2. User selects a shop.
3. Validate active membership.
4. Update current context.
5. Clear or refresh tenant-specific cached data.
6. Ensure subsequent queries use the new context.

Do not preserve data from the previous shop in client-side state.

## 10. Cache Isolation

Every tenant-specific cache key must include shop identity.

Example:

`shop:{shop_id}:customer:{customer_id}`

Never use tenant-independent keys for tenant-specific financial data.

Invalidate related cache entries when underlying records change.

## 11. Queue and Background Jobs

Jobs must carry the required tenant identifier.

When a job executes:

1. Resolve the shop.
2. Validate shop existence and status.
3. Establish tenant context.
4. Execute the operation.
5. Clear tenant context after execution.

Never assume the web request's session exists in a background worker.

## 12. Storage Isolation

Use private storage.

Suggested directory structure:

```text
private/
    shops/
        {shop_id}/
            customers/
            sales/
            payments/
            settlements/
```

File access must be authorized using the related database record.

Never rely only on knowing a file path.

## 13. Subscription Isolation

Each shop has its own subscription.

Subscription status must be checked when accessing paid features.

Suspending one shop must not affect other shops.

Platform administration must be separated from shop-level permissions.

## 14. Platform Administration

Platform administrators may manage:

- Shop registration
- Subscription status
- Plan configuration
- Platform usage
- Support requests

Access to tenant financial data must be restricted and audited.

Do not provide unrestricted tenant data access by default.

## 15. Tenant Isolation Testing

Automated tests must verify:

- Shop A cannot view Shop B customers.
- Shop A cannot edit Shop B customers.
- Shop A cannot access Shop B sales.
- Shop A cannot access Shop B payments.
- Shop A cannot access Shop B ledger.
- Shop A cannot download Shop B files.
- Shop A cannot create a payment for Shop B customer.
- Shop A cannot allocate payment to Shop B transaction.
- Shop A cannot access Shop B settlement.
- Unauthorized shop switching is rejected.
- Background jobs use the correct shop context.

Test both successful access and denied access.

## 16. Operational Rules

Never disable tenant scopes to make a feature work without reviewing the security impact.

Any cross-tenant reporting must be explicitly authorized and restricted to platform-level operations.

Any change to tenant resolution must receive a security review.

## 17. Acceptance Criteria

Multi-tenancy is complete only when:

- Tenant context is centralized.
- Every tenant-owned record has ownership.
- Queries are isolated.
- Services validate ownership.
- Policies enforce authorization.
- Files are isolated.
- Caches are isolated.
- Jobs establish tenant context.
- Automated cross-tenant tests pass.

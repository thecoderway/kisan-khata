# Kisan Khata — Multi-Tenancy Architecture

**Document Version:** 2.1  
**Status:** Approved  
**Architecture:** Shared Database, Shared Application  
**Database:** MySQL 8.4  
**Tenant Identifier:** `shop_id`

---

## 1. Purpose

Kisan Khata is a multi-tenant SaaS application.

Multiple independent shops use the same application infrastructure while maintaining strict logical data isolation.

Each shop operates as an independent tenant.

A shop must never access another shop's:

- Customers
- Sales
- Payments
- Financial transactions
- Ledgers
- Interest policies
- Settlements
- Reports
- Uploaded documents
- Employees and user accounts
- Subscription information

Tenant isolation is a critical security requirement.

## 2. Selected Multi-Tenancy Architecture

Use a shared database and shared application architecture.

All shops use the same MySQL database.

Every tenant-owned database record must contain a `shop_id` column.

Conceptual structure:

```text
Kisan Khata SaaS
       |
       |---- Shop A (Tenant A)
       |       |---- Owner
       |       |---- Managers
       |       |---- Staff
       |       |---- Customers
       |       |---- Sales
       |       |---- Payments
       |       |---- Financial Ledger
       |
       |---- Shop B (Tenant B)
       |       |---- Owner
       |       |---- Managers
       |       |---- Staff
       |       |---- Customers
       |       |---- Sales
       |       |---- Payments
       |       |---- Financial Ledger
       |
       |---- Shop C (Tenant C)
               |---- Owner
               |---- Managers
               |---- Staff
               |---- Customers
               |---- Sales
               |---- Payments
               |---- Financial Ledger
```

Data is logically separated using tenant ownership, query scoping and authorization.

## 3. User and Shop Relationship

The approved relationship is:

**1 User = 1 Shop = 1 Role**

Rules:

1. Every shop user belongs to exactly one shop.
2. Every shop user has exactly one role.
3. A user cannot belong to multiple shops.
4. A user cannot switch between shops.
5. Shop membership is established when an account is created.
6. A user's shop association cannot be changed through ordinary user operations.
7. Any exceptional ownership or account transfer process must be separately authorized and audited.

The database design must not use a many-to-many `shop_user` membership relationship.

The `users` table should contain the user's `shop_id` and role.

## 4. Shop User Capacity

Each shop has the following maximum user capacity:

| Role | Maximum users |
|---|---:|
| Owner | 1 |
| Manager | 2 |
| Staff | 5 |
| Total | 8 |

Rules:

- A shop must have one Owner.
- Owner can create Manager and Staff accounts.
- Manager and Staff cannot create other shop users.
- User capacity must be checked during account creation.
- Concurrent account creation must not exceed capacity.
- Capacity counts active shop users only: one active Owner, up to two active Managers, up to five active Staff, and eight active users total.
- Deactivated Manager and Staff accounts release both their role capacity and total active-user capacity; their accounts and audit history are retained.
- Reactivation must re-check role and total active-user capacity before the account becomes active.
- The sole active Owner cannot be deactivated or demoted through ordinary shop-user management. Ownership transfer requires a separately authorized and audited process.
- Only the Owner may create, deactivate, reactivate, or change the roles of Manager and Staff accounts.
- Manager may view the Staff list and active/deactivated status only; Manager cannot administer user accounts.
- Role changes must check destination-role capacity and release source-role capacity atomically.
- Account creation, deactivation, reactivation and role changes must be serialized per shop (for example, by locking the Shop row in a database transaction) so concurrent requests cannot exceed capacity.

The capacity must be enforced by backend services and database safeguards where practical. The active-user count and authorization checks must be server-side; client-supplied status or role values are never authoritative.

## 5. Tenant Identification

The current tenant must be resolved from the authenticated user's assigned shop.

The browser must not be trusted to supply the authoritative `shop_id`.

Conceptual flow:

1. User authenticates.
2. Application loads the authenticated user.
3. Application resolves the user's assigned shop.
4. Application validates that the user and shop are active.
5. Current tenant context is established.
6. Request executes within that tenant context.

The application must never allow a normal shop user to select an arbitrary shop ID to change tenant context.

The user's `shop_id` is the authoritative tenant association.

## 6. Centralized Tenant Context

Create a centralized tenant context abstraction.

Suggested class:

`App\Support\Tenancy\CurrentShop`

Responsibilities:

- Resolve the authenticated user's shop.
- Expose the current shop ID.
- Validate shop existence and status.
- Ensure the user belongs to the resolved shop.
- Prevent unauthorized tenant context changes.
- Provide a consistent tenant reference to application services.
- Support explicit tenant context for background jobs and system operations.

Avoid scattered session lookups throughout the application.

For authenticated HTTP requests, tenant context should be established by middleware.

For background jobs and CLI operations, tenant context must be explicitly established.

## 7. Tenant-Owned Models

Examples include:

- Customer
- Sale
- Payment
- FinancialTransaction
- LedgerEntry
- CustomerAccount
- InterestPolicy
- InterestCalculationRun
- Settlement
- Subscription
- AuditLog, where shop-specific

The `villages` LGD table is shared, read-only geographic reference data and is not tenant-owned; it must not have a `shop_id`. Shop and customer geographic code fields are attributes of their respective tenant-owned records and remain protected by the same tenant authorization and query boundaries as the rest of those records.

Every tenant-owned model must have a `shop_id` relationship.

Example:

```php
public function shop()
{
    return $this->belongsTo(Shop::class);
}
```

Customer-owned financial records must also validate customer ownership.

A relationship must never be considered valid merely because the referenced record ID exists.

## 8. Query Isolation

All tenant-owned queries must be tenant-scoped.

Preferred approach:

- Centralized tenant-aware query scopes.
- Repository or service methods requiring tenant context.
- Authorization policies.
- Database constraints where practical.

Do not rely on developers remembering to add `where('shop_id', ...)` to every query.

Laravel global scopes may be used as defense in depth, but must not be the only security boundary.

Important rules:

1. A query must not return records from another shop.
2. A resource lookup must validate tenant ownership.
3. Update and delete operations must validate tenant ownership.
4. Nested relationships must validate tenant ownership.
5. Financial queries must validate both shop and customer relationships.
6. Any intentional tenant-scope bypass must be restricted to explicitly authorized system operations.

## 9. Relationship Validation

When creating or updating a shop/customer location:

- The shop or customer record must be resolved within the authenticated user's current shop.
- Geographic codes must be validated against the shared `villages` master data and against each other; a village must belong to the submitted subdistrict and district.
- The client must not be allowed to assign a location by supplying an arbitrary display name or mismatched hierarchy.
- The shared reference data may be read across tenants, but writes/imports are restricted to controlled administrative or deployment operations.

When creating a sale:

- Customer must belong to the authenticated user's shop.
- Sale must use the authenticated user's `shop_id`.
- Client-supplied customer IDs must be validated.

When creating a payment:

- Customer must belong to the authenticated user's shop.
- Payment must use the authenticated user's `shop_id`.
- Financial account references must belong to the same shop and customer.
- No sale-level payment allocation is permitted.

When creating a settlement:

- Customer must belong to the authenticated user's shop.
- All financial references must belong to the same shop and customer.

When editing a transaction:

- Transaction must belong to the authenticated user's shop.
- User must have the required role.
- Owner-only correction rules must be enforced.

A valid foreign key does not automatically guarantee tenant ownership.

## 10. Tenant Isolation Layers

Apply defense in depth.

### Layer 1: Authentication

Only authenticated users may access protected shop functionality.

### Layer 2: User-Shop Association

The authenticated user's assigned shop establishes the tenant.

No multi-shop membership selection is permitted.

### Layer 3: Tenant Context

Centralized tenant context must be established for each request.

### Layer 4: Query Scoping

All tenant-owned queries must be restricted to the current shop.

### Layer 5: Authorization

Policies must verify both tenant ownership and user role.

### Layer 6: Service Validation

Business services must validate ownership before executing financial or administrative operations.

### Layer 7: File Authorization

Uploaded files must be checked against their parent tenant-owned database records.

### Layer 8: Database Integrity

Use composite tenant-aware foreign keys and constraints where practical to prevent cross-tenant relationships.

## 11. Role-Based Tenant Access

Tenant isolation and role authorization are separate security responsibilities.

### Owner

- Full access to the assigned shop.
- Manage shop settings.
- Manage subscriptions and billing.
- Create and manage Manager and Staff accounts.
- Edit posted sales and payments.
- Access all shop reports and financial records.

### Manager

- Manage customers.
- Manage sales and payments within approved permissions.
- View reports.
- View the Staff list and active/deactivated status only.
- Cannot create, deactivate, reactivate, or change the role of any shop user.
- Cannot change subscription, ownership or critical security settings.
- Cannot edit posted financial transactions.

### Staff

- View, add and edit customer profile information.
- Record credit sales.
- Record payments.
- View own work and transaction history.
- Cannot delete customers or edit/delete posted financial transactions.
- Has limited report and settings access.

### Owner protection

The sole active Owner cannot be deactivated or demoted through ordinary shop-user management. Ownership transfer is outside ordinary user administration and requires a separately authorized and audited process.

The initial release uses fixed roles.

Custom per-user permissions are not part of the initial release.

## 12. Cache Isolation

Every tenant-specific cache key must include shop identity.

Example:

```text
shop:{shop_id}:customer:{customer_id}
shop:{shop_id}:ledger:{customer_id}
shop:{shop_id}:dashboard:summary
```

Rules:

- Never use tenant-independent keys for tenant-specific financial data.
- Invalidate related cache entries when underlying records change.
- Do not reuse cached data across tenant contexts.
- Ensure user-specific cached data also includes user identity where necessary.

## 13. Queue and Background Jobs

Every tenant-specific job must carry the required `shop_id`.

When a job executes:

1. Resolve the shop using the job's tenant identifier.
2. Validate shop existence and status.
3. Establish explicit tenant context.
4. Execute the operation within that context.
5. Clear tenant context after execution.

Never assume the original web request's session exists in a background worker.

Examples:

- Interest Auto Settlement.
- Translation jobs.
- Report generation.
- Subscription processing.
- Financial reconciliation.
- Notification delivery.

System-wide scheduled tasks must iterate through shops explicitly and establish tenant context separately for each shop.

A job must never inherit a previous job's tenant context.

## 14. Storage Isolation

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
            reports/
```

Rules:

- Store uploaded files privately.
- Validate ownership through the related database record.
- Authorize every download request.
- Never rely only on knowing a file path.
- Prevent path traversal.
- Use temporary authorized download URLs only where appropriate.
- Ensure generated reports belong to the correct shop.

## 15. Subscription Isolation

Each shop has its own subscription.

Subscription status must be checked for paid feature access.

Rules:

1. Subscription belongs to a shop, not an individual user.
2. All users of the same shop share its subscription.
3. Suspending one shop must not affect other shops.
4. Subscription expiry must not expose another tenant's information.
5. Expired shops follow the approved read-only access policy.
6. Shop Owner retains permitted data export access after subscription expiry.

Subscription checks must be enforced by backend middleware or services, not only by hiding frontend controls.

## 16. Platform Administration

Platform administrators are separate from shop-level users.

Platform administration may manage:

- Shop registration.
- Shop status.
- Subscription plans.
- Subscription status.
- Platform usage.
- Support requests.
- Platform-level operational monitoring.

Access to tenant financial data must be restricted and audited.

Do not provide unrestricted tenant data access by default.

Any exceptional support access must have authorization, a legitimate purpose and an audit record.

## 17. API Tenant Isolation

All authenticated API requests must resolve tenant context from the authenticated user.

Rules:

- Never trust request-body `shop_id` as the authority.
- Validate ownership of all resource IDs.
- Use tenant-scoped route model binding or equivalent validation.
- Return an appropriate authorization/not-found response for cross-tenant resource access.
- Apply the same rules to nested API resources.
- Do not expose tenant data through predictable IDs.
- API tokens and sessions must not bypass tenant isolation.

Future Android applications must use the same tenant resolution and authorization rules.

## 18. Tenant Isolation Testing

Automated tests must verify:

- Shop A cannot view Shop B customers.
- Shop A cannot edit Shop B customers.
- Shop A cannot access Shop B sales.
- Shop A cannot access Shop B payments.
- Shop A cannot access Shop B ledger.
- Shop A cannot download Shop B files.
- Shop A cannot create a payment for Shop B customer.
- Shop A cannot access Shop B settlement.
- Shop A cannot access Shop B subscription details.
- A user cannot switch to another shop.
- A user cannot change their own `shop_id`.
- A user cannot exceed shop role capacity.
- Manager and Staff cannot perform Owner-only operations.
- Background jobs use the correct tenant context.
- Cache keys do not leak data across tenants.
- API requests reject cross-tenant resource IDs.

Test both successful access and denied access.

## 19. Operational Security Rules

1. Never disable tenant scopes to make a feature work without a security review.
2. Never accept a browser-supplied `shop_id` as authoritative.
3. Never allow ordinary users to change tenant association.
4. Never execute tenant-specific jobs without explicit tenant context.
5. Never expose private tenant files without authorization.
6. Any cross-tenant reporting must be explicitly authorized and restricted to platform-level operations.
7. Any change to tenant resolution must receive a security review.
8. Financial operations must validate tenant ownership before processing.
9. Tenant context must be cleared after execution where it is explicitly established.
10. Tenant isolation failures must be treated as security incidents.

## 20. Acceptance Criteria

Multi-tenancy is complete only when:

- Shared database and shared application architecture is implemented.
- Tenant context is centralized.
- One user belongs to exactly one shop.
- One user has exactly one role.
- No many-to-many shop membership is required.
- Shop switching is not supported for shop users.
- Shop user capacity limits are enforced.
- Every tenant-owned record has shop ownership.
- Queries are tenant-scoped.
- Services validate ownership.
- Policies enforce role authorization.
- Files are isolated.
- Caches are isolated.
- Jobs establish explicit tenant context.
- API requests enforce tenant ownership.
- Platform administration is separated from shop-level permissions.
- Automated cross-tenant tests pass.

## 21. Current implementation mapping

The current tenant foundation provides these reusable enforcement points:

- `App\Tenancy\CurrentShop` is registered as a Laravel scoped service. `ResolveCurrentShop` derives the shop from the authenticated user, accepts only Owner/Manager/Staff roles, rejects inactive users and inactive shops, and clears the context after the request completes.
- `BelongsToCurrentShop` and `TenantOwnedBuilder` provide fail-closed query scoping and tenant-safe set-based mutations for tenant-owned Eloquent models such as `ShopSetting`. Removing the global scope does not remove the builder's ownership checks on bulk writes.
- `UserBuilder` prevents shop reassignment and unsafe bulk insert/delete paths; bulk updates and increments are limited to the current shop. Model-instance persistence remains available for controlled authentication and lifecycle operations.
- `UserAuditLog` queries fail closed without a current shop, are scoped to the current shop, and retain append-only mutation guards. Its controlled lifecycle writer validates the locked shop, actor and target before using the query builder.
- Use `TenantCacheKey::make()` to prefix tenant cache keys with `shop:{shop_id}:`.
- Use the `tenant_private` filesystem disk together with `TenantPrivatePath::make()` for shop-prefixed private paths. The path helper rejects absolute paths and traversal segments.
- Tenant-specific queued jobs must implement `TenantAwareJob` and include `ResolveTenantForJob` in their job middleware. The middleware verifies an active shop and clears tenant context in a `finally` block.

These are tenant infrastructure primitives, not claims that customer attachments, tenant-specific cache consumers, background business jobs or exports already exist. Each future feature must use the relevant primitive and add resource-specific authorization, ownership and cross-tenant tests. Raw SQL/query-builder operations bypass Eloquent scopes and must remain limited to reviewed system operations with explicit tenant validation.

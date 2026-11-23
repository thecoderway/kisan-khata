# Laravel Development Conventions

Approved target stack: PHP 8.3, Laravel 13, MySQL 8.4. Inspect actual installed versions and manifests first. Do not install or upgrade dependencies without approval.

Use a Modular Monolith. Keep module responsibilities clear; do not introduce microservices without approval. Controllers handle HTTP concerns only; business workflows belong in focused services/actions. Use Form Requests for validation, policies for authorization, Eloquent relationships/scopes, migrations for schema changes, foreign keys and indexes where appropriate.

Financial rules:
- Use DECIMAL and decimal-safe arithmetic, never float/double for authoritative money.
- Use DB transactions, locks and idempotency for multi-step financial posting.
- No sale-level payment allocation or payment_allocations table.
- Owner may edit posted Sales/Payments with immutable before/after audit; Manager/Staff cannot.
- Never invent unresolved interest, rounding or backdated correction policy.

Tenant rules: derive shop from authenticated user; never trust client shop_id; validate ownership at service boundaries, including jobs/files/cache/API.

Follow existing frontend conventions; Blade/Bootstrap/Alpine are planned, not assumed installed. Use versioned API patterns and resources where appropriate. Avoid unnecessary dependencies, giant controllers, hidden side effects and unrelated refactors. Follow docs/09-testing-strategy.md and update relevant docs.
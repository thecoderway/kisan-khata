# ADR-0016 — Customer Lifecycle Visibility Across Financial Workspaces

**Status:** ACCEPTED  
**Date:** 2026-11-27  
**Decision Authority:** Product Owner

## Context

Customer lifecycle has three distinct states: Active, Inactive and Soft Deleted. The approved Business Rules prohibit selecting Inactive Customers for new transactions and prohibit soft deletion when financial history exists. Financial workspaces nevertheless need to preserve historical visibility. Reusing one Customer directory response for every financial operation caused Inactive Customers to appear in new Sale and Payment selectors.

## Decision

1. New Sale and Payment selectors contain only Active, non-deleted Customers. This is enforced both in the UI query and by the server-side posting path.
2. Customer Ledger and Reconciliation selectors contain only Customers with relevant financial history. Active and Inactive Customers are included. Soft Deleted Customers are included only when legacy financial history exists and only through this explicit historical lookup.
3. Owner-managed Customer interest-policy override selection may include Active and Inactive Customers, but excludes Soft Deleted Customers. Existing effective-dated policy history remains immutable.
4. Posted Sales and Payments remain visible regardless of later Customer lifecycle state. Their Customer relationship must be loaded including soft-deleted records so historical identity is not replaced by a blank label. The UI must show an Inactive or Soft Deleted lifecycle marker.
5. Soft deletion remains prohibited whenever any financial or transaction history exists. The historical lookup for Soft Deleted Customers is a defensive compatibility path for legacy/imported/inconsistent data; it does not authorize ordinary soft deletion with history.
6. Customer lifecycle changes never delete, hide, reverse, rewrite or recalculate financial history. Financial corrections remain governed by the existing Owner-only correction workflows.
7. Every lookup and financial endpoint remains tenant-scoped and server-authorized. The API must not trust client-supplied Shop IDs or lifecycle state.
8. New Sale/Payment posting must serialize against customer lifecycle changes. Financial posting and customer lifecycle operations use the same Customer-row-first lock order before any Customer Account lock or financial-history eligibility checks, preventing a concurrent soft-delete from racing with a posting and avoiding lock-order inversion.

## Consequences

- Transaction creation and historical financial selection use separate Customer directory modes.
- The Customer directory API exposes a dedicated financial-history mode that includes only Customers with financial records, including Soft Deleted records only when such history exists.
- Ledger and Reconciliation read paths can resolve a Soft Deleted Customer only when explicitly requested by its historical record ID and after existing policy/tenant authorization.
- Sale and Payment list queries preserve the historical Customer relationship with Laravel's soft-delete-aware relationship loading.
- Automated tests cover Active, Inactive and Soft Deleted visibility, posting restrictions, historical read access and tenant isolation.

## Related Documents

- `docs/02-business-rules.md` — Customer Lifecycle and Financial Module Customer Visibility
- `docs/05-multi-tenancy.md`
- `docs/06-security.md`
- `docs/phase-5-batch-7-authorization-api-ui-plan.md`
- ADR-0010 — User Lifecycle and Role Permissions
- ADR-0015 — Staff Shop-Wide Financial Read Access

## Reconsideration

Any change to these lifecycle visibility rules requires an explicitly approved superseding ADR and synchronized Business Rules before implementation changes begin.

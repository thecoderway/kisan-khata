# ADR-0010 — User Lifecycle and Role Permissions

**Status:** ACCEPTED  
**Date:** 2026-11-23  
**Decision Authority:** Product Owner

## Context

The approved product, business, tenancy and security documents establish fixed shop roles and capacity limits, but leave some user lifecycle and role-permission details open. This ADR records the Product Owner decisions that complete those rules and supersedes any conflicting or ambiguous wording in the related documents.

## Decisions

### 1. Active-user capacity

Each shop has the following active-user limits:

| Role | Maximum active users |
|---|---:|
| Owner | 1 |
| Manager | 2 |
| Staff | 5 |
| Total | 8 |

Only active shop users count toward these limits. Deactivated Manager and Staff accounts release their role capacity and total active-user capacity. Deactivation retains the account and audit history; it does not delete the user or historical activity.

Reactivation is allowed only when both the target role capacity and total active-user capacity are available. Creation, deactivation, reactivation and role changes must enforce capacity atomically to prevent concurrent requests from exceeding the limits.

The shop must retain one active Owner. The sole Owner cannot be deactivated or demoted through ordinary shop-user management. Ownership transfer requires a separate, explicitly authorized and audited process and is not included in ordinary Phase 3 user administration.

### 2. Role changes

Only the Shop Owner may change a Manager's or Staff member's role. Manager and Staff cannot change any user's role. A user cannot change their own role through ordinary user-management operations.

A role change must validate the destination role's capacity and update the role within the same transaction that protects the shop's capacity invariant. Every successful role change must be audited with the actor, affected user, previous role, new role and timestamp. The Owner role and platform-level roles are not changeable through ordinary shop-user role management.

### 3. Staff customer permissions

Staff may view, add and edit customer profile information. This permission applies to customer master/profile data only; it does not grant permission to alter posted sales, payments, ledger entries or other financial history.

Staff may not delete customers. Customer deletion/deactivation lifecycle behavior, if required, must follow separately approved customer-domain rules. Customer profile changes must be auditable.

### 4. Manager staff operations

Manager may view the shop's Staff list and each Staff member's active/deactivated status only. Manager may not create, deactivate, reactivate or change the role of any shop user. Only the Shop Owner may perform those user-administration operations.

## Security and Audit

- Enforce all role permissions server-side for web and API requests.
- Never trust client-supplied `shop_id`, role or active status as authoritative.
- Validate tenant ownership and role authorization independently.
- Protect capacity operations against concurrency, using a transaction and shop-level serialization/locking strategy.
- Record account creation, deactivation, reactivation and role changes in append-only audit history.
- Record actor, affected user, shop, action, relevant previous/new values and timestamp; include a reason/reference where applicable.
- Do not expose credentials, tokens, OTPs or other sensitive authentication data in user lists or audit records.

## Consequences

- Deactivated Manager/Staff accounts no longer block replacement accounts, but remain retained for auditability.
- Reactivation can fail if another account has consumed the available capacity.
- Owner is the sole administrator of shop-user lifecycle and Manager/Staff role assignment.
- Manager receives read-only visibility into Staff membership and status.
- Staff receives customer-profile editing capability without financial correction authority.
- Fixed roles remain in place; custom per-user permissions are not introduced.

## Related Documents

- `docs/01-product-requirements.md`
- `docs/02-business-rules.md`
- `docs/05-multi-tenancy.md`
- `docs/06-security.md`
- `docs/10-development-roadmap.md`
- `docs/decisions/ADR-0005-user-shop-role-relationship.md`

## Reconsideration

Any change to these rules requires explicit Product Owner approval and a superseding ADR. Documentation must be updated before implementation changes begin.

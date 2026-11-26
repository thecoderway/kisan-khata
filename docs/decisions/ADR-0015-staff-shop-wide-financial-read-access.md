# ADR-0015 — Staff Shop-Wide Financial Read Access

**Status:** ACCEPTED
**Date:** 2026-11-26
**Decision Authority:** Product Owner

## Context

ADR-0010 defines Staff customer-profile permissions and restricts Staff from financial correction authority. The approved Batch 7 product decision explicitly grants Staff visibility into all Customers and their financial records within their assigned Shop. This decision clarifies read access without expanding correction or administration privileges.

## Decision

1. Active Staff users may view every Customer profile in their assigned Shop.
2. Active Staff users may view all posted Sales, Payments, Customer Ledger entries and permitted financial transaction details belonging to their assigned Shop, regardless of which Staff member created the record.
3. Staff may continue recording new credit Sales and Payments as allowed by the approved Business Rules.
4. Staff may not revise posted Sales, reverse or replace Payments, edit/delete ledger entries, configure Shop or Customer interest policies, view Owner-only financial audit history, or run/repair reconciliation.
5. Owner and Manager retain their existing Shop-wide operational access, subject to their respective critical-setting and correction permissions.
6. Cross-Shop access remains prohibited. Tenant ownership and role authorization must be enforced server-side for every Web and API request.
7. Frontend visibility is not an authorization boundary; Policies/Gates and tenant-scoped queries remain mandatory.

## Consequences

- Customer, Sales, Payments and Ledger read queries must be Shop-wide for active Staff.
- Staff-created-by filters must not be applied to those read endpoints.
- Financial correction, interest administration, reconciliation and financial audit remain separately authorized.
- Tests must prove Staff can read another Staff member's Shop customer/financial records and cannot access another Shop or Owner-only workflows.

## Relationship to other decisions

- ADR-0010 remains authoritative for user lifecycle, customer profile permissions and correction restrictions.
- ADR-0015 explicitly expands Staff financial read visibility only.
- docs/02-business-rules.md and docs/06-security.md are updated to reflect this decision.
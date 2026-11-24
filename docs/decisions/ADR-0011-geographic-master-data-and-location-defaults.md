# ADR-0011 — Geographic Master Data and Shop/Customer Location Defaults

**Status:** ACCEPTED  
**Date:** 2026-11-24  
**Decision Authority:** Product Owner

## Context

Kisan Khata needs consistent administrative location selection for shops and customers. The Product Owner approved importing the Madhya Pradesh LGD village master dataset and decided that the shop's location should be used only to preselect a new customer's location, while allowing the user to change it.

The imported `villages` table is shared reference data. It is not tenant-owned data. The initial dataset contains the Madhya Pradesh snapshot dated 21-04-2026.

## Decisions

### 1. Shared LGD master data

- Use the shared `villages` table as the source for State → District → Subdistrict/Tehsil → Village selection.
- Use LGD codes as stable identifiers; store codes as strings.
- Village names are not unique and must be disambiguated by administrative hierarchy and code.
- The current imported dataset covers Madhya Pradesh only. Do not claim all-India coverage.
- Ordinary shop users have read-only access to lookup data; import/update operations are controlled administrative/deployment operations.

### 2. Editable Shop Profile location

- The Shop Owner can change the shop's State, District, Subdistrict/Tehsil and Village using cascading select boxes.
- Changing a parent selection clears all dependent child selections.
- The selected shop location is not a service-area restriction or a limit on customer locations.

### 3. Customer location defaults

- On opening a new customer form, preselect the shop's current location where available.
- The authorized user may change any customer location selection or clear/leave it blank.
- Customer location is independently saved on the customer record; it is not a live or inherited relationship to the shop location.
- Changing the shop location affects only future customer-form defaults. It must not update existing customer records.
- Editing an existing customer displays that customer's saved location, not the shop's current location as a replacement.

### 4. Hierarchy and validation

- State selection filters District options.
- District selection filters Subdistrict options.
- Subdistrict selection filters Village options.
- State changes clear District, Subdistrict and Village.
- District changes clear Subdistrict and Village.
- Subdistrict changes clear Village.
- The backend validates submitted codes and parent-child consistency against the shared master dataset.

## Data and tenancy impact

- The shared `villages` reference table has no `shop_id`.
- Shop and customer records remain tenant-owned and retain their own selected location codes.
- Shared lookup reads do not weaken tenant isolation for shop/customer data.
- No customer location is automatically rewritten when a shop location changes.

## Consequences

- LGD codes provide stable identity even when village names are duplicated.
- The same master-data lookup can be reused by Shop Profile and Customer Management.
- Customer creation is faster for shops whose customers commonly live near the shop, without restricting customers from other villages.
- Future expansion beyond Madhya Pradesh requires a reviewed additional dataset and import/update procedure.

## Related documents

- `docs/01-product-requirements.md`
- `docs/02-business-rules.md`
- `docs/04-database-architecture.md`
- `docs/05-multi-tenancy.md`
- `docs/06-security.md`
- `docs/07-ui-ux-guidelines.md`
- `docs/08-api-architecture.md`
- `docs/09-testing-strategy.md`
- `docs/10-development-roadmap.md`
- `docs/11-geographic-master-data.md`
- `docs/PROJECT-STATUS.md`

## Reconsideration

Any change to the source-of-truth model, editable shop location, customer preselection behavior, or independent customer location requires explicit Product Owner approval and a superseding ADR. Documentation must be updated before implementation changes begin.

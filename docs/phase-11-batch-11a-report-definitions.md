# Phase 11 — Batch 11A: Report Definitions and Read-Only Data Contracts

**Status:** IN PROGRESS — specification and source mapping  
**Date:** 2026-11-27  
**Base:** `main` at `29a2c0f5eec868a2a581a493e681098fe7de1df7`  
**Branch:** `feature/phase-11-batch-11a-report-contracts`

## 1. Purpose and implementation boundary

Define stable, auditable report contracts before implementing report APIs or UI. This batch includes documentation/specification, read-only source mapping, and a narrowly scoped authorization correction to the existing Interest Calculation History API because the approved Owner-only rule exposed an existing API security defect.

Included report families:

1. Shop-wide Customer Outstanding (as-of snapshot).
2. Sales Register and Sales Summary.
3. Payment Register and Collection Summary.
4. Interest Calculation / Posted Interest.

Excluded: Crop-Season Settlement (Phase 10 remains deferred), subscription/billing reports (Phase 12), new financial transaction types, accounting or interest recalculation, ledger writes, projection repair, migrations, export implementation and PDF implementation.

All reports must be projections over existing persisted records. A missing or ambiguous source definition is a blocker, not permission to invent a calculation.

## 2. Confirmed source-of-truth inventory

| Report | Authoritative source | Existing evidence |
|---|---|---|
| Customer Outstanding | Append-only `ledger_entries`, grouped by shop/customer/component and effective date | `LedgerEntry`; Customer Ledger API computes principal and interest running balances with Brick Math |
| Sales Register | Posted `sales` records | `SaleController::index` filters `posted_at IS NOT NULL`, sale date, customer and payment status |
| Payment Register | Posted `payments` records plus linked reversal/replacement relationships | `PaymentController::index` filters `posted_at IS NOT NULL`, payment date, customer and payment method |
| Interest Calculation | Completed `interest_calculation_runs` and immutable `interest_calculation_items` | Run stores trigger, status and exclusive cutoff; item stores interval, actual days, policy versions, rate, principal basis, unrounded and posted interest |
| Posted Interest | Interest calculation item linked to its ledger entry, reconciled against the relevant ledger entry | `InterestCalculationItem::ledger_entry_id` is nullable; do not count an unlinked item as a posted ledger amount |

The existing customer ledger read model groups allocation-level ledger entries into one customer-facing Payment/Reversal row, preserves the underlying immutable entries, and uses deterministic `effective_date → sequence_number → id` ordering. It derives balances with decimal-safe `BigDecimal` arithmetic.

## 3. Common contract rules

### 3.1 Tenant and authorization

- Resolve Shop exclusively from authenticated tenant context; never accept a client-provided `shop_id` as authority.
- Every query and related-record join must remain scoped to the same Shop.
- Enforce policies/Gates server-side for both web and API; hiding a navigation item is not authorization.
- Do not filter Shop-wide financial reads by the current creator.
- Preserve Customer identity for historical Sales/Payments even if the Customer later becomes Inactive or Soft Deleted. A Soft Deleted Customer may appear only through its valid historical financial records.
- Apply ADR-0010, ADR-0015 and ADR-0016. In particular, Staff can read Shop-wide posted Sales, Payments and Customer Ledger data, but cannot access Owner-only financial audit or reconciliation/repair workflows.

### 3.2 Date semantics

- User-entered report range fields are ISO dates (`YYYY-MM-DD`).
- For Sales and Payments, `from` and `to` are inclusive against `sale_date` and `payment_date` respectively; `from <= to`.
- Outstanding is a point-in-time snapshot with one required `as_of` date. Include ledger entries whose `effective_date <= as_of`; same-day entries are included. This is a calendar-date cutoff, not a timestamp cutoff.
- Interest has two distinct date concepts and they must not be conflated:
  - Calculation interval: persisted `interval_start_date` inclusive and `interval_end_date_exclusive` exclusive.
  - Posted-interest register: use the linked ledger entry's `effective_date` for the report date filter.
  - A calculation-run activity view, if exposed, filters on the persisted `cutoff_date_exclusive` and labels it explicitly as exclusive.
- Do not reinterpret or recalculate historical interest intervals.

### 3.3 Monetary and ordering rules

- Return authoritative monetary values as decimal strings with two fractional digits; use Brick Math/decimal-safe arithmetic, never PHP floats.
- Preserve source records and reversal links; reports are read-only and must not mutate source data.
- Use deterministic ordering with a unique tie-breaker. Ledger-derived ordering is `effective_date, sequence_number, id`; Sales/Payments use their effective business date followed by stable record ID.
- Summary totals must be calculated from the same filtered row set as the displayed report.
- Do not silently clamp negative or zero balances. Any default exclusion of zero/negative balances requires an explicit report-definition decision.
- Pagination must not change aggregate totals or opening/as-of balances.

## 4. Report contracts

### 4.1 Customer Outstanding — as-of snapshot

**Grain:** one row per Customer with financial ledger history in the authenticated Shop.

**Candidate columns:**
- Customer ID (internal key; not necessarily displayed).
- Customer name and available mobile.
- Customer lifecycle state (Active, Inactive or Soft Deleted marker).
- Outstanding Principal.
- Posted Unpaid Interest.
- Total Outstanding.

**Balance definition:**
- Principal = sum of principal-component ledger debits minus principal-component ledger credits through the inclusive `as_of` date.
- Interest = sum of interest-component ledger debits minus interest-component ledger credits through the inclusive `as_of` date.
- Total = Principal + Interest.
- Use immutable ledger entries, including posted reversals, as the source. Do not use the current projection as a historical as-of source.
- Do not add a separate interest calculator. Interest capitalization and payment allocation effects must be reflected only through their already-posted ledger entries.

**Required summary:** customer count in the selected result set; principal total; interest total; grand total. Summary must be computed over the full filtered set, not only the current page.

**Approved Product Owner decision — Option A:** include every Customer with financial history in the authenticated Shop, including positive, zero and negative balances. This is the default report population; no positive-balance-only exclusion is permitted. A positive-only quick filter may be offered as an explicit user-selected filter without changing the default. Customers without financial history are excluded.

### 4.2 Sales Register / Summary

**Grain:** one row per posted Sale.

**Candidate columns:** Sale number, sale date, Customer, description, gross posted Sale amount, payment status, created/posting metadata where already available.

**Filters:** inclusive sale-date range, Customer, payment status, text search. Only posted Sales (`posted_at IS NOT NULL`) are eligible.

**Summary:** posted Sale count and gross posted Sale amount, with optional counts/totals by persisted payment status. Paid and Unpaid Sales both remain Sales; sale-day automatic Payment is not subtracted from gross Sales.

**Correction treatment:** never rewrite historical Sale rows in a report. Display the currently persisted Sale description/status and preserve any approved revision/audit linkage where the report needs it. Any future financial Sale reversal semantics must be established from actual source records and ADRs before inclusion.

### 4.3 Payment Register / Collection Summary

**Grain:** one row per posted Payment record, including its persisted payment type and any reversal/replacement relationship.

**Candidate columns:** Payment number, payment date, Customer, payment type, payment method, reference number, amount, linked original/reversed/replacement Payment where applicable.

**Filters:** inclusive payment-date range, Customer, payment method, payment type, text search. Only posted Payments (`posted_at IS NOT NULL`) are eligible.

**Display and summary requirements:**
- Keep each persisted Payment/Reversal record visible as its own register event; never flatten or discard correction history.
- Distinguish `NORMAL`, `SALE_DAY_AUTO` and `REVERSAL` records.
- A sale-day automatic Payment is an accounting settlement of its own Paid Sale, not a cash collection. It must be reported separately from customer cash receipts.
- Show reversal and replacement linkage explicitly. Do not treat a replacement as an overwrite of the original.
- **Approved Product Owner decision — Option A:** base the cash-collection metric on NORMAL receipts less reversals linked to those NORMAL receipts. SALE_DAY_AUTO records are an accounting settlement of Paid Sales, not customer cash; report them separately and exclude them from cash collection.
- Every posted Payment and REVERSAL remains a separate register event. Preserve `reversal_of_payment_id`, `replacement_for_payment_id`, `PaymentRevision.reversal_payment_id`, `PaymentRevision.replacement_payment_id`, and audit linkage.
- A replacement Payment is a separate NORMAL posted record, but its existence/linkage alone is **not proof of a new real-world cash receipt**. Keep replacement-linked amounts in a separate, explicitly identified reconciliation bucket and exclude them from confirmed cash collected until an audit/evidence check establishes that money was actually received. If confirmed, count the replacement once; never count the reversed original as collected.
- Source verification found the correction service creates a reversal for the original NORMAL Payment, then optionally posts a replacement through `PaymentPostingService`; the replacement receives `replacement_for_payment_id` and a REPLACEMENT revision record. The current schema does not store a dedicated actual-cash-confirmation/evidence field. Therefore, replacement inclusion in confirmed collection is blocked pending an agreed auditable evidence source or explicit manual audit workflow.
- The current reversal service accepts only original `NORMAL` Payments. It rejects `SALE_DAY_AUTO` as a correction source; do not imply automatic-payment reversals are currently supported. Any legacy or future auto-payment reversal must remain separately identified and excluded from customer cash collection.

### 4.4 Interest Calculation / Posted Interest

**Calculation-detail grain:** one row per persisted Interest Calculation Item, with its parent Run.

**Candidate fields:** Customer, Sale number/date, trigger type, run status, exclusive cutoff date, interval start, interval end (exclusive), actual days, grace days applied, monthly rate, eligible principal basis, unrounded interest, posted interest, capitalization flag, policy version references, linked ledger entry.

**Posted-interest register grain:** one row per posted interest ledger effect, linked to the persisted calculation item where available. The linked ledger entry's effective date controls the posted-interest date filter. Include only completed runs and items with a valid linked ledger entry for posted totals.

**Read-only rule:** never recalculate interest for a report. Do not infer posted interest from policy settings or from an unposted calculation item. Keep calculation interval dates distinct from posting/effective dates.

**Approved Product Owner decision — Option A:** Interest Calculation and Posted Interest report detail is Owner-only. Manager and Staff must be denied report UI and API access. Posted interest already visible as part of the Customer Ledger remains governed by the ledger's existing permissions; this does not grant access to calculation-run, policy-version or report-detail metadata.

**Source-level security finding:** `FinancialWorkspaceController` explicitly restricts the `interest` web section to Owner. However, the existing `InterestCalculationHistoryController::index` and `show` endpoints currently authorize `viewCustomerLedger`, and `CustomerLedgerPolicy` allows active Owner, Manager and Staff users in the same Shop. Therefore the existing API can expose detailed interest history to Manager/Staff despite the Owner-only workspace. Batch 11 report endpoints must use a dedicated Owner-only authorization check; add regression tests proving Manager and Staff receive 403 from every interest-detail/report endpoint. Treat the existing history API authorization mismatch as a security defect. It is now corrected in the Batch 11A branch; see the authorization correction record below.

## 5. Reconciliation equations and invariants

1. **Outstanding:** for each Customer and for the whole Shop, principal and interest equal the sum of corresponding ledger debits less credits through the selected `as_of` date. Total Outstanding = Principal + Interest.
2. **Sales:** Sales Register gross total equals the sum of eligible posted Sale amounts for the exact selected date/customer/status filters. It is not reduced by sale-day automatic Payments.
3. **Payments:** every displayed register row maps to exactly one posted Payment record. Cash-collection summary must reconcile NORMAL receipts and their linked reversals; sale-day automatic Payments are separately reconciled to their originating Paid Sales.
4. **Interest:** posted-interest totals must reconcile to linked persisted calculation items and their authoritative ledger entries. Unlinked calculation items must not inflate posted totals.
5. **Cross-report:** no report may create, update, delete, repair or post financial records. Reading a report must not trigger interest calculation or change projections.

## 6. Role matrix — approved Batch 11A report access

| Report/data | Owner | Manager | Staff | Basis |
|---|---|---|---|---|
| Customer Outstanding / Ledger-derived balances | Existing Shop-wide financial access | Existing Shop-wide financial access | Existing Shop-wide financial read access | ADR-0015 plus ledger policy |
| Sales Register | Allowed | Allowed | Allowed | ADR-0015 |
| Payment Register | Allowed | Allowed | Allowed | ADR-0015 |
| Interest calculation/policy/report detail | Allowed | Denied | Denied | Product Owner approved Owner-only; API must enforce Owner-only independently of ledger permissions |
| Financial Audit / Reconciliation and repair | Existing Owner-only controls | Not allowed where current policy restricts | Not allowed | ADR-0015 and existing workspace authorization |

This matrix is a mapping of current approved access, not a new permission grant. The implementation must reuse the actual policies and verify each endpoint.

## 7. Approved Product Owner decisions and 11B implementation gates

The Product Owner approved the following decisions on 2026-11-27:

1. **Customer Outstanding — Option A:** default population is every customer with financial history in the Shop, including zero and negative balances. Customers without financial history are excluded. Positive-only is an optional explicit filter, never the default.
2. **Payment Collection — Option A:** confirmed cash collection is NORMAL receipts less reversals linked to those NORMAL receipts. SALE_DAY_AUTO is shown separately and excluded from cash collection. A replacement Payment is not counted merely because it is posted or linked; count it only after an audit confirms actual receipt of money. Until then, show it separately as an unverified replacement and exclude it from confirmed collection.
3. **Interest Access — Option A:** Interest Calculation and Posted Interest report detail is Owner-only. Manager and Staff are denied; Staff's existing Customer Ledger visibility remains unchanged.

The above business decisions are approved. The following source/implementation gates remain before 11B:

- Resolve the replacement actual-collection evidence/audit mechanism. Existing source creates a separately posted NORMAL replacement and stores linkage/revision/audit records, but does not persist an independent actual-cash-confirmation field. Do not infer real-world collection from posting alone.
- ~~Enforce Owner-only authorization on every interest report endpoint.~~ The existing Interest Calculation History API `index` and `show` actions now use the dedicated `viewInterestCalculationHistory` Gate, which permits only an active Owner in the same Shop. `Batch7FinancialWorkspaceApiTest` now covers Owner access, Manager/Staff denial on both actions, and inactive Owner denial. This resolves the identified existing-history API gap; every new interest report endpoint must use the same Owner-only authorization rule and have endpoint-specific tests.
- Verify the report-specific reconciliation equations against application services and MySQL integration fixtures, including all/zero/negative Outstanding, NORMAL receipt/reversal pairs, replacement included/excluded cases, SALE_DAY_AUTO separation, and interest ledger links.
- Approve sample layouts and the final report response schemas before API/UI implementation.

If any source trace contradicts these definitions, stop and update this contract before implementation. No unresolved collection metric or authorization gap may be bypassed in 11B.

## 8. Batch 11A acceptance checklist

- [x] Approved financial rules ADR-0012 reviewed.
- [x] Staff financial visibility ADR-0015 reviewed.
- [x] Customer lifecycle financial visibility ADR-0016 reviewed.
- [x] Existing Sales, Payments, Ledger and Interest API route/controller/model sources mapped.
- [x] Ledger date ordering, append-only behavior and decimal-safe balance method inspected.
- [x] Interest run/item persisted fields and linked ledger source inspected.
- [x] Product Owner decisions in Section 7 recorded.
- [x] Existing Interest Calculation History API restricted to active same-Shop Owner; Manager/Staff and inactive-Owner regression cases added (execution still requires CI/local test verification).
- [ ] Final report schemas and sample layouts approved.
- [ ] Report-specific reconciliation queries/equations verified against existing application services and MySQL integration tests.
- [ ] Batch 11A specification accepted before 11B implementation.

## 9. Safety

This document records report-definition work and the narrowly scoped existing Interest History API authorization correction. It does not authorize new report APIs/UI, migrations, new packages, exports, PDF generation, database writes, financial posting, projection repair, production operations, deployment or release. Phase 10 remains deferred; no Crop-Season Settlement reporting is included.


## 10. Source-level verification record — 2026-11-27

Read-only source review was performed on the active Batch 11A branch. The subsequent narrowly scoped Interest History API authorization correction changed application code and added regression coverage; no database records or schema were changed.

### Payment collection and correction trace

- `Payment` defines `NORMAL`, `SALE_DAY_AUTO` and `REVERSAL` types, plus self-links `reversal_of_payment_id` and `replacement_for_payment_id`. The payments migration enforces same-Shop/same-Customer foreign-key linkage and requires reversal type to carry an original-payment reference.
- `PaymentReversalService::correct` only accepts an original `NORMAL` Payment, rejects a second reversal, validates original ledger/allocation totals, writes an immutable `REVERSAL` Payment and reversing ledger entries, and appends a `REVERSAL` PaymentRevision plus financial audit event.
- If replacement is requested, the service calls `PaymentPostingService::post` in the same transaction. That service creates a separate `NORMAL` Payment with `replacement_for_payment_id` set to the original Payment, and the correction service appends a `REPLACEMENT` PaymentRevision referencing the new Payment. The replacement amount may differ from the original.
- Existing feature tests cover atomic reversal/replacement, explicit linkage, audit rollback, idempotent replay, and ledger/projection reconciliation. These tests prove application-record behavior, not independent proof that the replacement money was actually received.
- No dedicated persisted actual-cash-confirmation/evidence field was found in the inspected Payment/PaymentRevision schema or service path. Replacement inclusion in confirmed collection therefore remains gated on an auditable evidence source. SALE_DAY_AUTO correction is not supported by the current reversal service.

### Interest authorization trace

- The web Financial Workspace explicitly rejects non-Owner access to the `interest` section.
- The existing Interest Calculation History API uses `Gate::authorize('viewCustomerLedger', $customer)` in both list and detail actions. `CustomerLedgerPolicy` permits active Owner, Manager and Staff in the same Shop.
- Existing tests prove Staff cannot access Interest Policy or Financial Audit APIs, but the inspected tests do not establish Owner-only denial for the Interest History API. The API authorization mismatch must be corrected or separately approved for remediation before report detail is exposed.

### Tenant and ledger foundations

- `Payment`, `LedgerEntry` and interest run/item models use the current-Shop tenant scope; payment correction foreign keys also enforce Shop/Customer consistency.
- `LedgerEntry` and `InterestCalculationItem` are append-only at model level; completed InterestCalculationRun records are protected from mutation. The Customer Ledger uses deterministic effective-date/sequence/id ordering and decimal-safe BigDecimal balances.

### Verification limitations / remaining work

- This was a source-level read-only review. No live database sample-data audit or new MySQL reconciliation query was executed in this step.
- Existing tests cover reversal/replacement lifecycle and ledger reconciliation, but Phase 11 still needs report-specific tests for aggregate totals, date filters, replacement eligibility, auto-payment separation, all/zero/negative Outstanding rows, and Owner-only report API access.
- Batch 11A remains IN PROGRESS until sample layouts and reconciliation queries/equations are reviewed and accepted. Batch 11B must not begin until the actual-collection evidence rule and Interest API authorization gate are resolved.


### Interest History API authorization correction — 2026-11-27

- Added the dedicated `viewInterestCalculationHistory` Gate in `AppServiceProvider`. It requires an active Owner and matching authenticated Shop/customer tenant.
- Updated both `InterestCalculationHistoryController::index` and `show` to authorize through this dedicated Gate before querying calculation history.
- Added a feature test proving active Owner access, Manager and Staff 403 responses for both list and detail actions, and inactive Owner 403 responses for both actions.
- The existing Customer Ledger Gate/policy remains unchanged; Staff and Manager ledger access is not reduced.
- Code is committed on the Batch 11A feature branch. Automated execution, Pint and CI are still pending; do not treat this correction as verified until those checks pass.

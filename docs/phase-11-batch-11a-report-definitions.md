# Phase 11 — Batch 11A: Report Definitions and Read-Only Data Contracts

**Status:** IN PROGRESS — specification and source mapping  
**Date:** 2026-11-27  
**Base:** `main` at `29a2c0f5eec868a2a581a493e681098fe7de1df7`  
**Branch:** `feature/phase-11-batch-11a-report-contracts`

## 1. Purpose and implementation boundary

Define stable, auditable report contracts before implementing report APIs or UI. This batch is documentation/specification and read-only source mapping only.

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

**Open Product Owner decision:** whether the default report includes every Customer with financial history (including zero/negative balances) or defaults to positive outstanding only. The contract must support an explicit filter if approved; no implicit balance exclusion is allowed.

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
- The proposed cash-collection metric is NORMAL receipts less reversals linked to NORMAL receipts. Sale-day automatic Payments and reversals of those automatic Payments are reported separately and excluded from cash collected.

**Open Product Owner decision:** confirm the proposed net cash-collection equation and confirm whether replacement Payments are counted as new receipts (the current record model suggests they are separate posted records). Before implementation, trace reversal source types and verify that each reversal can be paired unambiguously to its original Payment.

### 4.4 Interest Calculation / Posted Interest

**Calculation-detail grain:** one row per persisted Interest Calculation Item, with its parent Run.

**Candidate fields:** Customer, Sale number/date, trigger type, run status, exclusive cutoff date, interval start, interval end (exclusive), actual days, grace days applied, monthly rate, eligible principal basis, unrounded interest, posted interest, capitalization flag, policy version references, linked ledger entry.

**Posted-interest register grain:** one row per posted interest ledger effect, linked to the persisted calculation item where available. The linked ledger entry's effective date controls the posted-interest date filter. Include only completed runs and items with a valid linked ledger entry for posted totals.

**Read-only rule:** never recalculate interest for a report. Do not infer posted interest from policy settings or from an unposted calculation item. Keep calculation interval dates distinct from posting/effective dates.

**Current access baseline:** the existing Financial Workspace restricts the Interest section to Owner. Retain Owner-only visibility for calculation/policy detail unless the Product Owner explicitly approves an expanded role matrix. Posted interest already visible as part of the Customer Ledger remains governed by the ledger's existing permissions.

**Open Product Owner decision:** confirm whether Interest Calculation/Posted Interest reports remain Owner-only, or whether Manager receives read access. Staff's existing permission to view posted Customer Ledger entries does not by itself authorize access to detailed policy-version/calculation-run metadata.

## 5. Reconciliation equations and invariants

1. **Outstanding:** for each Customer and for the whole Shop, principal and interest equal the sum of corresponding ledger debits less credits through the selected `as_of` date. Total Outstanding = Principal + Interest.
2. **Sales:** Sales Register gross total equals the sum of eligible posted Sale amounts for the exact selected date/customer/status filters. It is not reduced by sale-day automatic Payments.
3. **Payments:** every displayed register row maps to exactly one posted Payment record. Cash-collection summary must reconcile NORMAL receipts and their linked reversals; sale-day automatic Payments are separately reconciled to their originating Paid Sales.
4. **Interest:** posted-interest totals must reconcile to linked persisted calculation items and their authoritative ledger entries. Unlinked calculation items must not inflate posted totals.
5. **Cross-report:** no report may create, update, delete, repair or post financial records. Reading a report must not trigger interest calculation or change projections.

## 6. Role matrix — existing baseline and pending decision

| Report/data | Owner | Manager | Staff | Basis |
|---|---|---|---|---|
| Customer Outstanding / Ledger-derived balances | Existing Shop-wide financial access | Existing Shop-wide financial access | Existing Shop-wide financial read access | ADR-0015 plus ledger policy |
| Sales Register | Allowed | Allowed | Allowed | ADR-0015 |
| Payment Register | Allowed | Allowed | Allowed | ADR-0015 |
| Interest calculation/policy detail | Allowed | Pending Product Owner confirmation; existing workspace is Owner-only | Not authorized by current workspace; any expansion requires explicit approval | Current FinancialWorkspaceController and ADR-0015 |
| Financial Audit / Reconciliation and repair | Existing Owner-only controls | Not allowed where current policy restricts | Not allowed | ADR-0015 and existing workspace authorization |

This matrix is a mapping of current approved access, not a new permission grant. The implementation must reuse the actual policies and verify each endpoint.

## 7. Decisions required before 11B implementation

1. Customer Outstanding default: include all customers with financial history (including zero/negative) or positive outstanding only?
2. Payment collection summary: approve NORMAL receipts minus linked NORMAL reversals; keep SALE_DAY_AUTO separate; confirm treatment of replacement Payments.
3. Interest report: keep Owner-only or grant Manager read access? Staff remains limited to posted ledger visibility unless explicitly expanded.
4. Confirm whether Customer Outstanding should show zero-balance customers by default and whether a positive-only quick filter is required.

If any source trace contradicts these definitions, stop and update this contract before implementation. No endpoint/UI implementation should begin for an unresolved metric.

## 8. Batch 11A acceptance checklist

- [x] Approved financial rules ADR-0012 reviewed.
- [x] Staff financial visibility ADR-0015 reviewed.
- [x] Customer lifecycle financial visibility ADR-0016 reviewed.
- [x] Existing Sales, Payments, Ledger and Interest API route/controller/model sources mapped.
- [x] Ledger date ordering, append-only behavior and decimal-safe balance method inspected.
- [x] Interest run/item persisted fields and linked ledger source inspected.
- [ ] Product Owner decisions in Section 7 recorded.
- [ ] Final report schemas and sample layouts approved.
- [ ] Report-specific reconciliation queries/equations verified against existing application services and MySQL integration tests.
- [ ] Batch 11A specification accepted before 11B implementation.

## 9. Safety

This document authorizes report-definition work only. It does not authorize migrations, new packages, API/UI implementation, exports, PDF generation, database writes, financial posting, projection repair, production operations, deployment or release. Phase 10 remains deferred; no Crop-Season Settlement reporting is included.

# Kisan Khata — Business Rules

**Document Version:** 2.2  
**Status:** Approved  
**Related Document:** `docs/01-product-requirements.md`

---

## 1. Purpose

This document defines the business rules governing:

- Shops and users.
- Customers.
- Credit sales.
- Payments.
- Consolidated outstanding balances.
- Interest calculations.
- Excess payments are rejected; customer advance balances are not supported.
- Crop-season settlements.
- Transaction corrections.
- Financial audit history.

These rules define the expected business behavior of Kisan Khata.

The accounting engine, database schema, APIs and user interface must follow these rules.

Developers and AI coding agents must not invent missing financial rules.

## 2. Core Business Model

1. A shop provides goods or services to customers on credit.
2. Each customer has one consolidated account within that shop.
3. Customers may make multiple credit purchases.
4. Customers may make multiple payments.
5. Interest may apply according to the shop's configured policy.
6. Payments reduce the customer's total payable amount.
7. Remaining outstanding continues in the customer's consolidated account.
8. Crop-season settlements may close or carry forward an account balance.
9. All financial activity must remain traceable through the customer ledger.

## 3. Shop and User Rules

1. Kisan Khata is a multi-tenant SaaS application.
2. Every shop is an independent tenant.
3. Every tenant-owned record must belong to one shop.
4. One user belongs to exactly one shop.
5. One user has exactly one role.
6. A shop supports one Owner, up to two Managers and up to five Staff members.
7. A user must never access another shop's data.
8. Subscription belongs to the shop, not an individual user.

### 3.1 Roles

**Owner**
- Full shop-level control.
- Can configure financial policies.
- Can request eligible controlled Sale revisions and correct posted Payments only through reversal/replacement; direct edits are prohibited.
- Can manage employees and subscriptions.

**Manager**
- Can manage customers, sales, payments and permitted reports.
- Can view the shop's Staff list and each Staff member's active/deactivated status only.
- Cannot create, deactivate, reactivate, or change the role of shop users.
- Cannot edit posted financial transactions.
- Cannot change ownership, subscription or critical security settings.

**Staff**
- Can view, add and edit customer profile information.
- Can record credit sales and payments.
- Can view permitted customer and transaction information.
- Cannot delete customers or edit/delete posted financial transactions.
- Cannot configure interest policies or subscriptions.

**Owner-only user administration**
- Only the Shop Owner can create Manager and Staff accounts, deactivate/reactivate Manager and Staff accounts, and change Manager/Staff roles.
- The Shop Owner cannot deactivate or demote the sole Owner account through ordinary shop-user management. Ownership transfer requires a separately authorized and audited process.
- Every user lifecycle and role-change action must be authorized server-side and audited.

Custom per-user permission configuration is not part of the initial release.

### 3.2 Active Capacity and User Lifecycle

1. Role limits are one active Owner, up to two active Managers and up to five active Staff members; the total active shop-user limit is eight.
2. Deactivated Manager and Staff accounts do not count toward role or total active-user capacity.
3. Deactivation retains the user account and its audit history; it does not delete historical activity.
4. Reactivation is permitted only when both the target role limit and total active-user limit remain available.
5. Creation, deactivation, reactivation and role changes must enforce capacity atomically so concurrent requests cannot exceed limits.
6. A Manager or Staff role change must check capacity for the destination role and release capacity in the source role within the same transaction.
7. The sole active Owner cannot be deactivated or demoted through ordinary shop-user management. Ownership transfer is a separate authorized and audited process.
8. Manager may view the Staff list and active/deactivated status, but cannot create, deactivate, reactivate or change roles.
9. Only the Owner may administer Manager and Staff accounts and change Manager/Staff roles.

### 3.3 Shop Geographic Location and Customer Defaults

1. The Shop Owner can change the shop's State, District, Subdistrict/Tehsil and Village through hierarchical select boxes backed by the shared LGD geographic master data.
2. The shop location is a convenience default only. It must not restrict customers to the shop's village, subdistrict or district.
3. When a new customer form opens, preselect the shop's current location wherever it is configured.
4. The authorized user may override the preselected State, District, Subdistrict and Village independently, subject to valid hierarchy relationships.
5. Changing a parent selection clears dependent child selections: State clears District/Subdistrict/Village; District clears Subdistrict/Village; Subdistrict clears Village.
6. A shop location change affects only defaults for subsequently opened customer forms. It must not modify any existing customer location.
7. Shop location changes must be authorized and audited as shop-profile changes.

## 4. Customer Rules

1. A customer belongs to exactly one shop.
2. A customer cannot be accessed by another shop.
3. Customer name is mandatory.
4. Mobile number is optional.
5. Customer location is optional and may be blank when unknown.
6. Customer location consists of optional State, District, Subdistrict/Tehsil and Village LGD codes.
7. Customer location is saved independently on the customer record; it is not a live reference to the shop's current location.
8. The shop's current location is used only to preselect location fields when creating a new customer.
9. An authorized user may override any preselected customer location.
10. Location hierarchy must be validated server-side against the shared geographic master data.
11. A customer can have multiple credit sales.
12. A customer can make multiple payments.
13. A customer has one consolidated account.
14. A customer ledger must contain all relevant financial activity.
15. Customer information changes must be auditable.
16. Customer deactivation must not delete financial history.

### 4.1 Customer Lifecycle

Customer lifecycle has exactly three states:

- **Active:** The customer is available in the default customer directory and may be selected for new transactions.
- **Inactive:** The customer profile and all history are retained, but the customer is excluded from the default customer directory and cannot be selected for new transactions.
- **Soft Deleted:** The customer is logically deleted using the existing soft-delete mechanism. The record and audit history remain retained and are not available in ordinary customer workflows.

#### Authorization and tenant boundary

1. Only the Shop Owner and Manager may deactivate, reactivate, soft-delete or restore a customer.
2. Staff may view, create and edit customer profile information as otherwise permitted, but may not perform any customer lifecycle action.
3. Every lifecycle action must be authorized server-side for both Web and API requests. Hiding a control in the UI is not authorization.
4. The target customer must belong to the authenticated user's current shop. Cross-shop lifecycle access must fail closed.
5. The client must not choose or override the authoritative shop ID, actor ID or lifecycle state.
6. Lifecycle operations must be transactional and protected against concurrent requests so the final state and audit history cannot contradict one another.

#### Deactivation

1. Owner or Manager may deactivate an Active customer.
2. A deactivation reason is mandatory and must not be blank.
3. The customer status becomes `inactive`; the customer is not physically deleted.
4. Customer profile, historical activity and financial history remain unchanged and retained.
5. Inactive customers are excluded from the default customer list and cannot be selected for new transactions.
6. The action must append an audit event containing the shop, actor, customer, action, previous and new lifecycle state, reason and timestamp.

#### Reactivation

1. Owner or Manager may reactivate an Inactive customer.
2. A reactivation reason is optional; if supplied, it must be retained in the audit event.
3. The customer status becomes `active`.
4. Existing profile, historical activity and financial history remain unchanged.
5. The customer becomes available in the default customer list and for new transactions.
6. The action must append an audit event containing the shop, actor, customer, action, previous and new lifecycle state, optional reason and timestamp.

#### Soft deletion

1. Owner or Manager may soft-delete a customer only when the customer has no associated financial or transaction history.
2. A deletion reason is mandatory and must not be blank.
3. If the customer has any Sales, Payments, Udhaar/Outstanding or Ledger records, soft deletion is prohibited. An Inactive status does not bypass this restriction.
4. The eligibility check must be designed as a centralized, extensible domain check so future financial modules and relationships are included when introduced. The absence of a relationship in the current codebase must not be treated as proof that the customer is safe to delete.
5. Soft deletion must set `deleted_at`; ordinary application workflows must never physically delete the customer.
6. The customer's prior Active or Inactive status must be preserved. Soft deletion is represented by the soft-delete marker and must not overwrite that status.
7. The action must append an audit event containing the shop, actor, customer, action, preserved prior state, soft-deleted state, reason and timestamp.
8. If eligibility cannot be established safely, deletion must fail closed.

#### Restore

1. Owner or Manager may restore a Soft Deleted customer.
2. Restore must clear `deleted_at` and preserve the customer's pre-deletion Active or Inactive status.
3. Restore must not automatically activate an Inactive customer.
4. Tenant ownership and applicable duplicate/uniqueness validation must be checked before restoration.
5. Existing profile, historical activity and financial history must remain unchanged.
6. The action must append an audit event containing the shop, actor, customer, action, previous soft-deleted state, restored lifecycle state and timestamp.

#### Audit and integrity

1. Lifecycle audit events are append-only and tenant-scoped.
2. Each event must identify the actor, customer, shop, action, previous/new state, timestamp and reason where required or supplied.
3. Audit records must not be updated or deleted through ordinary application workflows.
4. A failed lifecycle operation must not leave a partial customer-state change or a misleading success audit event.
5. Concurrent lifecycle requests must serialize or otherwise use safe locking so invalid transitions cannot both succeed.
6. No permanent-delete action is available in the normal customer UI or API.

### 4.2 Crop-Season Tagging

1. A sale may be associated with a crop season.
2. Examples include Kharif and Rabi.
3. Crop-season tagging is for classification and reporting.
4. It must not create a separate customer account.
5. It must not independently change the consolidated outstanding balance.

## 5. Credit Sale Rules

1. A sale must belong to a valid customer.
2. A sale must belong to the current shop.
3. Sale amount must be greater than zero.
4. Sale date is mandatory.
5. Description is supported.
6. Customer name and other customer information must be retrieved from the associated customer record.
7. Every sale must preserve its original effective date.
8. Every posted sale must be reflected in the customer ledger.
9. Every sale must be traceable to its creator.
10. A posted sale cannot be physically deleted.

### 5.1 Interest Eligibility

Each sale must retain enough information to determine its interest eligibility independently.

A new sale must not inherit the calculation start date of an older consolidated outstanding balance.

The applicable grace period and interest policy must be applied according to the approved shop or customer policy.

### 5.2 Sale Correction

A posted Sale may be revised only through a controlled revision workflow while it is not locked by a later posted normal Payment or annual Interest Auto Settlement. Preserve before/after values, actor, timestamp and mandatory reason. Never mutate the posted Sale directly. A locked Sale cannot be corrected in the current approved scope.

When a sale is edited:

1. Preserve its previous values.
2. Store the updated values.
3. Record the editing user.
4. Record correction timestamp.
5. Record correction reason.
6. Preserve immutable audit history.
7. Recalculate affected customer financial results.
8. Preserve the original correction history.

Managers and Staff cannot edit posted sales.

## 6. Payment Rules

1. A payment must belong to a valid customer.
2. A payment must belong to the current shop.
3. Payment amount must be greater than zero.
4. Payment date is mandatory.
5. Description may be recorded.
6. Payment method may be recorded.
7. Every payment must appear in the customer's ledger.
8. A payment must never be processed twice.
9. Customer-facing payments remain consolidated at customer-account level.
10. The backend internally allocates each normal payment FIFO across eligible Sales, then applies the effective Shop Principal First or Interest First waterfall within each Sale. These allocations are immutable accounting records, not separate customer-facing payments.
11. Payment processing must use the customer's consolidated payable amount.

### 6.1 Consolidated Payment Calculation

When a Payment is received:

1. Begin a database transaction and lock the customer account.
2. Validate current Shop/customer ownership and idempotency.
3. Calculate eligible interest through the Payment effective date for all earlier eligible unpaid Sales/components.
4. Mark the corresponding interest checkpoint Settled.
5. Recompute total payable (outstanding principal plus posted unpaid interest).
6. Reject the entire Payment before creating any Payment, allocation, ledger or projection posting if its amount exceeds total payable.
7. Apply the accepted amount internally FIFO across oldest eligible Sales; within each Sale apply the effective Shop Principal First or Interest First waterfall.
8. Persist the Payment, allocation and ledger postings atomically.
9. Reconcile the account projection and commit.

The customer ledger must preserve original Sales and Payment entries.

### 6.2 Multiple Payments on the Same Date

If multiple payments are recorded on the same date:

1. The first payment triggers the required interest calculation through that date.
2. The first payment is subtracted from the payable amount.
3. The remaining outstanding becomes the new consolidated balance.
4. A subsequent payment on that same date must not calculate interest again for the same elapsed period.
5. The subsequent payment is subtracted directly from the remaining balance.

Elapsed days between same-date calculations are zero.

### 6.3 Payment After New Sales

If new sales occur after a payment:

1. The previous carried-forward outstanding retains its calculation cycle start date.
2. Every new sale retains its own sale date.
3. At the next calculation, the old carried-forward balance accrues interest from its applicable cycle start date.
4. New sales accrue interest from their own eligible dates.
5. The payable amount combines all eligible outstanding components and applicable interest.

### 6.4 Excess Payment — Rejection

A payment must not exceed the complete payable amount (outstanding principal plus posted unpaid interest). If the submitted amount is greater than the payable amount, reject the entire payment before creating any payment, allocation, ledger or balance-projection posting. Do not accept only the payable portion. Customer advance balances and automatic future-sale adjustment are not supported.


### 6.5 Payment Correction

Posted Payment correction must use a reversal and optional replacement. The original Payment is immutable and must never be edited or physically deleted.

The system must:

- Preserve the original amount and date.
- Store the corrected amount and date.
- Record the reason.
- Record the user and timestamp.
- Preserve the previous and updated values.
- Recalculate affected customer balances and interest.
- Maintain immutable audit history.

Managers and Staff cannot edit posted payments.

## 7. Interest Configuration Rules

1. Every shop has a default monthly interest rate.
2. The Owner may configure a customer-specific interest rate.
3. The applicable rate must be identifiable for every calculation.
4. The grace period is configurable.
5. The Shop default grace period is 30 calendar days. Sale Date is Day 0; the first eligible interest day follows 30 complete calendar days. No Customer-specific grace-period override is supported.
6. Interest calculation must use the approved policy version.
7. Historical calculations must retain the policy used at that time.

## 8. Interest Calculation Rules

### 8.1 Approved Formula

The approved day-count convention is Actual Days / 30.

**Interest = Eligible Outstanding × (Monthly Rate / 100) × (Actual Days / 30)**

### 8.2 Calculation Triggers

Interest calculation occurs only:

1. When a customer makes a payment.
2. On either of the two annual Auto Settlement dates configured by the Shop Owner.

The system must not post interest through an automatic daily or monthly periodic process.

### 8.3 Annual Auto Settlement

1. The initial Shop dates are 30 March and 30 October each year; the Owner may configure the two dates.
2. The system calculates eligible interest on those dates.
3. Every calculation must be recorded.
4. Repeated execution must not duplicate the same interest.
5. A payment and Auto Settlement on the same date must not calculate the same elapsed period twice.

### 8.4 Calculation Basis

The system must distinguish:

- Previously carried-forward consolidated outstanding.
- New sales after the previous payment or calculation.
- No customer advance balance (excess payments are rejected).
- Previously calculated interest.
- Applicable policy and grace period.

New sales must retain their own effective dates.

### 8.5 Capitalization Policy

Two policies are supported in the product design.

**Policy A — Capitalized Interest**

- Calculated interest is added to outstanding.
- Future eligible interest may apply to the updated outstanding.
- This creates a compounding effect.

**Policy B — Separate Interest**

- Interest remains separately identifiable.
- Future interest is not charged on previously accrued interest.

The initial Shop capitalization default is OFF. A Customer may explicitly override it; when no override exists, the Shop's OFF setting applies. Changes are effective-dated and prospective only.

### 8.6 Interest Calculation History

Every calculation must preserve:

- Customer.
- Shop.
- Calculation date.
- Start date.
- End date.
- Actual elapsed days.
- Eligible outstanding.
- Applicable monthly rate.
- Grace-period treatment.
- Calculation formula.
- Policy version.
- Unrounded result.
- Rounded result.

## 9. Crop-Season Settlement Rules

The application supports:

- Full settlement.
- Partial settlement.
- Outstanding balance carry-forward.
- Next crop-season opening balance.
- Settlement history.

Rules:

1. Settlement must preserve previous financial transactions.
2. Settlement must not physically delete sales or payments.
3. Remaining outstanding must be traceable.
4. The next crop-season balance must be explainable.
5. Full settlement and partial settlement must be distinguishable.
6. Settlement operations must be auditable.

The following details remain pending approval:

- Whether interest is capitalized during settlement.
- Whether unpaid interest remains separate.
- Whether a new opening balance is created.
- Exact interest start date after settlement.
- Settlement correction and recalculation behavior.

## 10. Outstanding Balance Rules

The customer's payable amount must be derived from financial history.

The system must maintain distinguishable information for:

- Credit sales.
- Payments received.
- Carried-forward outstanding.
- Interest.
- No customer advance balance; excess payments are rejected.
- Settlements.
- Approved adjustments.

A manually editable balance field must never be the authoritative accounting source.

The consolidated customer balance must be reproducible from recorded financial activity and approved rules.

## 11. Transaction Date Rules

The system must distinguish:

- Effective transaction date.
- Creation timestamp.
- Posting timestamp.
- Correction timestamp.

Interest calculations use the approved effective dates.

Backdated transaction permissions and their exact recalculation behavior require a separately approved rule.

## 12. Transaction Correction Rules

1. Only the Shop Owner may initiate an eligible controlled Sale revision or Payment reversal/replacement.
2. Posted financial records are never directly edited or physically deleted.
3. A Sale revision is allowed only before a later posted normal Payment or annual Interest Auto Settlement locks that Sale; locked Sales cannot be corrected in the current approved scope.
4. A posted Payment correction uses a separate reversal and optional replacement Payment.
5. Every Sale revision preserves before/after values, actor, timestamp and mandatory reason.
6. Reversal/replacement and affected ledger/projection changes are atomic and auditable.
7. Managers and Staff cannot initiate posted financial transaction corrections.
9. A correction must not silently change unrelated customer accounts.

The exact recalculation behavior for historical backdated corrections must be documented before implementation.

## 13. Financial Audit Trail

Important financial actions must record:

- Shop ID.
- User ID.
- Entity type.
- Entity ID.
- Action.
- Previous values, where applicable.
- Updated values, where applicable.
- Correction reason, where applicable.
- Timestamp.
- Relevant calculation or transaction reference.

Audit records must be protected against modification by ordinary users.

Sensitive information must not be unnecessarily copied into audit logs.

## 14. Rounding and Decimal Precision

1. Financial values must use decimal-safe arithmetic.
2. PHP floating-point arithmetic must not be used for authoritative calculations.
3. Monetary precision and interest precision must be defined centrally.
4. Rounding must be deterministic.
5. The exact rounding mode and point of rounding remain pending approval.
6. Every calculation must retain enough precision to explain its result.

## 15. Tenant Isolation Rules

Every shop has independent:

- Customers.
- Sales.
- Payments.
- Customer ledgers.
- Interest policies.
- Settlements.
- Employees.
- Reports.
- Subscriptions.

Cross-tenant access is prohibited.

Every financial operation must validate that the customer and all related records belong to the current shop.

## 16. Idempotency and Concurrency

1. A financial operation must not be posted twice because of retries.
2. Duplicate payment requests must not create duplicate payments.
3. Interest calculation must be idempotent for the same customer and effective period.
4. Same-date payment calculations must not duplicate interest.
5. Concurrent financial operations must not corrupt outstanding balances.
6. The system must use appropriate database transactions and locking.
7. The final balance must remain consistent after concurrent operations.

## 17. Subscription Rules

1. A subscription belongs to a shop.
2. All users of a shop share its subscription.
3. Initial plans are Free Trial, Basic, Standard and Premium.
4. Default trial duration is three months and is database-configurable.
5. Monthly and annual billing are supported.
6. Subscription expiry has a three-day grace period.
7. After grace expiry, shop access becomes read-only.
8. Financial history must remain preserved.
9. The Owner retains PDF and Excel/CSV export access after expiry.

Detailed plan entitlements and pricing are maintained separately.

## 18. Data Retention

1. Financial records must be retained according to approved retention requirements.
2. Shop deactivation must not immediately delete financial records.
3. Deletion must follow a controlled retention process.
4. Backups must be protected.
5. The final retention period requires approval.

## 19. Unresolved Product Decisions

The following items remain unresolved and require Product Owner approval before the affected behavior is implemented:

1. Detailed crop-season settlement accounting.
2. Backdated transaction correction/recalculation beyond the approved Sale revision and Payment reversal/replacement rules.
3. Subscription plan entitlements and pricing.
4. Payment gateway.
5. Financial data retention period.

Interest defaults, rounding/date conventions, annual settlement dates, same-date ordering, FIFO waterfall and the no-advance rule are finalized by ADR-0012 and are not unresolved.

No developer or AI coding agent may silently resolve these decisions.

## 20. Business Rule Change Management

Any change to an approved business rule requires:

1. Product Owner approval.
2. Updated documentation.
3. Impact analysis.
4. Database migration or data correction plan, where necessary.
5. Updated automated tests.
6. Review of historical financial compatibility.

## 21. Business Rule Priority

If a feature request conflicts with an approved financial rule:

1. Preserve financial integrity.
2. Stop implementation of the conflicting behavior.
3. Explain the conflict.
4. Request Product Owner approval.
5. Update documentation after approval.


---

## 13. Product Owner-approved Financial Rules — Phase 5 (2026-11-25)

This section is the latest approved product decision record for Sales, Payments, Interest, Customer Ledger and annual Interest Auto Settlement. Where an earlier section in this document conflicts with this section, this section takes precedence. The superseding architecture decision is ADR-0012.

### 13.1 Sale payment choice

1. Sale creation must require an explicit payment state: `Paid` or `Unpaid`.
2. For `Unpaid`, the full sale amount enters the customer's outstanding principal.
3. For `Paid`, the system must create a separate automatic payment entry for exactly the sale amount in the same atomic database transaction as the sale.
4. The automatic payment must be permanently linked to the originating sale and must not affect any pre-existing customer balance.
5. A sale created as `Paid` is excluded from future outstanding and interest calculations, is visible in the ledger as already paid, and must never be charged or collected again.
6. A sale-day automatic payment does not trigger interest calculation or an interest settlement event for the customer's older outstanding.
7. If either the sale or its automatic payment cannot be posted, neither may be committed.
8. Payment status and interest settlement status are separate concepts. A sale may be Interest-Settled while still Unpaid or Partially Paid.

### 13.2 Sale revisions and edit lock

1. Posted sales are never physically deleted.
2. A permitted correction creates an immutable revision containing before/after values, actor, timestamp and mandatory reason.
3. Once a later customer payment or annual Interest Auto Settlement has been posted, sales effective before that event are locked against ordinary editing.
4. The lock must be enforced server-side, not only in the UI.
5. Any exceptional correction of a locked historical sale requires a separately designed and approved reversal/recalculation workflow; do not bypass the lock or silently rewrite posted history.
6. Manager and Staff cannot edit posted financial transactions. Owner-only correction authority remains subject to the lock above.

### 13.3 Normal customer payments, pre-payment interest settlement and FIFO

1. A normal payment is recorded once against the customer's consolidated account; the customer-facing payment is not manually assigned to one sale.
2. Before accepting/posting a normal payment, the system must complete and persist the required interest calculation through the payment's effective date for every earlier eligible unpaid sale/account component. This means Interest Settlement Status becomes `Settled`; it does not mean the sale's Payment Status becomes `Paid`.
3. A sale created as Paid on its sale date is excluded from this later settlement and payment calculation.
4. Normal payments are internally applied by FIFO (First In, First Out): oldest eligible unpaid sale/component first, then the next oldest. This internal allocation must not change original sale amounts or make the customer-facing payment a sale-specific payment.
5. Internal allocation records must preserve the payment, sale/component, applied principal, applied posted interest, ordering and effective timestamp. Allocation history is immutable; corrections use reversal/replacement records.
6. A payment may be split across multiple oldest-first sale components. Fully paid sales are excluded from later allocations.
7. The Shop Owner selects the payment waterfall in Shop Settings: `Principal First` or `Interest First`. The selected order is applied within the FIFO allocation process. The policy and its effective date must be recorded; changing it must not rewrite earlier allocations.
8. Payment amount must be greater than zero and must not exceed the customer's total payable balance (outstanding principal plus posted, unpaid interest). Excess payments are rejected. Customer advances and automatic future-sale advance application are not supported.
9. Concurrent payment requests must serialize on the customer financial account and re-check the payable balance inside the transaction before posting.

### 13.4 Payment history and corrections

1. A posted payment is never physically deleted.
2. A mistaken payment is corrected through an auditable reversal and, where appropriate, a replacement payment; preserve the original payment and its allocations.
3. Record actor, timestamp, mandatory correction reason, original transaction reference and reversal/replacement references.
4. Managers and Staff cannot correct posted payments. Owner-only correction authority is enforced server-side.
5. Reversals must reverse corresponding internal allocations and rebuild affected balances/interest without mutating historical entries.

### 13.5 Shop and customer interest settings

1. Shop default monthly interest rate is `0%`.
2. Owner may change the Shop default monthly rate in Shop Settings.
3. A customer may have an optional customer-specific monthly rate configured during customer creation or editing. When present, it takes precedence over the Shop default.
4. Shop default grace period is `30 calendar days`; Owner may change it in Shop Settings. No customer-specific grace-period override is approved.
5. Shop Settings must hold two annual Interest Auto Settlement dates, both configured by the Owner.
6. Shop Settings must hold the payment waterfall: `Principal First` or `Interest First`.
7. Capitalization is configurable at Shop level and may be overridden at Customer level. If the customer has no explicit override, the Shop setting applies.
8. Shop and customer interest settings must be effective-dated/versioned. A rate or policy change applies from its effective date forward; prior periods retain the policy version that applied then.
9. A capitalization toggle affects only interest calculated for periods from its effective date onward. It must not retroactively reclassify previously posted interest.

### 13.6 Interest calculation conventions

1. Formula: `Eligible Principal × (Monthly Rate / 100) × (Actual Eligible Days / 30)`.
2. Use Actual Days / 30.
3. Round the posted interest amount to two decimal places using decimal-safe arithmetic and `HALF_UP` rounding. Preserve sufficient unrounded calculation precision for reproducibility.
4. Interest periods use a half-open date interval: start date inclusive, end date exclusive. Adjacent periods must not count the same day twice.
5. Sale date is Day 0 for grace-period counting. With the default 30-day grace period, the first eligible interest day is after 30 complete calendar days have elapsed.
6. Monthly rate defaults to zero; a zero rate produces zero interest.
7. Interest capitalization ON adds posted eligible interest to the future interest-bearing base according to the effective-dated policy. OFF keeps interest separate and excludes previously accrued interest from the future interest-bearing principal.
8. Interest calculation events are (a) normal customer payment, after calculating through that payment date and before applying the payment, and (b) the two Owner-configured annual Auto Settlement dates.
9. A sale-day automatic payment for a Paid sale is not a normal customer payment event and does not trigger interest calculation for older balances.
10. On a date shared by a normal payment and annual Auto Settlement, process the normal payment first; the annual settlement must not duplicate the same elapsed interval.
11. No ordinary daily or monthly interest-posting job is permitted.

### 13.7 Interest settlement status and annual settlement

1. Interest settlement status (`Pending` / `Settled`) is distinct from payment status (`Unpaid` / `Partially Paid` / `Paid`).
2. `Settled` means the required interest calculation through the relevant event date has been completed and recorded. It does not mean the sale or customer balance is fully paid.
3. Annual Auto Settlement dates are configured by the Shop Owner in Shop Settings.
4. Each settlement run must retain customer, effective date, eligible components, rate/policy versions, grace-period treatment, eligible days, principal/interest basis, unrounded and rounded results, posting references and idempotency identity.
5. Re-running the same event must not duplicate interest.

### 13.8 Ledger and balance invariants

1. Original sale amounts never decrease when a payment is received. Remaining balances are derived from immutable sale, payment, allocation, interest and reversal records.
2. Paid sales and their linked automatic payment remain visible in the ledger with an explicit `Already Paid` indication; their net outstanding contribution is zero.
3. Normal customer payments appear as one consolidated customer-level transaction. Internal FIFO allocations are supporting accounting records, not separate customer-facing payment transactions.
4. Ledger entries are append-only. Corrections use reversals and replacement entries; no posted financial history is silently overwritten.
5. Every financial record and allocation is tenant-scoped, auditable, idempotent and transactionally consistent.
6. Crop-season settlement is a separate business workflow from the annual Interest Auto Settlement event. Do not conflate their meanings.



### 13.9 Product Owner clarification — finalized defaults (2026-11-25)

The Product Owner has finalized the two previously open configuration values:
- Initial Shop capitalization default is **OFF**. A Customer may explicitly override it; absent an override, OFF is inherited.
- The two initial annual Interest Auto Settlement dates are **30 March** and **30 October** each year, configured as the Shop's two annual settlement dates. Owner configuration remains available under the approved settings model.


### 13.10 Convention approval boundary (2026-11-25)

The Product Owner explicitly approved Actual Days / 30 and two-decimal posted interest. The following detailed conventions were proposed during documentation drafting but have **not** yet received explicit Product Owner confirmation; treat them as blueprint recommendations, not immutable approved requirements:
- HALF_UP as the 2-decimal rounding mode.
- [start inclusive, end exclusive) interval boundary.
- Sale Date as grace-period Day 0 and first eligible day after 30 complete calendar days.
The blueprint must show these proposals and obtain confirmation before implementation. Do not represent them as final PO-approved decisions.


### 13.11 Product Owner-approved interest and allocation conventions (2026-11-25)

The Product Owner explicitly approved the following conventions; they are binding requirements, not proposals:

1. **Rounding:** HALF_UP to two decimal places for posted INR interest. Preserve sufficient decimal precision before final posting.
2. **Interest interval:** [start date inclusive, end date exclusive). Adjacent calculation periods must not count the same day twice.
3. **Grace-period counting:** Sale Date is Day 0. With a 30-calendar-day grace period, the first eligible interest day is after 30 complete calendar days have elapsed.
4. **Allocation order:** Apply FIFO at Sale level first. Within each Sale, apply the Shop's effective-dated Principal First or Interest First waterfall to that Sale's eligible outstanding components. Do not apply a global account-wide waterfall before FIFO.
5. **Locked Sale correction:** A Sale locked by a later posted Payment or annual Interest Auto Settlement cannot be exceptionally edited or recalculated under the current approved scope. Stop and require a separate explicit Product Owner approval and a separately designed workflow. No automatic Owner bypass.

These conventions apply to all new financial calculations and allocation postings. Historical posted records are not silently recomputed.

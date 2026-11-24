# Kisan Khata — Business Rules

**Document Version:** 2.1  
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
- Customer advances.
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
- Can edit posted sales and payments.
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

## 4. Customer Rules

1. A customer belongs to exactly one shop.
2. A customer cannot be accessed by another shop.
3. Customer name is mandatory.
4. Mobile number is optional.
5. Village is optional.
6. A customer can have multiple credit sales.
7. A customer can make multiple payments.
8. A customer has one consolidated account.
9. A customer ledger must contain all relevant financial activity.
10. Customer information changes must be auditable.
11. Customer deactivation must not delete financial history.

### 4.1 Crop-Season Tagging

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

The Shop Owner can directly edit a posted sale.

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
9. Payments are not allocated to individual sale IDs.
10. Payments are not divided into interest and principal components for payment allocation.
11. Payment processing must use the customer's consolidated payable amount.

### 6.1 Consolidated Payment Calculation

When a payment is received:

1. Identify the customer's current financial state.
2. Calculate all eligible interest through the payment date.
3. Include the existing outstanding and applicable new sales.
4. Determine the complete payable amount.
5. Subtract the received payment.
6. Carry forward the remaining amount as consolidated outstanding.
7. Set the new calculation cycle start date for that carried-forward balance to the payment date.

The customer ledger must preserve the original sales and payment entries.

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

### 6.4 Excess Payment and Customer Advance

If a payment exceeds the complete payable amount:

1. Outstanding becomes zero.
2. The excess amount becomes a customer advance.
3. The advance must be separately identifiable in the ledger.
4. The advance must not be treated as negative sales.
5. The advance must remain available for future account adjustments.

The exact automatic adjustment behavior against future sales requires a separately approved rule.

### 6.5 Payment Correction

The Shop Owner can directly edit a posted payment.

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
5. Default grace period is zero days.
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

1. The Owner configures two annual dates.
2. The system calculates eligible interest on those dates.
3. Every calculation must be recorded.
4. Repeated execution must not duplicate the same interest.
5. A payment and Auto Settlement on the same date must not calculate the same elapsed period twice.

### 8.4 Calculation Basis

The system must distinguish:

- Previously carried-forward consolidated outstanding.
- New sales after the previous payment or calculation.
- Customer advances.
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

The default policy and exact transition behavior remain pending final approval.

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
- Customer advances.
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

1. Only the Shop Owner can edit posted sales and payments.
2. Managers and Staff cannot edit posted financial transactions.
3. Physical deletion of posted financial history is prohibited.
4. Every correction must preserve previous and updated values.
5. Every correction must include the acting user and timestamp.
6. A correction reason must be recorded.
7. Affected balances and interest must be recalculated.
8. The audit history must remain immutable.
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

The following items must be finalized before implementation of the affected behavior:

1. Interest capitalization default: Policy A or Policy B.
2. Exact interest rounding precision and rounding mode.
3. Interest effective-date and day-boundary conventions.
4. Grace-period eligibility boundary.
5. Same-date payment and Auto Settlement execution ordering.
6. Detailed crop-season settlement accounting.
7. Automatic adjustment of customer advances against future sales.
8. Backdated transaction correction and recalculation rules.
9. Subscription plan entitlements and pricing.
10. Payment gateway.
11. Financial data retention period.

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

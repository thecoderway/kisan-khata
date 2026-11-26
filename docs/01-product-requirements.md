# Kisan Khata — Product Requirements Document (PRD)

**Document Version:** 2.1  
**Product:** Kisan Khata  
**Product Type:** Multi-Tenant SaaS Accounting Application  
**Initial Platform:** Responsive Web Application  
**Future Platform:** Android Application  
**Backend:** Laravel  
**Database:** MySQL  
**Supported Languages:** Hindi and English  
**Status:** Approved

---

## 1. Product Overview

Kisan Khata is a cloud-based accounting and customer credit management SaaS application designed for small and medium-sized merchants in Indian towns and villages.

Many merchants sell agricultural products and other goods to customers on credit and receive payments later, often around crop harvesting seasons.

Kisan Khata will replace handwritten account books with a digital platform that provides:

- Customer management.
- Credit sales recording.
- Payment recording.
- Consolidated outstanding balances.
- Interest calculation.
- Crop-season settlements.
- Complete financial history.
- Printable customer ledgers.
- Shop employee management.
- Subscription-based access.

The application must be simple enough for merchants with limited technical knowledge.

## 2. Product Goals

1. Replace manual credit notebooks.
2. Maintain accurate customer-wise financial accounts.
3. Record credit sales and payments.
4. Calculate interest using shop-approved policies.
5. Maintain consolidated customer outstanding balances.
6. Support partial and full settlements.
7. Support crop-season-based account management.
8. Provide transparent financial calculations.
9. Support Hindi and English.
10. Allow independent shops to operate securely on one SaaS platform.
11. Generate revenue through subscriptions.
12. Prepare the backend for a future Android application.

## 3. Target Users

### 3.1 Primary Shop Categories

- Agricultural input shops.
- Fertilizer retailers.
- Seed retailers.
- Pesticide retailers.
- Cloth merchants.
- Electronics retailers.
- General merchants.
- Other businesses offering customer credit.

### 3.2 Typical Customer

A farmer or other customer who purchases products on credit and pays the outstanding amount later.

## 4. SaaS and Shop Structure

Kisan Khata will operate as a multi-tenant SaaS application.

### 4.1 Tenancy Model

- Use one shared MySQL database.
- Each shop represents one independent tenant.
- All tenant-owned records must be associated with `shop_id`.
- One shop must never access another shop's financial or customer data.
- Tenant isolation must be enforced at application, service and database levels wherever applicable.

### 4.2 User–Shop Relationship

The approved relationship is:

**1 User = 1 Shop = 1 Role**

- A user belongs to only one shop.
- A user has only one role within that shop.
- Multiple-shop membership is not part of the initial product.
- Shop switching is not required.

### 4.3 Shop Capacity

Each shop supports:

- 1 Owner.
- Maximum 2 Managers.
- Maximum 5 Staff members.

These are fixed initial product limits, not independently configurable staff permissions. Capacity is measured against active shop users. Deactivated Manager and Staff accounts do not consume role or total active-user capacity; their account and audit history remain retained. Reactivation is allowed only when the relevant role and total active-user capacities are available. A shop must retain exactly one active Owner. The Owner account cannot be deactivated or demoted through ordinary shop-user management; ownership transfer requires a separately authorized and audited process.

## 5. User Roles and Permissions

### 5.1 Platform Owner

Responsible for the overall SaaS platform.

Capabilities:

- Manage shops.
- Manage subscription plans.
- Manage subscriptions.
- Suspend or reactivate shops.
- Monitor platform usage.
- View platform-level audit information.

Platform administrators must not automatically receive unrestricted access to tenant financial records.

### 5.2 Shop Owner

The owner has complete control over the shop.

Capabilities:

- Manage shop profile and settings.
- Manage customers.
- Create and manage sales.
- Record and manage payments.
- View complete customer ledgers.
- Configure interest policies.
- Configure annual Auto Settlement dates.
- Correct posted transactions only through the approved Sale revision workflow or Payment reversal/replacement workflow; direct edits are prohibited.
- View audit history.
- Manage Manager and Staff accounts: create, deactivate, reactivate, and change Manager/Staff roles.
- View and manage shop users' active status and role history.
- Manage settlements.
- Generate reports.
- Manage subscription and billing.
- Export shop data.

**Owner protection:** The Shop Owner cannot deactivate or demote their own Owner account through ordinary shop-user management. Ownership transfer is a separate, explicitly authorized and audited process.

### 5.3 Shop Manager

Maximum 2 Managers per shop.

Capabilities:

- Manage customers.
- Record credit sales.
- Record payments.
- View customer ledgers.
- View reports.
- View the Staff list and each Staff member's active/deactivated status only.

Manager cannot create, deactivate, reactivate, or change the role of any shop user.

Restrictions:

- Cannot manage subscriptions.
- Cannot change shop ownership.
- Cannot change critical security settings.
- Cannot edit posted financial transactions.
- Cannot change critical financial policies unless explicitly approved in a future product decision.

### 5.4 Shop Staff

Maximum 5 Staff members per shop.

Capabilities:

- View customers.
- Add customers.
- Edit existing customer profile information.
- Record credit sales.
- Record payments.
- View own work and transaction history.
- View permitted customer information.

Restrictions:

- Cannot delete customers or edit/delete posted financial transactions.
- Customer profile editing does not grant permission to alter sales, payments, ledger entries, or other financial history.
- Cannot configure interest policies.
- Cannot manage subscriptions.
- Cannot modify shop ownership or critical settings.
- Access to sensitive reports is restricted.

Custom per-user permission configuration is not included in the initial release.

## 6. Authentication and Registration

The system must support:

- Shop self-registration.
- Email verification.
- Mobile verification.
- Mobile OTP login.
- Email and password login.
- Logout.
- Password reset.
- Secure session management.

The shop owner creates Manager and Staff accounts.

A user account must be associated with only one shop and one role.

## 7. Shop Profile

Shop profile fields:

- Shop name.
- Owner name.
- Mobile number.
- Email.
- Address.
- Geographic location: State, District, Subdistrict/Tehsil and Village.
- PIN code.
- Preferred application language.
- Logo (optional).

### 7.1 Shop Geographic Location

- The Shop Owner can view and change the shop's State, District, Subdistrict/Tehsil and Village using hierarchical select boxes.
- The available location options must come from the shared LGD geographic master data, not manually typed or translated free-text values.
- Selecting a State filters District options; selecting a District filters Subdistrict options; selecting a Subdistrict filters Village options.
- Changing a parent selection clears all dependent child selections.
- The current imported master dataset covers Madhya Pradesh only. Do not represent it as an all-India dataset.
- Shop location is a default-selection convenience for customer creation only. It is not a restriction on which locations the shop may serve and is not a customer ownership or discovery boundary.
- Updating the shop location affects future customer-form defaults only; it must not update existing customers.

Shop information must appear on printable customer ledgers and receipts.

## 8. Customer Management

### 8.1 Customer Information

Required:

- Customer name.

Optional:

- Mobile number.
- Geographic location: State, District, Subdistrict/Tehsil and Village.
- Address.
- Notes.

The application must not require Father's name or other unnecessary information to create a customer.

Customer location is independently stored on the customer record. When creating a customer, the form initially preselects the shop's current location where available. The authorized user may change any location selection or leave the location blank when unknown. The saved customer location must not remain dynamically linked to the shop location.

### 8.2 Customer Operations

- Add customer.
- View customer.
- Edit customer details.
- Search customers.
- Filter customers.
- View customer ledger.
- View consolidated outstanding balance.
- View financial history.
- Tag sales with crop seasons.

### 8.3 Customer Account Structure

Each customer has one consolidated account within a shop.

The account must maintain:

- Original credit sales.
- Payments received.
- Outstanding balance.
- Interest calculations.
- Rejected excess payments; no customer advance balance.
- Settlements.
- Crop-season history.

Customer-facing payments remain consolidated; internal FIFO allocation across eligible Sales is required under ADR-0012.

### 8.4 Crop Season

Sales should support crop-season tagging, such as:

- Kharif.
- Rabi.
- Other configurable crop seasons.

Season tagging must not create separate customer accounts or break the consolidated outstanding balance.

## 9. Credit Sales Management

A sale represents goods or services provided to a customer on credit.

### 9.1 Sale Fields

- Customer.
- Sale date.
- Sale amount.
- Description.

### 9.2 Sale Operations

- Create sale.
- View sale.
- Edit sale.
- View sale history.
- Search sales.
- Filter by customer.
- Filter by date.
- View sale details.

Every sale must preserve its original transaction date because interest eligibility depends on the sale date.

### 9.3 Posted Sale Correction

The Shop Owner may request a controlled revision only while the Sale is not locked by a later posted normal Payment or annual Interest Auto Settlement. A revision must preserve immutable before/after values, actor, timestamp and mandatory reason. Direct mutation of a posted Sale is prohibited. Locked Sales cannot be corrected in the current approved scope.

When editing:

- Preserve the original values.
- Store the new values.
- Record the user who performed the edit.
- Record the date and time.
- Record the correction reason.
- Maintain immutable audit history.
- Recalculate affected customer balances and interest.

Managers and Staff cannot edit posted sales.

Physical deletion of posted financial history is prohibited.

## 10. Payment Management

A payment represents money received from a customer.

### 10.1 Payment Fields

- Customer.
- Payment date.
- Payment amount.
- Description.

Payment method may be recorded as additional information.

### 10.2 Payment Operations

- Record payment.
- View payment.
- Edit payment.
- View payment history.
- Print payment receipt.

### 10.3 Consolidated Payment Calculation

Customer-facing payments remain consolidated at customer-account level. The backend must record immutable internal FIFO allocations across eligible Sales; within each Sale, apply the effective Shop Principal First or Interest First waterfall. These internal allocations are not separate customer-facing payment transactions.

The payment process follows this sequence:

1. Lock the customer account within the posting transaction.
2. Calculate eligible interest through the Payment effective date for all earlier eligible unpaid Sales/components.
3. Mark the corresponding interest checkpoint Settled.
4. Recompute the complete payable amount (outstanding principal plus posted unpaid interest).
5. Reject the entire Payment before creating any Payment, allocation, ledger or projection posting if its amount exceeds total payable.
6. Apply the accepted amount internally FIFO across oldest eligible Sales; within each Sale apply the effective Shop Principal First or Interest First waterfall.
7. Persist Payment, allocation and ledger postings atomically, reconcile the account projection, and commit.

### 10.4 Multiple Payments on the Same Date

Interest must not be calculated repeatedly for the same elapsed period.

Example:

- First payment: Calculate interest through that date.
- Subtract the first payment from total payable.
- Second payment on the same date: No additional interest because elapsed days are zero.
- Subtract the second payment directly from the remaining outstanding.

### 10.5 Excess Payment

A payment must not exceed the customer's total payable balance (outstanding principal plus posted unpaid interest). If the submitted amount exceeds the payable balance, reject the entire payment before posting it. Do not accept a partial amount, create a customer advance, or carry an excess balance forward.


### 10.6 Posted Payment Correction

Posted Payment corrections must use a reversal and optional replacement Payment. The original Payment is never edited or deleted; each correction is immutable, auditable and transactionally reconciled.

The system must preserve:

- Original amount and date.
- New amount and date.
- Previous and updated values.
- Correction reason.
- User performing the edit.
- Timestamp.
- Recalculated financial results.

Managers and Staff cannot edit posted payments.

## 11. Customer Ledger and Outstanding Balance

Every customer must have one consolidated financial ledger.

### 11.1 Ledger Information

- Opening balance.
- Credit sales.
- Payments.
- Interest calculations.
- Rejected excess payments (no advance balance).
- Settlements.
- Adjustments.
- Closing balance.

### 11.2 Ledger Requirements

- Complete chronological history.
- Running outstanding balance.
- Date range filtering.
- Crop-season filtering.
- Clear transaction descriptions.
- Interest calculation explanations.
- Printable format.
- Hindi and English labels.

### 11.3 Financial History

The ledger must preserve the original transaction history even when an Owner corrects a posted transaction.

The current financial position must always be reproducible from the transaction history and approved calculation rules.

## 12. Interest Configuration and Calculation

### 12.1 Interest Configuration

Each shop has a default monthly interest rate.

The Shop Owner can optionally configure a different interest rate for an individual customer.

Configuration includes:

- Monthly interest rate.
- Customer-specific rate, if applicable.
- Grace period in days.
- Interest capitalization policy.
- Annual Auto Settlement dates.

The default grace period is zero days.

### 12.2 Calculation Formula

The approved day-count convention is Actual Days / 30.

Formula:

**Interest = Eligible Outstanding × (Monthly Rate / 100) × (Actual Days / 30)**

The calculation must use precise decimal arithmetic.

PHP floating-point arithmetic must never be used for authoritative financial calculations.

### 12.3 Interest Calculation Triggers

Interest is not posted through an automatic daily or monthly background process.

Interest calculation occurs only at these events:

1. When a customer makes a payment.
2. On either of the two annual Auto Settlement dates configured by the Shop Owner.

At these events, the system calculates all eligible interest through the applicable date.

### 12.4 Annual Auto Settlement Dates

The Shop Owner can configure two dates per year.

The system must:

- Execute the interest calculation for eligible customer accounts on these dates.
- Avoid duplicate interest calculation for the same date and period.
- Maintain calculation history.
- Record the applicable interest policy version.
- Preserve the previous outstanding balance and calculated results.

### 12.5 Same-Date Calculation Protection

If a payment and Auto Settlement occur on the same date, the same elapsed period must not generate interest twice.

The calculation must be idempotent.

### 12.6 New Sales After a Payment

After a payment, the consolidated carried-forward balance starts a new calculation cycle from the payment date.

However, new sales recorded after that payment must retain their individual sale dates.

At the next interest calculation:

- The carried-forward outstanding accrues interest from the previous payment or calculation date.
- Each new sale accrues interest from its own eligible sale date.
- The total payable is calculated by combining these components.

### 12.7 Interest Capitalization

The application should support two Owner-selectable policies:

**Policy A — Capitalized Interest**

- Calculated interest is added to the outstanding balance.
- Future interest may be calculated on the updated outstanding amount.
- This creates a compounding effect.

**Policy B — Separate Interest**

- Interest remains separately identifiable.
- Future interest is not charged on previously accrued interest.

The final default policy and detailed transition behavior require explicit approval before implementation.

### 12.8 Calculation History

Every calculation must preserve:

- Calculation date.
- Applicable interest rate.
- Calculation start date.
- Calculation end date.
- Actual elapsed days.
- Eligible outstanding amount.
- Formula used.
- Calculated interest.
- Interest policy version.
- Customer account reference.

## 13. Crop-Season Settlement

The application must support:

- Full settlement.
- Partial settlement.
- Outstanding balance carry-forward.
- Next crop-season opening balance.
- Settlement history.

A settlement must preserve all original sales, payments and interest history.

The settlement process must not silently close or delete earlier financial records.

Detailed settlement accounting and correction behavior must follow the approved business rules.

## 14. Reports

Initial reports:

1. Customer list.
2. Outstanding balance report.
3. Customer-wise ledger.
4. Credit sales report.
5. Payment report.
6. Interest report.
7. Crop-season report.
8. Settlement report.
9. Daily collection report.
10. Date-wise transaction report.

All reports must be shop-scoped.

## 15. Printing and Data Export

### 15.1 Customer Ledger Printing

The printable ledger should include:

- Shop name and contact information.
- Customer name.
- Mobile number.
- Village.
- Selected date range.
- Opening balance.
- Sales.
- Payments.
- Interest.
- Rejected excess payments (no advance balance).
- Running balance.
- Closing balance.
- Print date.

Use A4-friendly formatting.

Hindi text must render correctly.

### 15.2 Data Export

The Shop Owner must be able to export shop data in:

- PDF.
- Excel-compatible format.
- CSV.

Export access must remain available after subscription expiry, including during read-only access.

## 16. Subscription Management

Subscriptions belong to a shop, not individual users.

All users of a shop share the same subscription.

### 16.1 Initial Plans

Four plans:

1. Free Trial.
2. Basic.
3. Standard.
4. Premium.

Plan pricing and detailed entitlements will be defined separately.

### 16.2 Free Trial

- Default duration: 3 months.
- Duration must be configurable through the database.
- Trial is associated with the shop subscription.

### 16.3 Billing

Support:

- Monthly subscription.
- Annual subscription.
- Optional annual discount.
- Payment history.
- Renewal.

The initial payment gateway is not finalized. UPI is the likely preferred option.

### 16.4 Expiry and Grace Period

- Subscription expiry initiates a 3-day grace period.
- After the grace period, shop access becomes read-only.
- Financial records must not be deleted or altered because of subscription expiry.
- Shop Owner must retain PDF and Excel/CSV data export access.

## 17. Language and Translation

The application interface must support:

- Hindi.
- English.

### 17.1 Customer Data

Customer-entered information must be preserved in its original language.

The system must also maintain translated Hindi and English versions.

For example:

- Original customer name.
- Hindi translated name.
- English translated name.

The original text must never be overwritten by automatic translation.

Translated values should be correctable where translation quality is insufficient.

The same approach should be considered for customer descriptions and other relevant user-entered text.

## 18. Platform and Technology Requirements

### 18.1 Web Application

- Responsive web application.
- Desktop and mobile browser support.
- Simple forms.
- Readable controls.
- Hindi and English interface.

### 18.2 Backend

- Laravel-based backend.
- Modular monolith architecture.
- Shared MySQL database.
- Centralized business logic.
- Secure tenant isolation.
- Versioned REST API.

### 18.3 Future Android Application

Android is planned for a future phase.

The backend must be designed to support:

- Future Android authentication.
- Versioned API endpoints.
- Reusable financial services.
- Customer and transaction synchronization.

Offline synchronization is not part of the initial web release, but the architecture should avoid unnecessary barriers to supporting it later.

The Android application must never independently calculate authoritative interest or customer balances.

## 19. Security, Audit and Reliability

### 19.1 Tenant Security

- Strict shop-level data isolation.
- Server-side tenant resolution.
- Ownership validation.
- Authorization for all financial operations.
- Protection against cross-tenant access.

### 19.2 Audit Trail

Important actions must be auditable, including:

- Customer changes.
- Sale creation and correction.
- Payment creation and correction.
- Interest policy changes.
- Interest calculations.
- Settlements.
- User and role changes.
- Subscription changes.
- Security-sensitive actions.

Audit history must be protected from ordinary users and must not be silently modified.

### 19.3 Backup and Recovery

- Daily automated database backups.
- Encrypted off-site backups.
- Regular restore testing.
- Documented recovery procedure.

### 19.4 Data Retention

Financial history must be retained according to the approved retention policy and applicable legal requirements.

Shop deactivation and eventual deletion must follow a controlled process.

The exact retention period requires separate approval.

## 20. Initial Release Scope

The initial web release includes:

- Authentication.
- Shop registration.
- Shop and user management.
- Tenant isolation.
- Owner, Manager and Staff roles.
- Customer management.
- Crop-season tagging.
- Credit sales.
- Payments.
- Consolidated customer ledger.
- Interest configuration.
- Interest calculation engine.
- Annual Auto Settlement configuration.
- Excess-payment rejection; no customer advance balance.
- Crop-season settlements.
- Basic reports.
- Ledger printing.
- Data export.
- Subscription foundations.
- Audit history.
- Daily backups.

## 21. Excluded From Initial Release

Unless separately approved:

- Android application.
- Offline synchronization.
- Inventory management.
- GST accounting.
- Full double-entry accounting.
- Payroll.
- E-commerce.
- Automated WhatsApp messaging.
- Banking integrations.
- Custom per-user permission configuration.
- Multiple-shop membership for a single user.

## 22. Success Criteria

The initial product must allow a Shop Owner to:

1. Register a shop.
2. Create Manager and Staff accounts within shop limits.
3. Add customers.
4. Record credit sales.
5. Record payments.
6. View consolidated customer outstanding.
7. Understand interest calculations.
8. Handle multiple payments on the same date without duplicate interest.
9. View new sales and carried-forward balances correctly.
10. Reject payments that exceed total payable.
11. Perform full and partial settlements.
12. View complete customer financial history.
13. Correct posted transactions with audit history.
14. Print customer ledgers.
15. Export shop data.
16. Use the application in Hindi or English.
17. Access only authorized shop records.

---

## 23. Decisions Requiring Final Approval

The following items must be finalized before their respective implementation:

1. Interest capitalization default: Policy A or Policy B.
2. Exact interest rounding precision and rounding mode.
3. Interest effective-date and day-boundary conventions.
4. Grace-period eligibility boundary.
5. Detailed Auto Settlement behavior when a customer has already had a payment calculation on that date.
6. Detailed crop-season settlement accounting.
7. Translation provider and correction workflow.
8. Subscription plan prices and entitlements.
9. Payment gateway.
10. Financial data retention period.
11. Hosting region.

No developer or AI coding agent may silently decide these financial or business rules.

Any change to an approved requirement must be documented and approved by the Product Owner before implementation.

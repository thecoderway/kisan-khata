# Kisan Khata — Database Architecture

**Document Version:** 2.0  
**Status:** Approved  
**Database:** MySQL 8.4  
**Architecture:** Shared Database, Multi-Tenant  
**Backend:** Laravel 13

---

## 1. Database Design Principles

1. Use relational database design.
2. Use MySQL 8.4.
3. Use foreign keys wherever appropriate.
4. Every tenant-owned record must be associated with `shop_id`.
5. Use DECIMAL for financial amounts.
6. Preserve financial transaction history and audit history.
7. Do not store manually editable balances as authoritative accounting data.
8. Use Laravel migrations for schema changes.
9. Use database transactions for financial operations.
10. Design for future versioned API and Android support.
11. Enforce tenant isolation in application services and database relationships.
12. Avoid duplicate financial effects.

All table and column names are proposed. Final implementation must validate relationships, constraints and indexes against actual application requirements.

## 2. Core Table Groups

### Identity and Tenancy
- `users`
- `shops`
- `shop_settings`

### Customer Management
- `customers`

### Sales
- `sales`
- `sale_revisions`
- `sale_attachments`

### Payments
- `payments`
- `payment_revisions`
- `payment_attachments`

### Accounting
- `financial_transactions`
- `ledger_entries`
- `customer_accounts`
- `account_balance_snapshots`

### Interest
- `interest_policies`
- `interest_policy_versions`
- `interest_calculation_runs`
- `interest_calculation_items`

### Settlements
- `settlements`
- `settlement_items`

### Audit and SaaS
- `audit_logs`
- `subscription_plans`
- `subscriptions`
- `subscription_payments`
- `idempotency_keys`

**Important:** There is no `payment_allocations` table in this architecture.

## 3. users

Purpose: Store platform and shop user accounts.

Suggested columns:

- `id`
- `shop_id` — nullable only for platform-level accounts, if required
- `name`
- `email` — nullable, unique when present
- `mobile` — nullable, unique when present
- `password`
- `role` — OWNER, MANAGER, STAFF or platform role as separately defined
- `email_verified_at`
- `mobile_verified_at`
- `preferred_language`
- `is_active`
- `remember_token`
- `created_at`
- `updated_at`

Rules:

1. A shop user belongs to exactly one shop.
2. A shop user has exactly one role.
3. Shop users cannot switch between multiple shops.
4. Owner, Manager and Staff are fixed application roles.
5. Custom per-user permission configuration is not part of the initial release.
6. A shop can have only one Owner, up to two Managers and up to five Staff members.
7. Role and shop capacity constraints must be enforced by backend services and appropriate database constraints where practical.

The final platform administrator account structure may be separated from shop users if required.

## 4. shops

Purpose: Store merchant/shop information.

Suggested columns:

- `id`
- `name`
- `owner_name`
- `mobile`
- `email` — nullable
- `address` — nullable
- `state_code` — nullable; string LGD code
- `district_code` — nullable; string LGD code
- `subdistrict_code` — nullable; string LGD code
- `village_code` — nullable; string LGD code referencing `villages.villageCode` where supported
- `pincode` — nullable
- `logo_path` — nullable
- `timezone`
- `preferred_language`
- `status`
- `created_at`
- `updated_at`

Suggested statuses:

- ACTIVE
- SUSPENDED
- CLOSED

Each shop is an independent tenant. The Shop Owner can update the shop's geographic codes using hierarchical selectors. The shop's selected location is used only as the default location when a new customer form is opened; it does not constrain customer locations.

### 4.1 Geographic Master Data

The shared `villages` reference table stores the imported Madhya Pradesh LGD village snapshot. It is global reference data, not tenant-owned data, and must not contain `shop_id`. See [Geographic Master Data and Import Runbook](11-geographic-master-data.md) for its schema, provenance, import procedure and verification.

LGD codes are strings to preserve their source representation. Shop and customer location fields use application-style snake_case names and map to the source table's camelCase code columns. The current source table contains village rows with their State, District and Subdistrict codes and names; it does not introduce separate district or subdistrict tables.

## 5. shop_settings

Purpose: Store shop-level configuration.

Suggested columns:

- `id`
- `shop_id`
- `setting_key`
- `setting_value`
- `created_at`
- `updated_at`

Constraint:

- Unique `shop_id + setting_key`

Do not store passwords, tokens or sensitive secrets in ordinary settings fields.

Interest configuration should use dedicated interest policy tables rather than unstructured settings.

## 6. customers

Purpose: Store customer information and language-specific representations.

Suggested columns:

- `id`
- `shop_id`
- `customer_code`
- `name_original`
- `name_original_language`
- `name_hi` — nullable
- `name_en` — nullable
- `mobile` — nullable
- `state_code` — nullable; string LGD code
- `district_code` — nullable; string LGD code
- `subdistrict_code` — nullable; string LGD code
- `village_code` — nullable; string LGD code
- `address_original` — nullable
- `address_original_language` — nullable
- `address_hi` — nullable
- `address_en` — nullable
- `notes` — nullable
- `status`
- `created_by`
- `created_at`
- `updated_at`
- `deleted_at` — nullable

Rules:

1. Customer name is mandatory.
2. Mobile number and geographic location are optional.
3. Location codes must resolve to a valid hierarchy in the shared `villages` reference table.
4. Customer location is independently stored and is not dynamically inherited from the shop.
5. Original user-entered address and notes must be preserved.
6. Hindi and English translations must be stored separately for translatable user-entered text.
7. Automatic translation must never overwrite original text.
8. Translated text may be corrected independently.
9. Translation provider and workflow will be finalized separately.

Indexes:

- `shop_id + customer_code`
- `shop_id + mobile`
- `shop_id + name_original`

Customer code uniqueness must be scoped to the shop.

## 7. sales

Purpose: Store customer credit sales.

Suggested columns:

- `id`
- `shop_id`
- `customer_id`
- `sale_number`
- `sale_date`
- `amount DECIMAL(15,2)`
- `description_original` — nullable
- `description_original_language` — nullable
- `description_hi` — nullable
- `description_en` — nullable
- `crop_season` — nullable
- `status`
- `financial_transaction_id` — nullable
- `created_by`
- `posted_by` — nullable
- `posted_at` — nullable
- `created_at`
- `updated_at`

Rules:

1. Every sale belongs to one shop and one customer.
2. Sale date and amount are mandatory.
3. Amount must be greater than zero.
4. Every sale preserves its own effective date.
5. A sale must not inherit the calculation start date of an older outstanding balance.
6. Posted sale correction is permitted only to the Shop Owner.
7. Posted financial history must not be physically deleted.

Suggested statuses:

- DRAFT
- POSTED

Any additional status must follow an approved correction or cancellation workflow.

## 8. sale_revisions

Purpose: Preserve previous and updated versions of Owner-corrected sales.

Suggested columns:

- `id`
- `shop_id`
- `sale_id`
- `revision_number`
- `old_values JSON`
- `new_values JSON`
- `correction_reason`
- `changed_by`
- `changed_at`
- `created_at`

Rules:

- Preserve original and updated values.
- Record the acting Owner.
- Record correction reason and timestamp.
- Revision history must not be editable through normal application operations.
- Financial recalculation must reference the relevant correction.

## 9. sale_attachments

Suggested columns:

- `id`
- `shop_id`
- `sale_id`
- `file_path`
- `original_filename`
- `mime_type`
- `file_size`
- `uploaded_by`
- `created_at`

Files must be stored privately.

## 10. payments

Purpose: Store money received from customers.

Suggested columns:

- `id`
- `shop_id`
- `customer_id`
- `payment_number`
- `payment_date`
- `amount DECIMAL(15,2)`
- `payment_method` — nullable
- `reference_number` — nullable
- `description` — nullable
- `status`
- `financial_transaction_id` — nullable
- `created_by`
- `posted_by` — nullable
- `posted_at` — nullable
- `created_at`
- `updated_at`

Rules:

1. Every payment belongs to one shop and one customer.
2. Payment date and amount are mandatory.
3. Amount must be greater than zero.
4. Payments reduce the customer's consolidated payable.
5. Customer-facing payments remain consolidated at customer-account level.
6. Each normal payment has immutable internal FIFO allocation records across eligible Sales, with principal/posted-interest components applied by the effective per-Sale waterfall.
7. Multiple payments on the same date must not duplicate interest.
8. Excess payment must be rejected before posting; no advance balance is supported.
9. Only the Shop Owner may initiate a Payment reversal and optional replacement; posted Payments themselves are immutable and never directly edited.

## 11. payment_revisions

Purpose: Preserve previous and updated versions of corrected payments.

Suggested columns:

- `id`
- `shop_id`
- `payment_id`
- `revision_number`
- `old_values JSON`
- `new_values JSON`
- `correction_reason`
- `changed_by`
- `changed_at`
- `created_at`

Rules:

- Preserve old and new payment values.
- Record the acting Owner.
- Record correction reason and timestamp.
- Preserve immutable revision history.
- Trigger appropriate financial recalculation.

## 12. payment_attachments

Suggested columns:

- `id`
- `shop_id`
- `payment_id`
- `file_path`
- `original_filename`
- `mime_type`
- `file_size`
- `uploaded_by`
- `created_at`

## 13. Customer Advance Schema — Not Supported

No `customer_advances` table, `advance_balance` column, customer-advance transaction type, or future-sale advance adjustment is part of the approved schema. A payment exceeding total payable is rejected before any financial posting.

## 14. financial_transactions

Purpose: Central financial transaction reference.

Suggested columns:

- `id`
- `shop_id`
- `customer_id`
- `transaction_number`
- `transaction_type`
- `effective_date`
- `status`
- `source_type` — nullable
- `source_id` — nullable
- `description` — nullable
- `created_by`
- `posted_by` — nullable
- `posted_at` — nullable
- `created_at`
- `updated_at`

Conceptual transaction types:

- OPENING_BALANCE
- CREDIT_SALE
- PAYMENT
- INTEREST_CALCULATION
- INTEREST_POSTING
- SETTLEMENT
- ADJUSTMENT

Use a unique constraint on `shop_id + transaction_number`.

The exact transaction and ledger representation must be finalized during implementation design.

## 15. ledger_entries

Purpose: Preserve financial posting effects and provide chronological ledger history.

Suggested columns:

- `id`
- `shop_id`
- `customer_id`
- `financial_transaction_id`
- `component`
- `debit_amount DECIMAL(15,2)`
- `credit_amount DECIMAL(15,2)`
- `effective_date`
- `sequence_number`
- `description` — nullable
- `created_at`

Possible components:

- OUTSTANDING
- INTEREST
- SETTLEMENT
- ADJUSTMENT

Rules:

1. Every posted financial transaction must generate the required ledger effect.
2. Debit and credit semantics must be defined consistently.
3. Both debit and credit amounts must not be positive in the same entry.
4. Zero-value entries should normally be rejected.
5. Posted ledger history must not be silently edited or deleted.
6. Corrections must create traceable financial effects and preserve revision history.
7. Ledger entries must not introduce individual sale-level payment allocations.

The final receivable debit/credit convention must be documented before implementation.

## 16. customer_accounts

Purpose: Maintain one current financial account per customer per shop.

Suggested columns:

- `id`
- `shop_id`
- `customer_id`
- `outstanding_balance DECIMAL(15,2)`
- `last_interest_calculation_date` — nullable
- `last_payment_date` — nullable
- `version`
- `created_at`
- `updated_at`

Constraint:

- Unique `shop_id + customer_id`

Rules:

1. This is a current-state projection, not the authoritative financial history.
2. The account must be rebuildable from financial transactions and approved calculation rules.
3. Updates must occur transactionally.
4. Concurrent financial operations must not corrupt the account.
5. The account must represent only approved receivables and posted financial components; no advance balance exists.
6. The account must not be manually edited as an alternative to financial transactions.

## 17. account_balance_snapshots

Purpose: Optional performance optimization and historical balance reference.

Suggested columns:

- `id`
- `shop_id`
- `customer_id`
- `outstanding_balance DECIMAL(15,2)`
- `as_of_date`
- `last_transaction_id`
- `created_at`

Snapshots are projections, not the authoritative accounting source.

They must be rebuildable and reconcilable from financial history.

## 18. interest_policies

Purpose: Identify a shop's interest policy.

Suggested columns:

- `id`
- `shop_id`
- `name`
- `status`
- `created_by`
- `created_at`
- `updated_at`

A shop must have a default interest policy.

Customer-specific interest rates may override the shop default according to approved business rules.

## 19. interest_policy_versions

Purpose: Preserve historical interest configuration.

Suggested columns:

- `id`
- `shop_id`
- `interest_policy_id`
- `version_number`
- `monthly_rate DECIMAL(12,8)`
- `grace_period_days`
- `capitalization_policy`
- `effective_from`
- `effective_until` — nullable
- `approved_by`
- `approved_at`
- `created_at`

Possible capitalization policies:

- CAPITALIZED
- SEPARATE

The policy version must preserve the actual configuration used for historical calculations.

Customer-specific rates may require a separate customer policy override or versioned customer-interest configuration.

Exact policy transition behavior remains pending approval.

## 20. interest_calculation_runs

Purpose: Record each interest calculation execution.

Suggested columns:

- `id`
- `shop_id`
- `customer_id`
- `policy_version_id`
- `trigger_type`
- `calculation_date`
- `calculation_status`
- `total_interest DECIMAL(20,8)`
- `calculation_reference`
- `trigger_transaction_id` — nullable
- `executed_by` — nullable
- `executed_at`
- `created_at`

Possible trigger types:

- PAYMENT
- ANNUAL_AUTO_SETTLEMENT

The calculation reference and uniqueness strategy must prevent duplicate calculation for the same applicable period.

## 21. interest_calculation_items

Purpose: Preserve detailed calculation components.

Suggested columns:

- `id`
- `shop_id`
- `interest_calculation_run_id`
- `source_type`
- `source_id`
- `eligible_balance DECIMAL(20,8)`
- `rate_value DECIMAL(12,8)`
- `period_start`
- `period_end`
- `actual_days`
- `unrounded_interest DECIMAL(20,8)`
- `rounded_interest DECIMAL(15,2)`
- `explanation_json`
- `created_at`

The source may represent:

- Consolidated carried-forward outstanding.
- An individual new sale.
- A separately maintained interest component, where applicable.

Each calculation item must make it possible to explain which amount, date range and rate produced the interest.

## 22. settlements

Purpose: Store crop-season settlement records.

Suggested columns:

- `id`
- `shop_id`
- `customer_id`
- `settlement_number`
- `settlement_date`
- `outstanding_before DECIMAL(15,2)`
- `amount_received DECIMAL(15,2)`
- `outstanding_carried_forward DECIMAL(15,2)`
- `status`
- `financial_transaction_id`
- `notes` — nullable
- `created_by`
- `posted_by` — nullable
- `posted_at` — nullable
- `created_at`
- `updated_at`

Detailed settlement fields and accounting behavior must follow the final approved settlement rules.

Do not assume separate principal and interest settlement allocation.

## 23. settlement_items

Purpose: Preserve settlement component details.

Suggested columns:

- `id`
- `shop_id`
- `settlement_id`
- `source_transaction_id` — nullable
- `component`
- `amount DECIMAL(15,2)`
- `treatment`
- `created_at`

Possible treatments:

- PAID
- CARRIED_FORWARD
- ADJUSTED

Final component and treatment definitions depend on approved settlement accounting.

## 24. audit_logs

Purpose: Maintain audit history for financial, user and security-sensitive actions.

Suggested columns:

- `id`
- `shop_id` — nullable for platform-level events
- `user_id` — nullable for system events
- `action`
- `entity_type`
- `entity_id`
- `reason` — nullable
- `old_values JSON` — nullable
- `new_values JSON` — nullable
- `ip_address` — nullable
- `user_agent` — nullable
- `created_at`

Audit events include:

- Sale creation and correction.
- Payment creation and correction.
- Customer changes.
- Interest policy changes.
- Interest calculation.
- Settlement.
- User and role changes.
- Subscription changes.
- Security-sensitive operations.

Do not store passwords, tokens or unnecessary sensitive data.

Ordinary application users must not be able to modify or delete audit records.

## 25. SaaS Subscription Tables

### subscription_plans

Suggested columns:

- `id`
- `name`
- `description`
- `price DECIMAL(15,2)`
- `billing_interval`
- `trial_duration_days` — nullable
- `limits_json`
- `is_active`
- `created_at`
- `updated_at`

Initial plans:

- Free Trial
- Basic
- Standard
- Premium

### subscriptions

Suggested columns:

- `id`
- `shop_id`
- `subscription_plan_id`
- `starts_at`
- `ends_at`
- `trial_ends_at` — nullable
- `grace_ends_at` — nullable
- `status`
- `created_at`
- `updated_at`

Subscription belongs to the shop, not individual users.

### subscription_payments

Suggested columns:

- `id`
- `shop_id`
- `subscription_id`
- `amount DECIMAL(15,2)`
- `payment_reference`
- `payment_status`
- `paid_at` — nullable
- `created_at`
- `updated_at`

## 26. idempotency_keys

Purpose: Prevent duplicate financial operations caused by request retries.

Suggested columns:

- `id`
- `shop_id`
- `user_id`
- `idempotency_key`
- `request_hash`
- `response_reference` — nullable
- `status`
- `created_at`
- `updated_at`

Unique constraint:

- `shop_id + idempotency_key`

The same key with a different request payload must be rejected.

## 27. Important Database Constraints

Use:

- Foreign keys.
- Unique constraints.
- Check constraints supported by MySQL 8.4.
- Composite tenant-aware indexes.
- Non-null constraints for mandatory fields.

A foreign key alone does not guarantee that related records belong to the same shop.

Where necessary, use composite tenant-aware keys or enforce ownership through validated application services and database constraints.

Every financial operation must validate that customer, sale, payment and transaction references belong to the same shop.

## 28. Indexing Strategy

Frequently queried records should have indexes beginning with `shop_id`.

Examples:

- `customers(shop_id, customer_code)`
- `customers(shop_id, mobile)`
- `customers(shop_id, name_original)`
- `sales(shop_id, customer_id, sale_date)`
- `payments(shop_id, customer_id, payment_date)`
- `financial_transactions(shop_id, customer_id, effective_date)`
- `ledger_entries(shop_id, customer_id, effective_date)`
- `interest_calculation_runs(shop_id, customer_id, calculation_date)`
- `audit_logs(shop_id, entity_type, entity_id)`

Review indexes using actual query patterns and EXPLAIN.

Avoid unnecessary indexes on every column.

## 29. Data Retention

Financial records must be retained according to the approved retention policy and applicable legal requirements.

Do not cascade-delete posted financial transactions when a customer or shop is deactivated.

Use controlled archival and retention processes.

## 30. Migration Strategy

Every database schema change must use Laravel migrations.

Requirements:

- Migrations should be reversible where practical.
- Large production changes must be deployed safely.
- Existing financial data must not be silently modified.
- Data migrations must be tested.
- Backups must be available before high-risk changes.
- Tenant data integrity must be validated after migrations.
- Financial calculations must be reconciled after relevant data migrations.

## 31. Database Acceptance Criteria

- Every tenant-owned record has a tenant relationship.
- One shop user belongs to one shop and has one role.
- Shop user capacity limits are enforced.
- No many-to-many shop membership is required.
- Customer original and translated text are stored separately.
- Sales preserve their effective dates.
- Payments use consolidated payable.
- Customer-facing payments remain consolidated; immutable internal FIFO allocation records are required.
- Customer advances are not supported; excess payments are rejected.
- Interest policy versions are preserved.
- Interest calculation components are traceable.
- Owner corrections preserve revision and audit history.
- Settlements are traceable.
- Duplicate financial requests are prevented.
- Cross-tenant references are rejected.
- Customer account projections can be rebuilt.
- Financial history is not cascade-deleted.


---

## 32. Product Owner-approved Financial Schema Constraints — Phase 5 (2026-11-25)

This addendum supersedes conflicting statements elsewhere in this document and in ADR-0004. ADR-0012 controls.

1. A dedicated internal `payment_allocations` (or equivalently named immutable allocation) table is required for FIFO. This reverses the previous no-allocation decision.
2. Each allocation must be shop-scoped and reference the consolidated payment, customer, and sale (plus the relevant principal/posted-interest component). Store applied principal amount, applied interest amount, FIFO sequence, effective timestamp, policy version and reversal linkage as needed.
3. Enforce tenant-safe composite foreign keys or equivalent constraints so payment, sale, customer and shop references cannot cross tenants.
4. Allocation records are append-only. Reversals are separate records; do not update/delete original allocation history.
5. Sale original amount remains unchanged. Remaining principal/interest is a projection derived from immutable postings, allocations and reversals.
6. A Paid sale's same-day automatic payment is linked to that sale and must have an allocation limited to that originating sale. It must not be applied to older balances.
7. Customer-facing normal payments remain consolidated. FIFO allocations are internal accounting evidence and do not change the customer-facing payment model.
8. Customer advances are not part of the approved schema. Excess payment is rejected before posting.
9. Add Shop/Customer effective-dated policy storage for monthly rate, grace period, capitalization, payment waterfall and the two annual Interest Auto Settlement dates. Customer-level monthly-rate and capitalization overrides are supported; grace period and payment waterfall remain Shop-level settings.
10. Preserve policy version/effective date on calculation and allocation records. Historical policy changes must not rewrite existing postings.
11. Payment amount must be constrained by a transactionally rechecked payable balance (outstanding principal plus posted unpaid interest); enforce concurrency with customer-account row locking.
12. The schema must represent separate Sale Payment Status and Interest Settlement Status.
13. Financial corrections must preserve immutable revisions/reversals and audit references. Sales before a later posted Payment or annual Interest Auto Settlement are locked against ordinary editing.
14. Store INR amounts in MySQL DECIMAL with 2-decimal posted values; interest calculations must preserve adequate intermediate precision for HALF_UP rounding.

### 32.1 Minimum allocation record fields (logical contract)

- `id`, `shop_id`, `customer_id`, `payment_id`, `sale_id`
- `allocation_sequence` (FIFO order within a payment)
- `applied_principal_amount` DECIMAL(15,2)
- `applied_interest_amount` DECIMAL(15,2)
- `effective_at`
- `policy_version_id`
- `reversal_of_allocation_id` nullable
- `created_by`, `created_at`

Exact physical indexes, uniqueness constraints and whether sale components require a child allocation table are to be finalized in the Phase 5 implementation blueprint after schema-level reconciliation. Do not create an implementation migration until that blueprint is approved.



### 32.2 Finalized policy defaults (Product Owner clarification, 2026-11-25)

- Initial Shop capitalization setting must default to **OFF**; a Customer-level override is optional and inherits OFF when absent.
- Initial annual Interest Auto Settlement dates must be initialized to **30 March** and **30 October**. Keep both dates Owner-configurable at Shop level and version/configuration-auditable.


### 32.3 Approved interest conventions (2026-11-25)

- Actual Days / 30 formula.
- Posted INR interest rounded to two decimal places using HALF_UP.
- Interest interval is [start date inclusive, end date exclusive).
- Sale Date is grace-period Day 0; with a 30-calendar-day grace period, the first eligible interest day follows 30 complete calendar days.
- Preserve adequate intermediate decimal precision and event-boundary metadata.


### 32.4 Approved allocation and interest conventions (2026-11-25)

1. Allocation ordering must be deterministic and represented at two levels: (a) FIFO Sale sequence, then (b) per-Sale Principal/Interest waterfall sequence.
2. Allocation items must preserve sale_id, source component/interest-lot reference where applicable, applied principal, applied interest, sequence at both levels, effective timestamp and policy version.
3. Interest calculation run/items must persist interval start and exclusive end, eligible day count, grace-period evaluation result, applied rate/version, unrounded amount and posted HALF_UP amount.
4. Sale Date is Day 0. For a 30-calendar-day grace period, no interest day is eligible until 30 complete calendar days have elapsed.
5. Do not implement an exceptional correction bypass for a locked Sale. The service must reject the operation and preserve an audit/security event where appropriate. Any future exception requires a new approved workflow and ADR.

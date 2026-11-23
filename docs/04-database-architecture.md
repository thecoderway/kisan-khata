
# Kisan Khata — Database Architecture

**Document Version:** 1.0  
**Database:** MySQL  
**Architecture:** Shared Database, Multi-Tenant

---

## 1. Database Design Principles

1. Use relational database design.
2. Use foreign keys wherever appropriate.
3. Use tenant identifiers on tenant-owned tables.
4. Use DECIMAL for financial amounts.
5. Use indexes for common queries.
6. Preserve financial history.
7. Avoid storing duplicate authoritative balances.
8. Use migrations for schema changes.
9. Use explicit transaction status fields.
10. Design for future versioned API support.

All table names below are proposed names.

Column definitions and relationships must be validated against the actual Laravel and MySQL versions.

## 2. Core Table Groups

### Identity and Tenancy

- users
- shops
- shop_user
- shop_settings
- roles or permissions (depending on authorization implementation)

### Customer Management

- customers

### Sales

- sales
- sale_attachments

### Payments

- payments
- payment_allocations
- payment_attachments

### Accounting

- financial_transactions
- ledger_entries
- account_balance_snapshots

### Interest

- interest_policies
- interest_policy_versions
- interest_calculation_runs
- interest_calculation_items

### Settlements

- settlements
- settlement_items

### Audit and SaaS

- audit_logs
- subscriptions
- subscription_plans
- subscription_payments
- idempotency_keys

## 3. users

Purpose: Store platform user accounts.

Suggested columns:

- id
- name
- email (nullable, unique when present)
- mobile (nullable, unique when present)
- password
- email_verified_at
- mobile_verified_at
- preferred_language
- is_active
- remember_token
- created_at
- updated_at

Authentication identifiers must follow the selected Laravel authentication design.

## 4. shops

Purpose: Store merchant/shop information.

Suggested columns:

- id
- name
- owner_name
- mobile
- email (nullable)
- address (nullable)
- village (nullable)
- tehsil (nullable)
- district (nullable)
- state (nullable)
- pincode (nullable)
- logo_path (nullable)
- timezone
- preferred_language
- status
- created_at
- updated_at

Suggested statuses:

- ACTIVE
- SUSPENDED
- CLOSED

## 5. shop_user

Purpose: Connect users with shops.

Suggested columns:

- id
- shop_id
- user_id
- role
- permissions (only if a structured permission model is not used)
- is_active
- joined_at
- created_at
- updated_at

Constraints:

- Unique shop_id + user_id
- Foreign key to shops
- Foreign key to users

A user may belong to multiple shops.

## 6. shop_settings

Purpose: Store shop-level configuration.

Suggested columns:

- id
- shop_id
- setting_key
- setting_value
- created_at
- updated_at

Unique constraint:

- shop_id + setting_key

Do not store sensitive secrets in ordinary settings fields.

## 7. customers

Purpose: Store customer information.

Suggested columns:

- id
- shop_id
- customer_code
- name
- father_name
- mobile
- alternate_mobile (nullable)
- village
- address (nullable)
- pincode (nullable)
- notes (nullable)
- status
- created_by
- created_at
- updated_at
- deleted_at (nullable)

Indexes:

- shop_id + customer_code
- shop_id + mobile
- shop_id + name
- shop_id + father_name
- shop_id + village

Customer code uniqueness should be scoped to the shop.

## 8. sales

Purpose: Store credit sale documents.

Suggested columns:

- id
- shop_id
- customer_id
- sale_number
- sale_date
- amount DECIMAL(15,2)
- description (nullable)
- bill_number (nullable)
- notes (nullable)
- status
- financial_transaction_id (nullable)
- created_by
- posted_by (nullable)
- posted_at (nullable)
- created_at
- updated_at

Suggested statuses:

- DRAFT
- POSTED
- VOIDED

Posted sales must not be silently overwritten.

## 9. sale_attachments

Suggested columns:

- id
- shop_id
- sale_id
- file_path
- original_filename
- mime_type
- file_size
- uploaded_by
- created_at

Files must be stored privately.

## 10. payments

Purpose: Store money received from customers.

Suggested columns:

- id
- shop_id
- customer_id
- payment_number
- payment_date
- amount DECIMAL(15,2)
- payment_method
- reference_number (nullable)
- description (nullable)
- status
- financial_transaction_id (nullable)
- created_by
- posted_by (nullable)
- posted_at (nullable)
- created_at
- updated_at

Suggested statuses:

- DRAFT
- POSTED
- REVERSED

## 11. payment_allocations

Purpose: Record how a payment is distributed.

Suggested columns:

- id
- shop_id
- payment_id
- financial_transaction_id
- target_transaction_id (nullable)
- component
- allocated_amount DECIMAL(15,2)
- allocation_sequence
- policy_version_id (nullable)
- created_at

Possible components:

- PRINCIPAL
- INTEREST

Every allocation must be traceable to the payment and the outstanding item it reduces.

## 12. financial_transactions

Purpose: Central financial transaction header.

Suggested columns:

- id
- shop_id
- customer_id
- transaction_number
- transaction_type
- effective_date
- status
- source_type (nullable)
- source_id (nullable)
- description (nullable)
- created_by
- posted_by (nullable)
- posted_at (nullable)
- reversal_of_transaction_id (nullable)
- created_at
- updated_at

Suggested transaction types:

- OPENING_BALANCE
- CREDIT_SALE
- PAYMENT
- INTEREST_CHARGE
- SETTLEMENT
- ADJUSTMENT
- REVERSAL

Use unique constraints for transaction numbers within a shop.

## 13. ledger_entries

Purpose: Store immutable financial posting entries.

Suggested columns:

- id
- shop_id
- customer_id
- financial_transaction_id
- component
- debit_amount DECIMAL(15,2)
- credit_amount DECIMAL(15,2)
- effective_date
- sequence_number
- description (nullable)
- created_at

Possible components:

- PRINCIPAL
- INTEREST

Rules:

- A posted transaction produces the required ledger entries.
- Debit and credit semantics must remain consistent.
- Both amounts must not be positive in the same entry.
- A zero-value entry should normally be rejected.
- Posted entries must not be silently edited or deleted.

The application must define and document the receivable debit/credit convention.

## 14. account_balance_snapshots

Purpose: Optional performance optimization.

Suggested columns:

- id
- shop_id
- customer_id
- principal_balance DECIMAL(15,2)
- interest_balance DECIMAL(15,2)
- total_balance DECIMAL(15,2)
- as_of_date
- last_transaction_id
- created_at
- updated_at

Snapshots are projections, not the authoritative accounting source.

They must be rebuildable from ledger entries.

## 15. interest_policies

Purpose: Identify the shop's interest policy.

Suggested columns:

- id
- shop_id
- name
- status
- created_by
- created_at
- updated_at

## 16. interest_policy_versions

Purpose: Preserve the historical configuration of interest rules.

Suggested columns:

- id
- shop_id
- interest_policy_id
- version_number
- rate_value DECIMAL(12,8)
- rate_period
- interest_free_value
- interest_free_unit
- calculation_method
- day_count_convention
- partial_period_method
- compounding_method
- interest_basis
- rounding_method
- payment_allocation_method
- effective_from
- effective_until (nullable)
- approved_by
- approved_at
- created_at

Do not activate unapproved policy versions.

The fields above are candidates. Final structure must follow approved business decisions.

## 17. interest_calculation_runs

Purpose: Record calculation execution.

Suggested columns:

- id
- shop_id
- customer_id
- policy_version_id
- calculation_start_date
- calculation_end_date
- calculation_status
- total_interest DECIMAL(15,2)
- calculation_reference
- executed_by
- executed_at
- created_at

## 18. interest_calculation_items

Purpose: Record calculation details.

Suggested columns:

- id
- shop_id
- interest_calculation_run_id
- source_transaction_id
- eligible_principal DECIMAL(15,2)
- rate_value DECIMAL(12,8)
- period_start
- period_end
- eligible_days
- unrounded_interest DECIMAL(20,8)
- rounded_interest DECIMAL(15,2)
- explanation_json
- created_at

## 19. settlements

Purpose: Store crop-season settlement records.

Suggested columns:

- id
- shop_id
- customer_id
- settlement_number
- settlement_date
- principal_before DECIMAL(15,2)
- interest_before DECIMAL(15,2)
- payment_amount DECIMAL(15,2)
- principal_carried_forward DECIMAL(15,2)
- interest_carried_forward DECIMAL(15,2)
- status
- financial_transaction_id
- notes (nullable)
- created_by
- posted_by (nullable)
- posted_at (nullable)
- created_at
- updated_at

Final behavior depends on the approved settlement policy.

## 20. settlement_items

Purpose: Preserve settlement component details.

Suggested columns:

- id
- shop_id
- settlement_id
- source_transaction_id (nullable)
- component
- amount DECIMAL(15,2)
- treatment
- created_at

Possible treatments:

- PAID
- CARRIED_FORWARD
- ADJUSTED
- REVERSED

## 21. audit_logs

Suggested columns:

- id
- shop_id (nullable for platform-level events)
- user_id (nullable)
- action
- entity_type
- entity_id
- reason (nullable)
- old_values (nullable JSON)
- new_values (nullable JSON)
- ip_address (nullable)
- user_agent (nullable)
- created_at

Do not store passwords, tokens or unnecessary sensitive data.

## 22. SaaS Subscription Tables

### subscription_plans

- id
- name
- description
- price DECIMAL(15,2)
- billing_interval
- limits_json
- is_active
- created_at
- updated_at

### subscriptions

- id
- shop_id
- subscription_plan_id
- starts_at
- ends_at
- trial_ends_at (nullable)
- grace_ends_at (nullable)
- status
- created_at
- updated_at

### subscription_payments

- id
- shop_id
- subscription_id
- amount DECIMAL(15,2)
- payment_reference
- payment_status
- paid_at (nullable)
- created_at
- updated_at

## 23. idempotency_keys

Purpose: Prevent duplicate financial operations caused by request retries.

Suggested columns:

- id
- shop_id
- user_id
- idempotency_key
- request_hash
- response_reference (nullable)
- status
- created_at
- updated_at

Unique constraint:

- shop_id + idempotency_key

## 24. Important Database Constraints

Use:

- Foreign keys
- Unique constraints
- Check constraints where supported by the selected MySQL version
- Composite tenant indexes
- Non-null constraints for mandatory fields

A foreign key alone does not guarantee that related records belong to the same shop.

Where necessary, use composite tenant-aware keys or enforce ownership through validated application services and database constraints.

## 25. Indexing Strategy

Frequently queried records should have indexes beginning with shop_id.

Examples:

- customers(shop_id, mobile)
- customers(shop_id, name)
- sales(shop_id, customer_id, sale_date)
- payments(shop_id, customer_id, payment_date)
- financial_transactions(shop_id, customer_id, effective_date)
- ledger_entries(shop_id, customer_id, effective_date)

Review indexes using real query patterns and EXPLAIN.

Avoid adding unnecessary indexes to every column.

## 26. Data Retention

Financial records must be retained according to the product's approved retention policy and applicable legal requirements.

Do not cascade-delete posted financial transactions when a customer or shop is deactivated.

Use controlled archival and retention processes.

## 27. Migration Strategy

Every database schema change must use Laravel migrations.

Requirements:

- Migrations must be reversible where practical.
- Large production changes must be deployed safely.
- Existing financial data must not be silently modified.
- Data migrations must be tested.
- Backups must be available before high-risk changes.

## 28. Database Acceptance Criteria

- Every tenant-owned record has a tenant relationship.
- Financial transactions are traceable.
- Posted ledger entries are immutable.
- Payment allocations are recorded.
- Interest policy versions are preserved.
- Settlements are traceable.
- Duplicate requests are prevented.
- Cross-tenant references are rejected.
- Financial snapshots can be rebuilt.

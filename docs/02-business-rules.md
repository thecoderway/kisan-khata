
# Kisan Khata — Business Rules

**Document Version:** 1.0

---

## 1. Purpose

This document defines the business rules governing customer credit, payments, interest and crop-season settlements.

These rules are the source of truth for the application's financial behavior.

Developers must not invent missing accounting rules.

## 2. Core Business Model

A merchant sells goods to a customer on credit.

The customer pays the outstanding amount later.

The merchant may allow an interest-free period.

After the interest-free period, interest may apply according to the shop's configured policy.

Around harvesting seasons, the merchant and customer may settle the account.

Any remaining amount may continue as an outstanding balance.

## 3. Customer Rules

1. A customer belongs to one shop.
2. A customer cannot be accessed by another shop.
3. A customer may have multiple credit sales.
4. A customer may make multiple payments.
5. A customer must have a complete ledger.
6. Customer information changes must be auditable where necessary.

## 4. Credit Sale Rules

1. A credit sale must belong to a valid customer.
2. A credit sale must belong to the current shop.
3. Every sale must have an amount greater than zero.
4. Every sale must have a transaction date.
5. A sale may contain a description.
6. A bill photo is optional.
7. A posted sale must appear in the customer's ledger.
8. A posted sale must not be physically deleted.
9. Corrections must preserve historical information.

## 5. Payment Rules

1. A payment must belong to a valid customer.
2. A payment must belong to the current shop.
3. Payment amount must be greater than zero.
4. Payment date must be recorded.
5. Payment method may be recorded.
6. A payment must be reflected in the customer's ledger.
7. Every payment must have a documented allocation policy.
8. A payment must never be counted twice.
9. Reversing a payment must preserve its original record.
10. Payment allocation changes must be auditable.

## 6. Interest-Free Period

The initial business concept includes an interest-free period after a credit sale.

However, the exact meaning is not yet approved.

Possible interpretations:

- One calendar month
- 30 elapsed days
- A configurable number of days

The product owner must select the intended rule.

The system must support an explicit policy rather than hardcoding a duration.

## 7. Interest Rules

Interest is expected to be monthly.

The following decisions remain unresolved:

| Rule | Current Status |
|---|---|
| Interest rate | Not approved |
| Meaning of example 2.00 | Not approved |
| Interest-free period | Not approved |
| Daily or monthly calculation | Not approved |
| Partial-month treatment | Not approved |
| Day-count convention | Not approved |
| Simple or compound interest | Not approved |
| Interest on principal only | Not approved |
| Interest on unpaid interest | Not approved |
| Rounding policy | Not approved |
| Interest posting frequency | Not approved |

Do not implement a production interest policy until these decisions are approved.

## 8. Payment Allocation Rules

When a customer makes a payment, the system must determine which outstanding amounts are reduced.

Possible policies:

- Interest first, then principal
- Principal first, then interest
- Oldest outstanding invoice first
- User-selected allocation
- Proportional allocation

No policy is approved yet.

The selected policy must be stored with the financial allocation records.

Historical allocations must remain reproducible.

## 9. Crop-Season Settlement

The business model includes account settlements around harvest seasons.

A settlement may involve:

1. Calculating outstanding principal.
2. Calculating outstanding interest.
3. Recording the settlement date.
4. Recording the amount received.
5. Determining the remaining balance.
6. Carrying forward the remaining balance according to the approved policy.

The exact treatment is unresolved.

Questions requiring approval:

- Is outstanding interest crystallized at settlement?
- Does unpaid interest become principal?
- Does the new period start with principal only?
- Does interest begin on the settlement date or the following day?
- Are old invoices closed?
- Is a new opening balance created?
- How are settlement corrections handled?

Do not assume that settlement means the customer has paid the full amount.

## 10. Outstanding Balance

The system must distinguish:

- Original credit sales
- Payments received
- Principal outstanding
- Interest outstanding
- Adjustments
- Settlements

The total amount due must follow the approved accounting model.

Do not calculate balances by relying only on editable customer balance fields.

The ledger and financial transaction records must be the source of truth.

## 11. Transaction Dates

The system should distinguish:

- Transaction date
- Creation timestamp
- Posting timestamp
- Effective date (where applicable)

Backdated transactions must follow explicit permissions and audit rules.

Interest calculations must use the approved effective date.

## 12. Transaction Corrections

Posted financial records must not be silently edited or deleted.

Correction workflow:

1. Identify the original transaction.
2. Record a reversal or adjustment.
3. Preserve the original transaction.
4. Create a replacement transaction if required.
5. Record the reason.
6. Record the user performing the correction.
7. Preserve the complete audit history.

## 13. Financial Audit Trail

Every important financial action should record:

- Shop ID
- User ID
- Entity type
- Entity ID
- Action
- Previous state where appropriate
- New state where appropriate
- Reason
- Timestamp

Sensitive information must not be unnecessarily duplicated in logs.

## 14. Rounding

Financial rounding must be consistent.

The application must define:

- Monetary precision
- Interest precision
- Rounding mode
- Point at which rounding occurs

Never rely on PHP float behavior.

## 15. Tenant Isolation

Every shop must have independent:

- Customers
- Sales
- Payments
- Ledgers
- Interest policies
- Settlements
- Employees
- Reports

Cross-tenant access is prohibited.

## 16. Unresolved Product Decisions

Maintain these decisions in docs/decisions/.

No developer or AI coding agent may silently resolve financial ambiguities.

Any change to an approved financial rule requires:

- Product owner approval
- Updated documentation
- Impact analysis
- Migration or data correction plan where necessary
- Regression tests

## 17. Business Rule Priority

If a feature request conflicts with an approved financial rule:

1. Preserve financial integrity.
2. Stop implementation of the conflicting behavior.
3. Explain the conflict.
4. Request product owner approval.
5. Update documentation after approval.


# Financial Integrity Rules

These rules are mandatory for all financial modules in Kisan Khata.

## 1. Financial Accuracy

Financial correctness is more important than implementation convenience.

Never use PHP float or JavaScript floating-point arithmetic for authoritative financial calculations.

Use:
- MySQL DECIMAL columns.
- A suitable decimal arithmetic implementation.
- Explicit rounding policies.
- Validated monetary input.

Never convert authoritative financial values to float during calculations.

## 2. Financial Source of Truth

Posted financial transactions and their ledger entries are the source of truth.

Do not treat an editable customer balance column as the authoritative accounting record.

Any cached or projected balance must be rebuildable from posted financial entries.

## 3. Transaction Immutability

Once a financial transaction is posted:

- Do not silently edit its amount.
- Do not physically delete it.
- Do not overwrite its historical ledger entries.
- Do not remove its audit history.

Corrections must use approved reversal and replacement workflows.

## 4. Principal and Interest

Keep principal and interest separately identifiable.

Never merge them into one unexplained balance.

Every financial operation must preserve the distinction between:

- Principal receivable.
- Interest receivable.
- Principal payment.
- Interest payment.
- Adjustments.
- Reversals.
- Settlement carry-forward.

## 5. Interest Calculation

Never invent or assume an interest policy.

Refer to:
- docs/02-business-rules.md
- docs/03-accounting-engine.md

Unresolved decisions include:

- Interest rate meaning.
- Interest-free period.
- Daily or monthly calculation.
- Calendar month or elapsed days.
- Partial-period treatment.
- Day-count convention.
- Simple or compound interest.
- Interest basis.
- Payment allocation.
- Rounding.

If a required decision is missing, stop implementation of the affected financial behavior and ask the product owner.

## 6. Payment Allocation

Every posted payment must have explicit allocation records.

Do not silently decide whether payments reduce:

- Interest first.
- Principal first.
- Oldest invoices first.
- User-selected components.

Implement only the approved allocation policy.

Validate that total allocations do not exceed the payment amount.

## 7. Settlement Integrity

Settlement operations must preserve the original financial history.

Never assume that settlement automatically means:

- All interest becomes principal.
- All invoices are closed.
- Unpaid interest is waived.
- A new interest period begins immediately.

Follow the approved settlement policy.

## 8. Database Transactions

Use database transactions for financial posting.

Use appropriate row locking to protect concurrent account operations.

Revalidate account state after acquiring locks.

Prevent duplicate posting with idempotency protection and database constraints where appropriate.

## 9. Auditability

Important financial operations must record:

- Shop.
- User.
- Transaction reference.
- Effective date.
- Posting date.
- Operation type.
- Reason where applicable.
- Reversal reference where applicable.

Never remove historical evidence of a financial operation.

## 10. Required Tests

Every financial feature must include tests for:

- Successful posting.
- Invalid input.
- Boundary conditions.
- Decimal precision.
- Rounding.
- Duplicate requests.
- Reversal.
- Authorization.
- Tenant isolation.
- Ledger reconciliation.

## 11. Prohibited Shortcuts

Never:

- Use float for money.
- Directly modify posted ledger entries.
- Calculate interest in controllers.
- Calculate authoritative balances in Blade.
- Trust client-provided financial totals.
- Skip payment allocation records.
- Silently change financial policy.
- Ignore failed reconciliation.

## 12. Documentation

When a financial rule changes:

1. Obtain product owner approval.
2. Update business documentation.
3. Update the relevant architecture decision if necessary.
4. Update automated tests.
5. Review historical data implications.

These rules apply to all agents and all developers.

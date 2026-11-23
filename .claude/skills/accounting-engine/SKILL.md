
---
name: accounting-engine
description: Implement or review Kisan Khata financial transactions, ledger posting, payment allocation, interest calculations and crop-season settlements.
---

# Accounting Engine Skill

## Objective

Implement financially correct and auditable accounting workflows.

## Required Reading

- docs/02-business-rules.md
- docs/03-accounting-engine.md
- docs/04-database-architecture.md
- .claude/rules/financial-integrity.md
- .claude/agents/accounting-reviewer.md

## Critical Rule

Never invent a financial business rule.

If a required decision is unresolved, STOP the affected implementation and ask the product owner.

## Workflow

### Step 1: Identify the Financial Operation

Examples:

- Credit sale.
- Payment.
- Interest charge.
- Settlement.
- Adjustment.
- Reversal.

### Step 2: Identify Financial Components

Determine whether the operation affects:

- Principal.
- Interest.
- Both.
- Neither.

### Step 3: Identify Approved Rules

Find the approved:

- Transaction date rules.
- Posting rules.
- Payment allocation rules.
- Interest policy.
- Settlement policy.
- Rounding policy.

### Step 4: Define the Accounting Effect

Document:

- Opening state.
- Financial event.
- Expected ledger entries.
- Closing state.

Do not implement until the expected result is clear.

### Step 5: Design Transaction Posting

Identify:

- Transaction header.
- Ledger entries.
- Allocation records.
- Audit records.
- Reversal relationship.

### Step 6: Implement Safely

Use:

- Decimal arithmetic.
- Database transactions.
- Row locking where required.
- Idempotency.
- Unique constraints.
- Explicit posting services.

### Step 7: Preserve History

Posted records must not be silently changed or deleted.

Use approved compensating entries.

### Step 8: Verify Ledger Reconciliation

Recalculate balances from authoritative entries.

Compare projections against the ledger.

### Step 9: Test Edge Cases

Test:

- Zero and negative values.
- Large amounts.
- Decimal precision.
- Same-date entries.
- Backdated operations.
- Duplicate requests.
- Concurrent requests.
- Reversals.

### Step 10: Accounting Review

Use the accounting reviewer for high-risk changes.

## Required Output

Explain:

1. Financial operation.
2. Approved business rules.
3. Ledger impact.
4. Database changes.
5. Concurrency protection.
6. Reversal behavior.
7. Automated tests.
8. Remaining unresolved decisions.

## Completion Criteria

No unexplained financial change, duplicate posting or untraceable balance is acceptable.

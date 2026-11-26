# Phase 5 — Batch 6: Ledger Projection & Reconciliation

**Status:** Implementation in progress  
**Branch:** \`feat/batch-6-ledger-projection-reconciliation\`  
**Controlling rules:** ADR-0012 and the Phase 5 implementation blueprint  
**Database target:** MySQL 8.4 / InnoDB

## Objective

Keep the customer account projection and dated balance snapshots reproducible from append-only ledger postings. Provide a read-only reconciliation report and a separate explicit repair operation.

## Projection contract

- Ledger entries remain the authoritative financial source for principal and interest receivables.
- Customer account balances are calculated as debit totals minus credit totals by component using Brick\Math \`BigDecimal\`.
- Total payable is principal plus interest; negative component balances stop a rebuild.
- Projection writes occur under the existing customer-row then account-row lock and inside the caller's financial transaction.
- Projection version increments on rebuild. Last financial event time is derived from financial transaction chronology.
- The interest checkpoint is derived from the latest completed interest calculation, or from the in-flight cutoff explicitly supplied by the interest posting operation.
- Sale payment status is rebuilt from net sale ledger postings; original Sale amounts are not modified.
- Daily snapshots are keyed by Shop, Customer and business effective date. Each stores the ledger-derived balances and the latest ledger entry through that date.
- A change refreshes snapshots from the earliest affected effective date forward.

## Reconciliation contract

\`ReconciliationService::reconcileCustomer\` is read-only. It checks:

1. Missing or mismatched CustomerAccount projection.
2. Last completed interest checkpoint mismatch.
3. Sale payment status mismatch against net ledger postings.
4. Missing or mismatched daily account balance snapshots.
5. Posted Sale principal source totals against the original Sale amount.
6. Posted Payment ledger net against the Payment amount, including reversal direction.

The result contains Shop ID, Customer ID, a consistency flag and structured discrepancies with expected/actual values. It does not write, repair, post, or mutate any financial record.

## Explicit repair

\`ReconciliationService::rebuildAndReconcile\` is the explicit repair path. It locks the Customer and account, rebuilds the account projection and sale payment statuses from immutable ledger entries, refreshes affected snapshots, then returns a fresh reconciliation report. It does not create compensating financial transactions or change ledger history.

Repair is not exposed as a user-facing route in Batch 6. Any future API/UI must enforce Owner-only authorization and audit the repair request in Batch 7.

## Posting integration

Sale posting, normal Payment posting, Payment reversal/replacement and interest posting call the shared projection service inside their existing atomic financial transaction. The previous duplicated account-balance SQL/BigDecimal refresh helpers are removed from those posting services.

## Safety boundaries

- No financial formula, FIFO rule, waterfall, interest cutoff or ADR-0012 policy is changed.
- No new migration or schema alteration is required; this batch uses the already merged \`ledger_entries\`, \`customer_accounts\` and \`account_balance_snapshots\` schema.
- No production/development database migration, backfill or deployment is performed.
- Reconciliation is read-only; repair is explicit and ledger-derived.
- Crop-season settlement, historical restatement and locked Sale correction remain out of scope.

## Required verification

- Sale and Payment postings maintain account projections and snapshots.
- Read-only reconciliation detects intentionally corrupted projection, snapshot and Sale status without changing them.
- Explicit rebuild repairs those projections and produces a clean reconciliation result.
- Cross-Shop reconciliation is rejected.
- MySQL 8.4 CI must execute the feature tests and the existing locking integration test.

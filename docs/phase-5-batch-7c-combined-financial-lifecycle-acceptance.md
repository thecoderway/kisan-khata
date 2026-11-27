# Phase 5 — Batch 7C Combined Financial Lifecycle Acceptance

**Status:** Acceptance test added; branch CI and Product Owner browser acceptance pending.  
**Branch:** `test/batch-7c-combined-financial-acceptance`  
**Base:** `main` after Batch 7B PR #66 merge, commit `2651776c0698d14bb3c51743f4c541ac110ab114`.

## Purpose

Exercise the approved financial workflow as one connected customer-account lifecycle, building on Batch 6B, Batch 7A, Batch 7B and the existing Batch 8.7/8.8 acceptance suites. This is acceptance coverage only; it must not introduce a new accounting rule or duplicate the existing posting, FIFO, interest, ledger, projection, reversal or settlement engines.

## Combined lifecycle scenario

The new scenario in `tests/Feature/Financial/Batch87FinancialWorkflowAcceptanceTest.php` performs:

1. Post two unpaid Sales of ₹5,000 each for one Customer.
2. Post a normal Payment of ₹4,000, followed by a second normal Payment of ₹2,000.
3. Verify FIFO allocation of the second Payment across the first and second Sales, with the first Sale becoming PAID and the second becoming PARTIALLY_PAID.
4. Reverse the latest Payment with a ₹2,000 replacement Payment through `PaymentReversalService`.
5. Verify the original Payment remains immutable and the reversal/replacement relationship and revision history are preserved.
6. Verify Owner actor and correction reason in the financial audit record.
7. Verify Payment Details API revision history.
8. Verify final principal and total payable of ₹4,000, zero interest in this fixture, and consistent ledger/projection reconciliation.

Expected ledger totals after replacement: debit ₹12,000, credit ₹8,000, net customer payable ₹4,000. The two Sales total ₹10,000; the two original Payments total ₹6,000; the reversal and equal replacement net to zero.

## Existing acceptance coverage reused

- Batch 8.7: paid-sale automatic Payment atomicity, normal Payment atomicity, annual interest settlement and reconciliation, correction API Owner-only authorization and cross-Shop isolation.
- Batch 8.8: MySQL 8.4 competing Payment overdraw prevention, Sale-versus-Payment FIFO race, and same-day Payment/annual-settlement race.
- Batch 7B: immutable financial-record bulk mutation guards, reversal rollback, inactive Owner denial, later-payment dependency, completed settlement restriction, idempotency replay/payload conflict and Payment Details history.
- Batch 6: read-only reconciliation, explicit projection repair, Sale status and snapshot consistency.

The new test supplements these suites; it does not replace them.

## Acceptance gate

Batch 7C may be marked complete only after:
- Pint and the full GitHub Actions workflow pass on the exact PR head SHA, including MySQL 8.4 row-lock integration and the remaining Laravel suite.
- Existing tenant isolation and role-authorization tests remain green.
- Combined Sale/Payment/FIFO/reversal/replacement/ledger/projection/audit assertions pass.
- Product Owner completes the applicable manual UI smoke test for Sale and Payment Details/reversal history. Automated API assertions are not browser acceptance.
- Documentation and Project Status reflect observed results.

## Safety boundary

No migrations, schema changes, database operations, production data changes, deployment or release are included. Do not alter Ledger/Sales/Payments business rules or introduce Crop/Season tagging, Sale Bill Attachment, Payment Receipt Attachment, advance balances or physical deletion. Preserve financial history and the local untracked backup. PR merge is permitted only after exact-head CI is green, GitHub reports mergeability and no blocker remains; never merge directly to `main`.

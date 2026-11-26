# Phase 5 — Batch 8.7 Financial Workflow Acceptance

**Status:** Implementation branch prepared; CI execution and acceptance are pending.  
**Branch:** `test/phase-5-batch-8-7-financial-acceptance`  
**Base:** `main` at `338eec64c43b06be65282092a36be1587405df02`

## Purpose

Extend the Phase 5 release evidence beyond the targeted Batch 8.5 regressions. This batch focuses on a complete financial lifecycle, transactional rollback, payment-correction endpoint authorization and tenant isolation.

## Approved behavior

ADR-0012 remains the controlling Product Owner decision. Customer-facing payments remain consolidated; immutable internal FIFO allocation records are required across eligible Sales/components, applying the configured per-Sale Principal First or Interest First waterfall. Excess payments are rejected. No advance balance or sale-invoice payment UI is introduced.

## Added acceptance coverage

`tests/Feature/Financial/Batch87FinancialWorkflowAcceptanceTest.php` adds:

1. **Financial lifecycle:** Unpaid Sale, Paid Sale with linked automatic Payment, normal Payment, FIFO allocation excluding the Paid Sale, same-date annual interest settlement and final ledger/projection reconciliation.
2. **Paid Sale atomicity:** Inject an automatic-payment audit failure and assert the Sale, automatic Payment, financial transactions, ledger, idempotency and audit writes all roll back.
3. **Payment atomicity:** Inject a Payment audit failure and assert Payment, allocation, interest checkpoint, financial transaction and ledger writes roll back while the pre-existing Sale remains intact.
4. **Payment correction API:** Verify Manager and Staff denial, Owner success, and cross-Shop customer/payment isolation through the authenticated HTTP endpoint.

Existing MySQL 8.4 locking coverage remains in:
- `tests/Feature/Financial/CustomerAccountLockingTest.php`
- `tests/Feature/Financial/ProjectionRepairConcurrencyTest.php`

Existing policy matrix and financial API tests remain in place and are not replaced by these additions.

## Agent instruction consistency

ADR-0012 supersedes ADR-0004's older prohibition on internal Sale-wise allocation. `AGENTS.md`, `CLAUDE.md` and `.claude/rules/financial-integrity.md` are aligned so future implementation agents preserve consolidated customer-facing payments while maintaining the approved internal FIFO allocation records.

## Execution and release evidence

The GitHub Actions workflow runs PHP formatting, Composer validation, Vite production build, the dedicated MySQL 8.4 row-locking group and the remaining Laravel test suite.

No test result is considered passed until the relevant GitHub Actions run completes successfully. The repository does not currently configure a dedicated browser automation test runner; Vite build and HTTP feature tests are not represented as browser-level acceptance evidence.

## Safety boundary

No production database, production data, production migration, backfill, deployment or release is included or authorized. This batch does not close the Phase 5 release gate by itself; remaining concurrency and operational readiness evidence must be assessed against the full release checklist.

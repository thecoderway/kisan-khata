# Phase 5 — Batch 8.8 MySQL Financial Posting Concurrency Acceptance

**Status:** Implemented, merged and CI-verified  
**PR:** [#50 — Phase 5 final MySQL concurrency acceptance](https://github.com/thecoderway/kisan-khata/pull/50)  
**Merge commit:** `c740e44434071062c4cb8eac546f375a7e234126`  
**Test head:** `5fd97786b7ca78bd22a1f200d36cebf551182be4`  
**Controlling decision:** [ADR-0012](decisions/ADR-0012-approved-financial-rules.md)

## Objective

Add deterministic MySQL 8.4 integration coverage for critical financial posting races that were not sufficiently represented by the earlier lock-primitive and projection-repair tests. The tests exercise the existing domain services and Customer account-lock boundary; they do not change financial business behavior or schema.

## Added acceptance scenarios

Implemented in `tests/Feature/Financial/FinancialPostingConcurrencyTest.php`, grouped under `mysql-locking`.

### 1. Competing normal Payments cannot overdraw payable

- Create an Unpaid Sale for ₹100.
- Begin a primary MySQL transaction and post a ₹70 Payment while retaining the Customer lock.
- Start a second process using a separate MySQL connection and attempt a ₹50 Payment.
- Observe the worker waiting on the database row lock, then commit the first transaction.
- Assert the second Payment is rejected with the existing conflict response.
- Assert only the first Payment is persisted, the remaining principal/payable is ₹30, and reconciliation is consistent.

### 2. Sale-versus-Payment race preserves FIFO allocation

- Create the first Unpaid Sale for ₹100.
- Begin a primary transaction and post a second Unpaid Sale for ₹50 while retaining the Customer lock.
- Start a second process with a separate MySQL connection and attempt a ₹120 Payment.
- Confirm the Payment waits for the Sale transaction, then proceeds after commit.
- Assert the Payment allocates FIFO across both Sales in order, allocates exactly ₹120 using decimal-safe arithmetic, leaves ₹30 payable, and reconciles consistently.

### 3. Payment-versus-same-day annual settlement does not duplicate interest

- Create an Unpaid Sale for ₹1,000 under a 3% monthly policy with zero grace days.
- Begin a primary transaction and post a ₹10 Payment dated 30 October while retaining the Customer lock.
- Start annual settlement for the same Shop/date on a separate connection.
- Confirm settlement waits for Payment posting to commit, then completes.
- Assert only one positive interest calculation item is posted, the settlement run completes, the account interest is ₹28.00 under the approved [start inclusive, end exclusive) date convention, total payable is ₹1,018.00, and reconciliation is consistent.

## Test isolation and cleanup

The test creates an isolated Shop, Owner, Customer and effective-dated interest policy. It uses independent MySQL connections/processes and observes actual InnoDB lock waits through Performance Schema rather than relying on timing-only sleeps. The test cleanup deletes fixtures in foreign-key dependency order and does not use production data.

## Verification evidence

GitHub Actions [Laravel CI run 45879804264](https://github.com/thecoderway/kisan-khata/actions/runs/45879804264) completed successfully on the PR head:

- Composer validation — passed.
- Laravel Pint formatting — passed.
- Vite production build — passed.
- MySQL 8.4 row-lock integration group — passed, including all three new concurrency scenarios.
- Remaining Laravel test suite — passed.

The PR was squash-merged to `main`. The separate post-merge `main` CI run is tracked in [Project Status](PROJECT-STATUS.md); do not infer its result from the feature-head run.

## Scope and limitations

- Test-only change: no application code, accounting rule, database schema or migration changed.
- No production migration, backfill, deployment, production data mutation or release was performed or authorized.
- These automated tests do not replace Product Owner browser/UI smoke acceptance for the financial web workflows.
- Production environment, backup/restore and deployment/recovery readiness belong to Phase 14.

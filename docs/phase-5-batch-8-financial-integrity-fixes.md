# Phase 5 — Batch 8 Financial Integrity and Release Gate

**Last updated:** 2026-11-26  
**Current `main` baseline:** `5f98e0d4cdd3cf11eb3786004cac3a359aacb18c`  
**Delivery state:** Batches 8.2, 8.3, 8.4 and 8.5 are merged via PRs #44–#47. Batch 8.5 adds repair rollback/audit atomicity and reconciliation authorization regression coverage; it does not declare Phase 5 released.

## 1. Purpose and scope

Batch 8 consolidates the financial integrity review after the Phase 5 financial services, annual settlement, ledger projection/reconciliation, authorization, APIs and UI were merged. The work addresses code-level risks found during read-only integration review and records what is and is not verified for the Phase 5 release gate.

This document records:
- the initial integration audit (Batch 8.1);
- implemented corrections (Batch 8.2 and Batch 8.3);
- regression coverage and CI evidence;
- remaining acceptance work and explicit safety boundaries.

No new business rule is introduced here. ADR-0012 remains the controlling Product Owner decision for financial behavior.

## 2. Batch 8.1 — Read-only integration audit

The audit reviewed the repair API and service transaction boundaries, account locking, tenant scoping, financial policies, audit-log protections, routes and regression tests.

### P1 finding: repair API could retain a pre-lock REPEATABLE READ snapshot

The first implementation of the repair service acquired the Customer/Account lock before its own first consistent ledger read. However, the actual controller wrapped the flow in an outer transaction and performed reconciliation before calling that service. Under MySQL REPEATABLE READ, the nested Laravel transaction shared the outer transaction's snapshot. Consequently, the real API path could still hold a stale snapshot while waiting for a concurrent posting lock.

The direct service-level concurrency test did not exercise that controller transaction boundary. This was a release-blocking gap and required a regression test against the authenticated API endpoint.

### Other reviewed boundaries

The inspected tenant-owned models/builders and financial policies enforced current-Shop scoping and role checks for the reviewed paths. Audit-log mutation protections were also present. This was a scoped code review, not a blanket certification of every financial workflow or all possible raw SQL paths.

Repository branch-protection settings could not be inspected with the current GitHub integration permissions. Their state remains unknown and must be verified by a repository administrator.

## 3. Batch 8.2 — merged integrity corrections (PR #44)

**Merge commit:** `a187480a4cdf132a1ffe6b3af4a8ac185b4b226d`

### 3.1 Lock-first projection repair service

- Added a transaction path that acquires the Customer/Account lock before its first consistent ledger read.
- Rebuilds the projection under the same lock and reconciles again before commit.
- Keeps ordinary `reconcileCustomer()` read-only; it does not create or repair a missing projection.

### 3.2 Recurring settlement calendar validation

- Validates configured month/day pairs against a leap-year calendar.
- Rejects impossible dates such as 31 April.
- Allows 29 February as a recurring configuration; it is due only in actual leap years.

### 3.3 Regression coverage and documentation

- Added API coverage for impossible recurring settlement dates and valid 29 February.
- Added a deterministic MySQL two-connection projection-repair concurrency test.
- Updated Phase 5 financial integrity documentation and project status in the implementation batch.

## 4. Batch 8.3 — actual API-path concurrency correction (PR #45)

**Merge commit:** `bc9afb78811ca28d2238506bcba6ec1154ad05d7`

### 4.1 Root-cause correction

The controller no longer opens an outer transaction and performs a reconciliation read before the lock. It calls the new `ReconciliationService::repairAndReconcile()` path directly.

That service:
1. Starts the repair transaction.
2. Acquires Customer and Account locks before the first consistent ledger read.
3. Reconciles the current ledger and projection.
4. Rebuilds only when drift exists.
5. Performs final reconciliation and aborts/rolls back if consistency is not restored.
6. Invokes the audit callback inside the same transaction after successful repair and before commit.

The legacy `rebuildAndReconcile()` service method remains as a backwards-compatible wrapper. Ordinary reconciliation remains read-only.

### 4.2 Endpoint-level concurrency regression

The deterministic MySQL concurrency test now calls the authenticated repair HTTP endpoint from a separate database connection/process while a financial posting holds the account lock. It verifies that the repair waits for the posting, then uses the committed ledger snapshot and returns a consistent result.

This closes the specific test gap found in Batch 8.1: the test exercises the controller/API transaction boundary rather than calling only the service method.

## 5. CI evidence

GitHub Actions run [45868343441](https://github.com/thecoderway/kisan-khata/actions/runs/45868343441) for the PR #45 head completed with conclusion **success**.

All listed job steps completed successfully, including:
- Composer configuration validation;
- PHP formatting (Pint);
- frontend dependency installation and Vite production build;
- MySQL row-lock integration test;
- remaining Laravel test suite;
- container cleanup.

This CI run verifies the tested PR #45 head. It does not establish that the entire Phase 5 acceptance matrix is complete, and it is not evidence of production deployment, production database migration or live-environment verification.

## 6. Current merged delivery baseline

| Delivery | Status |
|---|---|
| Batch 5 — Annual Interest Auto Settlement | PR #40 merged |
| Batch 6 — Ledger Projection & Reconciliation | PR #41 merged |
| Batch 7 — Financial Authorization, APIs & UI | PR #43 merged |
| Batch 8.2 — Lock ordering and settlement calendar validation | PR #44 merged |
| Batch 8.3 — API-path lock-first atomic repair | PR #45 merged |
| Batch 8.4 — Documentation/release-gate synchronization | PR #46 merged; merge commit `a97cf7f9272e5a3d880a0a74ff23d43f633e9c3c` |
| Batch 8.5 — Repair rollback/audit atomicity and reconciliation authorization regression coverage | PR #47 merged; merge commit `5f98e0d4cdd3cf11eb3786004cac3a359aacb18c` |

Current `main` baseline for this document: `5f98e0d4cdd3cf11eb3786004cac3a359aacb18c`.

## 7. Batch 8.5 — targeted release-gate regression coverage

The current implementation branch adds:

- A failure-injection test that writes the repair audit event inside the repair callback, throws before transaction commit, and asserts both the projection repair and audit row are rolled back.
- API authorization tests proving Manager and Staff cannot repair projections and an Owner cannot address a Customer belonging to another Shop.
- Policy unit coverage for Manager, Staff, inactive Owner and cross-Shop Owner denial.

GitHub Actions run [45872271027](https://github.com/thecoderway/kisan-khata/actions/runs/45872271027) completed successfully. All steps passed, including the MySQL row-lock integration test, remaining Laravel test suite, Pint formatting, Composer validation and Vite production build. These tests cover the repair path and reconciliation authorization matrix only; they do not establish rollback/concurrency acceptance for all Sale, Payment, reversal or settlement workflows.

## 8. Remaining Phase 5 acceptance and release gate

<ins>Phase 5 is implemented through the merged batches above, but its final acceptance/release gate remains OPEN.</ins>

The following work still requires explicit evidence:

1. **End-to-end financial workflows:** exercise Sale, Payment, FIFO allocation and per-Sale waterfall, interest calculation/posting, annual settlement, ledger projection and reconciliation as complete business flows.
2. **Atomicity, rollback and idempotency:** verify failure injection and duplicate-request behavior across all critical posting, reversal, settlement and repair paths.
3. **Role authorization:** verify Owner, Manager and Staff permissions for every financial API/UI action, including denied actions and cross-Shop access attempts.
4. **MySQL 8.4 concurrency:** confirm deterministic locking/race coverage for Sale, Payment, reversal, settlement and projection repair, not only the repair path.
5. **Frontend/API regression:** verify UI behavior, API response contracts, validation errors and consistency between displayed balances and authoritative ledger results.
6. **Operational readiness:** complete environment, backup/restore, monitoring, security and deployment-runbook checks in the appropriate production-readiness phase.
7. **Branch governance:** have a repository administrator verify branch protection, required CI checks and review requirements. The current integration could not inspect these administration-level settings, so no assumption is made about their state.

A future release-gate report should link each criterion to its exact test, workflow run, review evidence or operational artifact and explicitly record any exclusions.

## 9. Explicit safety boundaries

- No new schema change or migration is part of Batch 8.2/8.3/8.4/8.5.
- No production migration, production data mutation, backfill or deployment has been performed or authorized.
- Do not run migrations or deployment as part of documentation synchronization.
- Do not mark Phase 5 released solely because implementation PRs are merged or CI is green.
- Any production migration or deployment requires separate explicit Product Owner approval.
- Any change to approved financial business behavior requires a separate decision recorded against ADR-0012 before implementation.

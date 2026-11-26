# Phase 5 — Batch 8.2 Financial Integrity Fixes

**Date:** 2026-11-26  
**Branch:** `fix/batch-8-financial-integrity`  
**Base:** `main` after Batch 7 merge (PR #43, commit `7b8b9791e04788569dc09935cc1854982732c717`).

## Scope

This batch addresses two code-level risks identified during the Batch 8.1 read-only audit and records the current Phase 5 delivery state. It adds no migrations, schema changes, data backfills or deployment steps.

## Fix 1 — Serialize projection repair before establishing a read snapshot

`ReconciliationService::rebuildAndReconcile()` now starts one transaction, acquires the existing customer/account lock through `CustomerAccountLockService`, then performs its first consistent ledger read. It rebuilds with `BalanceProjectionService::rebuildLocked()` under that same lock and reruns reconciliation before commit. This follows the lock order used by financial posting and avoids retaining a pre-lock MySQL REPEATABLE READ snapshot while waiting for the lock.

The ordinary `reconcileCustomer()` path remains read-only and does not acquire a lock or create a missing account projection.

## Fix 2 — Validate recurring settlement calendar dates

Shop interest policy writes validate each month/day pair against a leap-year calendar. Impossible dates such as 31 April are rejected. 29 February remains valid as a recurring configuration and is due only in actual leap years, consistent with scheduler behavior.

## Regression coverage

- Existing Batch 6 reconciliation tests cover read-only discrepancy detection, explicit repair, projection/snapshot/Sale-status rebuilding and cross-Shop rejection.
- Batch 7 API tests now cover rejection of an impossible calendar date and acceptance of 29 February.
- CI and MySQL concurrency evidence will be added after the branch checks complete.

## Verified delivery state

- Batch 5 annual settlement: merged via PR #40.
- Batch 6 ledger projection/reconciliation: merged via PR #41.
- Batch 7 financial authorization/APIs/UI: merged via PR #43.
- Batch 7 planning document PR #42 remains open separately.

## Boundaries

No migrations, schema changes, local/production data writes, backfills, deployment or merge to `main` are included. Batch 8 final release gate remains open until end-to-end financial, authorization, tenant-isolation, MySQL concurrency and frontend/API acceptance checks pass.

# Batch 5 — Annual Interest Auto Settlement

## Scope

Annual Interest Auto Settlement is independent of crop-season settlement. The two Shop-configured dates are read from the effective-dated `DEFAULT` interest policy version. Initial defaults remain 30 March and 30 October. A settlement uses the Shop timezone and the policy version effective on the settlement date.

## Execution

- Artisan command: `php artisan financial:annual-interest-settlement`
- Laravel scheduler: every minute, with overlap prevention.
- Each run evaluates the previous completed calendar date in each active Shop's timezone. This leaves the settlement date open for normal same-day payments.
- Only customers with a positive account payable are considered.
- The first active Shop Owner is recorded as the actor for the financial posting.
- A customer/date settlement run is unique and idempotent. Calculation and ledger posting use the existing customer-account lock and financial transaction idempotency boundary.
- A retry reuses the existing PENDING run; a completed run is immutable and replay-safe.
- A settlement never moves an interest checkpoint backwards.
- The existing interest engine handles effective-dated policy segmentation, interest lots, ledger entries, account projection and audit records. The settlement orchestrator does not duplicate those calculations.

## Operational behavior

The scheduler must be enabled in the deployment environment. Monitor command output and Laravel error reporting; a failed customer is reported and does not prevent other customer settlements from being attempted. Re-running the command is safe.

## Safety

- No daily/monthly interest posting job is introduced.
- Normal payment processing remains the payment event and must finish before the settlement command reaches the prior local date.
- No customer advance is introduced.
- No posted ledger entry is edited or deleted.
- No production migration, backfill, or deployment is performed by this feature branch.

## Remaining configuration surface

Settlement dates are already stored as effective-dated policy fields. This batch's scheduled runner consumes those fields. A dedicated Owner-facing policy editor/API is a separate presentation workflow and must preserve the existing policy-version immutability contract; do not update historical policy rows in place.

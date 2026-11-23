# Repository Agent Instructions

Target stack: PHP 8.3, Laravel 13, MySQL 8.4. Inspect composer.json, composer.lock, package.json and actual runtime before assuming versions.

- Do not install or upgrade PHP, Composer, Laravel, Node, database services, packages or system tools automatically.
- Do not run remote installer scripts.
- Do not run dependency changes, migrations, seeders or destructive commands without explaining impact and obtaining approval.
- Laravel Boost may be used only if already installed or explicitly approved; never install it automatically.
- Read CLAUDE.md and relevant approved docs/ and .claude/rules/ before changing application code.

Mandatory product invariants:
- Shared database tenancy; derive shop_id from authenticated user context, never client input.
- One User = One Shop = One Role; Owner, Manager, Staff.
- No sale-level payment allocation and no payment_allocations table.
- Payment reduces consolidated customer payable after interest is calculated through payment date; same-day subsequent payments do not duplicate interest.
- New sales retain their own dates; carried-forward outstanding starts a new calculation cycle on payment date.
- Excess payment is customer advance.
- Owner may edit posted Sales and Payments with immutable before/after audit history; Manager/Staff cannot.
- Preserve original customer text and store Hindi/English translations separately.
- Use decimal-safe financial arithmetic, transactions, locking and idempotency where applicable.

Inspect first, plan small changes, identify unresolved business rules, request approval for scope/architecture changes, implement, test and report verified results. Approved docs/ and accepted ADRs override conflicting legacy guidance.

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
- Follow accepted ADR-0012 for Phase 5 payment accounting: customer-facing Payments remain consolidated, with immutable internal FIFO allocation records across eligible Sales/components and the configured per-Sale waterfall. ADR-0012 supersedes ADR-0004's earlier prohibition on internal Sale-wise allocation.
- Payment reduces consolidated customer payable after interest is calculated through payment date; same-day subsequent payments do not duplicate interest.
- New sales retain their own dates; carried-forward outstanding starts a new calculation cycle on payment date.
- Excess payment is customer advance.
- Owner may edit posted Sales and Payments with immutable before/after audit history; Manager/Staff cannot.
- Preserve original customer text and store Hindi/English translations separately.
- Use decimal-safe financial arithmetic, transactions, locking and idempotency where applicable.

Mandatory UI table pagination:
- Every data table created or modified in the UI MUST implement pagination. Never render an unbounded or unpaginated data table.
- Use a default page size of 10 or 20 rows.
- Provide a page-size selector with options 10, 20, 50 and 100.
- Provide Previous and Next controls; disable Previous on the first page and Next on the last page.
- Show the current range and total record count, e.g. "Showing 1–10 of 245".
- Fetch only the requested page from the backend. Pass and validate page plus limit/pageSize parameters; never fetch all records and paginate only in the browser.
- Preserve active filters and relevant sort state when changing page size or page.
- Enforce a safe maximum page size of 100 on the server. Invalid values must be validated or safely normalized server-side.
- Any exception requires explicit, documented product-owner approval; do not silently omit pagination for small datasets, lookup tables or currently short result sets.
- Follow docs/ui/TABLE-GUIDELINES.md for the reusable UI and backend contract.

Inspect first, plan small changes, identify unresolved business rules, request approval for scope/architecture changes, implement, test and report verified results. Approved docs/ and accepted ADRs override conflicting legacy guidance.


Mandatory theme consistency:
- Tabler Core is the only approved UI theme/component system for authenticated application UI. All new or modified UI MUST follow Tabler component patterns, markup, responsive behavior and accessibility conventions, customized only with the approved Kisan Khata design tokens.
- Consult https://preview.tabler.io/ and https://github.com/tabler/tabler when component behavior or presentation is unclear. Match the installed Tabler package version; do not blindly copy newer APIs.
- Do not introduce another UI framework or unrelated visual system without an approved architecture decision. Any deliberate Tabler deviation requires documented product-owner approval.

# Kisan Khata — Claude Code Instructions

## Source of truth
The approved baseline is docs/01-product-requirements.md through docs/10-development-roadmap.md (v2.0, Approved), together with accepted ADRs in docs/decisions/. These take precedence over older instructions, examples and code conventions. If a rule is unresolved or documents conflict, stop and ask the product owner; never guess.

## Stack and architecture
- Target stack: PHP 8.3, Laravel 13, MySQL 8.4.
- Modular Monolith; no microservices without approval.
- Responsive web first; API-ready backend for later native Android.
- Inspect the actual manifests and runtime before acting. Do not install/upgrade dependencies, frameworks, tools or infrastructure without approval.

## Tenancy and roles
- Shared MySQL database; every tenant-owned record is scoped by shop_id.
- One User = One Shop = One Role.
- Roles: Owner, Manager, Staff; per-shop limits: 1 Owner, 2 Managers, 5 Staff.
- Resolve shop context from authenticated user server-side. Never trust client-supplied shop_id.
- Enforce tenant ownership in queries, relationships, jobs, files, cache, exports and APIs.

## Financial invariants
- There is NO sale-level payment allocation. Never create a payment_allocations table, allocation records, invoice allocation workflow or payment split between interest/principal.
- At payment: calculate eligible interest through payment date, determine consolidated payable, subtract received payment, and carry remainder forward from that date.
- Same-day subsequent payments must not duplicate interest.
- New sales retain their individual sale dates; carried-forward consolidated balance has its own calculation-cycle start date.
- Excess payment is retained as customer advance.
- Interest events: customer payment and two Owner-configured annual Auto Settlement dates. No daily/monthly posting job.
- Actual Days / 30 is the approved day-count basis. Do not invent unresolved rate defaults, rounding, date boundaries, capitalization defaults or exact annual dates.
- Owner may edit posted Sales and Payments. Manager/Staff may not. Preserve immutable before/after audit history and recalculate affected projections safely. Unspecified backdated correction rules require product-owner clarification.

## Customer, language and sales
- Customer name required; mobile and village optional.
- Sale: customer, date, amount, description; crop/season tag optional. Do not require product categories.
- Preserve original customer-entered text; store Hindi and English translations separately. Translation never overwrites original.
- UI supports Hindi and English.

## Subscription and platforms
- Subscription belongs to the shop and applies to all shop users.
- Free Trial, Basic, Standard, Premium; configurable trial (approved baseline 3 months), monthly/annual billing.
- Three-day grace after expiry, then read-only. Owner retains export access.
- Do not invent plan entitlements/pricing/payment gateway while pending.
- Android is later phase, uses same backend logic; offline sync requires separate approval.

## Engineering
Use decimal-safe money arithmetic and MySQL DECIMAL. Keep controllers thin; business logic in tested services. Use server-side validation, policies, transactions, locks and idempotency. Preserve audit history. Do not make unrelated changes or claim unverified tests/features complete.

## Mandatory UI table pagination
Every data table created or modified in the UI MUST be paginated. Never render an unbounded or unpaginated data table.
- Default page size: 10 or 20 rows.
- Include a page-size selector with options 10, 20, 50 and 100.
- Include Previous/Next navigation; disable Previous on the first page and Next on the last page.
- Display the current range and total count, e.g. "Showing 1–10 of 245".
- Backend endpoints and frontend queries MUST pass page and limit/pageSize parameters and fetch only that page. Do not fetch all records for client-side pagination.
- Preserve filters and sort state across pagination changes.
- Validate/normalize page size server-side and enforce a maximum of 100.
- Exceptions require explicit, documented product-owner approval.
- Follow docs/ui/TABLE-GUIDELINES.md for the complete implementation and accessibility contract.

## Workflow
Read relevant approved docs and ADRs; inspect existing code; propose scope and schema impact; identify unresolved decisions; obtain approval where needed; implement small steps; test and review tenant/security/financial integrity; update documentation and report actual results. Do not modify files or GitHub without explicit user authorization.

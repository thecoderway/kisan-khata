
# Kisan Khata — Project Starter Kit

Kisan Khata is a cloud-based, multi-tenant SaaS application for small-town Indian merchants who sell products to farmers on credit and collect payments later.

## Product Vision

Provide a simple digital accounting and credit ledger system for:

- Cloth merchants
- Electronics merchants
- Agricultural input and fertilizer shops
- Seed and pesticide retailers
- Other small-town merchants

The system should replace manual notebooks and help merchants maintain accurate customer balances, credit sales, payments, interest calculations and crop-season settlements.

## Technology Direction

- Backend: Laravel (PHP)
- Database: MySQL
- Frontend: Blade, Bootstrap 5 and Alpine.js (initial proposed stack)
- Hosting: Cloud
- Architecture: Modular Monolith
- Tenancy: Shared database with tenant isolation
- Phase 1: Responsive Web Application
- Phase 2: Android Application using versioned APIs
- Languages: Hindi and English
- Business model: SaaS Subscription

## Important Financial Principles

1. Never use PHP floating-point numbers for financial calculations.
2. Use DECIMAL database columns and precise decimal arithmetic.
3. Keep principal and interest separately identifiable.
4. Do not silently modify posted financial transactions.
5. Use reversal and replacement entries for corrections.
6. Every payment must have a recorded allocation.
7. Every financial operation must be auditable.
8. Never calculate interest using assumptions that have not been approved.
9. Never allow one shop to access another shop's data.
10. All financial operations must be transactional and concurrency-safe.

## Before Development

Read these documents in order:

1. docs/01-product-requirements.md
2. docs/02-business-rules.md
3. docs/03-accounting-engine.md
4. docs/04-database-architecture.md
5. docs/05-multi-tenancy.md
6. docs/06-security.md
7. docs/07-ui-ux-guidelines.md
8. docs/08-api-architecture.md
9. docs/09-testing-strategy.md
10. docs/10-development-roadmap.md

Then read CLAUDE.md and all applicable files inside .claude/.

## Important: Unresolved Business Decisions

Do not invent or silently assume these rules:

- Whether the example interest rate 2.00 means 2% per month.
- Whether the interest-free period means one calendar month or 30 days.
- Whether interest is calculated daily or monthly.
- Whether partial months attract full or proportional interest.
- Whether interest compounds.
- Whether interest applies to principal only or principal plus unpaid interest.
- Whether payments are allocated to interest first or principal first.
- How crop-season settlements affect existing balances.
- Whether settlement interest is crystallized or recalculated.

Record these questions in the appropriate decision documents.

Ask the product owner before implementing unresolved financial rules.

## Development Instructions

Use a step-by-step development approach.

For every development phase:

1. Inspect the existing codebase.
2. Read the relevant documentation.
3. Propose an implementation plan.
4. Identify affected database tables and services.
5. Implement the feature.
6. Write automated tests.
7. Run relevant tests.
8. Review security and tenant isolation.
9. Update PROJECT-STATUS.md.

Do not implement the entire application in one large operation.

## Project Status

Initial documentation and architecture setup.

Implementation has not been approved merely because this starter kit exists.

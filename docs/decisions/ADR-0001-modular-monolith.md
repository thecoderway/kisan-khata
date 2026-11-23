# ADR-0001 — Modular Monolith Architecture

**Status:** ACCEPTED  
**Date:** 2026-11-22

## Context
Kisan Khata is a multi-tenant SaaS with connected customer, sales, payment, accounting, interest, settlement, report and subscription modules. Shared financial data and an initially lean team favor a single deployable application with explicit boundaries.

## Decision
Use a Laravel Modular Monolith. Keep business modules logically separated, business rules in explicit services/domain components, and financial workflows transactional and testable. Do not introduce microservices in the initial release without a separately approved ADR.

## Consequences
Benefits: single deployment, shared database transactions, lower operational complexity and clearer module responsibilities. Trade-off: module boundaries require discipline and architectural review.

## Security and Financial Integrity
Every module enforces authorization and tenant ownership. Financial operations use decimal-safe arithmetic and preserve auditability. Payment processing follows the consolidated customer ledger model; no sale-level payment allocation.

## Reconsideration
Reconsider only if verified deployment/scaling requirements justify independent services; require product-owner approval and a superseding ADR.
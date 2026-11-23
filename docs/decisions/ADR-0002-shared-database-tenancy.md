# ADR-0002 — Shared Database Multi-Tenancy

**Status:** ACCEPTED  
**Date:** 2026-11-22

## Context
Kisan Khata serves independent shops requiring strong customer and financial data isolation with manageable initial infrastructure.

## Decision
Use one shared MySQL database with shop_id on tenant-owned records. Centralized tenant context, server-side authorization, relationship ownership validation and automated isolation tests are mandatory.

## Required safeguards
1. Never trust client-supplied shop_id.
2. Resolve tenant from authenticated user (one user belongs to one shop).
3. Scope tenant queries and validate related record ownership.
4. Protect private files and exports.
5. Isolate caches and explicitly establish tenant context in jobs.
6. Use database constraints where practical and test cross-tenant access.

## Consequences
Simpler centralized migrations and operations; tenant isolation becomes a critical application responsibility. Individual tenant restoration and noisy-neighbor concerns require operational planning.

## Security and Financial Integrity
Every customer, sale, payment and ledger record must belong to the same shop. Cross-tenant financial references are rejected.

## Reconsideration
Dedicated or hybrid databases require verified need, migration plan, product-owner approval and superseding ADR.
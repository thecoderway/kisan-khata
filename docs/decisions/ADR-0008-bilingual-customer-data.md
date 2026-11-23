# ADR-0008 — Bilingual Customer Data

**Status:** ACCEPTED  
**Date:** 2026-11-23

## Context
The application supports Hindi and English. Customer information may be entered in either language; translation must not destroy original text.

## Decision
Preserve the exact original customer-entered value and store Hindi and English translations separately. Automatic translation must never overwrite original text. Authorized users may correct translation values while preserving original input. UI localization is distinct from customer-data translation.

## Consequences
The application can display and search bilingual customer data while retaining the original source. Translation provider and implementation details may be selected technically without changing this invariant.

## Security and Financial Integrity
Protect customer data by tenant and role. Preserve original values during translation updates.

## Related Docs
docs/01-product-requirements.md, docs/07-ui-ux-guidelines.md, docs/04-database-architecture.md.

## Reconsideration
Requires explicit product-owner approval and a superseding ADR.
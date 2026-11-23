# ADR-0003 — Web-First Development with API-Ready Architecture

**Status:** ACCEPTED  
**Date:** 2026-11-22

## Context
The initial product is a responsive web application; a native Android app is planned later. Financial rules must not be duplicated across clients.

## Decision
Build the web application first with business logic independent of presentation. Introduce versioned REST APIs alongside backend modules as required. Future Android uses the same backend services and accounting rules. Offline synchronization is excluded from initial release unless separately approved.

## Consequences
Faster initial web delivery and shared backend logic; requires stable service boundaries, API versioning and separate client authentication considerations.

## Security and Financial Integrity
Web and API enforce identical tenant and role permissions. Interest, consolidated payment handling (without sale-level allocation), settlement and ledger generation remain backend responsibilities. Android is never authoritative for financial calculations.

## Reconsideration
Any change to platform sequencing or backend/client separation requires product-owner approval and a superseding ADR.
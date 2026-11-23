# ADR-0005 — User, Shop and Role Relationship

**Status:** ACCEPTED  
**Date:** 2026-11-23

## Context
The SaaS access model needs clear tenant ownership and predictable authorization without multi-shop membership complexity.

## Decision
One User = One Shop = One Role. Roles at launch are Owner, Manager and Staff. Each shop supports one Owner, up to two Managers and up to five Staff. Owner manages shop users. Resolve shop context from authenticated user server-side.

## Consequences
Authorization and tenant resolution are simpler. A user cannot switch between multiple shops under one account in the approved model. Any future membership expansion requires a new decision.

## Security and Financial Integrity
Never trust client shop_id. Enforce role permissions and shop ownership in server-side policies and services, including APIs, jobs, files and exports.

## Related Docs
docs/01-product-requirements.md, docs/04-database-architecture.md, docs/05-multi-tenancy.md, docs/06-security.md.

## Reconsideration
Requires explicit product-owner approval and a superseding ADR.
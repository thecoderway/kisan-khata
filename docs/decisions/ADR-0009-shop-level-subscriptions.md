# ADR-0009 — Shop-Level Subscriptions

**Status:** ACCEPTED  
**Date:** 2026-11-23

## Context
Subscription entitlement belongs to the merchant shop, not an individual login. All users of a shop share the same subscription status.

## Decision
Subscription belongs to the Shop and applies to all shop users. Plans: Free Trial, Basic, Standard and Premium. Trial duration is configurable with an approved baseline of three months. Monthly and annual billing are supported. After expiry, a three-day grace period is followed by read-only access. Owner retains data export access.

## Consequences
Subscription enforcement is consistent across all shop users. Exact plan entitlements, prices, payment gateway and remaining billing details are still pending and are not invented by this ADR.

## Security and Financial Integrity
Enforce subscription state server-side for web and API. Expiry read-only mode must not block Owner data export access.

## Related Docs
docs/01-product-requirements.md, docs/04-database-architecture.md, docs/06-security.md, docs/10-development-roadmap.md.

## Reconsideration
Requires explicit product-owner approval and a superseding ADR.
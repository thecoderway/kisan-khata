# Kisan Khata — Architecture Decision Records

ADRs record important accepted or proposed technical/business architecture decisions. Approved product documentation v2.0 is the baseline; ADRs must not invent policy beyond it.

## Status
- PROPOSED: awaiting approval.
- ACCEPTED: explicitly approved decision.
- REJECTED: not selected.
- SUPERSEDED: replaced by a later accepted ADR; preserve history.

## Index
| ADR | Topic | Status |
|---|---|---|
| ADR-0001 | Modular Monolith | ACCEPTED |
| ADR-0002 | Shared Database Multi-Tenancy | ACCEPTED |
| ADR-0003 | Web-First, API-Ready Architecture | ACCEPTED |
| ADR-0004 | Consolidated Customer Ledger and Payments | ACCEPTED |
| ADR-0005 | User, Shop and Role Relationship | ACCEPTED |
| ADR-0006 | Financial Transaction Correction and Audit | ACCEPTED |
| ADR-0007 | Interest Calculation Events | ACCEPTED — specified scope only; open parameters remain pending |
| ADR-0008 | Bilingual Customer Data | ACCEPTED |
| ADR-0009 | Shop-Level Subscriptions | ACCEPTED |
| ADR-0010 | User Lifecycle and Role Permissions | ACCEPTED |
| ADR-0011 | Geographic Master Data and Shop/Customer Location Defaults | ACCEPTED |
| ADR-0012 | Approved Financial Rules: Sale, Payment, Interest, FIFO Allocation and Settlement | ACCEPTED |
| ADR-0013 | Asynchronous Action Drawer Form Submission | ACCEPTED |
| ADR-0014 | Application-Wide Right-Side Drawer Standard | ACCEPTED |

## ADR template
Each ADR must include context, decision, consequences, security/data-integrity impact, related docs and reconsideration criteria. Preserve accepted decision history; changes require explicit approval and a superseding ADR.

If an ADR conflicts with docs/01–10 v2.0 or an explicitly approved later decision, stop and report the conflict. Do not silently change accepted decisions.

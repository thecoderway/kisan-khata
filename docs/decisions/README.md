
# Kisan Khata — Architecture Decision Records

## 1. Purpose

This directory contains Architecture Decision Records (ADRs).

An ADR documents an important technical or architectural decision.

It explains:

- The problem.
- The available options.
- The selected option.
- The reason for the decision.
- The consequences.
- The current status.

## 2. ADR Naming Convention

Use:

`ADR-NNNN-short-description.md`

Examples:

- ADR-0001-modular-monolith.md
- ADR-0002-shared-database-tenancy.md
- ADR-0003-web-first-api-ready.md

## 3. ADR Status

Use one of:

- PROPOSED
- ACCEPTED
- REJECTED
- SUPERSEDED

Do not mark a proposal as accepted without product owner or authorized technical approval.

## 4. ADR Template

```markdown
# ADR-NNNN — Decision Title

**Status:** PROPOSED  
**Date:** YYYY-MM-DD

## Context

Describe the problem and why a decision is required.

## Decision Drivers

- Driver 1
- Driver 2

## Options Considered

### Option A

Description and trade-offs.

### Option B

Description and trade-offs.

## Decision

Describe the selected option.

## Consequences

### Positive

- Benefits.

### Negative

- Trade-offs.

## Security Impact

Describe security implications.

## Financial Data Impact

Describe financial data implications.

## Future Reconsideration

Explain what circumstances would justify revisiting the decision.
```

## 5. Decision Rules

1. Avoid recording minor implementation details as ADRs.
2. Record decisions that affect system architecture.
3. Record decisions that are expensive to reverse.
4. Record decisions that affect financial integrity.
5. Record decisions that affect tenant isolation.
6. Link related documentation.
7. Preserve superseded ADRs.
8. Never silently rewrite an accepted decision.

## 6. Current ADR Index

| ADR | Topic | Status |
|---|---|---|
| ADR-0001 | Modular Monolith | PROPOSED |
| ADR-0002 | Shared Database Tenancy | PROPOSED |
| ADR-0003 | Web First and API Ready | PROPOSED |

## 7. Approval

Technical implementation should follow accepted ADRs.

If a new requirement conflicts with an accepted ADR:

1. Explain the conflict.
2. Evaluate alternatives.
3. Prepare a new ADR or revision.
4. Obtain approval.
5. Update related documentation.

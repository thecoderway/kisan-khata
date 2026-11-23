
---
name: architecture
description: Plan and review Kisan Khata modules, services, database relationships and architecture changes. Use before implementing major features or changing system structure.
---

# Kisan Khata Architecture Skill

## Objective

Design maintainable Laravel modules that follow the approved Kisan Khata architecture.

## Required Reading

Before starting:

- CLAUDE.md
- docs/01-product-requirements.md
- docs/04-database-architecture.md
- docs/05-multi-tenancy.md
- docs/08-api-architecture.md
- docs/decisions/README.md

## Workflow

### Step 1: Understand the Requirement

Identify:

- Business objective.
- User role.
- Affected shop.
- Affected entities.
- Expected behavior.
- Acceptance criteria.

Ask for clarification if the requirement is ambiguous.

### Step 2: Inspect Existing Architecture

Inspect:

- Laravel version.
- Existing modules.
- Models.
- Controllers.
- Services.
- Policies.
- Migrations.
- Routes.
- Tests.

Reuse existing working patterns where appropriate.

### Step 3: Define Module Boundaries

Identify:

- Module ownership.
- Public service interfaces.
- Data ownership.
- Dependencies.
- Events, if required.

Avoid unnecessary coupling.

### Step 4: Design Data Flow

Explain:

Request
→ Authentication
→ Tenant Context
→ Authorization
→ Validation
→ Application Service
→ Domain Logic
→ Database
→ Response

Adjust the flow according to the feature.

### Step 5: Evaluate Risks

Review:

- Tenant isolation.
- Financial integrity.
- Performance.
- API compatibility.
- Security.
- Migration impact.

### Step 6: Prepare Implementation Plan

List:

1. Files to create.
2. Files to modify.
3. Database changes.
4. Services.
5. Policies.
6. Routes.
7. Tests.
8. Documentation updates.

### Step 7: Review Decisions

If a proposal changes an accepted architecture decision, document the impact and request approval.

### Step 8: Implementation

Implement only the approved scope.

### Step 9: Verification

Run relevant tests and inspect the final changes.

## Completion Criteria

- Architecture is documented.
- Module responsibilities are clear.
- Tenant isolation is preserved.
- Financial logic is separated.
- Required tests are identified.
- No unapproved architectural change remains.

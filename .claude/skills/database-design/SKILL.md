
---
name: database-design
description: Design and review Kisan Khata MySQL schemas, relationships, indexes, migrations and tenant-aware data integrity constraints.
---

# Database Design Skill

## Objective

Design a reliable and maintainable MySQL schema for Kisan Khata.

## Required Reading

- docs/04-database-architecture.md
- docs/05-multi-tenancy.md
- docs/03-accounting-engine.md
- .claude/rules/financial-integrity.md
- .claude/rules/tenant-security.md

## Workflow

### Step 1: Understand Data Requirements

Identify:

- Entities.
- Relationships.
- Tenant ownership.
- Lifecycle.
- Retention requirements.
- Query patterns.

### Step 2: Inspect Existing Schema

Inspect existing migrations and models.

Do not create duplicate tables or relationships.

### Step 3: Define Relationships

For each relationship, specify:

- Parent.
- Child.
- Cardinality.
- Ownership.
- Deletion behavior.
- Required constraints.

### Step 4: Enforce Tenant Integrity

Use shop_id for tenant-owned records.

Validate related records belong to the same shop.

Use composite constraints where practical.

### Step 5: Define Data Types

Use:

- BIGINT or appropriate primary key type.
- VARCHAR for bounded text.
- TEXT for longer notes.
- DATE or DATETIME as appropriate.
- DECIMAL for money.
- JSON only for genuinely flexible structured data.

Do not use FLOAT or DOUBLE for financial amounts.

### Step 6: Define Indexes

Review:

- Tenant filtering.
- Customer search.
- Date filtering.
- Transaction references.
- Unique constraints.

Avoid excessive indexes.

### Step 7: Design Migrations

Migrations should:

- Be safe.
- Be reviewable.
- Avoid unnecessary data loss.
- Preserve financial history.
- Be reversible where practical.

### Step 8: Review Query Performance

Consider:

- Pagination.
- Composite indexes.
- N+1 queries.
- Ledger query volume.
- Large-table growth.

### Step 9: Test

Test:

- Constraints.
- Foreign keys.
- Duplicate references.
- Cross-tenant relationships.
- Migration up/down behavior where supported.

## Required Output

Provide:

1. Schema proposal.
2. Relationship explanation.
3. Indexing strategy.
4. Tenant integrity analysis.
5. Migration plan.
6. Data migration risks.
7. Required tests.

## Completion Criteria

The schema must preserve data integrity, tenant isolation and financial history.

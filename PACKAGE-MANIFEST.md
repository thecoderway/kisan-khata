
# Kisan Khata — Package Manifest

**Package:** Kisan Khata Claude Code Starter Kit  
**Manifest Version:** 1.0  
**Created:** 2026-11-22  
**Purpose:** Product documentation, architecture guidance and Claude Code development configuration.

---

## 1. Package Overview

Kisan Khata is a multi-tenant SaaS application for Indian merchants who manage customer credit sales, payments, interest and account settlements.

This starter kit provides:

- Product requirements.
- Business rules.
- Accounting architecture.
- Database architecture.
- Multi-tenancy architecture.
- Security guidelines.
- Bilingual UI/UX guidelines.
- API architecture.
- Testing strategy.
- Development roadmap.
- Architecture Decision Records.
- Claude Code project instructions.
- Specialized Claude Code rules.
- Specialized Claude Code agents.
- Reusable Claude Code skills.

This package is a documentation and AI development configuration kit.

It is not a completed Laravel application.

---

## 2. Package Structure

Expected directory structure:

```text
Kisan-Khata/
│
├── README_START_HERE.md
├── CLAUDE.md
├── PACKAGE-MANIFEST.md
│
├── docs/
│   ├── 01-product-requirements.md
│   ├── 02-business-rules.md
│   ├── 03-accounting-engine.md
│   ├── 04-database-architecture.md
│   ├── 05-multi-tenancy.md
│   ├── 06-security.md
│   ├── 07-ui-ux-guidelines.md
│   ├── 08-api-architecture.md
│   ├── 09-testing-strategy.md
│   ├── 10-development-roadmap.md
│   ├── PROJECT-STATUS.md
│   │
│   └── decisions/
│       ├── README.md
│       ├── ADR-0001-modular-monolith.md
│       ├── ADR-0002-shared-database-tenancy.md
│       └── ADR-0003-web-first-api-ready.md
│
└── .claude/
    │
    ├── rules/
    │   ├── financial-integrity.md
    │   ├── tenant-security.md
    │   ├── laravel-conventions.md
    │   └── bilingual-ux.md
    │
    ├── agents/
    │   ├── architect.md
    │   ├── accounting-reviewer.md
    │   ├── security-reviewer.md
    │   └── test-reviewer.md
    │
    └── skills/
        ├── architecture/
        │   └── SKILL.md
        │
        ├── laravel-development/
        │   └── SKILL.md
        │
        ├── accounting-engine/
        │   └── SKILL.md
        │
        ├── database-design/
        │   └── SKILL.md
        │
        ├── testing/
        │   └── SKILL.md
        │
        └── code-review/
            └── SKILL.md
```

---

## 3. Complete File Inventory

### 3.1 Root Files

| # | File | Purpose |
|---|---|---|
| 1 | README_START_HERE.md | Project introduction and reading instructions |
| 2 | CLAUDE.md | Main instructions for Claude Code |
| 3 | PACKAGE-MANIFEST.md | Package inventory and verification |

### 3.2 Product and Technical Documentation

| # | File | Purpose |
|---|---|---|
| 4 | docs/01-product-requirements.md | Product requirements and scope |
| 5 | docs/02-business-rules.md | Merchant workflows and business rules |
| 6 | docs/03-accounting-engine.md | Financial engine and ledger design |
| 7 | docs/04-database-architecture.md | Proposed database schema |
| 8 | docs/05-multi-tenancy.md | Shop isolation architecture |
| 9 | docs/06-security.md | Application security requirements |
| 10 | docs/07-ui-ux-guidelines.md | Bilingual UI/UX standards |
| 11 | docs/08-api-architecture.md | Versioned API architecture |
| 12 | docs/09-testing-strategy.md | Automated testing strategy |
| 13 | docs/10-development-roadmap.md | Development phases and milestones |
| 14 | docs/PROJECT-STATUS.md | Current project progress and pending decisions |

### 3.3 Architecture Decision Records

| # | File | Purpose |
|---|---|---|
| 15 | docs/decisions/README.md | ADR index and decision management |
| 16 | docs/decisions/ADR-0001-modular-monolith.md | Modular Monolith decision |
| 17 | docs/decisions/ADR-0002-shared-database-tenancy.md | Shared database tenancy decision |
| 18 | docs/decisions/ADR-0003-web-first-api-ready.md | Web-first and API-ready decision |

### 3.4 Claude Code Rules

| # | File | Purpose |
|---|---|---|
| 19 | .claude/rules/financial-integrity.md | Mandatory financial correctness rules |
| 20 | .claude/rules/tenant-security.md | Mandatory tenant security rules |
| 21 | .claude/rules/laravel-conventions.md | Laravel development conventions |
| 22 | .claude/rules/bilingual-ux.md | Hindi and English UI rules |

### 3.5 Claude Code Agents

| # | File | Purpose |
|---|---|---|
| 23 | .claude/agents/architect.md | Architecture review |
| 24 | .claude/agents/accounting-reviewer.md | Financial logic review |
| 25 | .claude/agents/security-reviewer.md | Security review |
| 26 | .claude/agents/test-reviewer.md | Testing and quality review |

### 3.6 Claude Code Skills

| # | File | Purpose |
|---|---|---|
| 27 | .claude/skills/architecture/SKILL.md | Architecture planning |
| 28 | .claude/skills/laravel-development/SKILL.md | Laravel feature development |
| 29 | .claude/skills/accounting-engine/SKILL.md | Financial engine development |
| 30 | .claude/skills/database-design/SKILL.md | Database schema design |
| 31 | .claude/skills/testing/SKILL.md | Automated testing |
| 32 | .claude/skills/code-review/SKILL.md | Code review |

---

## 4. Package Statistics

| Category | Expected Files |
|---|---:|
| Root files | 3 |
| Product and technical documentation | 11 |
| Architecture decision records | 4 |
| Claude Code rules | 4 |
| Claude Code agents | 4 |
| Claude Code skills | 6 |
| **Total** | **32** |

Important:

The inventory above contains 32 files, including this manifest.

The package structure is the source of truth for expected file count.

Do not count directories as files.

---

## 5. Architecture Decisions

The following decisions are currently documented as Accepted:

| Decision | Status |
|---|---|
| Modular Monolith | Accepted |
| Shared Database Multi-Tenancy | Accepted |
| Web First, API Ready | Accepted |

These are still proposed or unresolved:

- Exact Laravel and PHP versions.
- Frontend package versions.
- API authentication implementation.
- Interest calculation formula.
- Interest-free period interpretation.
- Payment allocation priority.
- Crop-season settlement behavior.
- Customer bilingual data storage strategy.
- Production hosting provider.
- Payment gateway.
- Android offline synchronization.

Claude Code must not silently convert unresolved decisions into accepted business rules.

---

## 6. Financial Safety Requirements

Before implementing financial features, verify that:

- PHP floats are not used for authoritative money calculations.
- Monetary database columns use appropriate DECIMAL precision.
- Principal and interest are separately identifiable.
- Posted transactions are immutable.
- Corrections use reversal workflows.
- Payments have explicit allocation records.
- Interest calculations use approved policies.
- Financial posting uses database transactions.
- Concurrent posting is protected.
- Idempotency prevents duplicate financial operations.
- Ledger entries reconcile.
- Financial history remains auditable.

A missing business decision is a reason to stop the affected implementation, not a reason to guess.

---

## 7. Multi-Tenancy Safety Requirements

Before releasing tenant-owned functionality, verify that:

- The current shop comes from authenticated membership.
- Client-provided shop_id is not trusted.
- Tenant-owned queries are correctly scoped.
- Related records belong to the same shop.
- Policies enforce authorization.
- File downloads validate tenant ownership.
- Background jobs establish tenant context.
- Cache keys are tenant-specific.
- Cross-tenant tests pass.

Any confirmed cross-tenant data exposure is a release-blocking defect.

---

## 8. Claude Code Usage Instructions

### Before Starting Development

Claude Code should:

1. Read CLAUDE.md.
2. Read README_START_HERE.md.
3. Read relevant product documentation.
4. Inspect the existing Laravel project.
5. Inspect installed PHP and Laravel versions.
6. Review relevant architecture decisions.
7. Identify unresolved business rules.
8. Prepare an implementation plan.

### During Development

Claude Code must:

- Follow the rules in .claude/rules/.
- Use relevant skills from .claude/skills/.
- Invoke specialist agents for appropriate high-risk reviews.
- Avoid unnecessary architectural changes.
- Preserve financial integrity.
- Preserve tenant isolation.
- Write automated tests.
- Update documentation when necessary.

### Before Marking Work Complete

Verify:

- Implementation.
- Validation.
- Authorization.
- Tenant isolation.
- Financial behavior.
- Tests.
- Documentation.
- Remaining risks.

Do not claim that tests passed unless they were actually executed.

---

## 9. Package Verification Checklist

Run this checklist after creating all files.

### File Verification

- [ ] All 32 expected files exist.
- [ ] No file is stored in the wrong directory.
- [ ] Filenames match this manifest.
- [ ] All Markdown files contain their expected content.
- [ ] No placeholder-only files remain.

### Documentation Verification

- [ ] CLAUDE.md references valid documents.
- [ ] README_START_HERE.md uses the correct reading order.
- [ ] All docs referenced by skills and agents exist.
- [ ] ADR statuses match the documented decisions.
- [ ] PROJECT-STATUS.md reflects actual implementation status.
- [ ] No unresolved business rule is presented as approved.

### Claude Configuration Verification

- [ ] Rule files are in .claude/rules/.
- [ ] Agent files are in .claude/agents/.
- [ ] Agent YAML frontmatter is valid.
- [ ] Skill directories contain SKILL.md.
- [ ] Skill names and descriptions are present.
- [ ] Skills and agents do not contradict CLAUDE.md.

### Technical Verification

- [ ] No unsupported technology version is assumed.
- [ ] No financial float calculation is prescribed.
- [ ] No client-provided tenant ID is trusted.
- [ ] No financial business rule is invented.
- [ ] No posted transaction is silently editable.
- [ ] No API and web accounting logic duplication is introduced.

---

## 10. Package Completion Status

This manifest describes the expected package.

A file should be marked complete only after:

1. It has been created at the expected path.
2. Its complete content has been inserted.
3. Its content has been reviewed.
4. Its references have been checked.

Do not interpret this manifest alone as proof that every file exists.

---

## 11. Future Package Extensions

The following documents may be added when the implementation requires them:

- Deployment architecture.
- CI/CD pipeline.
- Monitoring and logging.
- Backup and disaster recovery.
- Authentication ADR.
- Interest policy ADR.
- Payment allocation ADR.
- Settlement ADR.
- Subscription billing architecture.
- Android application architecture.
- Offline synchronization architecture.

New files must be added to this manifest and the package structure.

---

## 12. Final Principle

Kisan Khata must prioritize:

1. Financial correctness.
2. Tenant data isolation.
3. Security.
4. Business rule accuracy.
5. Maintainability.
6. User simplicity.
7. Scalability.

AI-generated code is not automatically correct.

Every important implementation must be inspected, tested and verified before being considered production-ready.

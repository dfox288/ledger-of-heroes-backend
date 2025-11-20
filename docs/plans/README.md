# Implementation Plans

This directory contains detailed implementation plans for features, enhancements, and refactorings.

---

## 🎯 Active Plans (In Progress)

### Parser/Importer Refactoring
**[2025-11-20-parser-importer-refactoring.md](2025-11-20-parser-importer-refactoring.md)**
- **Status:** 75% complete (Phase 1 & 2)
- **Branch:** main (merged)
- **Remaining:** Task 2.4 (LookupsGameEntities) + Phase 3 (Architecture)
- **Effort:** ~4-6 hours remaining
- **Goal:** Extract reusable concerns to eliminate code duplication

**Key Deliverables:**
- ✅ ParsesTraits concern
- ✅ ParsesRolls concern
- ✅ ImportsRandomTables concern
- ✅ ConvertsWordNumbers concern
- ✅ MapsAbilityCodes concern
- ✅ Extended MatchesProficiencyTypes
- ⏳ LookupsGameEntities concern (pending)
- ⏳ GeneratesSlugs concern (pending)
- ⏳ BaseImporter abstract class (pending)

---

### Class Importer Enhancements
**[2025-11-20-class-importer-enhancements.md](2025-11-20-class-importer-enhancements.md)**
- **Status:** ~40% complete (BATCH 2.1-2.3)
- **Branch:** `feature/class-importer-enhancements` (NOT merged)
- **Remaining:** BATCH 2.4-2.5 + Phase 3
- **Effort:** ~3.5 hours remaining
- **Goal:** Fix spells_known semantic issue + add proficiency choice support

**Key Deliverables:**
- ✅ Investigation (BATCH 0-1)
- ✅ Add spells_known column (BATCH 2.1)
- ✅ Update parser for spells_known (BATCH 2.2)
- ✅ Update importer for spells_known (BATCH 2.3)
- ⏳ Data migration (BATCH 2.4)
- ⏳ Update API (BATCH 2.5)
- ⏳ Proficiency choices (Phase 3)

---

## 📚 Reference Plans (Completed)

### Database Design
**[2025-11-17-dnd-compendium-database-design.md](2025-11-17-dnd-compendium-database-design.md)**
- **Status:** Complete, implemented
- **Type:** Architecture reference
- **Scope:** Complete database schema design for D&D 5e compendium

**Contents:**
- 59 migrations
- Polymorphic relationship patterns
- Lookup tables (sources, spell schools, damage types, etc.)
- Entity tables (spells, races, items, backgrounds, classes, feats, monsters)
- Junction tables (entity_sources, proficiencies, modifiers, etc.)

**Use:** Reference for understanding database architecture decisions

---

### XML Importer Implementation (Vertical Slices)
**[2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md](2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md)**
- **Status:** Complete, implemented
- **Type:** Implementation strategy reference
- **Scope:** Vertical slice approach for building importers

**Contents:**
- TDD methodology
- Parser → Importer → Command → API flow
- Spell → Race → Item → Background → Feat implementation sequence
- Testing strategies
- Common patterns

**Use:** Reference for implementing future importers (e.g., Monster Importer)

---

## 🎯 How to Use These Plans

### For Active Development
1. Read the **Active Plans** section
2. Check the plan's status and branch
3. Verify you're on the correct branch
4. Follow the batch structure (RED-GREEN-REFACTOR)
5. Run tests after each batch
6. Commit atomic changes

### For New Features
1. Review **Reference Plans** for patterns
2. Follow TDD mandate in `../CLAUDE.md`
3. Create new plan document (see template below)
4. Break into batches of ~30-60 min each
5. Include test examples and acceptance criteria

---

## 📝 Plan Template Structure

```markdown
# Feature Name Implementation Plan

**Created:** YYYY-MM-DD
**Estimated Effort:** X hours
**Branch:** (to be created)
**Prerequisites:** List dependencies

## Overview
Brief description of feature and goals

## Phases
### Phase 1: Name (X hours)
- BATCH 1.1: Task name (XX min)
  - Steps
  - Tests to write
  - Acceptance criteria

### Phase 2: Name (X hours)
...

## Verification
- [ ] Test checklist
- [ ] Code quality gates
- [ ] API changes documented

## Files to Create/Modify
List of expected file changes
```

---

## 🔍 Plan Status Quick Reference

| Plan | Status | Branch | Tests | Remaining |
|------|--------|--------|-------|-----------|
| Parser/Importer Refactoring | 75% | main | 468 ✅ | 4-6 hrs |
| Class Importer Enhancements | 40% | feature/class-importer-enhancements | 432 ✅ | 3.5 hrs |
| Database Design | 100% | main | - | - |
| XML Importer Strategy | 100% | main | - | - |

---

## 🎓 Planning Best Practices

1. **Break into Batches:** 30-60 minute chunks
2. **TDD Structure:** Test → Fail → Implement → Pass → Commit
3. **Clear Acceptance Criteria:** Define "done" upfront
4. **Estimate Conservatively:** Add 25% buffer
5. **Document Decisions:** Why, not just what
6. **Include Examples:** Code snippets for clarity
7. **Test Isolation:** Each batch independently testable

---

**Navigation:** [Main Docs](../README.md) | [Project Status](../PROJECT-STATUS.md) | [Main Codebase](../../CLAUDE.md)

🤖 Generated with [Claude Code](https://claude.com/claude-code)

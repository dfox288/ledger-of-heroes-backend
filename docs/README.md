# Documentation Index

**Last Updated:** 2025-11-20
**Current Branch:** main
**Status:** ✅ Refactoring Phase 1 & 2 Complete

---

## 🎯 Start Here

### For Current Session Context
**→ [SESSION-HANDOVER-2025-11-20-REFACTORING.md](SESSION-HANDOVER-2025-11-20-REFACTORING.md)** ⭐ LATEST (MAIN BRANCH)
- Parser/Importer refactoring complete (Phase 1 & 2)
- 468 tests passing
- 6 new concerns created
- Ready to continue with Phase 2.4 or Phase 3

### For Work In Progress (Feature Branch)
**→ [active/SESSION-HANDOVER-2025-11-21-COMPLETE.md](active/SESSION-HANDOVER-2025-11-21-COMPLETE.md)**
- Branch: `feature/class-importer-enhancements` (NOT merged)
- Status: BATCH 2.1-2.3 complete (spells_known feature)
- Tests: 432 passing

---

## 📋 Document Organization

### Active Documentation
```
docs/
├── README.md                                          ← You are here
├── SESSION-HANDOVER-2025-11-20-REFACTORING.md        ← Main branch state
├── PROJECT-STATUS.md                                  ← Quick stats
├── CLASS-IMPORTER-ISSUES-FOUND.md                    ← Investigation results
├── active/                                            ← Work in progress
│   ├── SESSION-HANDOVER-2025-11-21-COMPLETE.md       ← Feature branch
│   ├── SESSION-HANDOVER-2025-11-20-PHASE-3-COMPLETE.md
│   └── investigation-findings-BATCH-1.1.md
└── plans/                                             ← Implementation plans
    ├── 2025-11-20-parser-importer-refactoring.md     ← Active refactoring plan
    ├── 2025-11-20-class-importer-enhancements.md     ← Class importer plan
    └── (reference plans...)
```

### Archived Documentation
Older handovers and completed work documentation has been moved to `archive/` for reference.

---

## 📊 Current Project State

### Main Branch (Merged & Stable)
- **Tests:** 468 passing (2,966 assertions)
- **Migrations:** 59 complete
- **Models:** 23 Eloquent models
- **Concerns:** 10 total (6 new from refactoring)
- **Importers:** 6 working (Spells, Races, Items, Backgrounds, Classes, Feats)
- **API Endpoints:** 14 controllers, 24 resources

### Recent Accomplishments (Main Branch)
✅ **Parser/Importer Refactoring (Phase 1 & 2 - 75%)**
- Eliminated ~215 lines of duplication
- Created 6 reusable concerns (ParsesTraits, ParsesRolls, ImportsRandomTables, etc.)
- 30 new tests added
- All existing tests still passing

### Feature Branch Work (Not Yet Merged)
🔀 **Class Importer Enhancements** (branch: `feature/class-importer-enhancements`)
- Spells Known feature (BATCH 2.1-2.3 complete)
- 432 tests passing
- Ready to continue or merge

---

## 🚀 Quick Commands

### Verify Current State
```bash
# Check which branch you're on
git branch --show-current

# Run tests
docker compose exec php php artisan test

# Check latest commits
git log --oneline -10
```

### Resume Refactoring Work (Main Branch)
```bash
# Continue with Task 2.4: LookupsGameEntities (~1-2 hours)
# See: docs/plans/2025-11-20-parser-importer-refactoring.md

# Or continue with Phase 3: Architecture improvements
# See: SESSION-HANDOVER-2025-11-20-REFACTORING.md
```

### Resume Class Importer Work (Feature Branch)
```bash
# Switch to feature branch
git checkout feature/class-importer-enhancements

# Continue with BATCH 2.4: Data Migration
# See: active/SESSION-HANDOVER-2025-11-21-COMPLETE.md
```

---

## 📐 Implementation Plans

### Active Plans
- **[plans/2025-11-20-parser-importer-refactoring.md](plans/2025-11-20-parser-importer-refactoring.md)** - Refactoring roadmap (75% complete)
- **[plans/2025-11-20-class-importer-enhancements.md](plans/2025-11-20-class-importer-enhancements.md)** - Class importer improvements

### Reference Plans (Completed)
- **[plans/2025-11-17-dnd-compendium-database-design.md](plans/2025-11-17-dnd-compendium-database-design.md)** - Database architecture
- **[plans/2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md](plans/2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md)** - Original implementation strategy

---

## 🎯 Next Steps (Choose Your Path)

### Option A: Continue Refactoring (Main Branch) ⭐ RECOMMENDED
**Why:** Delivers more value, completes architectural improvements
- Task 2.4: LookupsGameEntities concern (~1-2 hours)
- Phase 3: GeneratesSlugs + BaseImporter (~3-4 hours)
- **Total:** 4-6 hours to complete refactoring

**See:** [SESSION-HANDOVER-2025-11-20-REFACTORING.md](SESSION-HANDOVER-2025-11-20-REFACTORING.md)

### Option B: Continue Class Importer (Feature Branch)
**Why:** Complete the spells_known feature work
- BATCH 2.4: Data migration (~60 min)
- BATCH 2.5: Update API (~15 min)
- Phase 3: Proficiency choices (~2 hours)
- **Total:** ~3.5 hours to complete

**See:** [active/SESSION-HANDOVER-2025-11-21-COMPLETE.md](active/SESSION-HANDOVER-2025-11-21-COMPLETE.md)

### Option C: Start New Feature
**Next Priority:** Monster Importer (~6-8 hours)
- 7 bestiary XML files ready
- Schema complete
- Can leverage all new refactoring concerns

---

## 🔍 Finding What You Need

| Need | Document |
|------|----------|
| Current state (main) | [SESSION-HANDOVER-2025-11-20-REFACTORING.md](SESSION-HANDOVER-2025-11-20-REFACTORING.md) |
| Feature branch work | [active/SESSION-HANDOVER-2025-11-21-COMPLETE.md](active/SESSION-HANDOVER-2025-11-21-COMPLETE.md) |
| Quick stats | [PROJECT-STATUS.md](PROJECT-STATUS.md) |
| Refactoring plan | [plans/2025-11-20-parser-importer-refactoring.md](plans/2025-11-20-parser-importer-refactoring.md) |
| Database design | [plans/2025-11-17-dnd-compendium-database-design.md](plans/2025-11-17-dnd-compendium-database-design.md) |
| TDD workflow | [../CLAUDE.md](../CLAUDE.md) |

---

## 📦 Branch Overview

| Branch | Status | Description | Tests |
|--------|--------|-------------|-------|
| `main` | ✅ Stable | Refactoring Phase 1 & 2 complete | 468 passing |
| `feature/class-importer-enhancements` | 🔀 WIP | Spells Known feature (BATCH 2.1-2.3) | 432 passing |
| `refactor/parser-importer-deduplication` | ✅ Merged | (Same as main) | - |
| `feature/entity-prerequisites` | ✅ Merged | Prerequisites system | - |
| `feature/background-enhancements` | ❓ Unknown | Needs verification | - |

---

**Navigation:** [Project Status](PROJECT-STATUS.md) | [Main Codebase](../CLAUDE.md) | [Plans](plans/)

🤖 Generated with [Claude Code](https://claude.com/claude-code)

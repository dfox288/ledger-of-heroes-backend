# Documentation Index

This directory contains all project documentation, plans, investigations, and session handovers.

---

## 📋 Current Status Documents

### Session Handovers (Read These First!)
- **[SESSION-HANDOVER-2025-11-20.md](SESSION-HANDOVER-2025-11-20.md)** ⭐ LATEST
  - Investigation + Planning session
  - Class Importer enhancements prepared
  - Ready for execution (~6 hours)

### Quick Start Guides
- **[QUICK-START-2025-11-21.md](QUICK-START-2025-11-21.md)** ⭐ TOMORROW
  - Fast path to start implementation
  - Command cheat sheet
  - Time checkpoints

### Investigation Reports
- **[CLASS-IMPORTER-ISSUES-FOUND.md](CLASS-IMPORTER-ISSUES-FOUND.md)**
  - Issues identified: Spells Known, Proficiency Choices
  - Druid level progression (resolved)
  - Investigation findings and recommendations

### Completed Features
- **[HANDOVER-2025-11-19-CLASS-IMPORTER-COMPLETE.md](HANDOVER-2025-11-19-CLASS-IMPORTER-COMPLETE.md)**
  - Class Importer implementation complete
  - 24 tests, 283 assertions
  - Fighter + Barbarian imported

- **[HANDOVER-2025-11-19-ENTITY-PREREQUISITES-COMPLETE.md](HANDOVER-2025-11-19-ENTITY-PREREQUISITES-COMPLETE.md)**
  - Entity Prerequisites system complete
  - Double polymorphic design
  - 27 new tests

---

## 📐 Implementation Plans

### Active Plans
- **[plans/2025-11-20-class-importer-enhancements.md](plans/2025-11-20-class-importer-enhancements.md)** ⭐ READY
  - Fix Spells Known semantic issue
  - Implement proficiency choice support
  - 13 batches, ~6 hours estimated
  - Full TDD approach with code examples

### Reference Plans (Completed)
- **[plans/2025-11-17-dnd-compendium-database-design.md](plans/2025-11-17-dnd-compendium-database-design.md)**
  - Original database design
  - 50+ migrations planned
  - Polymorphic relationships

- **[plans/2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md](plans/2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md)**
  - Vertical slice implementation strategy
  - Spell → Race → Item → Background importers
  - TDD methodology

---

## 🗂️ Project Documentation

### Overview
- **[../CLAUDE.md](../CLAUDE.md)** - Main project instructions
  - Current status: 426 tests, 6 importers
  - TDD mandate
  - Development workflow

### Project Status
- **[PROJECT-STATUS.md](PROJECT-STATUS.md)** - Quick stats
  - Test counts
  - Feature completion
  - Next priorities

---

## 📊 Status at a Glance

| Category | Status | Count |
|----------|--------|-------|
| **Tests** | ✅ Passing | 426 (2,733 assertions) |
| **Migrations** | ✅ Complete | 59 migrations |
| **Importers** | ✅ Working | 6/7 (Spells, Races, Items, Backgrounds, Classes, Feats) |
| **API Resources** | ✅ Complete | 24 resources |
| **API Endpoints** | ✅ Working | 14 controllers |
| **Entities Imported** | ✅ Complete | 477 spells, 119 races, 2,039 items, 34 backgrounds, 129 classes, 138 feats |

---

## 🎯 Next Steps (Priority Order)

### Immediate (Tomorrow)
1. **Execute Class Importer Enhancements** (~6 hours)
   - Read: [QUICK-START-2025-11-21.md](QUICK-START-2025-11-21.md)
   - Plan: [plans/2025-11-20-class-importer-enhancements.md](plans/2025-11-20-class-importer-enhancements.md)

### Short-term (This Week)
2. **Monster Importer** (~6-8 hours)
   - 7 bestiary XML files ready
   - Schema complete
   - Last major entity type

### Medium-term (Next Week)
3. **API Enhancements**
   - Filtering by proficiency types, conditions, rarity
   - Aggregation endpoints
   - OpenAPI/Swagger documentation

---

## 📁 File Organization

```
docs/
├── README.md                                          ← You are here
├── QUICK-START-2025-11-21.md                         ← Quick reference
├── SESSION-HANDOVER-2025-11-20.md                    ← Latest handover
├── CLASS-IMPORTER-ISSUES-FOUND.md                    ← Investigation
├── HANDOVER-2025-11-19-CLASS-IMPORTER-COMPLETE.md   ← Completed feature
├── HANDOVER-2025-11-19-ENTITY-PREREQUISITES-COMPLETE.md  ← Completed feature
├── PROJECT-STATUS.md                                  ← Quick stats
└── plans/
    ├── 2025-11-20-class-importer-enhancements.md     ← Active plan
    ├── 2025-11-17-dnd-compendium-database-design.md  ← Reference
    └── 2025-11-17-dnd-xml-importer-implementation-v4-vertical-slices.md  ← Reference
```

---

## 🔍 How to Find What You Need

**Starting a new session?**
→ Read [SESSION-HANDOVER-2025-11-20.md](SESSION-HANDOVER-2025-11-20.md)

**Need to implement something?**
→ Check [plans/](plans/) directory

**Want quick stats?**
→ Read [PROJECT-STATUS.md](PROJECT-STATUS.md)

**Need architecture context?**
→ Read [plans/2025-11-17-dnd-compendium-database-design.md](plans/2025-11-17-dnd-compendium-database-design.md)

**Want to understand methodology?**
→ Read [../CLAUDE.md](../CLAUDE.md) (TDD mandate)

---

## 🏷️ Document Types

- **SESSION-HANDOVER-*.md** - Context for next session
- **QUICK-START-*.md** - Fast reference guides
- **HANDOVER-*-COMPLETE.md** - Completed feature documentation
- **CLASS-IMPORTER-*.md** - Investigation reports
- **plans/*.md** - Implementation plans (active or reference)
- **PROJECT-STATUS.md** - Current stats
- **README.md** - This file (navigation)

---

**Last Updated:** 2025-11-20 Evening
**Current Branch:** `feature/entity-prerequisites`
**Next Branch:** `feature/class-importer-enhancements` (to be created)
**Status:** ✅ Ready for implementation

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>

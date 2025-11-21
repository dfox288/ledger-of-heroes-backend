# Documentation Index

**Last Updated:** 2025-11-21
**Current Branch:** main
**Status:** ✅ Production-Ready - Universal Tag System Complete

---

## 🎯 Start Here

### For Current Session Context
**→ [SESSION-HANDOVER-2025-11-21.md](SESSION-HANDOVER-2025-11-21.md)** ⭐ LATEST

**What Changed:**
- Session 1: Spell importer enhancements (damage types, higher levels, subclass matching, tagging)
- Session 2: Universal tag system across all 6 main entities
- 719 tests passing (4,700 assertions)
- All entities now support Spatie Tags with dedicated TagResource

---

## 📊 Quick Stats

- **Tests:** 719 passing (4,700 assertions) - 40s duration
- **Migrations:** 60 complete
- **Models:** 23 (all with HasFactory + HasTags where applicable)
- **API:** 25 Resources + 17 Controllers + 26 Form Requests
- **Importers:** 6 working (Spells, Races, Items, Backgrounds, Classes, Feats)
- **Search:** 3,002 documents indexed (Scout + Meilisearch)

---

## 📋 Document Organization

### Active Documentation
```
docs/
├── README.md                          ← You are here
├── SESSION-HANDOVER-2025-11-21.md     ← Latest session (spell + tags)
├── PROJECT-STATUS.md                  ← Quick reference stats
├── SEARCH.md                          ← Search system docs
├── MEILISEARCH-FILTERS.md             ← Advanced filtering syntax
├── plans/                             ← Implementation plans (reference)
├── recommendations/                   ← Analysis docs (exceptions, etc.)
└── archive/                           ← Older handovers (2025-11-19 to 2025-11-21)
```

### Main Codebase Documentation
- **`../CLAUDE.md`** - Essential development guide (compacted from 968 → 457 lines)
  - TDD workflow
  - Form Request patterns
  - Exception handling
  - Tag system usage
  - Quick start commands
  - Architecture patterns

---

## 🚀 Quick Commands

### Database Setup
```bash
# Full reset + seed + import subset
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php bash -c 'for file in import-files/spells-phb.xml import-files/spells-tce.xml; do php artisan import:spells "$file" || true; done'
docker compose exec php php artisan search:configure-indexes
docker compose exec php php artisan test
```

### Development
```bash
# Run tests
docker compose exec php php artisan test
docker compose exec php php artisan test --filter=Api

# Format code
docker compose exec php ./vendor/bin/pint

# Check git status
git status && git log --oneline -5
```

---

## 🔍 Finding What You Need

| Need | Document |
|------|----------|
| **Latest session** | [SESSION-HANDOVER-2025-11-21.md](SESSION-HANDOVER-2025-11-21.md) |
| **Quick stats** | [PROJECT-STATUS.md](PROJECT-STATUS.md) |
| **TDD workflow** | [../CLAUDE.md](../CLAUDE.md#critical-development-standards) |
| **Search system** | [SEARCH.md](SEARCH.md) |
| **Filter syntax** | [MEILISEARCH-FILTERS.md](MEILISEARCH-FILTERS.md) |
| **Database design** | [plans/2025-11-17-dnd-compendium-database-design.md](plans/2025-11-17-dnd-compendium-database-design.md) |
| **Exception patterns** | [recommendations/CUSTOM-EXCEPTIONS-ANALYSIS.md](recommendations/CUSTOM-EXCEPTIONS-ANALYSIS.md) |

---

## 🎯 Next Steps

### Priority 1: Monster Importer ⭐ RECOMMENDED
- **Why:** Completes the D&D compendium (last major entity type)
- **Effort:** 6-8 hours with TDD
- **Benefits:** 7 bestiary XML files ready, schema complete, can reuse all 15 traits
- **See:** [SESSION-HANDOVER-2025-11-21.md](SESSION-HANDOVER-2025-11-21.md#next-steps)

### Priority 2: Import Remaining Data
- **Why:** Populate database with full content
- **Effort:** 1-2 hours (just running import commands)
- **Content:**
  - 6 more spell files (~300 spells)
  - Races, Items, Backgrounds, Feats (importers ready)

### Priority 3: API Enhancements
- Additional filtering/aggregation endpoints
- Rate limiting implementation
- Caching strategy
- Tag-based filtering

---

## 📦 Recent Accomplishments (2025-11-21)

### Spell Importer Enhancements ✅
1. **Damage Type Parsing** - SpellEffect.damage_type_id now populated
2. **Subclass-Specific Associations** - "Fighter (Eldritch Knight)" → Eldritch Knight subclass
3. **Higher Levels Extraction** - "At Higher Levels:" section in dedicated column
4. **Fuzzy Subclass Matching** - "Archfey" → "The Archfey"
5. **Subclass Alias Mapping** - "Coast" → "Circle of the Land"

### Universal Tag System ✅
1. **TagResource Created** - Consistent serialization (id, name, slug, type)
2. **6 Models Updated** - Spell, Race, Item, Background, Class, Feat all have HasTags
3. **6 Resources Updated** - All include tags by default
4. **6 Controllers Updated** - All eager-load tags
5. **11 Tests Added** - Comprehensive coverage (3 unit + 8 integration)
6. **83 Touch Spells** + **33 Ritual Caster** spells tagged

---

## 📚 Historical Context

### Archived Sessions
- **2025-11-20:** Refactoring, API enhancements, Form Requests, Scramble fixes
- **2025-11-19:** Class importer, entity prerequisites, slug system, language system
- **Earlier:** Initial importers, database design, search system

See `archive/` for detailed history.

---

## 🚦 Status: READY FOR NEXT SESSION

The application is production-ready with:
- ✅ 6 working importers
- ✅ Universal tag support
- ✅ Complete search system
- ✅ 719 tests passing
- ✅ Clean git history
- ✅ Comprehensive documentation

**Next agent can:** Implement Monster importer, import remaining data, or add new features

**No blockers.** 🚀

---

**Navigation:** [Main Codebase](../CLAUDE.md) | [Project Status](PROJECT-STATUS.md) | [Latest Session](SESSION-HANDOVER-2025-11-21.md)

🤖 Generated with [Claude Code](https://claude.com/claude-code)

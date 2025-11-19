# Session Handover: Class Importer Implementation (In Progress)

**Date:** 2025-11-19
**Branch:** `feature/entity-prerequisites`
**Status:** 🟡 BATCH 1 Complete, BATCH 2 Starting
**Context:** Implementing Fighter Class Importer using vertical slice + TDD approach

---

## 🎯 Session Goal

Implement complete **Fighter Class Importer** (vertical slice) to import D&D 5e Fighter class from XML files into database, then expose via API.

**Approach:** Vertical slice with 5 batches, maximum subagent parallelism, strict TDD

---

## ✅ What's Been Completed

### BATCH 1: Foundation (COMPLETE)

#### Models & Relationships
**Files created/modified:**
- `app/Models/CharacterClass.php` - Added 8 relationships:
  - `parentClass()`, `subclasses()` (self-referential)
  - `features()`, `levelProgression()`, `counters()` (class-specific)
  - `proficiencies()`, `traits()`, `sources()` (polymorphic)
  - `getIsBaseClassAttribute()` computed property
- `app/Models/ClassFeature.php` (NEW) - Features gained at each level
- `app/Models/ClassLevelProgression.php` (NEW) - Spell slot progression
- `app/Models/ClassCounter.php` (NEW) - Resource tracking (Ki, Rage, etc.)

**All models:**
- Use HasFactory trait
- No timestamps
- Proper fillable/casts
- BelongsTo CharacterClass relationships

#### Factories
**Files created:**
- `database/factories/CharacterClassFactory.php`
  - States: `baseClass()`, `subclass()`, `spellcaster()`
  - Generates hierarchical slugs: "fighter-battle-master"
- `database/factories/ClassFeatureFactory.php`
  - States: `atLevel(int)`, `optional()`, `forClass(CharacterClass)`
- `database/factories/ClassLevelProgressionFactory.php`
  - States: `fullCaster()`, `halfCaster()`, `atLevel(int)`
  - Includes realistic D&D 5e spell slot progressions
- `database/factories/ClassCounterFactory.php`
  - States: `shortRest()`, `longRest()`, `noReset()`, `atLevel(int)`

#### Parser Skeleton
**Files created:**
- `app/Services/Parsers/ClassXmlParser.php`
  - Uses traits: `ParsesSourceCitations`, `MatchesProficiencyTypes`
  - Method stubs: `parse()`, `parseClass()`, `parseProficiencies()`, `parseTraits()`, `parseFeatures()`, `parseSpellSlots()`, `parseCounters()`, `detectSubclasses()`
- `tests/Unit/Parsers/ClassXmlParserTest.php`
  - First test: `it_parses_fighter_base_class` (currently RED - expected)
  - Uses real XML: `import-files/class-fighter-phb.xml`

**Test Status:** ❌ 1 test failing (RED phase - correct for TDD)

---

## 📋 Implementation Plan Overview

### Batch Structure
```
✅ BATCH 1: Foundation (Models, Factories, Parser Skeleton) - COMPLETE
🔄 BATCH 2: Parser Implementation (7 tasks, sequential TDD)
⏳ BATCH 3: Importer Implementation (7 tasks, sequential TDD)
⏳ BATCH 4: API Layer (3 tasks, parallel)
⏳ BATCH 5: Integration & Verification (sequential)
```

### BATCH 2: Parser Implementation (IN PROGRESS)
**Sequential execution required (TDD):**

1. **Task 2A:** Basic class data parsing (name, hit_die, description)
2. **Task 2B:** Proficiency parsing (armor, weapons, tools, skills)
3. **Task 2C:** Trait parsing (flavor text + source citations)
4. **Task 2D:** Feature parsing (autolevel → features by level)
5. **Task 2E:** Spell slot parsing ("2,3,2" → cantrips + spell slots)
6. **Task 2F:** Counter parsing (Ki Points, Rage, Superiority Dice)
7. **Task 2G:** Subclass detection (pattern matching + grouping) - MOST COMPLEX

**Each task follows:** RED (failing test) → GREEN (implement) → Format (Pint) → Commit

**Estimated time:** 3-4 hours

### BATCH 3: Importer Implementation
**Tasks:**
1. Base class import (use ImportsSources, ImportsTraits, ImportsProficiencies traits)
2. Feature import (to class_features table)
3. Spell progression import (to class_level_progression table)
4. Counter import (to class_counters table)
5. Subclass import (hierarchical slugs, parent_class_id)
6. Multi-source tracking (entity_sources polymorphic)
7. Artisan command: `php artisan import:classes {file}`

**Estimated time:** 4-5 hours

### BATCH 4: API Layer
**Tasks (can parallelize):**
1. API Resources (ClassResource, ClassFeatureResource, etc.)
2. API Controller (index, show with dual ID/slug routing)
3. API Tests (list, show, pagination, filtering)

**Estimated time:** 2-3 hours

### BATCH 5: Integration & Verification
1. Import Fighter from 3 XML files (PHB, TCE, XGE)
2. Verify database records (1 base + 3 subclasses + all related data)
3. Test API endpoints manually
4. Run full test suite
5. Quality gates (Pint, all tests pass)

**Estimated time:** 1 hour

---

## 🗂️ Key Files & Locations

### Models
```
app/Models/
├── CharacterClass.php (updated)
├── ClassFeature.php (new)
├── ClassLevelProgression.php (new)
└── ClassCounter.php (new)
```

### Factories
```
database/factories/
├── CharacterClassFactory.php (updated)
├── ClassFeatureFactory.php (new)
├── ClassLevelProgressionFactory.php (new)
└── ClassCounterFactory.php (new)
```

### Parser (In Progress)
```
app/Services/Parsers/
└── ClassXmlParser.php (skeleton, needs implementation)

tests/Unit/Parsers/
└── ClassXmlParserTest.php (1 test, currently RED)
```

### XML Data
```
import-files/
├── class-fighter-phb.xml (847 lines)
├── class-fighter-tce.xml (434 lines)
└── class-fighter-xge.xml (263 lines)
```

---

## 📊 Database Schema (Already Exists)

### Tables Ready
- ✅ `classes` - Base classes + subclasses (parent_class_id, slug)
- ✅ `class_features` - Features at each level (level, feature_name, is_optional, sort_order)
- ✅ `class_level_progression` - Spell slots (cantrips_known, spell_slots_1st..9th)
- ✅ `class_counters` - Resource tracking (counter_name, counter_value, reset_timing)
- ✅ Polymorphic tables: `proficiencies`, `character_traits`, `entity_sources`

### Seeders
- ✅ `CharacterClassSeeder` - 13 base classes already seeded

---

## 🎯 XML Structure Analysis

### Key Elements
```xml
<class>
  <name>Fighter</name>
  <hd>10</hd>
  <proficiency>Strength, Constitution, ...</proficiency>
  <numSkills>2</numSkills>
  <armor>Light Armor, Medium Armor, Heavy Armor, Shields</armor>
  <weapons>Simple Weapons, Martial Weapons</weapons>

  <autolevel level="1">
    <feature optional="YES">
      <name>Starting Fighter</name>
      <text>...</text>
    </feature>
  </autolevel>

  <!-- Spell slots (for Eldritch Knight) -->
  <autolevel level="3">
    <slots optional="YES">2,2</slots> <!-- cantrips, 1st level -->
  </autolevel>

  <!-- Counters (for Battle Master) -->
  <autolevel level="3">
    <counter>
      <name>Superiority Die</name>
      <value>4</value>
      <reset>S</reset>
      <subclass>Battle Master</subclass>
    </counter>
  </autolevel>

  <!-- Subclass features -->
  <autolevel level="3">
    <feature optional="YES">
      <name>Martial Archetype: Battle Master</name>
      <text>...</text>
    </feature>
  </autolevel>
</class>
```

### Subclass Detection Patterns
1. `"Martial Archetype: Battle Master"` → Extract "Battle Master"
2. `"Combat Superiority (Battle Master)"` → Extract from parentheses
3. `<counter><subclass>Battle Master</subclass></counter>` → Direct tag

---

## 🔄 Reusable Components

### Existing Traits (Already Built)
- ✅ `ParsesSourceCitations` - Extract "Source: PHB p. 70"
- ✅ `MatchesProficiencyTypes` - Fuzzy match proficiencies
- ✅ `ImportsSources` - entity_sources polymorphic import
- ✅ `ImportsTraits` - character_traits polymorphic import
- ✅ `ImportsProficiencies` - proficiencies polymorphic import

### Reference Parsers
- `RaceXmlParser.php` - Similar structure, good reference
- `FeatXmlParser.php` - Good trait usage examples
- `BackgroundXmlParser.php` - Random table extraction

### Reference Importers
- `RaceImporter.php` - Polymorphic relationships, hierarchical slugs
- `FeatImporter.php` - Uses all import traits
- `BackgroundImporter.php` - Simple, clean example

---

## 🧪 Testing Strategy

### Test Counts (Target)
- **Parser tests:** 10+ unit tests (BATCH 2)
- **Importer tests:** 15+ feature tests (BATCH 3)
- **API tests:** 8+ feature tests (BATCH 4)
- **Total new tests:** 35-40+

### TDD Workflow
```bash
# 1. Write failing test (RED)
docker compose exec php php artisan test --filter=ClassXmlParserTest

# 2. Implement code (GREEN)
# ... edit ClassXmlParser.php ...

# 3. Format code
docker compose exec php ./vendor/bin/pint

# 4. Commit
git add .
git commit -m "feat: parse basic Fighter class data"
```

---

## 📝 Expected End Result

### After Full Implementation
```bash
# Import Fighter
php artisan import:classes import-files/class-fighter-phb.xml
# → Creates 4 classes (1 base + 3 subclasses)
# → Creates 60+ features
# → Creates 20 spell slot records (Eldritch Knight)
# → Creates 8 counter records (Battle Master)

# API Usage
GET /api/v1/classes/fighter
{
  "id": 1,
  "name": "Fighter",
  "slug": "fighter",
  "hit_die": 10,
  "is_base_class": true,
  "subclasses": [
    {"name": "Battle Master", "slug": "fighter-battle-master"},
    {"name": "Champion", "slug": "fighter-champion"},
    {"name": "Eldritch Knight", "slug": "fighter-eldritch-knight"}
  ],
  "features": [...],
  "proficiencies": [...],
  "traits": [...]
}
```

---

## 🚀 Next Steps (When Resuming)

1. **Continue BATCH 2** - Implement ClassXmlParser with TDD
   - Start with Task 2A (basic class data parsing)
   - Follow RED → GREEN → Commit cycle
   - Complete all 7 parser tasks

2. **After BATCH 2 Complete** - Move to BATCH 3 (Importer)
   - Create ClassImporter.php
   - Import base class + proficiencies + traits + sources
   - Import features, spell progression, counters
   - Import subclasses with hierarchical slugs
   - Create artisan command

3. **After BATCH 3 Complete** - Move to BATCH 4 (API)
   - Create API resources
   - Create API controller with dual routing
   - Write API tests

4. **After BATCH 4 Complete** - BATCH 5 (Integration)
   - Full import test with real XML
   - Manual API verification
   - Quality gates

---

## 📚 Documentation

### Planning Documents
- `docs/CLASS-IMPORTER-BRAINSTORM.md` - Comprehensive 29-page analysis
- `docs/HANDOVER-2025-11-19-CLASS-IMPORTER-IN-PROGRESS.md` - This file

### Updated Files
- `CLAUDE.md` - Will be updated after completion with class importer info

---

## ⚠️ Important Notes

### Design Decisions
1. **Vertical Slice:** Complete Fighter end-to-end before other classes
2. **Hierarchical Slugs:** "fighter-battle-master" for subclasses
3. **Multi-Source Support:** entity_sources tracks PHB + TCE + XGE
4. **Subclass Detection:** Pattern matching on feature names + counter tags
5. **TDD Mandatory:** Every feature starts with failing test

### Current Branch
- **Branch:** `feature/entity-prerequisites` (continuing here)
- **Commits:** 3 commits from feat fixes earlier in session
  - `3b3acd3` - fix: enhance feat prerequisites and modifier parsing
  - `be07b44` - fix: parse proficiency XML elements in feats
  - `08db6d5` - fix: filter weapon prerequisites by subcategory

### Database State
- Fresh migration ready: `php artisan migrate:fresh --seed`
- 13 base classes seeded
- All tables ready for class data

---

## 🎯 Success Criteria

**Before marking COMPLETE:**
- [ ] All 50+ tests passing (parser + importer + API)
- [ ] Fighter imported from 3 XML files
- [ ] 1 base class + 3 subclasses in database
- [ ] All relationships working (features, proficiencies, traits, etc.)
- [ ] API endpoints return correct JSON structure
- [ ] Code formatted with Pint
- [ ] No regressions in existing tests (400+ tests still passing)
- [ ] Documentation updated

---

## 💡 Quick Reference Commands

```bash
# Run tests
docker compose exec php php artisan test --filter=ClassXmlParser
docker compose exec php php artisan test --filter=ClassImporter
docker compose exec php php artisan test --filter=ClassApi

# Format code
docker compose exec php ./vendor/bin/pint

# Import Fighter
docker compose exec php php artisan import:classes import-files/class-fighter-phb.xml

# Check database
docker compose exec php php artisan tinker
>>> CharacterClass::where('slug', 'fighter')->first();
>>> CharacterClass::where('slug', 'fighter-battle-master')->first();

# API calls
curl http://localhost/api/v1/classes
curl http://localhost/api/v1/classes/fighter
curl http://localhost/api/v1/classes/fighter-battle-master
```

---

**Resume Point:** Start BATCH 2 with single subagent implementing ClassXmlParser using TDD (7 tasks, sequential execution)

**Estimated remaining time:** 10-13 hours total for BATCH 2-5

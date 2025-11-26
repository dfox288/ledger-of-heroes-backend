# Next Steps Overview - What's Possible Now?

**Date:** 2025-11-22
**Current Status:** Monster API Complete, Test Suite Optimized

---

## Current State Summary

### ✅ What's Complete
- **7 Entity APIs:** Spells, Items, Classes, Feats, Backgrounds, Races (partial), Monsters
- **598 Monsters Imported:** Full stat blocks with strategies
- **Search System:** Meilisearch + Scout (3,002 documents indexed)
- **Test Suite:** 1,005 tests (optimized, 9.4% faster)
- **Strategy Pattern:** Item (5 strategies) + Monster (5 strategies)
- **OpenAPI Docs:** Auto-generated via Scramble

### 🔄 What's Partially Complete
- **Race API:** Importer ready, no API endpoints yet
- **Background API:** Importer ready, no API endpoints yet
- **SpellcasterStrategy:** Creates MonsterSpellcasting, but doesn't sync entity_spells

### 📊 What's Available
- **Data:** 477 spells, 131 classes, 598 monsters, 516 items, feats, backgrounds
- **Infrastructure:** Docker, MySQL, Meilisearch, Scout, Scramble
- **Patterns:** Strategy, Resource, Request, DTO, Service layer

---

## Path Options (Categorized by Type)

### 🎯 Feature Development (High Impact)

#### 1. **Enhance SpellcasterStrategy** (3-4 hours, HIGH VALUE)
**Status:** Ready to implement
**Goal:** Sync entity_spells table for monsters with spellcasting

**What You Get:**
- Query monster spell lists via relationships: `$monster->entitySpells`
- Filter monsters by known spells: `GET /api/v1/monsters?spells=fireball`
- New endpoint: `GET /api/v1/monsters/{id}/spells`
- Consistent with ChargedItemStrategy pattern

**Implementation:**
```php
// In SpellcasterStrategy::enhance()
foreach ($spellNames as $spellName) {
    $spell = Spell::where('slug', Str::slug($spellName))->first();
    if ($spell) {
        $monster->entitySpells()->attach($spell->id);
    }
}
```

**Files to Modify:**
- `app/Services/Importers/Strategies/Monster/SpellcasterStrategy.php`
- `tests/Unit/Strategies/Monster/SpellcasterStrategyTest.php`

**Effort:** 3-4 hours (TDD + re-import 598 monsters)

**Benefits:**
- More queryable data
- Better API filtering
- Consistent with Item spell references

---

#### 2. **Race API Endpoints** (2-3 hours, MEDIUM VALUE)
**Status:** Importer complete, data ready
**Goal:** Create RESTful API for Races

**What You Get:**
- `GET /api/v1/races` - List with pagination, filtering
- `GET /api/v1/races/{id|slug}` - Show with relationships
- Race search integration
- Consistent with Monster/Item pattern

**Files to Create:**
- `app/Http/Controllers/Api/RaceController.php`
- `app/Http/Resources/RaceResource.php`
- `app/Http/Requests/RaceIndexRequest.php`
- `app/Http/Requests/RaceShowRequest.php`
- `tests/Feature/Api/RaceApiTest.php`

**Filters:**
- Size, speed, ability score bonuses, subraces

**Effort:** 2-3 hours (following Monster pattern)

---

#### 3. **Background API Endpoints** (2-3 hours, MEDIUM VALUE)
**Status:** Importer complete, data ready
**Goal:** Create RESTful API for Backgrounds

**What You Get:**
- `GET /api/v1/backgrounds` - List with pagination
- `GET /api/v1/backgrounds/{id|slug}` - Show with relationships
- Background search integration
- Consistent with other entity patterns

**Files to Create:**
- `app/Http/Controllers/Api/BackgroundController.php`
- `app/Http/Resources/BackgroundResource.php`
- `app/Http/Requests/BackgroundIndexRequest.php`
- `app/Http/Requests/BackgroundShowRequest.php`
- `tests/Feature/Api/BackgroundApiTest.php`

**Effort:** 2-3 hours (following Monster pattern)

---

#### 4. **Monster Lair Actions & Regional Effects** (6-8 hours, MEDIUM VALUE)
**Status:** Requires schema changes
**Goal:** Add advanced monster features

**What You Get:**
- Lair actions for legendary monsters
- Regional effects (e.g., Dragon lairs)
- More complete monster data

**Schema Changes Required:**
- New table: `monster_lair_actions`
- New table: `monster_regional_effects`
- New models + factories
- Parser enhancements
- Importer updates

**Effort:** 6-8 hours (schema + TDD + data migration)

**Priority:** Medium (nice-to-have, but not critical)

---

### 🧩 Additional Monster Strategies (2-3 hours each)

#### 5. **FiendStrategy** (2-3 hours, LOW VALUE)
**Goal:** Extract fiend-specific traits (devils, demons)

**Features:**
- Detect immunity to fire/poison
- Extract devil/demon resistances
- Tag fiend types

**Monsters Affected:** ~30-40 fiends

**Priority:** Low (DefaultStrategy handles adequately)

---

#### 6. **CelestialStrategy** (2-3 hours, LOW VALUE)
**Goal:** Extract celestial-specific traits (angels)

**Features:**
- Detect radiant damage abilities
- Extract divine resistances
- Tag celestial types

**Monsters Affected:** ~15-20 celestials

**Priority:** Low (DefaultStrategy handles adequately)

---

#### 7. **ConstructStrategy** (2-3 hours, LOW VALUE)
**Goal:** Extract construct immunities

**Features:**
- Detect immunity to poison/charm/exhaustion
- Extract construct traits
- Tag construct types

**Monsters Affected:** ~20-30 constructs

**Priority:** Low (DefaultStrategy handles adequately)

---

#### 8. **ShapechangerStrategy** (2-3 hours, LOW VALUE)
**Goal:** Extract shapechanger abilities

**Features:**
- Detect lycanthrope transformations
- Extract doppelganger traits
- Tag shapechanger types

**Monsters Affected:** ~10-15 shapechangers

**Priority:** Low (DefaultStrategy handles adequately)

---

### 📚 Documentation & Polish (1-4 hours total)

#### 9. **Update README.md** (1 hour, MEDIUM VALUE)
**Goal:** Reflect current state of project

**Updates Needed:**
- Monster API endpoints
- Update test count: 1,040 → 1,005
- Update entity count: 6 → 7 entities
- Add monster search examples
- Update feature list

**Effort:** 1 hour

**Benefits:** Better onboarding, clearer docs

---

#### 10. **Create Postman Collection** (2 hours, MEDIUM VALUE)
**Goal:** Make API easier to explore

**What to Create:**
- Collection for all 7 entity endpoints
- Example requests with filters
- Environment variables
- Documentation annotations

**Effort:** 2 hours

**Benefits:** Better API discoverability, easier testing

---

#### 11. **API Usage Examples** (2 hours, LOW-MEDIUM VALUE)
**Goal:** Show common API patterns

**Examples:**
- "Find all level 3 spells that deal fire damage"
- "Get all dragons with CR > 10"
- "Find items that grant AC bonuses"
- "Search for spells by name with typo tolerance"

**Format:** Markdown with curl/httpie examples

**Effort:** 2 hours

---

### 🔧 Code Quality & Maintenance (2-10 hours)

#### 12. **Fix Flaky Monster Search Test** (1-2 hours, LOW VALUE)
**Status:** Known issue, low impact
**Goal:** Make `MonsterApiTest::can_search_monsters_by_name` reliable

**Issue:** Test fails in full suite, passes individually (race condition)

**Investigation Needed:**
- Test order dependency
- Scout index timing
- Database state pollution

**Effort:** 1-2 hours debugging

**Priority:** Low (test is valid, feature works)

---

#### 13. **Add Challenge Rating Numeric Column** (2-3 hours, LOW-MEDIUM VALUE)
**Status:** Enhancement opportunity
**Goal:** Fix CR filtering edge cases

**Problem:** `challenge_rating` is VARCHAR ("1/4", "1/2")
**Solution:** Add `cr_numeric` DECIMAL column

**Migration:**
```php
Schema::table('monsters', function (Blueprint $table) {
    $table->decimal('cr_numeric', 5, 2)->nullable()->after('challenge_rating');
});

// Data migration
"1/4" → 0.25
"1/2" → 0.50
"10"  → 10.00
```

**Benefits:**
- Accurate CR range filtering
- No CAST AS DECIMAL hacks
- Better performance

**Effort:** 2-3 hours (migration + tests)

---

#### 14. **Test Reduction Phase 2-5** (2-10 hours, LOW VALUE)
**Status:** Optional, strategy documented
**Goal:** Further reduce test suite

**Phases Available:**
- Phase 2: Search consolidation (-21 tests, 2 hours)
- Phase 3: Form request consolidation (-50 tests, 4 hours)
- Phase 4: XML reconstruction reduction (-40 tests, 3 hours)
- Phase 5: Parser consolidation (-12 tests, 1 hour)

**Total Potential:** -123 additional tests (12% further reduction)

**Priority:** Low (test suite already optimized)

---

### 🚀 Advanced Features (4-12 hours each)

#### 15. **Character Builder API** (8-12 hours, HIGH VALUE)
**Status:** New feature
**Goal:** API endpoints for character creation

**Features:**
- Race selection with ability score bonuses
- Class selection with proficiencies
- Background selection with equipment
- Spell selection by class
- Feat selection with prerequisites
- Stat calculation (AC, HP, saves)

**Endpoints:**
- `POST /api/v1/characters` - Create character
- `GET /api/v1/characters/{id}` - Show character
- `GET /api/v1/characters/{id}/available-spells` - Spell options
- `GET /api/v1/characters/{id}/available-feats` - Feat options

**Complexity:** High (business logic, validation, calculations)

**Effort:** 8-12 hours (TDD required)

---

#### 16. **Encounter Builder API** (6-10 hours, MEDIUM-HIGH VALUE)
**Status:** New feature
**Goal:** API for creating balanced encounters

**Features:**
- Monster selection by CR/type
- XP budget calculation
- Difficulty estimation (easy/medium/hard/deadly)
- Party level support

**Endpoints:**
- `POST /api/v1/encounters` - Create encounter
- `GET /api/v1/encounters/{id}` - Show encounter
- `POST /api/v1/encounters/{id}/monsters` - Add monster
- `GET /api/v1/encounters/{id}/difficulty` - Calculate difficulty

**Complexity:** Medium (requires DMG encounter building rules)

**Effort:** 6-10 hours (TDD required)

---

#### 17. **Spell Slot Tracker API** (4-6 hours, MEDIUM VALUE)
**Status:** New feature
**Goal:** Track spell slots per character

**Features:**
- Spell slot allocation by class/level
- Short/long rest recovery
- Spell casting consumption
- Multi-class support

**Endpoints:**
- `POST /api/v1/characters/{id}/cast-spell` - Cast spell
- `POST /api/v1/characters/{id}/rest` - Short/long rest
- `GET /api/v1/characters/{id}/spell-slots` - Show available slots

**Complexity:** Medium (requires spell slot tables)

**Effort:** 4-6 hours (TDD required)

---

### 🎨 Frontend Development (20-40 hours)

#### 18. **Build Frontend Application** (20-40 hours, VERY HIGH VALUE)
**Status:** New project
**Goal:** Create web UI for D&D Compendium

**Technology Options:**
- **Inertia.js + Vue 3** - Laravel-native SPA
- **Next.js + React** - Separate frontend
- **Livewire + Alpine** - Laravel full-stack

**Features:**
- Browse spells/items/monsters
- Search with autocomplete
- Filter by properties
- Character builder UI
- Encounter builder UI

**Complexity:** Very High (full application)

**Effort:** 20-40 hours (MVP)

**Priority:** Low (API-first approach is working)

---

### 📊 Performance & Optimization (2-8 hours)

#### 19. **API Caching Strategy** (3-5 hours, MEDIUM VALUE)
**Status:** Not implemented
**Goal:** Cache frequently accessed data

**Implementation:**
- Cache lookup tables (sources, schools, etc.) - 1 hour expiry
- Cache search results - 5 minute expiry
- Cache entity show endpoints - 15 minute expiry
- Cache tags: entity type, filters

**Tools:**
- Laravel Cache
- Redis (optional)
- Cache headers (ETags, Last-Modified)

**Effort:** 3-5 hours (implementation + testing)

**Benefits:**
- Reduced database load
- Faster response times
- Better scalability

---

#### 20. **Database Indexing Review** (2-3 hours, MEDIUM VALUE)
**Status:** Basic indexes exist
**Goal:** Optimize query performance

**Review:**
- Add composite indexes for common filters
- Add indexes on foreign keys
- Review slow query log
- Add indexes for sort columns

**Examples:**
```sql
CREATE INDEX idx_spells_level_school ON spells(level, spell_school_id);
CREATE INDEX idx_monsters_cr_type ON monsters(challenge_rating, type);
CREATE INDEX idx_items_rarity_type ON items(rarity, item_type_id);
```

**Effort:** 2-3 hours (analysis + migration)

---

#### 21. **Add Rate Limiting** (2-3 hours, LOW-MEDIUM VALUE)
**Status:** Not implemented
**Goal:** Prevent API abuse

**Implementation:**
- Per-IP rate limiting (60 requests/minute)
- Per-endpoint throttling
- Rate limit headers (X-RateLimit-*)
- 429 Too Many Requests responses

**Effort:** 2-3 hours (middleware + tests)

---

### 🧪 Data Quality & Validation (3-8 hours)

#### 22. **Data Quality Audit** (3-4 hours, MEDIUM VALUE)
**Status:** Not performed
**Goal:** Identify missing/incorrect data

**Audit:**
- Missing source citations
- Incomplete spell descriptions
- Missing monster traits
- Orphaned relationships
- Duplicate entries

**Output:** Report with recommendations

**Effort:** 3-4 hours (queries + analysis)

---

#### 23. **Add Data Validation Rules** (4-6 hours, MEDIUM VALUE)
**Status:** Basic validation exists
**Goal:** Stricter data integrity

**Enhancements:**
- Spell level 0-9 enforcement (database constraint)
- CR validation (0-30 range)
- Ability score validation (1-30)
- Enum constraints (damage types, sizes, etc.)

**Effort:** 4-6 hours (migrations + tests)

---

### 🔒 Security & Auth (8-16 hours)

#### 24. **Add API Authentication** (8-12 hours, HIGH VALUE for production)
**Status:** Not implemented (public API)
**Goal:** Require API keys for access

**Implementation:**
- Laravel Sanctum tokens
- API key generation
- Per-user rate limiting
- Usage tracking
- Admin dashboard

**Endpoints:**
- `POST /api/v1/auth/register` - Create account
- `POST /api/v1/auth/login` - Get token
- `POST /api/v1/auth/logout` - Revoke token

**Effort:** 8-12 hours (TDD required)

**Priority:** Medium (depends on deployment plan)

---

#### 25. **Add RBAC (Role-Based Access Control)** (6-8 hours, MEDIUM VALUE)
**Status:** Not implemented
**Goal:** Different permission levels

**Roles:**
- **Public:** Read-only access
- **Contributor:** Submit corrections
- **Moderator:** Approve changes
- **Admin:** Full access

**Implementation:**
- Spatie Laravel-Permission package
- Policies for each entity
- Middleware for role checks

**Effort:** 6-8 hours (implementation + tests)

---

## Recommended Priorities

### 🥇 Tier 1: High Impact, Low Effort (RECOMMENDED)
1. **Enhance SpellcasterStrategy** (3-4 hours) - More queryable data
2. **Update README.md** (1 hour) - Better documentation
3. **Race API Endpoints** (2-3 hours) - Complete entity coverage
4. **Background API Endpoints** (2-3 hours) - Complete entity coverage

**Total:** ~10 hours
**Impact:** 2 new APIs, better monster data, updated docs

---

### 🥈 Tier 2: Medium Impact, Medium Effort
1. **API Caching Strategy** (3-5 hours) - Performance
2. **Add CR Numeric Column** (2-3 hours) - Better filtering
3. **Create Postman Collection** (2 hours) - API usability
4. **Database Indexing Review** (2-3 hours) - Performance

**Total:** ~10 hours
**Impact:** Better performance, easier API exploration

---

### 🥉 Tier 3: Advanced Features (High Effort)
1. **Character Builder API** (8-12 hours) - New functionality
2. **Encounter Builder API** (6-10 hours) - DM tools
3. **API Authentication** (8-12 hours) - Production readiness

**Total:** ~30 hours
**Impact:** New features, production-ready

---

### 🎯 Tier 4: Long-term Projects
1. **Build Frontend Application** (20-40 hours) - Full app
2. **Test Reduction Phase 2-5** (10 hours) - Maintenance
3. **Additional Monster Strategies** (8-12 hours) - Marginal improvements

**Total:** ~50 hours
**Impact:** Complete application

---

## Decision Matrix

| Option | Effort | Impact | Complexity | ROI |
|--------|--------|--------|------------|-----|
| SpellcasterStrategy Enhancement | 3-4h | High | Low | ⭐⭐⭐⭐⭐ |
| Race API | 2-3h | High | Low | ⭐⭐⭐⭐⭐ |
| Background API | 2-3h | High | Low | ⭐⭐⭐⭐⭐ |
| Update README | 1h | Medium | Low | ⭐⭐⭐⭐ |
| API Caching | 3-5h | High | Medium | ⭐⭐⭐⭐ |
| CR Numeric Column | 2-3h | Medium | Low | ⭐⭐⭐ |
| Postman Collection | 2h | Medium | Low | ⭐⭐⭐ |
| Database Indexing | 2-3h | Medium | Medium | ⭐⭐⭐ |
| Character Builder | 8-12h | Very High | High | ⭐⭐⭐⭐ |
| Encounter Builder | 6-10h | High | Medium | ⭐⭐⭐⭐ |
| API Auth | 8-12h | High | Medium | ⭐⭐⭐ |
| Frontend App | 20-40h | Very High | Very High | ⭐⭐⭐⭐ |
| Additional Strategies | 2-3h each | Low | Low | ⭐⭐ |
| Test Reduction | 2-10h | Low | Medium | ⭐⭐ |

---

## My Recommendations

### For Next Session (2-4 hours)
**Focus on completing entity coverage:**
1. ✅ Enhance SpellcasterStrategy (3-4h)
2. ✅ Update README.md (1h)

**Total:** 4-5 hours
**Result:** Better monster data + updated docs

---

### For Next 2-3 Sessions (10-15 hours)
**Complete all entity APIs:**
1. ✅ Race API Endpoints (2-3h)
2. ✅ Background API Endpoints (2-3h)
3. ✅ API Caching Strategy (3-5h)
4. ✅ Create Postman Collection (2h)

**Total:** 10-15 hours
**Result:** 7 complete entity APIs + performance improvements

---

### Long-term Vision (30-50 hours)
**Build complete D&D application:**
1. ✅ Character Builder API (8-12h)
2. ✅ Encounter Builder API (6-10h)
3. ✅ API Authentication (8-12h)
4. ✅ Frontend Application (20-40h)

**Total:** 50+ hours
**Result:** Full-featured D&D Compendium + Character Builder

---

## What Would You Like to Work On?

I recommend starting with **Tier 1** items for maximum impact with minimal effort. Which sounds most interesting to you?

1. **SpellcasterStrategy Enhancement** - Make monster spells queryable
2. **Race/Background APIs** - Complete entity coverage
3. **Performance Improvements** - Caching + indexing
4. **Advanced Features** - Character/Encounter builders
5. **Something else?** - Tell me what interests you!

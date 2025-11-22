# Static Reference Entity Reverse Relationships - Design Document

**Date:** 2025-11-22
**Status:** Design Complete - Ready for Implementation
**Estimated Effort:** 4-6 hours (Tier 1), 8-12 hours (Tier 1 + Tier 2)

---

## Executive Summary

Add reverse relationship endpoints to static reference entities (spell schools, damage types, conditions) to enable powerful queries like "Show all Evocation spells" and "Show all fire damage items". Following the proven pattern from existing spell reverse relationships (`/spells/{id}/classes`), this enhancement unlocks 6 new high-value endpoints with minimal code duplication.

### Goals

1. **Query Flexibility** - Enable reverse lookups from reference entities to main entities
2. **Pattern Consistency** - Follow existing `/spells/{id}/classes` pattern for all new endpoints
3. **API Discoverability** - RESTful URLs that are self-documenting
4. **Zero Breaking Changes** - Purely additive, fully backward compatible

### Non-Goals

- Generic/dynamic relationship resolver (rejected in favor of explicit, type-safe endpoints)
- `?include=` query parameters (rejected due to pagination issues with nested data)
- Filtering capabilities on reverse endpoints (can be added later if needed)

---

## Architecture Overview

### Three Relationship Patterns

Static reference entities use three distinct database relationship patterns:

1. **Direct Foreign Keys** (Simple HasMany)
   - Example: `spell_schools.id` ← `spells.spell_school_id`
   - Entities: SpellSchool, Size, ItemType

2. **HasManyThrough** (Through Intermediate Table)
   - Example: `damage_types.id` → `spell_effects.damage_type_id` → `spells.id`
   - Entities: DamageType (spells via spell_effects)

3. **Polymorphic Many-to-Many** (MorphedByMany)
   - Example: `conditions.id` ← `entity_conditions(reference_type, reference_id)`
   - Entities: Condition, Language, ProficiencyType, AbilityScore

Each pattern requires slightly different Eloquent relationship definitions but produces identical API endpoints.

---

## Tier 1: High-Value Entities (Priority)

### 1. SpellSchool → Spells

**Use Case:** "Show me all Evocation spells for my damage-focused wizard"

**Endpoint:** `GET /api/v1/spell-schools/{id|code|slug}/spells`

**Relationship Pattern:** Direct Foreign Key (HasMany)

**Model Method:**
```php
// app/Models/SpellSchool.php
public function spells(): HasMany {
    return $this->hasMany(Spell::class);
}
```

**Controller Method:**
```php
// app/Http/Controllers/Api/SpellSchoolController.php
public function spells(SpellSchool $spellSchool) {
    $spells = $spellSchool->spells()
        ->with(['spellSchool', 'sources', 'tags'])
        ->paginate(50);
    return SpellResource::collection($spells);
}
```

**Route:**
```php
Route::get('spell-schools/{spellSchool}/spells', [SpellSchoolController::class, 'spells'])
    ->name('spell-schools.spells');
```

**Example Queries:**
- `GET /api/v1/spell-schools/evocation/spells` - All Evocation spells (~60 spells)
- `GET /api/v1/spell-schools/3/spells` - By numeric ID
- `GET /api/v1/spell-schools/EV/spells` - By school code

**Tests (4):**
1. Returns spells for school
2. Returns empty when school has no spells
3. Accepts numeric ID
4. Paginates results

---

### 2. DamageType → Spells & Items

**Use Case:** "Find all fire spells and fire weapons for my pyromancer build"

**Endpoints:**
- `GET /api/v1/damage-types/{id|code|slug}/spells`
- `GET /api/v1/damage-types/{id|code|slug}/items`

**Relationship Pattern:**
- Spells: HasManyThrough (via spell_effects)
- Items: Direct FK (HasMany)

**Model Methods:**
```php
// app/Models/DamageType.php

// NEW - spells relationship via spell_effects
public function spells(): HasManyThrough {
    return $this->hasManyThrough(
        Spell::class,           // Final model
        SpellEffect::class,     // Intermediate model
        'damage_type_id',       // FK on spell_effects table
        'id',                   // FK on spells table
        'id',                   // Local key on damage_types table
        'spell_id'              // Local key on spell_effects table
    )->distinct(); // Prevent duplicates
}

// EXISTING - items relationship
public function items(): HasMany {
    return $this->hasMany(Item::class, 'damage_type_id');
}
```

**Controller Methods:**
```php
// app/Http/Controllers/Api/DamageTypeController.php

public function spells(DamageType $damageType) {
    $spells = $damageType->spells()
        ->with(['spellSchool', 'sources', 'tags'])
        ->paginate(50);
    return SpellResource::collection($spells);
}

public function items(DamageType $damageType) {
    $items = $damageType->items()
        ->with(['itemType', 'sources', 'tags'])
        ->paginate(50);
    return ItemResource::collection($items);
}
```

**Routes:**
```php
Route::get('damage-types/{damageType}/spells', [DamageTypeController::class, 'spells'])
    ->name('damage-types.spells');
Route::get('damage-types/{damageType}/items', [DamageTypeController::class, 'items'])
    ->name('damage-types.items');
```

**Example Queries:**
- `GET /api/v1/damage-types/fire/spells` - Fire spells (Fireball, Burning Hands, ~24 spells)
- `GET /api/v1/damage-types/fire/items` - Fire weapons (Flame Tongue, etc.)
- `GET /api/v1/damage-types/1/spells` - By numeric ID

**Tests (8):**
1. Returns spells for damage type
2. Returns empty when damage type has no spells
3. Accepts numeric ID for spells
4. Paginates spell results
5. Returns items for damage type
6. Returns empty when damage type has no items
7. Accepts numeric ID for items
8. Paginates item results

---

### 3. Condition → Spells & Monsters

**Use Case:** "Which spells inflict the poisoned condition? Which monsters can inflict it?"

**Endpoints:**
- `GET /api/v1/conditions/{id|slug}/spells`
- `GET /api/v1/conditions/{id|slug}/monsters`

**Relationship Pattern:** Polymorphic Many-to-Many (MorphedByMany via entity_conditions)

**Database Table Structure:**
```sql
entity_conditions:
  - reference_type (polymorphic: App\Models\Spell, App\Models\Monster)
  - reference_id (polymorphic ID)
  - condition_id (FK to conditions)
  - effect_type ('inflicts', 'immunity', 'resistance', 'advantage')
  - description (optional context)
```

**Model Methods:**
```php
// app/Models/Condition.php

public function spells(): MorphToMany {
    return $this->morphedByMany(
        Spell::class,
        'reference',
        'entity_conditions',
        'condition_id',
        'reference_id'
    )
        ->withPivot('effect_type', 'description')
        ->wherePivot('effect_type', 'inflicts'); // Only "inflicts" relationships
}

public function monsters(): MorphToMany {
    return $this->morphedByMany(
        Monster::class,
        'reference',
        'entity_conditions',
        'condition_id',
        'reference_id'
    )
        ->withPivot('effect_type', 'description')
        ->wherePivot('effect_type', 'inflicts'); // Only "inflicts" relationships
}
```

**Controller Methods:**
```php
// app/Http/Controllers/Api/ConditionController.php

public function spells(Condition $condition) {
    $spells = $condition->spells()
        ->with(['spellSchool', 'sources', 'tags'])
        ->paginate(50);
    return SpellResource::collection($spells);
}

public function monsters(Condition $condition) {
    $monsters = $condition->monsters()
        ->with(['size', 'type', 'sources', 'tags'])
        ->paginate(50);
    return MonsterResource::collection($monsters);
}
```

**Routes:**
```php
Route::get('conditions/{condition}/spells', [ConditionController::class, 'spells'])
    ->name('conditions.spells');
Route::get('conditions/{condition}/monsters', [ConditionController::class, 'monsters'])
    ->name('conditions.monsters');
```

**Example Queries:**
- `GET /api/v1/conditions/poisoned/spells` - Spells that inflict poisoned
- `GET /api/v1/conditions/poisoned/monsters` - Monsters that inflict poisoned
- `GET /api/v1/conditions/5/spells` - By numeric ID

**Tests (8):**
1. Returns spells for condition
2. Returns empty when condition has no spells
3. Accepts numeric ID for spells
4. Paginates spell results
5. Returns monsters for condition
6. Returns empty when condition has no monsters
7. Accepts numeric ID for monsters
8. Paginates monster results

---

## Tier 2: Optional High-Value Entities

### 4. AbilityScore → Spells (Saving Throws)

**Use Case:** "Show all spells requiring DEX saves to target enemies with low DEX"

**Endpoint:** `GET /api/v1/ability-scores/{id|code}/spells`

**Relationship Pattern:** Polymorphic via entity_saving_throws

**Model Method:**
```php
// app/Models/AbilityScore.php (ALREADY EXISTS as entitiesRequiringSave())

// RENAME/ADD for clarity
public function spells(): MorphToMany {
    return $this->morphedByMany(
        Spell::class,
        'entity',
        'entity_saving_throws',
        'ability_score_id',
        'entity_id'
    )
        ->withPivot('save_effect', 'is_initial_save')
        ->withTimestamps();
}
```

**Estimated Data Volume:**
- DEX: ~79 spells (Fireball, Lightning Bolt, Grease)
- WIS: ~40 spells (Charm Person, Dominate)
- CON: ~35 spells (Poison, disease effects)

---

### 5. ProficiencyType → Classes, Races, Backgrounds

**Use Case:** "Which classes get longsword proficiency? Which races get it innately?"

**Endpoints:**
- `GET /api/v1/proficiency-types/{id|slug}/classes`
- `GET /api/v1/proficiency-types/{id|slug}/races`
- `GET /api/v1/proficiency-types/{id|slug}/backgrounds`

**Relationship Pattern:** Polymorphic via proficiencies table

**Database Complexity:** Medium (proficiencies has multiple nullable FKs)

---

### 6. Language → Races, Backgrounds

**Use Case:** "Which races speak Draconic innately?"

**Endpoints:**
- `GET /api/v1/languages/{id|slug}/races`
- `GET /api/v1/languages/{id|slug}/backgrounds`

**Relationship Pattern:** Polymorphic via entity_languages

---

### 7. Size → Races, Monsters

**Use Case:** "Show all Medium races" or "Show all Tiny monsters"

**Endpoints:**
- `GET /api/v1/sizes/{id|code}/races`
- `GET /api/v1/sizes/{id|code}/monsters`

**Relationship Pattern:** Direct Foreign Key (HasMany)

---

## Testing Strategy

### Test Structure (Following Proven Pattern)

All tests follow the pattern established in `SpellReverseRelationshipsApiTest.php`:

**Standard Test Cases (4 per relationship):**
1. ✅ Returns entities for reference (happy path)
2. ✅ Returns empty when reference has no entities
3. ✅ Accepts numeric ID routing
4. ✅ Paginates results correctly

**Example Test Structure:**
```php
namespace Tests\Feature\Api;

use App\Models\SpellSchool;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellSchoolReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_spells_for_spell_school()
    {
        $evocation = SpellSchool::factory()->create([
            'name' => 'Evocation',
            'code' => 'EV',
            'slug' => 'evocation'
        ]);

        $fireball = Spell::factory()->create([
            'spell_school_id' => $evocation->id,
            'name' => 'Fireball'
        ]);

        $magicMissile = Spell::factory()->create([
            'spell_school_id' => $evocation->id,
            'name' => 'Magic Missile'
        ]);

        // Different school - should not appear
        $cure = Spell::factory()->create(['name' => 'Cure Wounds']);

        $response = $this->getJson("/api/v1/spell-schools/{$evocation->slug}/spells");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Fireball')
            ->assertJsonPath('data.1.name', 'Magic Missile');
    }

    #[Test]
    public function it_returns_empty_when_school_has_no_spells()
    {
        $school = SpellSchool::factory()->create(['code' => 'CUSTOM']);

        $response = $this->getJson("/api/v1/spell-schools/{$school->code}/spells");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_spells_endpoint()
    {
        $school = SpellSchool::factory()->create();
        $spell = Spell::factory()->create(['spell_school_id' => $school->id]);

        $response = $this->getJson("/api/v1/spell-schools/{$school->id}/spells");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_paginates_spell_results()
    {
        $school = SpellSchool::factory()->create();
        Spell::factory()->count(75)->create(['spell_school_id' => $school->id]);

        $response = $this->getJson("/api/v1/spell-schools/{$school->code}/spells?per_page=25");

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 75)
            ->assertJsonPath('meta.per_page', 25);
    }
}
```

### Test Coverage Summary

**Tier 1:**
- SpellSchoolReverseRelationshipsApiTest: 4 tests, 12 assertions
- DamageTypeReverseRelationshipsApiTest: 8 tests, 24 assertions
- ConditionReverseRelationshipsApiTest: 8 tests, 24 assertions
- **Total: 20 tests, 60 assertions**

**Tier 2 (Optional):**
- AbilityScoreReverseRelationshipsApiTest: 4 tests, 12 assertions
- ProficiencyTypeReverseRelationshipsApiTest: 12 tests, 36 assertions
- LanguageReverseRelationshipsApiTest: 8 tests, 24 assertions
- SizeReverseRelationshipsApiTest: 8 tests, 24 assertions
- **Total: 32 tests, 96 assertions**

---

## Documentation Standards

### PHPDoc Pattern (5-Star Quality)

Following the proven pattern from `SpellController` and `MonsterController`, each new endpoint method receives comprehensive PHPDoc:

```php
/**
 * List all spells in this school of magic
 *
 * Returns a paginated list of spells belonging to a specific school of magic.
 * Supports all spell fields including level, concentration, ritual, damage types,
 * saving throws, and component requirements.
 *
 * **Basic Examples:**
 * - Evocation spells: `GET /api/v1/spell-schools/evocation/spells`
 * - Evocation by ID: `GET /api/v1/spell-schools/3/spells`
 * - Evocation by code: `GET /api/v1/spell-schools/EV/spells`
 * - Pagination: `GET /api/v1/spell-schools/evocation/spells?per_page=25&page=2`
 *
 * **School-Specific Use Cases:**
 * - Damage dealers (Evocation): Direct damage spells (Fireball, Magic Missile, Lightning Bolt)
 * - Mind control (Enchantment): Charm Person, Dominate Monster, Suggestion
 * - Buffs & debuffs (Transmutation): Haste, Slow, Polymorph, Enlarge/Reduce
 * - Information gathering (Divination): Detect Magic, Scrying, Identify
 * - Defense (Abjuration): Shield, Counterspell, Dispel Magic, Protection spells
 * - Summoning (Conjuration): Summon spells, Create Food and Water, Teleport
 * - Trickery (Illusion): Invisibility, Mirror Image, Silent Image, Disguise Self
 * - Undead & life force (Necromancy): Animate Dead, Vampiric Touch, Speak with Dead
 *
 * **Character Building:**
 * - Wizard school specialization (pick one school to focus on)
 * - Spell selection optimization (identify your school's best spells)
 * - Thematic spellcasting (pure Evocation blaster, pure Enchantment controller)
 *
 * **Reference Data:**
 * - 8 schools of magic in D&D 5e
 * - Total: 477 spells across all schools
 * - Evocation: ~60 spells (largest school, damage-focused)
 * - Enchantment: ~40 spells (mind-affecting)
 * - Transmutation: ~55 spells (versatile utility)
 * - Conjuration: ~45 spells (summoning & teleportation)
 *
 * @param SpellSchool $spellSchool The school of magic (by ID, code, or slug)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
public function spells(SpellSchool $spellSchool)
{
    $spells = $spellSchool->spells()
        ->with(['spellSchool', 'sources', 'tags'])
        ->paginate(50);

    return SpellResource::collection($spells);
}
```

**Documentation Checklist:**
- ✅ Real entity names in examples (not generic IDs)
- ✅ Use case explanations (WHY, not just HOW)
- ✅ Reference data counts (total spells, distribution)
- ✅ Multiple routing examples (slug, ID, code)
- ✅ Character building context (when would you use this?)
- ✅ Scramble-compatible @param/@return tags

---

## Implementation Plan

### Phase 1: TDD Setup (2-3 hours)

**Tasks:**
1. Create test files (3 files)
2. Write failing tests (20 tests total)
3. Verify tests fail with expected errors

**Test Files:**
- `tests/Feature/Api/SpellSchoolReverseRelationshipsApiTest.php` (4 tests)
- `tests/Feature/Api/DamageTypeReverseRelationshipsApiTest.php` (8 tests)
- `tests/Feature/Api/ConditionReverseRelationshipsApiTest.php` (8 tests)

**Run:** `php artisan test --filter=ReverseRelationships`

**Expected:** 20 failures (routes not defined)

---

### Phase 2: Minimal Implementation (1-2 hours)

**Tasks:**
1. Add model relationships (3 models)
2. Add controller methods (3 controllers, 6 methods total)
3. Add routes (6 routes)
4. Verify tests pass

**Model Changes:**

```php
// app/Models/SpellSchool.php
// VERIFY spells() relationship exists (should already be defined)

// app/Models/DamageType.php
// ADD spells() HasManyThrough relationship

// app/Models/Condition.php
// ADD spells() and monsters() MorphToMany relationships
```

**Controller Changes:**

```php
// app/Http/Controllers/Api/SpellSchoolController.php
// ADD spells() method

// app/Http/Controllers/Api/DamageTypeController.php
// ADD spells() and items() methods

// app/Http/Controllers/Api/ConditionController.php
// ADD spells() and monsters() methods
```

**Route Changes:**

```php
// routes/api.php
// ADD 6 new routes in appropriate sections
```

**Run:** `php artisan test`

**Expected:** All tests passing (1,137 tests)

---

### Phase 3: Documentation (30-45 minutes)

**Tasks:**
1. Add 5-star PHPDoc to all 6 new methods
2. Update CHANGELOG.md
3. Verify Scramble OpenAPI docs

**PHPDoc Targets:**
- `SpellSchoolController::spells()` (~60 lines)
- `DamageTypeController::spells()` (~60 lines)
- `DamageTypeController::items()` (~60 lines)
- `ConditionController::spells()` (~60 lines)
- `ConditionController::monsters()` (~60 lines)

**Run:** `php artisan scramble:docs`

**Verify:** All new endpoints appear in OpenAPI spec at `http://localhost:8080/docs/api`

---

### Phase 4: Quality Gates (15-30 minutes)

**Tasks:**
1. Format code with Pint
2. Run full test suite
3. Manual endpoint testing
4. Create session handover document

**Commands:**
```bash
# Format code
./vendor/bin/pint

# Full test suite
php artisan test

# Manual testing
curl http://localhost:8080/api/v1/spell-schools/evocation/spells | jq
curl http://localhost:8080/api/v1/damage-types/fire/spells | jq
curl http://localhost:8080/api/v1/conditions/poisoned/spells | jq
```

---

## Success Criteria

### Must Have (Tier 1)

- ✅ **20 new tests passing** (zero regressions in existing 1,117 tests)
- ✅ **6 new endpoints functional** (spell-schools, damage-types, conditions)
- ✅ **Slug/ID/Code routing** working for all endpoints
- ✅ **Pagination** working (50 per page default, customizable)
- ✅ **5-star PHPDoc** for all new methods (~360 lines of documentation)
- ✅ **Scramble OpenAPI docs** auto-generated and accurate
- ✅ **Code formatted** with Pint (zero violations)
- ✅ **Zero breaking changes** (fully backward compatible)
- ✅ **CHANGELOG.md updated** with feature descriptions
- ✅ **Session handover document** created

### Nice to Have (Tier 2 - Optional)

- 🔄 AbilityScore → spells (saving throws)
- 🔄 ProficiencyType → classes/races/backgrounds
- 🔄 Language → races/backgrounds
- 🔄 Size → races/monsters

---

## Files Modified/Created

### Created (3 test files)
1. `tests/Feature/Api/SpellSchoolReverseRelationshipsApiTest.php`
2. `tests/Feature/Api/DamageTypeReverseRelationshipsApiTest.php`
3. `tests/Feature/Api/ConditionReverseRelationshipsApiTest.php`

### Modified (7 implementation files)
1. `app/Models/SpellSchool.php` - Verify spells() relationship
2. `app/Models/DamageType.php` - Add spells() HasManyThrough
3. `app/Models/Condition.php` - Add spells(), monsters() MorphToMany
4. `app/Http/Controllers/Api/SpellSchoolController.php` - Add spells() method + PHPDoc
5. `app/Http/Controllers/Api/DamageTypeController.php` - Add spells(), items() methods + PHPDoc
6. `app/Http/Controllers/Api/ConditionController.php` - Add spells(), monsters() methods + PHPDoc
7. `routes/api.php` - Add 6 new routes

### Modified (2 documentation files)
1. `CHANGELOG.md` - Document new features
2. `docs/SESSION-HANDOVER-2025-11-22-STATIC-REFERENCE-REVERSE-RELATIONSHIPS.md` - Implementation handover

---

## Risk Assessment

### Low Risk
- ✅ **Pattern proven** - Identical to existing `/spells/{id}/classes` implementation
- ✅ **No schema changes** - All relationships use existing tables
- ✅ **Zero breaking changes** - Purely additive endpoints
- ✅ **Simple relationships** - Standard Eloquent patterns (HasMany, HasManyThrough, MorphToMany)

### Potential Issues & Mitigations

**Issue:** DamageType → Spells HasManyThrough may return duplicates if spell has multiple effects with same damage type

**Mitigation:** Use `->distinct()` in relationship definition

**Issue:** Condition polymorphic relationship may include unintended effect types (immunity, resistance)

**Mitigation:** Use `->wherePivot('effect_type', 'inflicts')` to filter only relevant relationships

**Issue:** Pagination may be slow for large result sets (e.g., "all Medium monsters")

**Mitigation:** Existing database indexes on FK columns should provide adequate performance. Monitor in production.

---

## Future Enhancements (Out of Scope)

1. **Filtering on reverse endpoints** - Add query params like `?level=1` to `/spell-schools/{id}/spells`
2. **Sorting options** - Custom sort orders beyond default alphabetical
3. **Relationship counts** - Add `spells_count` to SpellSchoolResource
4. **Meilisearch integration** - Use search indexes for sub-10ms queries
5. **Conditional inclusions** - `?include=spells` on SpellSchool show() endpoint

---

## Appendix: Example Queries

### SpellSchool Queries
```bash
# All Evocation spells (damage dealers)
GET /api/v1/spell-schools/evocation/spells

# All Enchantment spells (mind control)
GET /api/v1/spell-schools/enchantment/spells

# Transmutation by ID
GET /api/v1/spell-schools/8/spells

# Pagination
GET /api/v1/spell-schools/evocation/spells?per_page=25&page=2
```

### DamageType Queries
```bash
# Fire spells (Fireball, Burning Hands)
GET /api/v1/damage-types/fire/spells

# Fire weapons (Flame Tongue, Fire Arrow)
GET /api/v1/damage-types/fire/items

# Psychic spells (Mind Spike, Synaptic Static)
GET /api/v1/damage-types/psychic/spells

# Slashing weapons
GET /api/v1/damage-types/slashing/items
```

### Condition Queries
```bash
# Spells that poison
GET /api/v1/conditions/poisoned/spells

# Monsters that poison
GET /api/v1/conditions/poisoned/monsters

# Spells that stun
GET /api/v1/conditions/stunned/spells

# Spells that charm
GET /api/v1/conditions/charmed/spells
```

---

**End of Design Document**

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>

# SpellcasterStrategy Enhancement Plan

**Date:** 2025-11-22
**Status:** Ready to Implement (Next Session)
**Estimated Effort:** 3-4 hours

---

## Goal

Enhance `SpellcasterStrategy` to sync monster spells to the `entity_spells` polymorphic table, making monster spell lists queryable via relationships (following the pattern established by `ChargedItemStrategy`).

---

## Current State

### What Works Now
- `SpellcasterStrategy` applies to monsters with spellcasting (`monsterData['spells']` not empty)
- Creates `MonsterSpellcasting` records with spell metadata (slots, DC, attack bonus)
- Monster XML contains spell names in `<spells>` tag as comma-separated list
- Spell data stored but NOT synced to `entity_spells` table

### Current Implementation
**File:** `app/Services/Importers/Strategies/Monster/SpellcasterStrategy.php`

```php
class SpellcasterStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return isset($monsterData['spells']) && ! empty($monsterData['spells']);
    }

    public function afterCreate(Monster $monster, array $monsterData): void
    {
        // TODO: Implement spell syncing in future iteration
        // For now, just mark that strategy was selected
    }

    public function extractMetadata(array $monsterData): array
    {
        $metadata = parent::extractMetadata($monsterData);
        $metadata['has_spells'] = ! empty($monsterData['spells']);
        $metadata['has_spell_slots'] = ! empty($monsterData['slots']);

        return $metadata;
    }
}
```

**Current Behavior:** Strategy applies and logs metadata, but does NOT create spell relationships.

---

## Desired State

### What We Want
- Parse spell names from `monsterData['spells']` (comma-separated string)
- Look up each spell in database (case-insensitive)
- Sync spells to `entity_spells` pivot table
- Track metrics: `spells_matched`, `spells_not_found`
- Log warnings for spells not found in database
- Enable new API capabilities:
  - Filter monsters by known spells: `GET /api/v1/monsters?spells=fireball`
  - Query monster spells: `$monster->entitySpells`
  - New endpoint: `GET /api/v1/monsters/{id}/spells`

### Expected Behavior After Enhancement
```php
// Monster with spells
$lich = Monster::where('slug', 'lich')->first();
$lich->entitySpells; // Collection of Spell models

// Filter by spell
Monster::whereHas('entitySpells', function ($query) {
    $query->where('slug', 'fireball');
})->get();
```

---

## Implementation Pattern (Follow ChargedItemStrategy)

### Reference Implementation
**File:** `app/Services/Parsers/Strategies/ChargedItemStrategy.php`

**Key Methods:**
1. `extractSpells()` - Parse spell names from description
2. `normalizeSpellName()` - Convert to Title Case
3. `findSpell()` - Case-insensitive database lookup
4. Metrics: `setMetric('spell_references_found')`, `incrementMetric('spells_matched')`
5. Warnings: `addWarning("Spell not found: {$name}")`

**Spell Lookup Pattern:**
```php
private function findSpell(string $name): ?Spell
{
    return Spell::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
}
```

**Normalization Pattern:**
```php
private function normalizeSpellName(string $name): string
{
    $name = trim($name);
    return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
}
```

---

## Step-by-Step Implementation (TDD)

### Phase 1: Write Tests (1-1.5 hours)

**File to Create:** `tests/Unit/Strategies/Monster/SpellcasterStrategyEnhancementTest.php`

**Test Cases:**
1. ✅ `it_syncs_single_spell_to_entity_spells()`
   - Monster with one spell (e.g., "Fire Bolt")
   - Assert spell synced to entity_spells table
   - Assert metric: spells_matched = 1

2. ✅ `it_syncs_multiple_spells_to_entity_spells()`
   - Monster with comma-separated spells (e.g., "Fireball, Lightning Bolt, Cone of Cold")
   - Assert all spells synced
   - Assert metric: spells_matched = 3

3. ✅ `it_handles_case_insensitive_spell_matching()`
   - Monster spell: "fireball" (lowercase)
   - Database spell: "Fireball" (title case)
   - Assert spell matched and synced

4. ✅ `it_logs_warning_for_spell_not_found()`
   - Monster spell: "Nonexistent Spell"
   - Assert warning logged
   - Assert metric: spells_not_found = 1
   - Assert spell NOT synced to entity_spells

5. ✅ `it_handles_mixed_found_and_not_found_spells()`
   - Monster spells: "Fireball, Nonexistent Spell, Lightning Bolt"
   - Assert 2 spells synced
   - Assert 1 warning logged
   - Assert metrics: spells_matched = 2, spells_not_found = 1

6. ✅ `it_trims_whitespace_from_spell_names()`
   - Monster spell: " Fireball , Lightning Bolt "
   - Assert spells matched despite extra whitespace

7. ✅ `it_handles_empty_spell_list_gracefully()`
   - Monster with empty spells string
   - Assert no errors, no spells synced

8. ✅ `it_uses_existing_spell_cache_for_performance()`
   - Mock database to verify spell lookup is cached (optional, for performance)

**Test Setup Pattern:**
```php
use App\Models\Monster;
use App\Models\Spell;
use App\Models\SpellSchool;
use App\Services\Importers\Strategies\Monster\SpellcasterStrategy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellcasterStrategyEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private SpellcasterStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new SpellcasterStrategy;
    }

    #[Test]
    public function it_syncs_single_spell_to_entity_spells(): void
    {
        // Arrange: Create spell in database
        $school = SpellSchool::factory()->create(['code' => 'EVO']);
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'slug' => 'fireball',
            'spell_school_id' => $school->id,
        ]);

        // Create monster
        $monster = Monster::factory()->create(['name' => 'Lich']);

        // Monster data with spell
        $monsterData = [
            'spells' => 'Fireball',
        ];

        // Act: Call strategy
        $this->strategy->afterCreate($monster, $monsterData);

        // Assert: Spell synced to entity_spells
        $this->assertTrue($monster->entitySpells()->where('spell_id', $fireball->id)->exists());

        // Assert: Metrics tracked
        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertEquals(1, $metadata['spells_matched'] ?? 0);
    }
}
```

**Run Tests (Expect RED):**
```bash
docker compose exec php php artisan test --filter=SpellcasterStrategyEnhancement
```

All tests should FAIL initially (TDD RED phase).

---

### Phase 2: Implement Enhancement (1-1.5 hours)

**File to Modify:** `app/Services/Importers/Strategies/Monster/SpellcasterStrategy.php`

**Implementation:**

```php
<?php

namespace App\Services\Importers\Strategies\Monster;

use App\Models\Monster;
use App\Models\Spell;

class SpellcasterStrategy extends AbstractMonsterStrategy
{
    /** @var array<string, Spell> Cache of spell lookups */
    private array $spellCache = [];

    public function appliesTo(array $monsterData): bool
    {
        return isset($monsterData['spells']) && ! empty($monsterData['spells']);
    }

    public function afterCreate(Monster $monster, array $monsterData): void
    {
        if (empty($monsterData['spells'])) {
            return;
        }

        $this->syncSpells($monster, $monsterData['spells']);
    }

    public function extractMetadata(array $monsterData): array
    {
        $metadata = parent::extractMetadata($monsterData);
        $metadata['has_spells'] = ! empty($monsterData['spells']);
        $metadata['has_spell_slots'] = ! empty($monsterData['slots']);

        return $metadata;
    }

    /**
     * Sync monster spells to entity_spells table.
     *
     * @param Monster $monster
     * @param string $spellsString Comma-separated spell names
     */
    private function syncSpells(Monster $monster, string $spellsString): void
    {
        $spellNames = $this->parseSpellNames($spellsString);

        if (empty($spellNames)) {
            return;
        }

        $this->setMetric('spell_references_found', count($spellNames));

        foreach ($spellNames as $spellName) {
            $spell = $this->findSpell($spellName);

            if ($spell) {
                // Sync to entity_spells pivot table
                $monster->entitySpells()->attach($spell->id);
                $this->incrementMetric('spells_matched');
            } else {
                $this->incrementMetric('spells_not_found');
                $this->addWarning("Spell not found in database: {$spellName}");
            }
        }
    }

    /**
     * Parse comma-separated spell names.
     *
     * @param string $spellsString
     * @return array<int, string> Normalized spell names
     */
    private function parseSpellNames(string $spellsString): array
    {
        $names = explode(',', $spellsString);

        return array_map(
            fn($name) => $this->normalizeSpellName($name),
            $names
        );
    }

    /**
     * Normalize spell name to Title Case.
     *
     * @param string $name
     * @return string
     */
    private function normalizeSpellName(string $name): string
    {
        $name = trim($name);
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Find spell by name (case-insensitive) with caching.
     *
     * @param string $name
     * @return Spell|null
     */
    private function findSpell(string $name): ?Spell
    {
        $cacheKey = mb_strtolower($name);

        if (! isset($this->spellCache[$cacheKey])) {
            $this->spellCache[$cacheKey] = Spell::whereRaw('LOWER(name) = ?', [$cacheKey])->first();
        }

        return $this->spellCache[$cacheKey];
    }
}
```

**Run Tests (Expect GREEN):**
```bash
docker compose exec php php artisan test --filter=SpellcasterStrategyEnhancement
```

All tests should PASS (TDD GREEN phase).

---

### Phase 3: Refactor (Optional, 30 minutes)

**Potential Improvements:**
1. Extract spell lookup to shared trait (if other strategies need it)
2. Add spell cache clearing between imports
3. Consider batch insert for performance (if needed)

**Run Full Test Suite:**
```bash
docker compose exec php php artisan test
```

Ensure no regressions (all 1,005+ tests should still pass).

---

### Phase 4: Re-Import Monsters (5-10 minutes)

**Why:** Existing 598 monsters don't have entity_spells relationships yet.

**Command:**
```bash
docker compose exec php php artisan import:all --only=monsters --skip-migrate
```

**Expected Output:**
```
Importing monsters...
✓ Successfully imported 598 monsters

Strategy Statistics:
+---------------------+----------+----------+
| Strategy            | Monsters | Warnings |
+---------------------+----------+----------+
| SpellcasterStrategy | 45       | 3        |  ← Updated with spell syncing
| DragonStrategy      | 12       | 0        |
| DefaultStrategy     | 598      | 0        |
+---------------------+----------+----------+
```

**Verify:**
```bash
docker compose exec php php artisan tinker

# Check Lich spells
$lich = \App\Models\Monster::where('slug', 'lich')->first();
$lich->entitySpells()->count(); // Should return > 0

# Check specific spell
$lich->entitySpells()->where('slug', 'fireball')->exists(); // true/false
```

---

### Phase 5: Update API (Optional, 1 hour)

**If Time Permits:**

**Add Monster Spell Filtering:**
Update `MonsterIndexRequest` to accept `spells` parameter.

**Add Monster Spell Endpoint:**
```php
// app/Http/Controllers/Api/MonsterController.php
public function spells(Monster $monster)
{
    $monster->load(['entitySpells' => function ($query) {
        $query->orderBy('name');
    }]);

    return SpellResource::collection($monster->entitySpells);
}

// routes/api.php
Route::get('/monsters/{monster}/spells', [MonsterController::class, 'spells']);
```

---

## Files to Modify

### Required Changes
1. **`app/Services/Importers/Strategies/Monster/SpellcasterStrategy.php`**
   - Add `syncSpells()` method
   - Add `parseSpellNames()` method
   - Add `normalizeSpellName()` method
   - Add `findSpell()` method with caching
   - Update `afterCreate()` to call `syncSpells()`

2. **`tests/Unit/Strategies/Monster/SpellcasterStrategyTest.php`** (NEW FILE)
   - 8 comprehensive test cases
   - Follow TDD approach (write first, watch fail)

### Optional Changes (If Time Permits)
3. **`app/Http/Controllers/Api/MonsterController.php`**
   - Add `spells()` method for spell list endpoint

4. **`app/Http/Requests/MonsterIndexRequest.php`**
   - Add `spells` filter parameter validation

5. **`routes/api.php`**
   - Add `/monsters/{monster}/spells` route

---

## Verification Checklist

After implementation, verify:

- [ ] All tests passing: `php artisan test`
- [ ] Strategy tests passing: `php artisan test --filter=SpellcasterStrategy`
- [ ] Monster import successful: `php artisan import:all --only=monsters --skip-migrate`
- [ ] Database check: Monsters with spells have entity_spells records
- [ ] Metrics tracked: `spells_matched`, `spells_not_found` in logs
- [ ] Warnings logged: Spells not found show in `storage/logs/import-strategy-*.log`
- [ ] Code formatted: `./vendor/bin/pint`
- [ ] No regressions: Full test suite still passes

---

## Expected Metrics (After Re-Import)

**Monsters with Spellcasting:** ~45-50 (based on bestiary XML)

**Example Monsters:**
- Lich (high-level spells)
- Archmage (full caster)
- Drow (innate spells)
- Mind Flayer (psionic spells)
- Vampire (charm spells)

**Expected Metrics:**
- `spell_references_found`: ~300-400 spell references total
- `spells_matched`: ~250-350 (75-90% match rate)
- `spells_not_found`: ~50-100 (spells not in database, e.g., homebrew, psionics)

**Common Missing Spells:**
- Psionics (not in standard spell list)
- Homebrew content
- Variant spell names

---

## Documentation Updates (After Implementation)

### 1. CLAUDE.md
Update "What's Next" section:
```markdown
### Priority 1: Enhance SpellcasterStrategy ⭐ COMPLETE
**Status:** Implemented (2025-11-22)
**Result:** Monster spells now queryable via entity_spells table

**What Was Done:**
- Added spell name parsing from monster XML
- Implemented case-insensitive spell lookup with caching
- Synced ~300 spell relationships for 45 spellcasting monsters
- Tracked metrics: spells_matched (85%), spells_not_found (15%)
- Re-imported 598 monsters to populate entity_spells

**API Enhancements:**
- Filter monsters by spell: `GET /api/v1/monsters?spells=fireball`
- Query monster spells: `GET /api/v1/monsters/{id}/spells`
- Relationship: `$monster->entitySpells`
```

### 2. CHANGELOG.md
Add entry under `[Unreleased] > ### Added`:
```markdown
- **Monster Spell Syncing** - Spellcasting monsters now have queryable spell relationships
  - SpellcasterStrategy syncs spells to `entity_spells` polymorphic table
  - Case-insensitive spell matching with performance caching
  - Metrics: ~300 spell references, 85% match rate, 15% not found
  - 45 spellcasting monsters enhanced (Lich, Archmage, Drow, etc.)
  - API filtering: `GET /api/v1/monsters?spells=fireball`
  - New endpoint: `GET /api/v1/monsters/{id}/spells`
  - Tests: 8 new strategy tests for spell syncing
```

### 3. README.md
Update Monster API section:
```markdown
#### Monsters
```bash
GET /api/v1/monsters/{id}/spells       # Get monster spell list
GET /api/v1/monsters?spells=fireball   # Filter by known spell
```
```

---

## Common Issues & Solutions

### Issue 1: Spell Not Found Warnings
**Symptom:** High `spells_not_found` count in logs

**Causes:**
- Spell names in monster XML don't match database spell names
- Homebrew spells not imported
- Psionic abilities (not spells)

**Solution:**
- Review warnings in `storage/logs/import-strategy-*.log`
- Add missing spells to database OR
- Accept as expected (psionics, homebrew are legitimately missing)

### Issue 2: Duplicate Entity Spells
**Symptom:** Unique constraint violation on entity_spells

**Cause:** Strategy called multiple times for same monster

**Solution:** Add `->syncWithoutDetaching()` instead of `->attach()` OR check if relationship exists before attaching

### Issue 3: Performance Issues
**Symptom:** Import takes >5 minutes

**Cause:** Database query for each spell lookup (no caching)

**Solution:** Already implemented in plan (see `$spellCache` property)

---

## Success Criteria

Implementation is complete when:

1. ✅ All 8 strategy tests passing
2. ✅ Full test suite passing (1,005+ tests)
3. ✅ 598 monsters re-imported successfully
4. ✅ At least 40 monsters have entity_spells relationships
5. ✅ Spell match rate ≥ 75%
6. ✅ Warnings logged for missing spells
7. ✅ Code formatted with Pint
8. ✅ Documentation updated (CLAUDE.md, CHANGELOG.md, README.md)

---

## Estimated Timeline

**Total: 3-4 hours**

- Phase 1 (Tests): 1-1.5 hours
- Phase 2 (Implementation): 1-1.5 hours
- Phase 3 (Refactor): 30 minutes (optional)
- Phase 4 (Re-Import): 5-10 minutes
- Phase 5 (API Updates): 1 hour (optional)

**Core Implementation:** 2-3 hours (Phases 1-4)
**With API Enhancements:** 3-4 hours (all phases)

---

## References

**Similar Implementation:**
- `app/Services/Parsers/Strategies/ChargedItemStrategy.php` - Spell lookup pattern
- `app/Services/Importers/Concerns/ImportsEntitySpells.php` - Entity spell sync trait

**Models:**
- `app/Models/Monster.php` - Has `entitySpells()` relationship
- `app/Models/Spell.php` - Spell model
- `app/Models/MonsterSpellcasting.php` - Spellcasting metadata

**Tests:**
- `tests/Unit/Services/Parsers/Strategies/ChargedItemStrategyTest.php` - Similar test patterns
- `tests/Unit/Strategies/Monster/` - Monster strategy tests

**Migration:**
- `entity_spells` table already exists (polymorphic pivot)
- No schema changes needed

---

**End of Plan**

**Next Session:** Start with Phase 1 (write tests), follow TDD approach.

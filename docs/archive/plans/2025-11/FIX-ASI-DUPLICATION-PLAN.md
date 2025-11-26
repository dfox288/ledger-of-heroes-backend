# Fix ASI Duplication - Implementation Plan

**Date:** 2025-11-25
**Status:** Ready for Implementation
**Estimated Effort:** 4-6 hours (Phase 1-3)

---

## 🎯 Executive Summary

**Root Causes Identified:**
1. **Parser Bug (Line 622):** Overly broad regex matches "Destroy Undead (CR 1/2)" as fake subclass
2. **Importer Missing Deduplication (Line 201):** Always uses `create()` instead of `updateOrCreate()`
3. **Multi-File Imports:** Batch imports with `--merge` don't clear features before re-importing base classes

**Impact:** 7 classes have 1-2 duplicate ASI modifiers (Cleric, Druid, Monk, Barbarian, Rogue, Ranger, Warlock)

**Fix Strategy:** Three-phase approach (immediate symptom fix → root cause fix → prevention)

---

## 📊 Implementation Phases

### Phase 1: Immediate Fix (1 hour) - IMPORTER DEDUPLICATION

**Goal:** Stop creating duplicates immediately

**File:** `app/Services/Importers/ClassImporter.php`

**Change at Line 201:**

```php
// BEFORE:
if (! empty($featureData['grants_asi'])) {
    Modifier::create([
        'reference_type' => get_class($class),
        'reference_id' => $class->id,
        'modifier_category' => 'ability_score',
        'level' => $featureData['level'],
        'value' => '+2',
        'ability_score_id' => null,
        'is_choice' => true,
        'choice_count' => 2,
        'condition' => 'Choose one ability score to increase by 2, or two ability scores to increase by 1 each',
    ]);
}

// AFTER:
if (! empty($featureData['grants_asi'])) {
    Modifier::updateOrCreate(
        [
            // Unique key - prevents duplicates
            'reference_type' => get_class($class),
            'reference_id' => $class->id,
            'modifier_category' => 'ability_score',
            'level' => $featureData['level'],
        ],
        [
            // Values to set/update
            'value' => '+2',
            'ability_score_id' => null,
            'is_choice' => true,
            'choice_count' => 2,
            'condition' => 'Choose one ability score to increase by 2, or two ability scores to increase by 1 each',
        ]
    );
}
```

**Test Steps:**
```bash
# 1. Re-import all classes
docker compose exec php php artisan import:all

# 2. Verify no duplicates
docker compose exec php php docs/verify-asi-data.php

# 3. Run tests
docker compose exec php php artisan test --filter=Importer
```

**Success Criteria:**
- ✅ All classes have correct ASI count (5 for most, 7 for Fighter)
- ✅ No duplicates at any level
- ✅ Re-running import doesn't create new duplicates

---

### Phase 2: Root Cause Fix (2 hours) - PARSER REGEX

**Goal:** Stop creating fake "CR 1/2" subclasses

**File:** `app/Services/Parsers/ClassXmlParser.php`

**Change at Line 622-635:**

```php
// BEFORE:
if (preg_match('/\(([^)]+)\)$/', $name, $matches)) {
    $possibleSubclass = trim($matches[1]);
    // Only consider it a subclass if it:
    // 1. Not a common qualifier like "Revised" or "Alternative"
    // 2. Not a number (like "Action Surge (2)")
    // 3. Not a lowercase phrase (like "two uses")
    // 4. Starts with a capital letter (subclass names are proper nouns)
    if (! in_array(strtolower($possibleSubclass), ['revised', 'alternative', 'optional', 'variant'])
        && ! is_numeric($possibleSubclass)
        && preg_match('/^[A-Z]/', $possibleSubclass)
        && ! preg_match('/^\d+/', $possibleSubclass)) {
        $subclassNames[] = $possibleSubclass;
    }
}

// AFTER:
if (preg_match('/\(([^)]+)\)$/', $name, $matches)) {
    $possibleSubclass = trim($matches[1]);

    // Define false positive patterns
    $falsePositivePatterns = [
        '/^CR\s+\d+/',                   // CR 1/2, CR 3, CR 4
        '/^CR\s+\d+\/\d+/',              // CR 1/2 specifically
        '/^\d+\s*\/\s*(rest|day)/',      // 2/rest, 3/day
        '/^\d+(st|nd|rd|th)/',           // 2nd, 3rd, 4th
        '/\buses?\b/i',                  // one use, two uses
        '/^\d+\s+slots?/i',              // 2 slots
        '/^level\s+\d+/i',               // level 5
    ];

    // Check against false positive patterns
    $isFalsePositive = false;
    foreach ($falsePositivePatterns as $pattern) {
        if (preg_match($pattern, $possibleSubclass)) {
            $isFalsePositive = true;
            break;
        }
    }

    // Skip if false positive or in exclusion list
    if ($isFalsePositive
        || in_array(strtolower($possibleSubclass), ['revised', 'alternative', 'optional', 'variant'])
        || is_numeric($possibleSubclass)
        || ! preg_match('/^[A-Z]/', $possibleSubclass)
        || preg_match('/^\d+/', $possibleSubclass)) {
        continue;
    }

    $subclassNames[] = $possibleSubclass;
}
```

**Test Cases to Add:**

Create `tests/Unit/Parsers/ClassXmlParserSubclassDetectionTest.php`:

```php
<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Tests\TestCase;

class ClassXmlParserSubclassDetectionTest extends TestCase
{
    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ClassXmlParser();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_valid_subclass_names()
    {
        $xml = <<<XML
        <class>
            <name>Fighter</name>
            <autolevel level="3">
                <feature><name>Martial Archetype (Battle Master)</name></feature>
            </autolevel>
        </class>
        XML;

        $result = $this->parser->parse($xml);

        $this->assertContains('Battle Master', $result[0]['subclasses'] ?? []);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_ignores_cr_ratings_as_subclasses()
    {
        $xml = <<<XML
        <class>
            <name>Cleric</name>
            <autolevel level="5">
                <feature><name>Destroy Undead (CR 1/2)</name></feature>
            </autolevel>
            <autolevel level="8">
                <feature><name>Destroy Undead (CR 1)</name></feature>
            </autolevel>
        </class>
        XML;

        $result = $this->parser->parse($xml);

        $this->assertEmpty($result[0]['subclasses'] ?? []);
        $this->assertNotContains('CR 1/2', $result[0]['subclasses'] ?? []);
        $this->assertNotContains('CR 1', $result[0]['subclasses'] ?? []);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_ignores_usage_counts_as_subclasses()
    {
        $xml = <<<XML
        <class>
            <name>Warlock</name>
            <autolevel level="2">
                <feature><name>Eldritch Invocations (2 uses)</name></feature>
            </autolevel>
        </class>
        XML;

        $result = $this->parser->parse($xml);

        $this->assertEmpty($result[0]['subclasses'] ?? []);
        $this->assertNotContains('2 uses', $result[0]['subclasses'] ?? []);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_ignores_per_rest_counts_as_subclasses()
    {
        $xml = <<<XML
        <class>
            <name>Monk</name>
            <autolevel level="5">
                <feature><name>Ki (5/rest)</name></feature>
            </autolevel>
        </class>
        XML;

        $result = $this->parser->parse($xml);

        $this->assertEmpty($result[0]['subclasses'] ?? []);
        $this->assertNotContains('5/rest', $result[0]['subclasses'] ?? []);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_ignores_ordinal_numbers_as_subclasses()
    {
        $xml = <<<XML
        <class>
            <name>Monk</name>
            <autolevel level="6">
                <feature><name>Unarmored Movement (3rd)</name></feature>
            </autolevel>
        </class>
        XML;

        $result = $this->parser->parse($xml);

        $this->assertEmpty($result[0]['subclasses'] ?? []);
        $this->assertNotContains('3rd', $result[0]['subclasses'] ?? []);
    }
}
```

**Manual Verification:**
```bash
# 1. Check for fake CR subclasses BEFORE fix
docker compose exec mysql mysql -uroot -ppassword dnd_compendium -e "
  SELECT slug, name, parent_class_id
  FROM classes
  WHERE slug LIKE '%cr-%' OR slug LIKE '%2-rest%';"

# 2. Apply parser fix

# 3. Re-import classes
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php php artisan import:all

# 4. Check NO fake subclasses AFTER fix
docker compose exec mysql mysql -uroot -ppassword dnd_compendium -e "
  SELECT slug, name, parent_class_id
  FROM classes
  WHERE slug LIKE '%cr-%' OR slug LIKE '%2-rest%';"
# Should return 0 rows

# 5. Verify ASI counts correct
docker compose exec php php docs/verify-asi-data.php
```

**Success Criteria:**
- ✅ No fake "CR 1/2", "CR 1", "CR 2" subclasses created
- ✅ Parser tests all pass
- ✅ Feature tests still pass
- ✅ Import logs show no warnings about invalid subclasses

---

### Phase 3: Prevention (1-2 hours) - ARCHITECTURAL SAFEGUARDS

**Goal:** Ensure this never happens again in ANY importer

#### 3A. Create Reusable Trait

**Create:** `app/Services/Importers/Concerns/ImportsModifiers.php`

```php
<?php

namespace App\Services\Importers\Concerns;

use App\Models\Modifier;
use Illuminate\Database\Eloquent\Model;

trait ImportsModifiers
{
    /**
     * Create or update a modifier, preventing duplicates.
     *
     * @param  Model  $entity  The entity (CharacterClass, Race, etc.)
     * @param  string  $category  ability_score, skill, speed, etc.
     * @param  array  $data  Modifier data (value, condition, level, etc.)
     * @return Modifier
     */
    protected function importModifier(Model $entity, string $category, array $data): Modifier
    {
        $uniqueKeys = [
            'reference_type' => get_class($entity),
            'reference_id' => $entity->id,
            'modifier_category' => $category,
        ];

        // Add level to unique key if present (for ASI tracking)
        if (isset($data['level'])) {
            $uniqueKeys['level'] = $data['level'];
        }

        // Add ability/skill/damage type to unique key if present
        if (isset($data['ability_score_id'])) {
            $uniqueKeys['ability_score_id'] = $data['ability_score_id'];
        }
        if (isset($data['skill_id'])) {
            $uniqueKeys['skill_id'] = $data['skill_id'];
        }
        if (isset($data['damage_type_id'])) {
            $uniqueKeys['damage_type_id'] = $data['damage_type_id'];
        }

        return Modifier::updateOrCreate($uniqueKeys, array_merge($data, $uniqueKeys));
    }

    /**
     * Import ASI modifier specifically (common case).
     *
     * @param  Model  $entity
     * @param  int  $level
     * @param  string  $value  Default '+2'
     * @return Modifier
     */
    protected function importAsiModifier(Model $entity, int $level, string $value = '+2'): Modifier
    {
        return $this->importModifier($entity, 'ability_score', [
            'level' => $level,
            'value' => $value,
            'ability_score_id' => null,
            'is_choice' => true,
            'choice_count' => 2,
            'condition' => 'Choose one ability score to increase by 2, or two ability scores to increase by 1 each',
        ]);
    }
}
```

**Update ClassImporter to use trait:**

```php
class ClassImporter extends BaseImporter
{
    use ImportsModifiers;  // Add this

    // Replace line 201:
    if (! empty($featureData['grants_asi'])) {
        $this->importAsiModifier($class, $featureData['level']);
    }
}
```

#### 3B. Add Database Unique Constraint (Optional but Recommended)

**Create Migration:** `database/migrations/2025_11_25_add_unique_constraint_to_entity_modifiers.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove any existing duplicates first
        DB::statement("
            DELETE m1 FROM entity_modifiers m1
            INNER JOIN entity_modifiers m2
            WHERE m1.id > m2.id
              AND m1.reference_type = m2.reference_type
              AND m1.reference_id = m2.reference_id
              AND m1.modifier_category = m2.modifier_category
              AND COALESCE(m1.level, 0) = COALESCE(m2.level, 0)
              AND COALESCE(m1.ability_score_id, 0) = COALESCE(m2.ability_score_id, 0)
              AND COALESCE(m1.skill_id, 0) = COALESCE(m2.skill_id, 0)
              AND COALESCE(m1.damage_type_id, 0) = COALESCE(m2.damage_type_id, 0)
        ");

        Schema::table('entity_modifiers', function (Blueprint $table) {
            $table->unique([
                'reference_type',
                'reference_id',
                'modifier_category',
                'level',
                'ability_score_id',
                'skill_id',
                'damage_type_id',
            ], 'unique_modifier');
        });
    }

    public function down(): void
    {
        Schema::table('entity_modifiers', function (Blueprint $table) {
            $table->dropUnique('unique_modifier');
        });
    }
};
```

**Note:** Unique constraint with nullable columns requires special handling. Consider creating a computed column or using `COALESCE` in the constraint.

#### 3C. Add Model Validation (Defensive Programming)

**Update:** `app/Models/Modifier.php`

```php
protected static function boot()
{
    parent::boot();

    static::creating(function ($modifier) {
        // Check for duplicate ASI modifiers
        if ($modifier->modifier_category === 'ability_score' && $modifier->level) {
            $exists = self::where([
                'reference_type' => $modifier->reference_type,
                'reference_id' => $modifier->reference_id,
                'modifier_category' => 'ability_score',
                'level' => $modifier->level,
            ])->exists();

            if ($exists) {
                \Log::warning("Attempted to create duplicate ASI modifier", [
                    'reference_type' => $modifier->reference_type,
                    'reference_id' => $modifier->reference_id,
                    'level' => $modifier->level,
                ]);

                return false; // Prevent creation
            }
        }
    });
}
```

#### 3D. Add Integration Test

**Create:** `tests/Feature/Importers/ClassImporterDeduplicationTest.php`

```php
<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\Modifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassImporterDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_create_duplicate_asi_modifiers_on_re_import()
    {
        // Import Cleric for first time
        $this->artisan('import:classes', [
            'file' => 'import-files/class-cleric-phb.xml',
        ])->assertExitCode(0);

        $cleric = CharacterClass::where('slug', 'cleric')->first();
        $this->assertNotNull($cleric);

        // Count ASI modifiers after first import
        $firstCount = Modifier::where([
            'reference_type' => get_class($cleric),
            'reference_id' => $cleric->id,
            'modifier_category' => 'ability_score',
        ])->count();

        $this->assertEquals(5, $firstCount, 'Cleric should have exactly 5 ASI modifiers');

        // Re-import same file (simulates re-running import)
        $this->artisan('import:classes', [
            'file' => 'import-files/class-cleric-phb.xml',
        ])->assertExitCode(0);

        // Count ASI modifiers after second import
        $secondCount = Modifier::where([
            'reference_type' => get_class($cleric),
            'reference_id' => $cleric->id,
            'modifier_category' => 'ability_score',
        ])->count();

        $this->assertEquals(5, $secondCount, 'Re-import should not create duplicate ASI modifiers');
        $this->assertEquals($firstCount, $secondCount);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_batch_import_without_duplicates()
    {
        // Import all Cleric files via batch (PHB + XGE + TCE + etc.)
        $files = glob(base_path('import-files/class-cleric-*.xml'));

        foreach ($files as $file) {
            $this->artisan('import:classes', ['file' => $file])
                ->assertExitCode(0);
        }

        $cleric = CharacterClass::where('slug', 'cleric')
            ->whereNull('parent_class_id')
            ->first();

        $asiCount = Modifier::where([
            'reference_type' => get_class($cleric),
            'reference_id' => $cleric->id,
            'modifier_category' => 'ability_score',
        ])->count();

        $this->assertEquals(5, $asiCount, 'Batch import should not create duplicate ASI modifiers');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_create_fake_cr_subclasses()
    {
        $this->artisan('import:classes', [
            'file' => 'import-files/class-cleric-phb.xml',
        ])->assertExitCode(0);

        // Check for fake "CR 1/2", "CR 1", etc. subclasses
        $fakeSubclasses = CharacterClass::where('slug', 'like', '%cr-%')->get();

        $this->assertCount(0, $fakeSubclasses, 'Parser should not create fake CR subclasses');
    }
}
```

---

## 🎯 Testing Strategy

### Before Fix:
```bash
# 1. Document current duplicates
docker compose exec php php docs/verify-asi-data.php > /tmp/before-fix.txt

# 2. Count fake subclasses
docker compose exec mysql mysql -uroot -ppassword dnd_compendium -e "
  SELECT COUNT(*) FROM classes WHERE slug LIKE '%cr-%';" > /tmp/fake-subclasses-before.txt
```

### After Phase 1:
```bash
# 1. Apply importer fix
# 2. Re-import all
docker compose exec php php artisan import:all

# 3. Verify no new duplicates
docker compose exec php php docs/verify-asi-data.php > /tmp/after-phase1.txt
diff /tmp/before-fix.txt /tmp/after-phase1.txt

# 4. Test re-import safety
docker compose exec php php artisan import:classes import-files/class-cleric-phb.xml
docker compose exec php php docs/verify-asi-data.php
# Should still show 5 ASIs for Cleric
```

### After Phase 2:
```bash
# 1. Apply parser fix
# 2. Fresh import
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php php artisan import:all

# 3. Verify no fake subclasses
docker compose exec mysql mysql -uroot -ppassword dnd_compendium -e "
  SELECT COUNT(*) FROM classes WHERE slug LIKE '%cr-%';"
# Should return 0

# 4. Run parser tests
docker compose exec php php artisan test tests/Unit/Parsers/ClassXmlParserSubclassDetectionTest.php
```

### After Phase 3:
```bash
# 1. Run integration tests
docker compose exec php php artisan test tests/Feature/Importers/ClassImporterDeduplicationTest.php

# 2. Run full test suite
docker compose exec php php artisan test
```

---

## 📝 Documentation Updates

### Update CLAUDE.md

Add section under "XML Import System":

```markdown
### Deduplication Strategy

**All importers MUST use `updateOrCreate()` for modifiers to prevent duplicates:**

```php
use App\Services\Importers\Concerns\ImportsModifiers;

class YourImporter extends BaseImporter
{
    use ImportsModifiers;

    // Use trait method:
    $this->importModifier($entity, 'ability_score', [...]);

    // Or for ASI specifically:
    $this->importAsiModifier($entity, $level);
}
```

**Why:** Multiple imports (PHB + XGE + TCE) can trigger re-processing of same data. Using `create()` causes duplicates.
```

### Update CHARACTER-BUILDER-ANALYSIS.md

Add note in "Quick Wins Before Starting":

```markdown
### Task 0: ASI Duplicates - RESOLVED ✅

**Status:** Fixed via parser regex improvement and importer deduplication (2025-11-25)

**Root Cause:** Parser incorrectly identified "Destroy Undead (CR 1/2)" as subclass, combined with importer using `create()` instead of `updateOrCreate()`.

**Fix Applied:**
1. Updated `ClassXmlParser` line 622 to filter false positive subclass patterns
2. Updated `ClassImporter` line 201 to use `updateOrCreate()` for modifiers
3. Added `ImportsModifiers` trait for reusability
4. Added integration tests to prevent regression

**Verification:**
```bash
docker compose exec php php docs/verify-asi-data.php
# All classes should show correct ASI count (no duplicates)
```
```

---

## ✅ Success Criteria Checklist

- [ ] **Phase 1 Complete:**
  - [ ] `ClassImporter::importFeatures()` uses `updateOrCreate()`
  - [ ] Re-import doesn't create duplicates
  - [ ] All existing duplicates cleaned up by natural re-import

- [ ] **Phase 2 Complete:**
  - [ ] Parser regex filters "CR X" patterns
  - [ ] No fake subclasses created
  - [ ] All parser tests pass
  - [ ] Fresh import creates clean data

- [ ] **Phase 3 Complete:**
  - [ ] `ImportsModifiers` trait created and used
  - [ ] Integration tests pass
  - [ ] Optional: Database constraint added
  - [ ] Optional: Model validation added
  - [ ] Documentation updated

- [ ] **Verification Complete:**
  - [ ] `verify-asi-data.php` shows 0 duplicates
  - [ ] All 1,489+ tests still pass
  - [ ] No fake "CR" subclasses in database
  - [ ] Re-running imports is idempotent (no duplicates)

---

## 🚀 Implementation Order

**Recommended sequence:**

1. **Start with Phase 1** (1 hour)
   - Quick win, immediate symptom fix
   - Run `import:all` to clean existing duplicates naturally

2. **Then Phase 2** (2 hours)
   - Root cause fix prevents future issues
   - Add parser tests for confidence

3. **Finally Phase 3** (1-2 hours)
   - Long-term architectural improvements
   - Makes future importers safer

**Total:** 4-6 hours for complete fix + prevention

---

## 📞 Support Commands

**Check current duplicates:**
```bash
docker compose exec php php docs/verify-asi-data.php
```

**Check fake subclasses:**
```bash
docker compose exec mysql mysql -uroot -ppassword dnd_compendium -e "
  SELECT slug, name FROM classes WHERE slug LIKE '%cr-%' OR slug LIKE '%rest%';"
```

**Re-import cleanly:**
```bash
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php php artisan import:all
```

**Run deduplication tests:**
```bash
docker compose exec php php artisan test tests/Feature/Importers/ClassImporterDeduplicationTest.php
```

---

**Status:** Ready for Implementation
**Assignee:** TBD
**Priority:** High (blocks character builder)
**Complexity:** Medium
**Risk:** Low (thoroughly tested approach)

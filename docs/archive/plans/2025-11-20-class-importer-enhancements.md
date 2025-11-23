# Implementation Plan: Class Importer Enhancements

**Date:** 2025-11-20
**Branch:** `feature/class-importer-enhancements` (to be created)
**Estimated Effort:** 6 hours
**Prerequisites:** All existing tests passing (426 tests)

---

## Overview

This plan addresses three confirmed issues with the Class Importer:

1. **Spells Known Counter → Spell Progression Migration** - Move "Spells Known" from counters table to spell progression table
2. **Proficiency Choice Support** - Implement "choose X skills from list" functionality using `numSkills` data
3. **Feature Investigation** - Verify no modifiers/proficiencies exist in feature elements

---

## Scaffolding

### BATCH 0: Environment Setup

**Runner:** Sail (PHP container)

**Tasks:**
```bash
# 1. Ensure on main/feature-entity-prerequisites branch
git status

# 2. Create new branch
git checkout -b feature/class-importer-enhancements

# 3. Verify environment
docker compose exec php php -v  # Should be 8.4
docker compose exec php php artisan --version  # Should be Laravel 12.x

# 4. Fresh database + reimport
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php bash -c 'for file in import-files/class-*.xml; do php artisan import:classes "$file"; done'

# 5. Verify starting point
docker compose exec php php artisan test  # Should be 426+ tests passing
```

**Validation:**
- [ ] Branch created successfully
- [ ] Database fresh with classes imported
- [ ] All tests passing
- [ ] No uncommitted changes

---

## PHASE 1: Investigation - Feature Modifiers/Proficiencies

**Goal:** Confirm whether `<feature>` elements contain `<modifier>` or `<proficiency>` child elements that need parsing.

### BATCH 1.1: Search Class XML Files

**Tasks:**
```bash
# Search for modifiers within feature elements
grep -l "<feature>" import-files/class-*.xml | \
  xargs -I {} sh -c 'echo "=== {} ==="; grep -A 30 "<feature>" {} | grep "<modifier"' > \
  docs/investigation-feature-modifiers.txt

# Search for proficiencies within feature elements
grep -l "<feature>" import-files/class-*.xml | \
  xargs -I {} sh -c 'echo "=== {} ==="; grep -A 30 "<feature>" {} | grep "<proficiency"' > \
  docs/investigation-feature-proficiencies.txt

# Search for random tables within feature text
grep -l "<feature>" import-files/class-*.xml | \
  xargs -I {} sh -c 'echo "=== {} ==="; grep -A 30 "<feature>" {} | grep -E "\|[0-9]"' > \
  docs/investigation-feature-tables.txt
```

**Manual Review:**
- Review `docs/investigation-feature-*.txt` files
- Document findings in `docs/CLASS-IMPORTER-ISSUES-FOUND.md`
- If modifiers/proficiencies found → add to plan
- If not found → mark as closed, remove from scope

**Validation:**
- [ ] Investigation files created
- [ ] Findings documented
- [ ] Scope confirmed (likely: no changes needed)

**Estimated Time:** 30 minutes

---

## PHASE 2: Spells Known Migration

### BATCH 2.1: Add spells_known Column to class_level_progression

**TDD:** Write migration test first

**Test File:** `tests/Feature/Migrations/ClassLevelProgressionSpellsKnownTest.php`

```php
<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassLevelProgressionSpellsKnownTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_level_progression_table_has_spells_known_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('class_level_progression', 'spells_known'),
            'class_level_progression table should have spells_known column'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function spells_known_column_is_nullable(): void
    {
        $columns = Schema::getColumns('class_level_progression');
        $spellsKnownColumn = collect($columns)->firstWhere('name', 'spells_known');

        $this->assertNotNull($spellsKnownColumn, 'spells_known column should exist');
        $this->assertTrue($spellsKnownColumn['nullable'], 'spells_known should be nullable');
    }
}
```

**Implementation:**

Create migration: `database/migrations/2025_11_20_create_spells_known_column.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_level_progression', function (Blueprint $table) {
            $table->unsignedTinyInteger('spells_known')
                ->nullable()
                ->after('spell_slots_9th')
                ->comment('Number of spells known at this level (for limited-known casters)');
        });
    }

    public function down(): void
    {
        Schema::table('class_level_progression', function (Blueprint $table) {
            $table->dropColumn('spells_known');
        });
    }
};
```

**Commands:**
```bash
# Run migration
docker compose exec php php artisan migrate

# Run tests
docker compose exec php php artisan test --filter=ClassLevelProgressionSpellsKnownTest
```

**Validation:**
- [ ] Migration test passes
- [ ] Column exists in database
- [ ] Column is nullable unsigned tinyint

**Commit:**
```bash
git add database/migrations tests/Feature/Migrations
git commit -m "feat: add spells_known column to class_level_progression

- Add nullable spells_known column after spell_slots_9th
- Add migration tests for new column
- Supports limited-known casters like Eldritch Knight

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Estimated Time:** 30 minutes

---

### BATCH 2.2: Update Parser to Extract Spells Known from Counters

**TDD:** Write parser unit test first

**Test File:** `tests/Unit/Parsers/ClassXmlParserSpellsKnownTest.php`

```php
<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Tests\TestCase;

class ClassXmlParserSpellsKnownTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_spells_known_into_spell_progression(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <autolevel level="3">
      <slots optional="YES">2,2</slots>
      <counter>
        <name>Spells Known</name>
        <value>3</value>
      </counter>
    </autolevel>
    <autolevel level="4">
      <slots optional="YES">2,3</slots>
      <counter>
        <name>Spells Known</name>
        <value>4</value>
      </counter>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser();
        $data = $parser->parse($xml);

        $this->assertCount(2, $data[0]['spell_progression']);

        // Level 3
        $this->assertEquals(3, $data[0]['spell_progression'][0]['level']);
        $this->assertEquals(3, $data[0]['spell_progression'][0]['spells_known']);
        $this->assertEquals(2, $data[0]['spell_progression'][0]['cantrips_known']);

        // Level 4
        $this->assertEquals(4, $data[0]['spell_progression'][1]['level']);
        $this->assertEquals(4, $data[0]['spell_progression'][1]['spells_known']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_include_spells_known_in_counters(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <autolevel level="3">
      <counter>
        <name>Spells Known</name>
        <value>3</value>
      </counter>
      <counter>
        <name>Second Wind</name>
        <value>1</value>
        <reset>S</reset>
      </counter>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser();
        $data = $parser->parse($xml);

        // Should only have Second Wind counter
        $this->assertCount(1, $data[0]['counters']);
        $this->assertEquals('Second Wind', $data[0]['counters'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_spells_known_without_slots(): void
    {
        // Some levels might have spells_known counter but no slots element
        // Parser should still create spell_progression entry
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <autolevel level="3">
      <counter>
        <name>Spells Known</name>
        <value>3</value>
      </counter>
    </autolevel>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser();
        $data = $parser->parse($xml);

        $this->assertCount(1, $data[0]['spell_progression']);
        $this->assertEquals(3, $data[0]['spell_progression'][0]['level']);
        $this->assertEquals(3, $data[0]['spell_progression'][0]['spells_known']);
        $this->assertEquals(0, $data[0]['spell_progression'][0]['cantrips_known']);
    }
}
```

**Implementation:**

Update `app/Services/Parsers/ClassXmlParser.php`:

```php
/**
 * Parse spell slots from autolevel elements.
 * Also extracts "Spells Known" from counters and merges into progression.
 *
 * @return array<int, array<string, mixed>>
 */
private function parseSpellSlots(SimpleXMLElement $element): array
{
    $spellProgression = [];
    $spellsKnownByLevel = [];

    // First pass: collect Spells Known from counters
    foreach ($element->autolevel as $autolevel) {
        $level = (int) $autolevel['level'];

        foreach ($autolevel->counter as $counterElement) {
            $name = (string) $counterElement->name;

            if (strtolower($name) === 'spells known') {
                $spellsKnownByLevel[$level] = (int) $counterElement->value;
            }
        }
    }

    // Second pass: build spell progression with slots and spells_known
    foreach ($element->autolevel as $autolevel) {
        $level = (int) $autolevel['level'];

        // Check if this autolevel has spell slots OR spells known
        $hasSlots = isset($autolevel->slots);
        $hasSpellsKnown = isset($spellsKnownByLevel[$level]);

        if ($hasSlots || $hasSpellsKnown) {
            $progression = ['level' => $level];

            // Parse slots if present
            if ($hasSlots) {
                $slotsString = (string) $autolevel->slots;
                $slots = array_map('intval', explode(',', $slotsString));

                $progression['cantrips_known'] = $slots[0] ?? 0;
                $progression['spell_slots_1st'] = $slots[1] ?? 0;
                $progression['spell_slots_2nd'] = $slots[2] ?? 0;
                $progression['spell_slots_3rd'] = $slots[3] ?? 0;
                $progression['spell_slots_4th'] = $slots[4] ?? 0;
                $progression['spell_slots_5th'] = $slots[5] ?? 0;
                $progression['spell_slots_6th'] = $slots[6] ?? 0;
                $progression['spell_slots_7th'] = $slots[7] ?? 0;
                $progression['spell_slots_8th'] = $slots[8] ?? 0;
                $progression['spell_slots_9th'] = $slots[9] ?? 0;
            } else {
                // No slots, but has spells_known - initialize with zeros
                $progression['cantrips_known'] = 0;
                $progression['spell_slots_1st'] = 0;
                $progression['spell_slots_2nd'] = 0;
                $progression['spell_slots_3rd'] = 0;
                $progression['spell_slots_4th'] = 0;
                $progression['spell_slots_5th'] = 0;
                $progression['spell_slots_6th'] = 0;
                $progression['spell_slots_7th'] = 0;
                $progression['spell_slots_8th'] = 0;
                $progression['spell_slots_9th'] = 0;
            }

            // Add spells_known if present
            $progression['spells_known'] = $spellsKnownByLevel[$level] ?? null;

            $spellProgression[] = $progression;
        }
    }

    return $spellProgression;
}

/**
 * Parse counters (Ki, Rage, etc.) from autolevel elements.
 * EXCLUDES "Spells Known" which is handled by parseSpellSlots().
 *
 * @return array<int, array<string, mixed>>
 */
private function parseCounters(SimpleXMLElement $element): array
{
    $counters = [];

    foreach ($element->autolevel as $autolevel) {
        $level = (int) $autolevel['level'];

        foreach ($autolevel->counter as $counterElement) {
            $name = (string) $counterElement->name;

            // Skip "Spells Known" - it's handled by parseSpellSlots()
            if (strtolower($name) === 'spells known') {
                continue;
            }

            $value = (int) $counterElement->value;

            // Parse reset timing
            $resetTiming = null;
            if (isset($counterElement->reset)) {
                $reset = (string) $counterElement->reset;
                $resetTiming = match ($reset) {
                    'S' => 'short_rest',
                    'L' => 'long_rest',
                    default => null,
                };
            }

            // Parse subclass if present
            $subclass = null;
            if (isset($counterElement->subclass)) {
                $subclass = (string) $counterElement->subclass;
            }

            $counters[] = [
                'level' => $level,
                'name' => $name,
                'value' => $value,
                'reset_timing' => $resetTiming,
                'subclass' => $subclass,
            ];
        }
    }

    return $counters;
}
```

**Commands:**
```bash
# Run parser tests
docker compose exec php php artisan test --filter=ClassXmlParserSpellsKnownTest

# Run all parser tests to ensure no regressions
docker compose exec php php artisan test --filter=ClassXmlParserTest
```

**Validation:**
- [ ] New parser tests pass
- [ ] Existing parser tests still pass
- [ ] "Spells Known" excluded from counters array
- [ ] "Spells Known" included in spell_progression array

**Commit:**
```bash
git add app/Services/Parsers tests/Unit/Parsers
git commit -m "feat: parse Spells Known into spell progression instead of counters

- Extract Spells Known from counter elements
- Merge into spell_progression array with spells_known field
- Exclude Spells Known from counters array
- Handle cases where spells_known exists without slots
- Add comprehensive parser tests

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Estimated Time:** 1 hour

---

### BATCH 2.3: Update Importer to Save spells_known

**TDD:** Update importer test

**Test File:** `tests/Feature/Importers/ClassImporterTest.php` (update existing)

Add new test method:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_imports_spells_known_into_spell_progression(): void
{
    $xml = file_get_contents(base_path('import-files/class-fighter-phb.xml'));
    $parser = new ClassXmlParser();
    $importer = new ClassImporter();

    $data = $parser->parse($xml);
    $class = $importer->import($data[0]);

    // Eldritch Knight should have spells_known in progression
    $eldritchKnight = CharacterClass::where('slug', 'fighter-eldritch-knight')->first();
    $this->assertNotNull($eldritchKnight);

    // Check level 3 progression
    $level3 = $eldritchKnight->levelProgression()->where('level', 3)->first();
    $this->assertNotNull($level3);
    $this->assertEquals(3, $level3->spells_known);

    // Check level 4 progression
    $level4 = $eldritchKnight->levelProgression()->where('level', 4)->first();
    $this->assertNotNull($level4);
    $this->assertEquals(4, $level4->spells_known);

    // Verify NO "Spells Known" counter exists
    $spellsKnownCounter = $eldritchKnight->counters()
        ->where('name', 'Spells Known')
        ->count();
    $this->assertEquals(0, $spellsKnownCounter, 'Should not have Spells Known counter');
}
```

**Implementation:**

Update `app/Services/Importers/ClassImporter.php`:

```php
/**
 * Import spell progression for a class.
 */
protected function importSpellProgression(CharacterClass $class, array $spellProgression): void
{
    // Clear existing progression
    $class->levelProgression()->delete();

    // Create new progression entries
    foreach ($spellProgression as $progression) {
        ClassLevelProgression::create([
            'character_class_id' => $class->id,
            'level' => $progression['level'],
            'cantrips_known' => $progression['cantrips_known'],
            'spell_slots_1st' => $progression['spell_slots_1st'],
            'spell_slots_2nd' => $progression['spell_slots_2nd'],
            'spell_slots_3rd' => $progression['spell_slots_3rd'],
            'spell_slots_4th' => $progression['spell_slots_4th'],
            'spell_slots_5th' => $progression['spell_slots_5th'],
            'spell_slots_6th' => $progression['spell_slots_6th'],
            'spell_slots_7th' => $progression['spell_slots_7th'],
            'spell_slots_8th' => $progression['spell_slots_8th'],
            'spell_slots_9th' => $progression['spell_slots_9th'],
            'spells_known' => $progression['spells_known'] ?? null, // NEW
        ]);
    }
}
```

**Commands:**
```bash
# Run importer tests
docker compose exec php php artisan test --filter=ClassImporterTest

# Fresh import to verify
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php php artisan import:classes import-files/class-fighter-phb.xml
```

**Validation:**
- [ ] Importer test passes
- [ ] Eldritch Knight has spells_known in level_progression
- [ ] No "Spells Known" counters in class_counters table

**Commit:**
```bash
git add app/Services/Importers tests/Feature/Importers
git commit -m "feat: import spells_known into spell progression table

- Update ClassImporter to save spells_known field
- Add test verifying spells_known imported correctly
- Verify no Spells Known counters created

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Estimated Time:** 45 minutes

---

### BATCH 2.4: Data Migration - Move Existing Spells Known

**TDD:** Write migration test

**Test File:** `tests/Feature/Migrations/MigrateSpellsKnownDataTest.php`

```php
<?php

namespace Tests\Feature\Migrations;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassLevelProgression;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrateSpellsKnownDataTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_migrates_spells_known_from_counters_to_progression(): void
    {
        // Create a test class with spell progression
        $class = CharacterClass::factory()->create(['slug' => 'test-class']);

        // Create spell progression entries
        $prog3 = ClassLevelProgression::create([
            'character_class_id' => $class->id,
            'level' => 3,
            'cantrips_known' => 2,
            'spell_slots_1st' => 2,
            'spells_known' => null, // Will be populated by migration
        ]);

        $prog4 = ClassLevelProgression::create([
            'character_class_id' => $class->id,
            'level' => 4,
            'cantrips_known' => 2,
            'spell_slots_1st' => 3,
            'spells_known' => null,
        ]);

        // Create old-style Spells Known counters
        ClassCounter::create([
            'character_class_id' => $class->id,
            'level' => 3,
            'name' => 'Spells Known',
            'value' => 3,
        ]);

        ClassCounter::create([
            'character_class_id' => $class->id,
            'level' => 4,
            'name' => 'Spells Known',
            'value' => 4,
        ]);

        // Create a different counter that should NOT be deleted
        ClassCounter::create([
            'character_class_id' => $class->id,
            'level' => 1,
            'name' => 'Second Wind',
            'value' => 1,
            'reset_timing' => 'S',
        ]);

        // Run migration
        Artisan::call('migrate', ['--path' => 'database/migrations/2025_11_20_migrate_spells_known_data.php']);

        // Verify data moved to progression
        $prog3->refresh();
        $prog4->refresh();

        $this->assertEquals(3, $prog3->spells_known);
        $this->assertEquals(4, $prog4->spells_known);

        // Verify Spells Known counters deleted
        $spellsKnownCounters = ClassCounter::where('name', 'Spells Known')->count();
        $this->assertEquals(0, $spellsKnownCounters);

        // Verify other counters preserved
        $secondWindCounter = ClassCounter::where('name', 'Second Wind')->count();
        $this->assertEquals(1, $secondWindCounter);
    }
}
```

**Implementation:**

Create migration: `database/migrations/2025_11_20_migrate_spells_known_data.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Get all "Spells Known" counters
            $counters = DB::table('class_counters')
                ->where('name', 'Spells Known')
                ->get();

            Log::info('Migrating Spells Known counters', ['count' => $counters->count()]);

            foreach ($counters as $counter) {
                // Update corresponding progression entry
                $updated = DB::table('class_level_progression')
                    ->where('character_class_id', $counter->character_class_id)
                    ->where('level', $counter->level)
                    ->update(['spells_known' => $counter->value]);

                if ($updated === 0) {
                    // No progression entry exists for this level - create one
                    DB::table('class_level_progression')->insert([
                        'character_class_id' => $counter->character_class_id,
                        'level' => $counter->level,
                        'cantrips_known' => 0,
                        'spell_slots_1st' => 0,
                        'spell_slots_2nd' => 0,
                        'spell_slots_3rd' => 0,
                        'spell_slots_4th' => 0,
                        'spell_slots_5th' => 0,
                        'spell_slots_6th' => 0,
                        'spell_slots_7th' => 0,
                        'spell_slots_8th' => 0,
                        'spell_slots_9th' => 0,
                        'spells_known' => $counter->value,
                    ]);
                }
            }

            // Delete all "Spells Known" counters
            $deleted = DB::table('class_counters')
                ->where('name', 'Spells Known')
                ->delete();

            Log::info('Spells Known migration complete', [
                'counters_migrated' => $counters->count(),
                'counters_deleted' => $deleted,
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            // Get all progression entries with spells_known
            $progressions = DB::table('class_level_progression')
                ->whereNotNull('spells_known')
                ->get();

            Log::info('Rolling back Spells Known migration', ['count' => $progressions->count()]);

            foreach ($progressions as $progression) {
                // Recreate counter
                DB::table('class_counters')->insert([
                    'character_class_id' => $progression->character_class_id,
                    'level' => $progression->level,
                    'name' => 'Spells Known',
                    'value' => $progression->spells_known,
                    'reset_timing' => null,
                ]);
            }

            // Clear spells_known from progression
            DB::table('class_level_progression')
                ->whereNotNull('spells_known')
                ->update(['spells_known' => null]);

            Log::info('Spells Known rollback complete');
        });
    }
};
```

**Commands:**
```bash
# Run migration test
docker compose exec php php artisan test --filter=MigrateSpellsKnownDataTest

# Run migration on real data
docker compose exec php php artisan migrate

# Verify in tinker
docker compose exec php php artisan tinker --execute="
\$ek = App\Models\CharacterClass::where('slug', 'fighter-eldritch-knight')->first();
echo 'Eldritch Knight Level 3 spells_known: ' . \$ek->levelProgression()->where('level', 3)->first()->spells_known;
echo PHP_EOL;
echo 'Spells Known counters remaining: ' . App\Models\ClassCounter::where('name', 'Spells Known')->count();
"
```

**Validation:**
- [ ] Migration test passes
- [ ] Migration runs successfully on production data
- [ ] All Spells Known counters moved to progression
- [ ] Other counters preserved
- [ ] Migration is reversible

**Commit:**
```bash
git add database/migrations tests/Feature/Migrations
git commit -m "feat: migrate Spells Known from counters to spell progression

- Create data migration to move existing Spells Known counters
- Update class_level_progression with spells_known values
- Delete Spells Known entries from class_counters
- Add rollback support
- Add migration test

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Estimated Time:** 1 hour

---

### BATCH 2.5: Update API Resources

**TDD:** Update API test

**Test File:** `tests/Feature/Api/ClassResourceCompleteTest.php` (update existing)

Add assertion to existing test:

```php
#[\PHPUnit\Framework\Attributes\Test]
public function class_resource_includes_all_new_relationships(): void
{
    // ... existing test setup ...

    // Add assertion for spells_known in level progression
    if ($response['data']['level_progression']) {
        $progression = $response['data']['level_progression'][0];
        $this->assertArrayHasKey('spells_known', $progression);
    }
}
```

**Implementation:**

Update `app/Http/Resources/ClassLevelProgressionResource.php`:

```php
public function toArray(Request $request): array
{
    return [
        'level' => $this->level,
        'cantrips_known' => $this->cantrips_known,
        'spell_slots_1st' => $this->spell_slots_1st,
        'spell_slots_2nd' => $this->spell_slots_2nd,
        'spell_slots_3rd' => $this->spell_slots_3rd,
        'spell_slots_4th' => $this->spell_slots_4th,
        'spell_slots_5th' => $this->spell_slots_5th,
        'spell_slots_6th' => $this->spell_slots_6th,
        'spell_slots_7th' => $this->spell_slots_7th,
        'spell_slots_8th' => $this->spell_slots_8th,
        'spell_slots_9th' => $this->spell_slots_9th,
        'spells_known' => $this->spells_known, // NEW
    ];
}
```

**Commands:**
```bash
# Run API tests
docker compose exec php php artisan test --filter=ClassResourceCompleteTest
docker compose exec php php artisan test --filter=ClassApiTest
```

**Validation:**
- [ ] API test passes
- [ ] spells_known field appears in API response
- [ ] Null values handled gracefully

**Commit:**
```bash
git add app/Http/Resources tests/Feature/Api
git commit -m "feat: expose spells_known in ClassLevelProgressionResource

- Add spells_known field to API response
- Update API tests to verify field presence
- Handle null values gracefully

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Estimated Time:** 15 minutes

---

## PHASE 3: Proficiency Choice Support

### BATCH 3.1: Add Choice Fields to proficiencies Table

**TDD:** Write migration test

**Test File:** `tests/Feature/Migrations/ProficiencyChoiceFieldsTest.php`

```php
<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProficiencyChoiceFieldsTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function proficiencies_table_has_choice_fields(): void
    {
        $this->assertTrue(
            Schema::hasColumn('proficiencies', 'is_choice'),
            'proficiencies table should have is_choice column'
        );

        $this->assertTrue(
            Schema::hasColumn('proficiencies', 'choices_allowed'),
            'proficiencies table should have choices_allowed column'
        );

        $this->assertTrue(
            Schema::hasColumn('proficiencies', 'choice_group'),
            'proficiencies table should have choice_group column'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function is_choice_defaults_to_false(): void
    {
        $columns = Schema::getColumns('proficiencies');
        $isChoiceColumn = collect($columns)->firstWhere('name', 'is_choice');

        $this->assertNotNull($isChoiceColumn);
        $this->assertFalse($isChoiceColumn['nullable']);
        $this->assertEquals('0', $isChoiceColumn['default']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function choices_allowed_is_nullable(): void
    {
        $columns = Schema::getColumns('proficiencies');
        $choicesAllowedColumn = collect($columns)->firstWhere('name', 'choices_allowed');

        $this->assertNotNull($choicesAllowedColumn);
        $this->assertTrue($choicesAllowedColumn['nullable']);
    }
}
```

**Implementation:**

Create migration: `database/migrations/2025_11_20_add_choice_fields_to_proficiencies.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->boolean('is_choice')
                ->default(false)
                ->after('proficiency_subcategory')
                ->comment('Whether this is a choice-based proficiency (choose X from list)');

            $table->unsignedTinyInteger('choices_allowed')
                ->nullable()
                ->after('is_choice')
                ->comment('Number of choices allowed (e.g., "choose 2 skills")');

            $table->string('choice_group', 50)
                ->nullable()
                ->after('choices_allowed')
                ->comment('Groups proficiencies that are part of the same choice set');
        });
    }

    public function down(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->dropColumn(['is_choice', 'choices_allowed', 'choice_group']);
        });
    }
};
```

**Commands:**
```bash
# Run migration
docker compose exec php php artisan migrate

# Run tests
docker compose exec php php artisan test --filter=ProficiencyChoiceFieldsTest
```

**Validation:**
- [ ] Migration test passes
- [ ] Columns exist with correct types
- [ ] is_choice defaults to false
- [ ] choices_allowed is nullable

**Commit:**
```bash
git add database/migrations tests/Feature/Migrations
git commit -m "feat: add choice support fields to proficiencies table

- Add is_choice boolean (default false)
- Add choices_allowed tinyint (nullable)
- Add choice_group string (nullable)
- Support 'choose X from list' proficiency mechanics
- Add migration tests

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Estimated Time:** 30 minutes

---

### BATCH 3.2: Update Parser to Detect numSkills

**TDD:** Write parser test

**Test File:** `tests/Unit/Parsers/ClassXmlParserProficiencyChoicesTest.php`

```php
<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Tests\TestCase;

class ClassXmlParserProficiencyChoicesTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_marks_skills_as_choices_when_num_skills_present(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <proficiency>Strength, Constitution, Acrobatics, Animal Handling, Athletics, History</proficiency>
    <numSkills>2</numSkills>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser();
        $data = $parser->parse($xml);

        $proficiencies = $data[0]['proficiencies'];

        // Should have 2 saving throws + 4 skills
        $this->assertCount(6, $proficiencies);

        // Find skill proficiencies
        $skills = array_filter($proficiencies, fn($p) => $p['type'] === 'skill');
        $this->assertCount(4, $skills);

        // All skills should be marked as choices
        foreach ($skills as $skill) {
            $this->assertTrue($skill['is_choice'], "Skill {$skill['name']} should be marked as choice");
            $this->assertEquals(2, $skill['choices_allowed']);
            $this->assertEquals('initial_skills', $skill['choice_group']);
        }

        // Saving throws should NOT be choices
        $savingThrows = array_filter($proficiencies, fn($p) => $p['type'] === 'saving_throw');
        foreach ($savingThrows as $save) {
            $this->assertFalse($save['is_choice'] ?? false);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_skills_without_num_skills(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Bard</name>
    <hd>8</hd>
    <proficiency>Dexterity, Charisma, Acrobatics, Animal Handling, Arcana</proficiency>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser();
        $data = $parser->parse($xml);

        $skills = array_filter($data[0]['proficiencies'], fn($p) => $p['type'] === 'skill');

        // Without numSkills, skills should NOT be choices
        foreach ($skills as $skill) {
            $this->assertFalse($skill['is_choice'] ?? false);
        }
    }
}
```

**Implementation:**

Update `app/Services/Parsers/ClassXmlParser.php`:

```php
/**
 * Parse a single class element.
 *
 * @return array<string, mixed>
 */
private function parseClass(SimpleXMLElement $element): array
{
    $data = [
        'name' => (string) $element->name,
        'hit_die' => (int) $element->hd,
    ];

    // Parse description from first text element if exists
    if (isset($element->text)) {
        $description = [];
        foreach ($element->text as $text) {
            $description[] = trim((string) $text);
        }
        $data['description'] = implode("\n\n", $description);
    }

    // Parse skill choices (needed for proficiency parsing)
    $skillChoices = null;
    if (isset($element->numSkills)) {
        $skillChoices = (int) $element->numSkills;
    }

    // Parse proficiencies (pass skill choices for marking)
    $data['proficiencies'] = $this->parseProficiencies($element, $skillChoices);

    // ... rest of method unchanged
}

/**
 * Parse proficiencies from class XML.
 *
 * @return array<int, array<string, mixed>>
 */
private function parseProficiencies(SimpleXMLElement $element, ?int $skillChoices = null): array
{
    $proficiencies = [];

    // ... armor, weapon, tool parsing unchanged ...

    // Parse saving throws and skills from <proficiency> element
    if (isset($element->proficiency)) {
        $items = array_map('trim', explode(',', (string) $element->proficiency));
        $abilityScores = ['Strength', 'Dexterity', 'Constitution', 'Intelligence', 'Wisdom', 'Charisma'];

        foreach ($items as $item) {
            if (in_array($item, $abilityScores)) {
                // Saving throw
                $proficiencies[] = [
                    'type' => 'saving_throw',
                    'name' => $item,
                    'proficiency_type_id' => null,
                    'is_choice' => false,
                    'choices_allowed' => null,
                    'choice_group' => null,
                ];
            } else {
                // Skill (possibly choice-based)
                $proficiencyType = $this->matchProficiencyType($item);
                $isChoice = ($skillChoices !== null && $skillChoices > 0);

                $proficiencies[] = [
                    'type' => 'skill',
                    'name' => $item,
                    'proficiency_type_id' => $proficiencyType?->id,
                    'is_choice' => $isChoice,
                    'choices_allowed' => $isChoice ? $skillChoices : null,
                    'choice_group' => $isChoice ? 'initial_skills' : null,
                ];
            }
        }
    }

    return $proficiencies;
}
```

**Commands:**
```bash
# Run parser tests
docker compose exec php php artisan test --filter=ClassXmlParserProficiencyChoicesTest

# Run all parser tests
docker compose exec php php artisan test --filter=ClassXmlParserTest
```

**Validation:**
- [ ] Parser tests pass
- [ ] Skills marked as choices when numSkills present
- [ ] Skills NOT marked as choices when numSkills absent
- [ ] Saving throws never marked as choices

**Commit:**
```bash
git add app/Services/Parsers tests/Unit/Parsers
git commit -m "feat: parse numSkills and mark skill proficiencies as choices

- Extract numSkills from class XML
- Mark skill proficiencies with is_choice, choices_allowed, choice_group
- Saving throws never marked as choices
- Add comprehensive parser tests

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Estimated Time:** 45 minutes

---

### BATCH 3.3: Update Importer to Save Choice Fields

**TDD:** Update importer test

**Test File:** `tests/Feature/Importers/ClassImporterTest.php` (add new test)

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_imports_choice_based_skill_proficiencies(): void
{
    $xml = file_get_contents(base_path('import-files/class-fighter-phb.xml'));
    $parser = new ClassXmlParser();
    $importer = new ClassImporter();

    $data = $parser->parse($xml);
    $class = $importer->import($data[0]);

    // Fighter has 10 skill options, choose 2
    $fighter = CharacterClass::where('slug', 'fighter')->first();

    // Get skill proficiencies
    $skillProfs = $fighter->proficiencies()->where('type', 'skill')->get();

    // Should have ~10 skills (from PHB XML)
    $this->assertGreaterThan(5, $skillProfs->count());

    // All should be marked as choices
    foreach ($skillProfs as $prof) {
        $this->assertTrue((bool) $prof->is_choice, "Skill {$prof->name} should be a choice");
        $this->assertEquals(2, $prof->choices_allowed);
        $this->assertEquals('initial_skills', $prof->choice_group);
    }

    // Verify saving throws are NOT choices
    $savingThrows = $fighter->proficiencies()->where('type', 'saving_throw')->get();
    foreach ($savingThrows as $save) {
        $this->assertFalse((bool) $save->is_choice);
        $this->assertNull($save->choices_allowed);
        $this->assertNull($save->choice_group);
    }
}
```

**Implementation:**

Update `app/Services/Importers/ClassImporter.php`:

```php
/**
 * Import proficiencies for a class.
 */
protected function importProficiencies(Model $entity, array $proficiencies): void
{
    // Clear existing proficiencies
    $entity->proficiencies()->delete();

    // Create new proficiency records
    foreach ($proficiencies as $profData) {
        Proficiency::create([
            'reference_type' => get_class($entity),
            'reference_id' => $entity->id,
            'type' => $profData['type'],
            'name' => $profData['name'],
            'proficiency_type_id' => $profData['proficiency_type_id'] ?? null,
            'is_choice' => $profData['is_choice'] ?? false,
            'choices_allowed' => $profData['choices_allowed'] ?? null,
            'choice_group' => $profData['choice_group'] ?? null,
        ]);
    }
}
```

**Commands:**
```bash
# Run importer tests
docker compose exec php php artisan test --filter=ClassImporterTest

# Fresh import
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php php artisan import:classes import-files/class-fighter-phb.xml

# Verify in tinker
docker compose exec php php artisan tinker --execute="
\$fighter = App\Models\CharacterClass::where('slug', 'fighter')->first();
\$skills = \$fighter->proficiencies()->where('type', 'skill')->get();
echo 'Fighter skills: ' . \$skills->count() . PHP_EOL;
echo 'Choice-based: ' . \$skills->where('is_choice', true)->count() . PHP_EOL;
echo 'Choices allowed: ' . \$skills->first()->choices_allowed . PHP_EOL;
"
```

**Validation:**
- [ ] Importer test passes
- [ ] Fighter skills marked as is_choice=true
- [ ] choices_allowed=2 on all skill proficiencies
- [ ] Saving throws NOT marked as choices

**Commit:**
```bash
git add app/Services/Importers tests/Feature/Importers
git commit -m "feat: import proficiency choice metadata

- Save is_choice, choices_allowed, choice_group to database
- Update ImportsProficiencies trait
- Add comprehensive importer tests
- Verify choice fields populated correctly

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Estimated Time:** 30 minutes

---

### BATCH 3.4: Update API Resources

**TDD:** Update API test

**Test File:** `tests/Feature/Api/ClassApiTest.php` (update existing test)

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_includes_proficiency_choice_metadata_in_response(): void
{
    // Import Fighter which has choice-based skills
    $xml = file_get_contents(base_path('import-files/class-fighter-phb.xml'));
    $parser = new ClassXmlParser();
    $importer = new ClassImporter();
    $data = $parser->parse($xml);
    $importer->import($data[0]);

    $response = $this->getJson('/api/v1/classes/fighter');

    $response->assertStatus(200);

    // Find a skill proficiency in the response
    $proficiencies = $response->json('data.proficiencies');
    $skillProf = collect($proficiencies)->firstWhere('type', 'skill');

    $this->assertNotNull($skillProf);
    $this->assertArrayHasKey('is_choice', $skillProf);
    $this->assertArrayHasKey('choices_allowed', $skillProf);
    $this->assertArrayHasKey('choice_group', $skillProf);

    $this->assertTrue($skillProf['is_choice']);
    $this->assertEquals(2, $skillProf['choices_allowed']);
    $this->assertEquals('initial_skills', $skillProf['choice_group']);
}
```

**Implementation:**

Update `app/Http/Resources/ProficiencyResource.php`:

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'type' => $this->type,
        'name' => $this->name,
        'is_choice' => (bool) $this->is_choice, // NEW
        'choices_allowed' => $this->choices_allowed, // NEW
        'choice_group' => $this->choice_group, // NEW
        'grants' => $this->grants,
        'skill' => new SkillResource($this->whenLoaded('skill')),
        'ability_score' => new AbilityScoreResource($this->whenLoaded('abilityScore')),
        'proficiency_type' => new ProficiencyTypeResource($this->whenLoaded('proficiencyType')),
    ];
}
```

**Commands:**
```bash
# Run API tests
docker compose exec php php artisan test --filter=ClassApiTest
```

**Validation:**
- [ ] API test passes
- [ ] Choice fields appear in API response
- [ ] Boolean/int types correct in JSON

**Commit:**
```bash
git add app/Http/Resources tests/Feature/Api
git commit -m "feat: expose proficiency choice metadata in API

- Add is_choice, choices_allowed, choice_group to ProficiencyResource
- Update API tests to verify choice fields
- Enable frontend to render 'choose 2 skills' UI

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Estimated Time:** 15 minutes

---

## PHASE 4: Final Verification & Documentation

### BATCH 4.1: Full System Test

**Tasks:**
```bash
# 1. Fresh database
docker compose exec php php artisan migrate:fresh --seed

# 2. Reimport ALL classes
docker compose exec php bash -c 'for file in import-files/class-*.xml; do php artisan import:classes "$file"; done'

# 3. Run full test suite
docker compose exec php php artisan test

# 4. Format code
docker compose exec php ./vendor/bin/pint

# 5. Manual API verification
curl "http://localhost:8080/api/v1/classes/fighter-eldritch-knight" | jq '.data.level_progression[0].spells_known'
curl "http://localhost:8080/api/v1/classes/fighter" | jq '.data.proficiencies[] | select(.type=="skill") | {name, is_choice, choices_allowed}'
```

**Validation:**
- [ ] All 426+ tests pass
- [ ] No Spells Known counters in database
- [ ] Eldritch Knight has spells_known in progression
- [ ] Fighter skills marked as choices with choices_allowed=2
- [ ] Code formatted with Pint
- [ ] No PHPStan errors

**Estimated Time:** 30 minutes

---

### BATCH 4.2: Update Documentation

**Files to Update:**

1. **docs/CLASS-IMPORTER-ISSUES-FOUND.md** - Mark issues as resolved

2. **CLAUDE.md** - Update test count, mention new features

3. **Create docs/SESSION-HANDOVER-2025-11-20.md** - Session summary

**Template for Session Handover:**

```markdown
# Session Handover: Class Importer Enhancements - COMPLETE

**Date:** 2025-11-20
**Branch:** `feature/class-importer-enhancements`
**Status:** ✅ Ready for merge
**Duration:** ~6 hours
**Tests Added:** ~15 new tests

## What Was Fixed

### 1. ✅ Spells Known → Spell Progression Migration
- Added `spells_known` column to `class_level_progression` table
- Updated parser to extract "Spells Known" from counters
- Updated importer to save to progression table
- Created data migration to move existing data
- Updated API resource to expose new field
- **Result:** Spells Known now semantically correct in spell progression

### 2. ✅ Proficiency Choice Support
- Added `is_choice`, `choices_allowed`, `choice_group` to `proficiencies` table
- Updated parser to detect `numSkills` and mark skills as choices
- Updated importer to save choice metadata
- Updated API resource to expose choice fields
- **Result:** Frontend can now render "choose 2 skills from list" UI

### 3. ✅ Feature Investigation
- Searched all class XML files for modifiers/proficiencies in features
- **Result:** No `<modifier>` or `<proficiency>` elements found within `<feature>` elements
- **Conclusion:** Not an issue - closed

## Tests Added
- `ClassLevelProgressionSpellsKnownTest` - Migration tests
- `ClassXmlParserSpellsKnownTest` - Parser tests (3 tests)
- `MigrateSpellsKnownDataTest` - Data migration test
- `ProficiencyChoiceFieldsTest` - Migration tests
- `ClassXmlParserProficiencyChoicesTest` - Parser tests (2 tests)
- Updated `ClassImporterTest` - Importer tests (2 new tests)
- Updated `ClassApiTest` - API tests (1 new test)

## Database Changes
- `class_level_progression.spells_known` - Nullable tinyint
- `proficiencies.is_choice` - Boolean, default false
- `proficiencies.choices_allowed` - Nullable tinyint
- `proficiencies.choice_group` - Nullable varchar(50)
- Data migration moved ~150 "Spells Known" counters to progression

## API Changes
- `ClassLevelProgressionResource` now includes `spells_known`
- `ProficiencyResource` now includes `is_choice`, `choices_allowed`, `choice_group`

## Commits (8 total)
1. Add spells_known column migration
2. Update parser for spells_known
3. Update importer for spells_known
4. Create data migration for spells_known
5. Update API resource for spells_known
6. Add proficiency choice fields migration
7. Update parser for proficiency choices
8. Update importer for proficiency choices
9. Update API resource for proficiency choices

## What's Next
- Merge to main/feature-entity-prerequisites
- Consider Monster Importer as next priority
- Or: API enhancements (filtering, aggregations, OpenAPI docs)
```

**Validation:**
- [ ] All documentation updated
- [ ] Session handover created
- [ ] CLAUDE.md reflects current state

**Estimated Time:** 30 minutes

---

### BATCH 4.3: Git Cleanup & Merge Preparation

**Tasks:**
```bash
# 1. Review all commits
git log --oneline feature/class-importer-enhancements

# 2. Verify no uncommitted changes
git status

# 3. Final test run
docker compose exec php php artisan test

# 4. Push branch
git push -u origin feature/class-importer-enhancements

# 5. Prepare for merge (do NOT execute yet - wait for approval)
echo "Branch ready for review and merge"
```

**Validation:**
- [ ] All changes committed
- [ ] Branch pushed to remote
- [ ] Ready for merge

**Estimated Time:** 15 minutes

---

## Total Estimated Time: 6 hours

## Rollback Plan

If issues are discovered:

```bash
# Rollback migrations
docker compose exec php php artisan migrate:rollback --step=3

# Checkout previous branch
git checkout feature/entity-prerequisites

# Fresh database
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php bash -c 'for file in import-files/class-*.xml; do php artisan import:classes "$file"; done'
```

---

## Quality Gates Checklist

Before marking complete:
- [ ] All 426+ existing tests pass
- [ ] All new tests pass (15+ new tests)
- [ ] Code formatted with Pint
- [ ] No PHPStan errors
- [ ] Manual API verification successful
- [ ] Database migration reversible
- [ ] Documentation updated
- [ ] Git history clean
- [ ] Branch pushed to remote

---

**Plan Created:** 2025-11-20
**Ready for Execution:** Yes
**Execution Command:** `/superpowers-laravel:execute-plan`

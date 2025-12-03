# Subclass Spell Lists Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Parse and expose subclass-specific spell lists (domain spells, circle spells, expanded spell lists) from XML, linking them to actual Spell records via entity_spells.

**Architecture:** Parse spell tables from ClassFeature descriptions using existing table detection. Create EntitySpell records with ClassFeature as the polymorphic reference. Add computed `is_always_prepared` accessor based on parent class (Cleric/Druid = true, Warlock = false). Expose via ClassFeatureResource.

**Tech Stack:** Laravel 12, PHPUnit 11, existing ImportsEntitySpells trait, ItemTableDetector/Parser

**GitHub Issue:** #63

---

## Background

### XML Format (all three types use same pipe-delimited format)

**Cleric Domain Spells:**
```
Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds
3rd | lesser restoration, spiritual weapon
5th | beacon of hope, revivify
```

**Druid Circle Spells:**
```
Arctic:
Druid Level | Circle Spells
3rd | hold person, spike growth
5th | sleet storm, slow
```

**Warlock Expanded Spells:**
```
Fiend Expanded Spells:
Spell Level | Spells
1st | burning hands, command
2nd | blindness/deafness, scorching ray
```

### Key Files Reference

- **Model:** `app/Models/ClassFeature.php`
- **Model:** `app/Models/EntitySpell.php`
- **Parser:** `app/Services/Parsers/ClassXmlParser.php`
- **Importer:** `app/Services/Importers/Concerns/ImportsClassFeatures.php`
- **Existing Trait:** `app/Services/Importers/Concerns/ImportsEntitySpells.php`
- **Resource:** `app/Http/Resources/ClassFeatureResource.php`
- **Resource:** `app/Http/Resources/EntitySpellResource.php`

---

## Task 1: Add spells() relationship to ClassFeature model

**Files:**
- Modify: `app/Models/ClassFeature.php`
- Test: `tests/Feature/Models/ClassFeatureSpellsTest.php` (new)

**Step 1: Write the failing test**

Create `tests/Feature/Models/ClassFeatureSpellsTest.php`:

```php
<?php

namespace Tests\Feature\Models;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class ClassFeatureSpellsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function class_feature_can_have_spells(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Cleric']);
        $feature = ClassFeature::factory()->create([
            'class_id' => $class->id,
            'feature_name' => 'Divine Domain: Life Domain',
        ]);
        $spell = Spell::factory()->create(['name' => 'Bless']);

        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $spell->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        $this->assertCount(1, $feature->spells);
        $this->assertEquals('Bless', $feature->spells->first()->name);
    }

    #[Test]
    public function class_feature_spells_include_pivot_data(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Cleric']);
        $feature = ClassFeature::factory()->create([
            'class_id' => $class->id,
            'feature_name' => 'Divine Domain: Life Domain',
        ]);
        $spell = Spell::factory()->create(['name' => 'Lesser Restoration']);

        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $spell->id,
            'level_requirement' => 3,
            'is_cantrip' => false,
        ]);

        $loadedSpell = $feature->spells()->first();
        $this->assertEquals(3, $loadedSpell->pivot->level_requirement);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Feature/Models/ClassFeatureSpellsTest.php --filter=class_feature_can_have_spells`

Expected: FAIL with "Call to undefined method ... spells()"

**Step 3: Write minimal implementation**

Add to `app/Models/ClassFeature.php` after the `specialTags()` method:

```php
/**
 * Spells granted by this feature (domain spells, circle spells, expanded spells).
 *
 * Uses entity_spells polymorphic table. The level_requirement pivot field
 * indicates the class level at which each spell is gained.
 */
public function spells(): MorphToMany
{
    return $this->morphToMany(
        Spell::class,
        'reference',
        'entity_spells',
        'reference_id',
        'spell_id'
    )->withPivot([
        'level_requirement',
        'is_cantrip',
        'usage_limit',
    ]);
}
```

Add the import at the top of the file:
```php
use Illuminate\Database\Eloquent\Relations\MorphToMany;
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test tests/Feature/Models/ClassFeatureSpellsTest.php`

Expected: PASS (both tests)

**Step 5: Commit**

```bash
git add app/Models/ClassFeature.php tests/Feature/Models/ClassFeatureSpellsTest.php
git commit -m "feat: add spells relationship to ClassFeature model (Issue #63)"
```

---

## Task 2: Add is_always_prepared computed accessor

**Files:**
- Modify: `app/Models/ClassFeature.php`
- Modify: `tests/Feature/Models/ClassFeatureSpellsTest.php`

**Step 1: Write the failing test**

Add to `tests/Feature/Models/ClassFeatureSpellsTest.php`:

```php
#[Test]
public function cleric_domain_spells_are_always_prepared(): void
{
    $cleric = CharacterClass::factory()->create(['name' => 'Cleric']);
    $lifeDomain = CharacterClass::factory()->create([
        'name' => 'Life Domain',
        'parent_class_id' => $cleric->id,
    ]);
    $feature = ClassFeature::factory()->create([
        'class_id' => $lifeDomain->id,
        'feature_name' => 'Divine Domain: Life Domain',
    ]);

    $this->assertTrue($feature->is_always_prepared);
}

#[Test]
public function druid_circle_spells_are_always_prepared(): void
{
    $druid = CharacterClass::factory()->create(['name' => 'Druid']);
    $arcticCircle = CharacterClass::factory()->create([
        'name' => 'Circle of the Land (Arctic)',
        'parent_class_id' => $druid->id,
    ]);
    $feature = ClassFeature::factory()->create([
        'class_id' => $arcticCircle->id,
        'feature_name' => 'Circle Spells (Circle of the Land)',
    ]);

    $this->assertTrue($feature->is_always_prepared);
}

#[Test]
public function warlock_expanded_spells_are_not_always_prepared(): void
{
    $warlock = CharacterClass::factory()->create(['name' => 'Warlock']);
    $fiend = CharacterClass::factory()->create([
        'name' => 'The Fiend',
        'parent_class_id' => $warlock->id,
    ]);
    $feature = ClassFeature::factory()->create([
        'class_id' => $fiend->id,
        'feature_name' => 'Expanded Spell List (The Fiend)',
    ]);

    $this->assertFalse($feature->is_always_prepared);
}

#[Test]
public function base_class_features_are_not_always_prepared(): void
{
    $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);
    $feature = ClassFeature::factory()->create([
        'class_id' => $fighter->id,
        'feature_name' => 'Second Wind',
    ]);

    $this->assertFalse($feature->is_always_prepared);
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Feature/Models/ClassFeatureSpellsTest.php --filter=always_prepared`

Expected: FAIL with "Undefined property: ... is_always_prepared"

**Step 3: Write minimal implementation**

Add to `app/Models/ClassFeature.php` in the Accessors section:

```php
/**
 * Check if spells from this feature are always prepared.
 *
 * D&D Context:
 * - Cleric domain spells: Always prepared, don't count against limit
 * - Druid circle spells: Always prepared, don't count against limit
 * - Paladin oath spells: Always prepared, don't count against limit
 * - Warlock expanded spells: Added to spell list options, NOT auto-prepared
 *
 * Determined by the base class (parent of subclass).
 */
public function getIsAlwaysPreparedAttribute(): bool
{
    // Get the base class name (parent of subclass, or self if base class)
    $class = $this->characterClass;
    if (! $class) {
        return false;
    }

    // If this is a subclass, get the parent class name
    $baseClassName = $class->parent_class_id !== null && $class->parentClass
        ? strtolower($class->parentClass->name)
        : strtolower($class->name);

    // These classes have "always prepared" subclass spells
    $alwaysPreparedClasses = ['cleric', 'druid', 'paladin'];

    return in_array($baseClassName, $alwaysPreparedClasses);
}
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test tests/Feature/Models/ClassFeatureSpellsTest.php --filter=always_prepared`

Expected: PASS (all 4 tests)

**Step 5: Commit**

```bash
git add app/Models/ClassFeature.php tests/Feature/Models/ClassFeatureSpellsTest.php
git commit -m "feat: add is_always_prepared accessor to ClassFeature (Issue #63)"
```

---

## Task 3: Create spell table parser trait

**Files:**
- Create: `app/Services/Parsers/Concerns/ParsesSubclassSpellTables.php`
- Test: `tests/Unit/Parsers/ParsesSubclassSpellTablesTest.php` (new)

**Step 1: Write the failing test**

Create `tests/Unit/Parsers/ParsesSubclassSpellTablesTest.php`:

```php
<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\Concerns\ParsesSubclassSpellTables;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit-pure')]
class ParsesSubclassSpellTablesTest extends TestCase
{
    use ParsesSubclassSpellTables;

    #[Test]
    public function parses_cleric_domain_spells(): void
    {
        $text = <<<'TEXT'
Domain Spells:
At each indicated cleric level, add the listed spells to your spells prepared.

Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds
3rd | lesser restoration, spiritual weapon
5th | beacon of hope, revivify
7th | death ward, guardian of faith
9th | mass cure wounds, raise dead

Source: Player's Handbook (2014) p. 60
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertCount(5, $result);

        // Check first level
        $this->assertEquals(1, $result[0]['level']);
        $this->assertEquals(['bless', 'cure wounds'], $result[0]['spells']);

        // Check third level
        $this->assertEquals(3, $result[1]['level']);
        $this->assertEquals(['lesser restoration', 'spiritual weapon'], $result[1]['spells']);

        // Check ninth level
        $this->assertEquals(9, $result[4]['level']);
        $this->assertEquals(['mass cure wounds', 'raise dead'], $result[4]['spells']);
    }

    #[Test]
    public function parses_druid_circle_spells(): void
    {
        $text = <<<'TEXT'
Arctic:
At each indicated level, add the listed spells to your druid spell list.

Arctic:
Druid Level | Circle Spells
3rd | hold person, spike growth
5th | sleet storm, slow
7th | freedom of movement, ice storm
9th | commune with nature, cone of cold

Source: Player's Handbook (2014) p. 68
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertCount(4, $result);

        // Druid circles start at 3rd level
        $this->assertEquals(3, $result[0]['level']);
        $this->assertEquals(['hold person', 'spike growth'], $result[0]['spells']);
    }

    #[Test]
    public function parses_warlock_expanded_spells(): void
    {
        $text = <<<'TEXT'
The Fiend lets you choose from an expanded list of spells when you learn a warlock spell.

Fiend Expanded Spells:
Spell Level | Spells
1st | burning hands, command
2nd | blindness/deafness, scorching ray
3rd | fireball, stinking cloud
4th | fire shield, wall of fire
5th | flame strike, hallow

Source: Player's Handbook (2014) p. 109
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertCount(5, $result);

        // Warlock uses spell level, not class level
        $this->assertEquals(1, $result[0]['level']);
        $this->assertEquals(['burning hands', 'command'], $result[0]['spells']);

        // Check spell with slash in name
        $this->assertEquals(2, $result[1]['level']);
        $this->assertContains('blindness/deafness', $result[1]['spells']);
    }

    #[Test]
    public function returns_null_for_text_without_spell_table(): void
    {
        $text = <<<'TEXT'
At 1st level, you gain proficiency with heavy armor.

Source: Player's Handbook (2014) p. 60
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNull($result);
    }

    #[Test]
    public function handles_three_spells_per_level(): void
    {
        // Some homebrew or variant rules might have 3 spells
        $text = <<<'TEXT'
Custom Domain Spells:
Cleric Level | Spells
1st | spell one, spell two, spell three

Source: Test
TEXT;

        $result = $this->parseSubclassSpellTable($text);

        $this->assertNotNull($result);
        $this->assertEquals(['spell one', 'spell two', 'spell three'], $result[0]['spells']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Unit/Parsers/ParsesSubclassSpellTablesTest.php`

Expected: FAIL with "Trait ... not found"

**Step 3: Write minimal implementation**

Create `app/Services/Parsers/Concerns/ParsesSubclassSpellTables.php`:

```php
<?php

namespace App\Services\Parsers\Concerns;

/**
 * Trait for parsing subclass spell tables from feature descriptions.
 *
 * Handles three formats:
 * - Cleric domain spells: "Cleric Level | Spells"
 * - Druid circle spells: "Druid Level | Circle Spells"
 * - Warlock expanded spells: "Spell Level | Spells"
 */
trait ParsesSubclassSpellTables
{
    /**
     * Parse a subclass spell table from feature description text.
     *
     * @param  string  $text  The feature description text
     * @return array<int, array{level: int, spells: array<string>}>|null Parsed spell data or null if no table found
     */
    protected function parseSubclassSpellTable(string $text): ?array
    {
        // Pattern matches:
        // "Cleric Level | Spells"
        // "Druid Level | Circle Spells"
        // "Spell Level | Spells"
        // Followed by rows like "1st | bless, cure wounds"
        $tablePattern = '/(?:Cleric Level|Druid Level|Spell Level)\s*\|\s*(?:Circle )?Spells\s*\n((?:\d+(?:st|nd|rd|th)\s*\|[^\n]+\n?)+)/i';

        if (! preg_match($tablePattern, $text, $matches)) {
            return null;
        }

        $tableRows = $matches[1];
        $result = [];

        // Parse each row: "1st | bless, cure wounds"
        $rowPattern = '/(\d+)(?:st|nd|rd|th)\s*\|\s*(.+)/i';

        if (preg_match_all($rowPattern, $tableRows, $rowMatches, PREG_SET_ORDER)) {
            foreach ($rowMatches as $row) {
                $level = (int) $row[1];
                $spellsText = trim($row[2]);

                // Split spells by comma, handling potential "and" before last spell
                $spells = array_map(
                    fn ($s) => trim(preg_replace('/^and\s+/i', '', $s)),
                    preg_split('/\s*,\s*/', $spellsText)
                );

                // Filter empty strings
                $spells = array_values(array_filter($spells, fn ($s) => ! empty($s)));

                $result[] = [
                    'level' => $level,
                    'spells' => $spells,
                ];
            }
        }

        return ! empty($result) ? $result : null;
    }

    /**
     * Check if text contains a subclass spell table.
     */
    protected function hasSubclassSpellTable(string $text): bool
    {
        return preg_match('/(?:Cleric Level|Druid Level|Spell Level)\s*\|\s*(?:Circle )?Spells/i', $text) === 1;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test tests/Unit/Parsers/ParsesSubclassSpellTablesTest.php`

Expected: PASS (all 5 tests)

**Step 5: Commit**

```bash
git add app/Services/Parsers/Concerns/ParsesSubclassSpellTables.php tests/Unit/Parsers/ParsesSubclassSpellTablesTest.php
git commit -m "feat: add ParsesSubclassSpellTables trait for parsing domain/circle/expanded spells (Issue #63)"
```

---

## Task 4: Create subclass spell importer trait

**Files:**
- Create: `app/Services/Importers/Concerns/ImportsSubclassSpells.php`
- Test: `tests/Unit/Importers/Concerns/ImportsSubclassSpellsTest.php` (new)

**Step 1: Write the failing test**

Create `tests/Unit/Importers/Concerns/ImportsSubclassSpellsTest.php`:

```php
<?php

namespace Tests\Unit\Importers\Concerns;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use App\Services\Importers\Concerns\ImportsSubclassSpells;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class ImportsSubclassSpellsTest extends TestCase
{
    use ImportsSubclassSpells;
    use RefreshDatabase;

    private CharacterClass $cleric;
    private CharacterClass $lifeDomain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cleric = CharacterClass::factory()->create(['name' => 'Cleric']);
        $this->lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'parent_class_id' => $this->cleric->id,
        ]);
    }

    #[Test]
    public function imports_domain_spells_for_feature(): void
    {
        // Create spells that will be referenced
        Spell::factory()->create(['name' => 'Bless']);
        Spell::factory()->create(['name' => 'Cure Wounds']);

        $feature = ClassFeature::factory()->create([
            'class_id' => $this->lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
            'description' => <<<'TEXT'
Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds

Source: Player's Handbook (2014) p. 60
TEXT,
        ]);

        $this->importSubclassSpells($feature, $feature->description);

        $this->assertCount(2, EntitySpell::where('reference_type', ClassFeature::class)->get());

        $blessSpell = EntitySpell::whereHas('spell', fn ($q) => $q->where('name', 'Bless'))->first();
        $this->assertNotNull($blessSpell);
        $this->assertEquals(1, $blessSpell->level_requirement);
        $this->assertEquals($feature->id, $blessSpell->reference_id);
    }

    #[Test]
    public function imports_multiple_spell_levels(): void
    {
        Spell::factory()->create(['name' => 'Bless']);
        Spell::factory()->create(['name' => 'Cure Wounds']);
        Spell::factory()->create(['name' => 'Lesser Restoration']);
        Spell::factory()->create(['name' => 'Spiritual Weapon']);

        $feature = ClassFeature::factory()->create([
            'class_id' => $this->lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
            'description' => <<<'TEXT'
Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds
3rd | lesser restoration, spiritual weapon

Source: Player's Handbook (2014) p. 60
TEXT,
        ]);

        $this->importSubclassSpells($feature, $feature->description);

        $this->assertCount(4, EntitySpell::where('reference_type', ClassFeature::class)->get());

        // Check level 1 spells
        $blessSpell = EntitySpell::whereHas('spell', fn ($q) => $q->where('name', 'Bless'))->first();
        $this->assertEquals(1, $blessSpell->level_requirement);

        // Check level 3 spells
        $lesserRestoration = EntitySpell::whereHas('spell', fn ($q) => $q->where('name', 'Lesser Restoration'))->first();
        $this->assertEquals(3, $lesserRestoration->level_requirement);
    }

    #[Test]
    public function skips_spells_not_found_in_database(): void
    {
        // Only create one of the spells
        Spell::factory()->create(['name' => 'Bless']);
        // Don't create 'Cure Wounds'

        $feature = ClassFeature::factory()->create([
            'class_id' => $this->lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
            'description' => <<<'TEXT'
Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds

Source: Player's Handbook (2014) p. 60
TEXT,
        ]);

        $this->importSubclassSpells($feature, $feature->description);

        // Only Bless should be imported
        $this->assertCount(1, EntitySpell::where('reference_type', ClassFeature::class)->get());
    }

    #[Test]
    public function clears_existing_spells_before_import(): void
    {
        $spell = Spell::factory()->create(['name' => 'Old Spell']);
        $feature = ClassFeature::factory()->create([
            'class_id' => $this->lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
        ]);

        // Create existing spell association
        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $spell->id,
            'level_requirement' => 1,
        ]);

        // Now import with new description (no spell table)
        $this->importSubclassSpells($feature, 'No spell table here');

        // Old spell should be cleared
        $this->assertCount(0, EntitySpell::where('reference_type', ClassFeature::class)->get());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Unit/Importers/Concerns/ImportsSubclassSpellsTest.php`

Expected: FAIL with "Trait ... not found"

**Step 3: Write minimal implementation**

Create `app/Services/Importers/Concerns/ImportsSubclassSpells.php`:

```php
<?php

namespace App\Services\Importers\Concerns;

use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use App\Services\Parsers\Concerns\ParsesSubclassSpellTables;
use Illuminate\Support\Facades\Log;

/**
 * Trait for importing subclass spell associations (domain, circle, expanded spells).
 *
 * Creates EntitySpell records linking ClassFeature → Spell with level_requirement.
 */
trait ImportsSubclassSpells
{
    use ParsesSubclassSpellTables;

    /**
     * Import subclass spells from a feature's description text.
     *
     * Parses spell tables and creates entity_spells records for each spell.
     *
     * @param  ClassFeature  $feature  The subclass feature (e.g., "Divine Domain: Life Domain")
     * @param  string  $description  The feature description containing the spell table
     */
    protected function importSubclassSpells(ClassFeature $feature, string $description): void
    {
        // Clear existing spell associations for this feature
        EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->delete();

        // Parse spell table from description
        $spellData = $this->parseSubclassSpellTable($description);

        if ($spellData === null) {
            return;
        }

        foreach ($spellData as $levelData) {
            $classLevel = $levelData['level'];

            foreach ($levelData['spells'] as $spellName) {
                $this->createSpellAssociation($feature, $spellName, $classLevel);
            }
        }
    }

    /**
     * Create a spell association for a feature.
     */
    private function createSpellAssociation(ClassFeature $feature, string $spellName, int $classLevel): void
    {
        // Look up spell by name (case-insensitive)
        $spell = Spell::whereRaw('LOWER(name) = ?', [strtolower($spellName)])->first();

        if (! $spell) {
            Log::warning("Subclass spell not found: {$spellName} (for feature: {$feature->feature_name})");

            return;
        }

        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $spell->id,
            'level_requirement' => $classLevel,
            'is_cantrip' => $spell->level === 0,
        ]);
    }
}
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test tests/Unit/Importers/Concerns/ImportsSubclassSpellsTest.php`

Expected: PASS (all 4 tests)

**Step 5: Commit**

```bash
git add app/Services/Importers/Concerns/ImportsSubclassSpells.php tests/Unit/Importers/Concerns/ImportsSubclassSpellsTest.php
git commit -m "feat: add ImportsSubclassSpells trait for domain/circle/expanded spell import (Issue #63)"
```

---

## Task 5: Integrate into ImportsClassFeatures trait

**Files:**
- Modify: `app/Services/Importers/Concerns/ImportsClassFeatures.php`
- Test: `tests/Feature/Importers/ClassImporterSubclassSpellsTest.php` (new)

**Step 1: Write the failing test**

Create `tests/Feature/Importers/ClassImporterSubclassSpellsTest.php`:

```php
<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use App\Services\Importers\ClassImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('importers')]
class ClassImporterSubclassSpellsTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    private ClassImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = app(ClassImporter::class);

        // Create spells that will be referenced
        Spell::factory()->create(['name' => 'Bless', 'level' => 1]);
        Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);
        Spell::factory()->create(['name' => 'Lesser Restoration', 'level' => 2]);
        Spell::factory()->create(['name' => 'Spiritual Weapon', 'level' => 2]);
    }

    #[Test]
    public function imports_cleric_domain_spells(): void
    {
        $xml = $this->getClericDomainXml();

        $this->importer->import($xml);

        // Find the Life Domain subclass
        $lifeDomain = CharacterClass::where('name', 'Life Domain')->first();
        $this->assertNotNull($lifeDomain);

        // Find the domain feature
        $domainFeature = ClassFeature::where('class_id', $lifeDomain->id)
            ->where('feature_name', 'like', '%Life Domain%')
            ->first();
        $this->assertNotNull($domainFeature);

        // Check spell associations
        $spells = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $domainFeature->id)
            ->get();

        $this->assertGreaterThanOrEqual(2, $spells->count());

        // Check specific spell
        $blessSpell = $spells->first(fn ($s) => $s->spell->name === 'Bless');
        $this->assertNotNull($blessSpell);
        $this->assertEquals(1, $blessSpell->level_requirement);
    }

    private function getClericDomainXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Cleric</name>
    <hd>8</hd>
    <proficiency>Wisdom, Charisma</proficiency>
    <spellAbility>Wisdom</spellAbility>
    <autolevel level="1">
      <slots>3,2</slots>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Divine Domain: Life Domain</name>
        <text>The Life domain focuses on the vibrant positive energy.

Domain Spells:
At each indicated cleric level, add the listed spells to your spells prepared.

Life Domain Spells:
Cleric Level | Spells
1st | bless, cure wounds
3rd | lesser restoration, spiritual weapon

Source: Player's Handbook (2014) p. 60</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Feature/Importers/ClassImporterSubclassSpellsTest.php`

Expected: FAIL (spells not imported yet)

**Step 3: Write minimal implementation**

Modify `app/Services/Importers/Concerns/ImportsClassFeatures.php`:

1. Add the use statement at the top (with other use statements):

```php
use App\Services\Importers\Concerns\ImportsSubclassSpells;
```

2. Add the trait usage in the trait declaration:

```php
trait ImportsClassFeatures
{
    use ImportsSubclassSpells;
```

3. Add the spell import call in `importFeatures()` method, after the data tables import (around line 100):

```php
            // Import data tables from pipe-delimited tables in description text
            // This handles BOTH dice-based random tables AND reference tables (dice_type = null)
            $this->importDataTablesFromText($feature, $featureData['description'], clearExisting: false);

            // Import subclass spell tables (domain spells, circle spells, expanded spells)
            if ($this->hasSubclassSpellTable($featureData['description'])) {
                $this->importSubclassSpells($feature, $featureData['description']);
            }
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test tests/Feature/Importers/ClassImporterSubclassSpellsTest.php`

Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Importers/Concerns/ImportsClassFeatures.php tests/Feature/Importers/ClassImporterSubclassSpellsTest.php
git commit -m "feat: integrate subclass spell import into ClassImporter (Issue #63)"
```

---

## Task 6: Expose spells in ClassFeatureResource

**Files:**
- Modify: `app/Http/Resources/ClassFeatureResource.php`
- Test: `tests/Feature/Api/ClassFeatureSpellsApiTest.php` (new)

**Step 1: Write the failing test**

Create `tests/Feature/Api/ClassFeatureSpellsApiTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class ClassFeatureSpellsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function class_api_includes_feature_spells(): void
    {
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric']);
        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'parent_class_id' => $cleric->id,
        ]);
        $feature = ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
            'level' => 1,
        ]);

        $bless = Spell::factory()->create(['name' => 'Bless', 'level' => 1]);
        $cureWounds = Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);

        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $bless->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);
        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $cureWounds->id,
            'level_requirement' => 1,
            'is_cantrip' => false,
        ]);

        $response = $this->getJson("/api/v1/classes/{$lifeDomain->slug}");

        $response->assertStatus(200);

        // Find the feature in response
        $features = $response->json('data.features');
        $domainFeature = collect($features)->firstWhere('feature_name', 'Divine Domain: Life Domain');

        $this->assertNotNull($domainFeature);
        $this->assertArrayHasKey('spells', $domainFeature);
        $this->assertCount(2, $domainFeature['spells']);
        $this->assertArrayHasKey('is_always_prepared', $domainFeature);
        $this->assertTrue($domainFeature['is_always_prepared']);
    }

    #[Test]
    public function feature_spells_include_level_requirement(): void
    {
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric']);
        $lifeDomain = CharacterClass::factory()->create([
            'name' => 'Life Domain',
            'parent_class_id' => $cleric->id,
        ]);
        $feature = ClassFeature::factory()->create([
            'class_id' => $lifeDomain->id,
            'feature_name' => 'Divine Domain: Life Domain',
            'level' => 1,
        ]);

        $lesserRestoration = Spell::factory()->create(['name' => 'Lesser Restoration', 'level' => 2]);

        EntitySpell::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'spell_id' => $lesserRestoration->id,
            'level_requirement' => 3,
            'is_cantrip' => false,
        ]);

        $response = $this->getJson("/api/v1/classes/{$lifeDomain->slug}");

        $response->assertStatus(200);

        $features = $response->json('data.features');
        $domainFeature = collect($features)->firstWhere('feature_name', 'Divine Domain: Life Domain');
        $spell = $domainFeature['spells'][0];

        $this->assertEquals(3, $spell['level_requirement']);
        $this->assertEquals('Lesser Restoration', $spell['spell']['name']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test tests/Feature/Api/ClassFeatureSpellsApiTest.php`

Expected: FAIL (spells not included in response)

**Step 3: Write minimal implementation**

Modify `app/Http/Resources/ClassFeatureResource.php`:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassFeatureResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level,
            'feature_name' => $this->feature_name,
            'description' => $this->description,
            'is_optional' => $this->is_optional,
            'is_multiclass_only' => $this->is_multiclass_only,
            'is_choice_option' => $this->is_choice_option,
            'is_always_prepared' => $this->is_always_prepared,
            'parent_feature_id' => $this->parent_feature_id,
            'sort_order' => $this->sort_order,

            // Relationships
            'data_tables' => EntityDataTableResource::collection(
                $this->whenLoaded('dataTables')
            ),

            // Subclass spells (domain, circle, expanded)
            'spells' => $this->whenLoaded('spells', function () {
                return $this->spells->map(fn ($spell) => [
                    'spell' => new SpellResource($spell),
                    'level_requirement' => $spell->pivot->level_requirement,
                    'is_cantrip' => $spell->pivot->is_cantrip,
                ]);
            }),

            // Nested child features (choice options)
            'choice_options' => ClassFeatureResource::collection(
                $this->whenLoaded('childFeatures')
            ),
        ];
    }
}
```

**Step 4: Update ClassResource to eager-load spells**

Modify `app/Http/Resources/ClassResource.php` - find where features are loaded and add spells:

In the `toArray` method, update the features relationship to include spells eager loading. Find the ClassController and update the `show` method to include the spells relationship.

Modify `app/Http/Controllers/Api/ClassController.php` in the `show` method:

Find the line that loads features and update it to include spells:

```php
'features.spells',
```

This should be added to the `with` array wherever features are loaded.

**Step 5: Run test to verify it passes**

Run: `docker compose exec php php artisan test tests/Feature/Api/ClassFeatureSpellsApiTest.php`

Expected: PASS

**Step 6: Commit**

```bash
git add app/Http/Resources/ClassFeatureResource.php app/Http/Controllers/Api/ClassController.php tests/Feature/Api/ClassFeatureSpellsApiTest.php
git commit -m "feat: expose subclass spells in ClassFeatureResource API (Issue #63)"
```

---

## Task 7: Run full test suite and verify

**Files:**
- No new files

**Step 1: Run Unit-Pure suite**

Run: `docker compose exec php php artisan test --testsuite=Unit-Pure`

Expected: All tests pass

**Step 2: Run Unit-DB suite**

Run: `docker compose exec php php artisan test --testsuite=Unit-DB`

Expected: All tests pass

**Step 3: Run Feature-DB suite**

Run: `docker compose exec php php artisan test --testsuite=Feature-DB`

Expected: All tests pass

**Step 4: Run Importers suite**

Run: `docker compose exec php php artisan test --testsuite=Importers`

Expected: All tests pass

**Step 5: Format code**

Run: `docker compose exec php ./vendor/bin/pint`

**Step 6: Final commit**

```bash
git add -A
git commit -m "chore: format code with Pint"
```

---

## Task 8: Update CHANGELOG and close issue

**Files:**
- Modify: `CHANGELOG.md`

**Step 1: Update CHANGELOG**

Add under `[Unreleased]`:

```markdown
### Added
- Subclass spell lists (domain spells, circle spells, expanded spell lists) now parsed and exposed via API (Issue #63)
- `ClassFeature.spells()` relationship for accessing subclass-granted spells
- `ClassFeature.is_always_prepared` computed accessor for determining spell preparation rules
- `ParsesSubclassSpellTables` trait for parsing spell tables from feature descriptions
- `ImportsSubclassSpells` trait for creating EntitySpell records from parsed data
```

**Step 2: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: update CHANGELOG for subclass spell lists feature (Issue #63)"
```

**Step 3: Push and close issue**

```bash
git push origin main
gh issue close 63 --repo dfox288/dnd-rulebook-project --comment "Implemented in commits. Subclass spell lists (domain, circle, expanded) are now parsed from XML and exposed via the Classes API with spell references and level_requirement data."
```

---

## Summary

| Task | Description | Est. Time |
|------|-------------|-----------|
| 1 | Add `spells()` relationship to ClassFeature | 5 min |
| 2 | Add `is_always_prepared` accessor | 5 min |
| 3 | Create spell table parser trait | 10 min |
| 4 | Create spell importer trait | 10 min |
| 5 | Integrate into ImportsClassFeatures | 5 min |
| 6 | Expose in ClassFeatureResource | 10 min |
| 7 | Run full test suite | 5 min |
| 8 | Update CHANGELOG and close issue | 3 min |

**Total:** ~53 minutes

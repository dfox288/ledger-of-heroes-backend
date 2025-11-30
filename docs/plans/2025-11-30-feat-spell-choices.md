# Feat Spell Choices Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable `entity_spells` to store spell choices with constraints (school, class, level, ritual) for feats like Shadow Touched, Magic Initiate, and Ritual Caster.

**Architecture:** Extend `entity_spells` table with choice fields using `choice_group` pattern consistent with `entity_items`. Parser detects choice patterns from description text and outputs structured data. Importer creates multiple rows per choice group when multiple schools allowed.

**Tech Stack:** Laravel 12.x, PHPUnit 11, MySQL/SQLite, Meilisearch

**Issue:** [#64 - Traits: parser does not extract all data](https://github.com/dfox288/dnd-rulebook-project/issues/64)

---

## Task 1: Migration - Add Spell Choice Columns

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_spell_choice_support_to_entity_spells_table.php`

**Step 1: Create migration**

```bash
docker compose exec php php artisan make:migration add_spell_choice_support_to_entity_spells_table
```

**Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First, make spell_id nullable for choice rows
        Schema::table('entity_spells', function (Blueprint $table) {
            $table->unsignedBigInteger('spell_id')->nullable()->change();
        });

        Schema::table('entity_spells', function (Blueprint $table) {
            // Choice support
            $table->boolean('is_choice')->default(false)->after('is_cantrip');
            $table->unsignedTinyInteger('choice_count')->nullable()->after('is_choice')
                ->comment('Number of spells player picks from this pool');
            $table->string('choice_group')->nullable()->after('choice_count')
                ->comment('Groups rows representing same choice (e.g., "spell_choice_1")');

            // Constraints for spell choices
            $table->unsignedTinyInteger('max_level')->nullable()->after('choice_group')
                ->comment('0=cantrip, 1-9=max spell level for choice');
            $table->unsignedBigInteger('school_id')->nullable()->after('max_level');
            $table->unsignedBigInteger('class_id')->nullable()->after('school_id');
            $table->boolean('is_ritual_only')->default(false)->after('class_id');

            // Foreign keys
            $table->foreign('school_id')->references('id')->on('spell_schools')->onDelete('set null');
            $table->foreign('class_id')->references('id')->on('character_classes')->onDelete('set null');

            // Indexes
            $table->index('is_choice');
            $table->index('choice_group');
        });
    }

    public function down(): void
    {
        Schema::table('entity_spells', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['class_id']);
            $table->dropIndex(['is_choice']);
            $table->dropIndex(['choice_group']);
            $table->dropColumn([
                'is_choice',
                'choice_count',
                'choice_group',
                'max_level',
                'school_id',
                'class_id',
                'is_ritual_only',
            ]);
        });

        Schema::table('entity_spells', function (Blueprint $table) {
            $table->unsignedBigInteger('spell_id')->nullable(false)->change();
        });
    }
};
```

**Step 3: Run migration**

```bash
docker compose exec php php artisan migrate
```

Expected: Migration completes successfully.

**Step 4: Commit**

```bash
git add database/migrations/*add_spell_choice_support*
git commit -m "feat: add spell choice support columns to entity_spells table

Adds is_choice, choice_count, choice_group, max_level, school_id,
class_id, and is_ritual_only columns for storing spell choices
with constraints (Issue #64)"
```

---

## Task 2: Update EntitySpell Model

**Files:**
- Modify: `app/Models/EntitySpell.php`

**Step 1: Read existing model**

Review `app/Models/EntitySpell.php` to understand current structure.

**Step 2: Update fillable, casts, and add relationships**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EntitySpell extends BaseModel
{
    protected $fillable = [
        'reference_type',
        'reference_id',
        'spell_id',
        'ability_score_id',
        'level_requirement',
        'usage_limit',
        'is_cantrip',
        // Charge costs (for items)
        'charges_cost_min',
        'charges_cost_max',
        'charges_cost_formula',
        // Choice support
        'is_choice',
        'choice_count',
        'choice_group',
        'max_level',
        'school_id',
        'class_id',
        'is_ritual_only',
    ];

    protected $casts = [
        'level_requirement' => 'integer',
        'is_cantrip' => 'boolean',
        'charges_cost_min' => 'integer',
        'charges_cost_max' => 'integer',
        'is_choice' => 'boolean',
        'choice_count' => 'integer',
        'max_level' => 'integer',
        'is_ritual_only' => 'boolean',
    ];

    public function reference(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'reference_type', 'reference_id');
    }

    public function spell(): BelongsTo
    {
        return $this->belongsTo(Spell::class);
    }

    public function abilityScore(): BelongsTo
    {
        return $this->belongsTo(AbilityScore::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(SpellSchool::class, 'school_id');
    }

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }
}
```

**Step 3: Verify model loads**

```bash
docker compose exec php php artisan tinker --execute="new App\Models\EntitySpell;"
```

Expected: No errors.

**Step 4: Commit**

```bash
git add app/Models/EntitySpell.php
git commit -m "feat: update EntitySpell model with choice fields and relationships

Adds fillable fields, casts, and school()/characterClass() relationships
for spell choice support (Issue #64)"
```

---

## Task 3: Parser Tests - School-Constrained Choices

**Files:**
- Modify: `tests/Unit/Parsers/FeatXmlParserTest.php`

**Step 1: Write failing test for Shadow Touched spell choice**

Add to `tests/Unit/Parsers/FeatXmlParserTest.php`:

```php
#[Test]
public function it_parses_school_constrained_spell_choice()
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Shadow Touched (Charisma)</name>
        <text>Your exposure to the Shadowfell's magic has changed you, granting you the following benefits:

	• Increase your Intelligence, Wisdom, or Charisma score by 1, to a maximum of 20.

	• You learn the invisibility spell and one 1st-level spell of your choice. The 1st-level spell must be from the illusion or necromancy school of magic. You can cast each of these spells without expending a spell slot. Once you cast either of these spells in this way, you can't cast that spell in this way again until you finish a long rest. You can also cast these spells using spell slots you have of the appropriate level. The spells' spellcasting ability is the ability increased by this feat.

Source:	Tasha's Cauldron of Everything p. 80</text>
        <modifier category="ability score">charisma +1</modifier>
    </feat>
</compendium>
XML;

    $feats = $this->parser->parse($xml);

    $this->assertCount(1, $feats);
    $this->assertArrayHasKey('spells', $feats[0]);

    // Should have fixed spell (Invisibility) + spell choice
    $spells = $feats[0]['spells'];
    $this->assertGreaterThanOrEqual(2, count($spells));

    // Find the fixed spell
    $fixedSpells = array_filter($spells, fn($s) => isset($s['spell_name']));
    $this->assertNotEmpty($fixedSpells);
    $fixedSpell = array_values($fixedSpells)[0];
    $this->assertEquals('Invisibility', $fixedSpell['spell_name']);

    // Find the choice spell(s)
    $choiceSpells = array_filter($spells, fn($s) => isset($s['is_choice']) && $s['is_choice'] === true);
    $this->assertNotEmpty($choiceSpells);

    $choice = array_values($choiceSpells)[0];
    $this->assertTrue($choice['is_choice']);
    $this->assertEquals(1, $choice['choice_count']);
    $this->assertEquals(1, $choice['max_level']);
    $this->assertContains('illusion', $choice['schools']);
    $this->assertContains('necromancy', $choice['schools']);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=it_parses_school_constrained_spell_choice
```

Expected: FAIL - choice data not yet parsed.

**Step 3: Commit failing test**

```bash
git add tests/Unit/Parsers/FeatXmlParserTest.php
git commit -m "test: add failing test for school-constrained spell choices

Tests Shadow Touched pattern: 'one 1st-level spell from illusion or
necromancy school' (Issue #64)"
```

---

## Task 4: Parser Tests - Class-Constrained Choices

**Files:**
- Modify: `tests/Unit/Parsers/FeatXmlParserTest.php`

**Step 1: Write failing test for Magic Initiate**

```php
#[Test]
public function it_parses_class_constrained_spell_choices()
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Magic Initiate (Bard)</name>
        <text>You learn two bard cantrips of your choice.
	In addition, choose one 1st-level bard spell. You learn that spell and can cast it at its lowest level. Once you cast it, you must finish a long rest before you can cast it again using this feat.
	Your spellcasting ability for these spells is Charisma.

Source:	Player's Handbook (2014) p. 168</text>
    </feat>
</compendium>
XML;

    $feats = $this->parser->parse($xml);

    $this->assertCount(1, $feats);
    $this->assertArrayHasKey('spells', $feats[0]);

    $spells = $feats[0]['spells'];
    $choiceSpells = array_filter($spells, fn($s) => isset($s['is_choice']) && $s['is_choice'] === true);
    $this->assertCount(2, $choiceSpells); // cantrips + 1st-level spell

    $choiceSpells = array_values($choiceSpells);

    // First choice: 2 cantrips
    $cantripsChoice = array_filter($choiceSpells, fn($s) => $s['max_level'] === 0);
    $this->assertNotEmpty($cantripsChoice);
    $cantripsChoice = array_values($cantripsChoice)[0];
    $this->assertEquals(2, $cantripsChoice['choice_count']);
    $this->assertEquals('bard', strtolower($cantripsChoice['class_name']));

    // Second choice: 1 first-level spell
    $spellChoice = array_filter($choiceSpells, fn($s) => $s['max_level'] === 1);
    $this->assertNotEmpty($spellChoice);
    $spellChoice = array_values($spellChoice)[0];
    $this->assertEquals(1, $spellChoice['choice_count']);
    $this->assertEquals('bard', strtolower($spellChoice['class_name']));
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=it_parses_class_constrained_spell_choices
```

Expected: FAIL.

**Step 3: Commit failing test**

```bash
git add tests/Unit/Parsers/FeatXmlParserTest.php
git commit -m "test: add failing test for class-constrained spell choices

Tests Magic Initiate pattern: 'two bard cantrips' and 'one 1st-level
bard spell' (Issue #64)"
```

---

## Task 5: Parser Tests - Ritual-Constrained Choices

**Files:**
- Modify: `tests/Unit/Parsers/FeatXmlParserTest.php`

**Step 1: Write failing test for Ritual Caster**

```php
#[Test]
public function it_parses_ritual_constrained_spell_choices()
{
    $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Ritual Caster (Bard)</name>
        <prerequisite>Intelligence or Wisdom 13 or higher</prerequisite>
        <text>You have learned a number of spells that you can cast as rituals. These spells are written in a ritual book, which you must have in hand while casting one of them.
	When you choose this feat, you acquire a ritual book holding two 1st-level bard spells of your choice. The spells you choose must have the ritual tag. Charisma is your spellcasting ability for these spells.
	If you come across a spell in written form, such as a magical spell scroll or a wizard's spellbook, you might be able to add it to your ritual book. The spell must be on the bard spell list, the spell's level can be no higher than half your level (rounded up), and it must have the ritual tag. The process of copying the spell into your ritual book takes 2 hours per level of the spell, and costs 50 gp per level. The cost represents material components you expend as you experiment with the spell to master it, as well as the fine inks you need to record it.

Source:	Player's Handbook (2014) p. 169</text>
    </feat>
</compendium>
XML;

    $feats = $this->parser->parse($xml);

    $this->assertCount(1, $feats);
    $this->assertArrayHasKey('spells', $feats[0]);

    $spells = $feats[0]['spells'];
    $choiceSpells = array_filter($spells, fn($s) => isset($s['is_choice']) && $s['is_choice'] === true);
    $this->assertNotEmpty($choiceSpells);

    $choice = array_values($choiceSpells)[0];
    $this->assertTrue($choice['is_choice']);
    $this->assertEquals(2, $choice['choice_count']);
    $this->assertEquals(1, $choice['max_level']);
    $this->assertEquals('bard', strtolower($choice['class_name']));
    $this->assertTrue($choice['is_ritual_only']);
}
```

**Step 2: Run test to verify it fails**

```bash
docker compose exec php php artisan test --filter=it_parses_ritual_constrained_spell_choices
```

Expected: FAIL.

**Step 3: Commit failing test**

```bash
git add tests/Unit/Parsers/FeatXmlParserTest.php
git commit -m "test: add failing test for ritual-constrained spell choices

Tests Ritual Caster pattern: 'two 1st-level bard spells with ritual
tag' (Issue #64)"
```

---

## Task 6: Implement Parser - Spell Choice Detection

**Files:**
- Modify: `app/Services/Parsers/FeatXmlParser.php`

**Step 1: Enhance parseSpells() method**

Replace the `parseSpells()` method in `app/Services/Parsers/FeatXmlParser.php`:

```php
/**
 * Parse spells granted by a feat from description text.
 *
 * Handles both fixed spells ("You learn the misty step spell") and
 * spell choices ("one 1st-level spell of your choice from illusion or necromancy").
 *
 * @return array<int, array<string, mixed>>
 */
private function parseSpells(string $text): array
{
    $spells = [];
    $choiceGroupCounter = 1;

    // 1. Parse fixed spells: "You learn the {spell name} spell"
    if (preg_match_all('/you learn the ([a-z][a-z\s\']+?) spell/i', $text, $matches)) {
        foreach ($matches[1] as $spellName) {
            $spellName = trim($spellName);
            $spellName = ucwords(strtolower($spellName));

            // Skip generic patterns
            if (preg_match('/^\d+(st|nd|rd|th)-level/i', $spellName) ||
                stripos($spellName, 'cantrip') !== false ||
                stripos($spellName, 'of your choice') !== false) {
                continue;
            }

            $spells[] = [
                'spell_name' => $spellName,
                'pivot_data' => [
                    'is_cantrip' => false,
                    'usage_limit' => $this->detectUsageLimit($text),
                ],
            ];
        }
    }

    // 2. Parse school-constrained spell choices
    // Pattern: "one 1st-level spell of your choice" + "must be from the X or Y school"
    if (preg_match('/(?:one|two|three)\s+(\d+)(?:st|nd|rd|th)-level spell(?:s)? of your choice/i', $text, $levelMatch)) {
        if (preg_match('/must be from the ([a-z]+)(?: or ([a-z]+))? school/i', $text, $schoolMatch)) {
            $count = $this->wordToNumber(strtolower(preg_match('/^(one|two|three)/i', $text, $countMatch) ? $countMatch[1] : 'one'));
            $schools = array_filter([strtolower($schoolMatch[1]), isset($schoolMatch[2]) ? strtolower($schoolMatch[2]) : null]);

            $spells[] = [
                'is_choice' => true,
                'choice_count' => $count,
                'choice_group' => 'spell_choice_' . $choiceGroupCounter++,
                'max_level' => (int) $levelMatch[1],
                'schools' => $schools,
                'class_name' => null,
                'is_ritual_only' => false,
            ];
        }
    }

    // 3. Parse class-constrained cantrip choices
    // Pattern: "You learn two bard cantrips of your choice"
    if (preg_match('/(one|two|three|four)\s+([a-z]+)\s+cantrips?\s+of your choice/i', $text, $cantripMatch)) {
        $count = $this->wordToNumber(strtolower($cantripMatch[1]));
        $className = strtolower($cantripMatch[2]);

        $spells[] = [
            'is_choice' => true,
            'choice_count' => $count,
            'choice_group' => 'spell_choice_' . $choiceGroupCounter++,
            'max_level' => 0, // 0 = cantrip
            'schools' => [],
            'class_name' => $className,
            'is_ritual_only' => false,
        ];
    }

    // 4. Parse class-constrained spell choices
    // Pattern: "choose one 1st-level bard spell" or "one 1st-level bard spell"
    if (preg_match('/(?:choose\s+)?(one|two|three)\s+(\d+)(?:st|nd|rd|th)-level\s+([a-z]+)\s+spell/i', $text, $classSpellMatch)) {
        // Don't duplicate if already captured by school pattern
        $hasSchoolConstraint = preg_match('/must be from the [a-z]+ school/i', $text);
        if (! $hasSchoolConstraint) {
            $count = $this->wordToNumber(strtolower($classSpellMatch[1]));
            $level = (int) $classSpellMatch[2];
            $className = strtolower($classSpellMatch[3]);

            // Check for ritual constraint
            $isRitualOnly = (bool) preg_match('/must have the ritual tag/i', $text);

            $spells[] = [
                'is_choice' => true,
                'choice_count' => $count,
                'choice_group' => 'spell_choice_' . $choiceGroupCounter++,
                'max_level' => $level,
                'schools' => [],
                'class_name' => $className,
                'is_ritual_only' => $isRitualOnly,
            ];
        }
    }

    return $spells;
}
```

**Step 2: Run all spell choice tests**

```bash
docker compose exec php php artisan test --filter="spell_choice"
```

Expected: All 3 new tests PASS.

**Step 3: Run existing spell tests to ensure no regression**

```bash
docker compose exec php php artisan test --filter="FeatXmlParserTest"
```

Expected: All tests PASS.

**Step 4: Commit**

```bash
git add app/Services/Parsers/FeatXmlParser.php
git commit -m "feat: implement spell choice parsing in FeatXmlParser

Detects school-constrained, class-constrained, and ritual-constrained
spell choices from feat description text (Issue #64)"
```

---

## Task 7: Update ImportsEntitySpells Trait

**Files:**
- Modify: `app/Services/Importers/Concerns/ImportsEntitySpells.php`

**Step 1: Update trait to handle choice data**

```php
<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait for importing spell associations for polymorphic entities.
 *
 * Handles both fixed spells and spell choices with constraints.
 */
trait ImportsEntitySpells
{
    /**
     * Import spell associations for a polymorphic entity.
     *
     * @param  Model  $entity  The entity (Item, Race, Feat, etc.)
     * @param  array  $spellsData  Array of spell associations (fixed or choice)
     */
    protected function importEntitySpells(Model $entity, array $spellsData): void
    {
        // Clear existing spell associations
        DB::table('entity_spells')
            ->where('reference_type', get_class($entity))
            ->where('reference_id', $entity->id)
            ->delete();

        foreach ($spellsData as $spellData) {
            if (isset($spellData['is_choice']) && $spellData['is_choice']) {
                $this->importSpellChoice($entity, $spellData);
            } else {
                $this->importFixedSpell($entity, $spellData);
            }
        }
    }

    /**
     * Import a fixed spell (existing behavior).
     */
    private function importFixedSpell(Model $entity, array $spellData): void
    {
        $spell = Spell::whereRaw('LOWER(name) = ?', [strtolower($spellData['spell_name'])])
            ->first();

        if (! $spell) {
            Log::warning("Spell not found: {$spellData['spell_name']} (for {$entity->name})");

            return;
        }

        DB::table('entity_spells')->insert([
            'reference_type' => get_class($entity),
            'reference_id' => $entity->id,
            'spell_id' => $spell->id,
            'is_choice' => false,
            'is_cantrip' => $spellData['pivot_data']['is_cantrip'] ?? false,
            'usage_limit' => $spellData['pivot_data']['usage_limit'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Import a spell choice with constraints.
     *
     * Creates one row per allowed school (if school-constrained),
     * or a single row (if class-constrained).
     */
    private function importSpellChoice(Model $entity, array $choiceData): void
    {
        $schools = $choiceData['schools'] ?? [];
        $className = $choiceData['class_name'] ?? null;

        // Look up class_id if class-constrained
        $classId = null;
        if ($className) {
            $characterClass = CharacterClass::whereRaw('LOWER(name) = ?', [$className])->first();
            if ($characterClass) {
                $classId = $characterClass->id;
            } else {
                Log::warning("CharacterClass not found: {$className} (for {$entity->name})");
            }
        }

        // School-constrained: create one row per school
        if (! empty($schools)) {
            foreach ($schools as $schoolName) {
                $school = SpellSchool::whereRaw('LOWER(name) = ?', [$schoolName])->first();
                if (! $school) {
                    Log::warning("SpellSchool not found: {$schoolName} (for {$entity->name})");

                    continue;
                }

                DB::table('entity_spells')->insert([
                    'reference_type' => get_class($entity),
                    'reference_id' => $entity->id,
                    'spell_id' => null,
                    'is_choice' => true,
                    'choice_count' => $choiceData['choice_count'],
                    'choice_group' => $choiceData['choice_group'],
                    'max_level' => $choiceData['max_level'],
                    'school_id' => $school->id,
                    'class_id' => $classId,
                    'is_ritual_only' => $choiceData['is_ritual_only'] ?? false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            // Class-constrained only (no school constraint): single row
            DB::table('entity_spells')->insert([
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'spell_id' => null,
                'is_choice' => true,
                'choice_count' => $choiceData['choice_count'],
                'choice_group' => $choiceData['choice_group'],
                'max_level' => $choiceData['max_level'],
                'school_id' => null,
                'class_id' => $classId,
                'is_ritual_only' => $choiceData['is_ritual_only'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
```

**Step 2: Verify import works**

```bash
docker compose exec php php artisan tinker --execute="
\$parser = new App\Services\Parsers\FeatXmlParser();
\$xml = file_get_contents('/var/www/fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/02_Supplements/Tashas_Cauldron_of_Everything/feats-tce.xml');
\$feats = \$parser->parse(\$xml);
\$shadowTouched = collect(\$feats)->firstWhere('name', 'Shadow Touched (Charisma)');
print_r(\$shadowTouched['spells']);
"
```

Expected: Shows fixed spell (Invisibility) + choice data with schools.

**Step 3: Commit**

```bash
git add app/Services/Importers/Concerns/ImportsEntitySpells.php
git commit -m "feat: update ImportsEntitySpells to handle spell choices

Creates multiple rows for school-constrained choices, single row for
class-constrained choices. Supports is_ritual_only constraint (Issue #64)"
```

---

## Task 8: Update EntitySpellResource

**Files:**
- Modify: `app/Http/Resources/EntitySpellResource.php`

**Step 1: Add choice fields to resource**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntitySpellResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'spell_id' => $this->spell_id,
            'spell' => new SpellResource($this->whenLoaded('spell')),
            'ability_score_id' => $this->ability_score_id,
            'ability_score' => new AbilityScoreResource($this->whenLoaded('abilityScore')),
            'level_requirement' => $this->level_requirement,
            'usage_limit' => $this->usage_limit,
            'is_cantrip' => $this->is_cantrip,

            // Charge costs (for items that cast spells)
            'charges_cost_min' => $this->charges_cost_min,
            'charges_cost_max' => $this->charges_cost_max,
            'charges_cost_formula' => $this->charges_cost_formula,

            // Choice support
            'is_choice' => $this->is_choice,
            'choice_count' => $this->when($this->is_choice, $this->choice_count),
            'choice_group' => $this->when($this->is_choice, $this->choice_group),
            'max_level' => $this->when($this->is_choice, $this->max_level),
            'school' => new SpellSchoolResource($this->whenLoaded('school')),
            'character_class' => new CharacterClassResource($this->whenLoaded('characterClass')),
            'is_ritual_only' => $this->when($this->is_choice, $this->is_ritual_only),
        ];
    }
}
```

**Step 2: Create CharacterClassResource if not exists**

Check if `app/Http/Resources/CharacterClassResource.php` exists. If not, create it:

```bash
docker compose exec php php artisan make:resource CharacterClassResource
```

Then update it:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterClassResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Resources/EntitySpellResource.php app/Http/Resources/CharacterClassResource.php
git commit -m "feat: update EntitySpellResource with choice fields

Adds is_choice, choice_count, choice_group, max_level, school,
character_class, and is_ritual_only fields (Issue #64)"
```

---

## Task 9: Update FeatResource for Spell Choices

**Files:**
- Modify: `app/Http/Resources/FeatResource.php`

**Step 1: Add spell_choices grouping to FeatResource**

The current `FeatResource` already has `'spells' => EntitySpellResource::collection($this->whenLoaded('spells'))`. We can keep this and let the frontend handle the grouping, OR we can add a computed `spell_choices` field.

For cleaner API consumption, add a computed accessor:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FeatResource extends JsonResource
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
            'slug' => $this->slug,
            'name' => $this->name,
            'prerequisites_text' => $this->prerequisites_text,
            'prerequisites' => EntityPrerequisiteResource::collection($this->whenLoaded('prerequisites')),
            'description' => $this->description,

            // Computed fields
            'is_half_feat' => $this->is_half_feat,
            'parent_feat_slug' => $this->parent_feat_slug,

            // Relationships
            'modifiers' => ModifierResource::collection($this->whenLoaded('modifiers')),
            'proficiencies' => ProficiencyResource::collection($this->whenLoaded('proficiencies')),
            'conditions' => EntityConditionResource::collection($this->whenLoaded('conditions')),
            'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'spells' => EntitySpellResource::collection($this->whenLoaded('spells')),

            // Computed: grouped spell choices for easier frontend consumption
            'spell_choices' => $this->when(
                $this->relationLoaded('spells'),
                fn () => $this->getGroupedSpellChoices()
            ),
        ];
    }

    /**
     * Group spell choices by choice_group for frontend consumption.
     *
     * @return array<string, array<string, mixed>>|null
     */
    private function getGroupedSpellChoices(): ?array
    {
        $choices = $this->spells->where('is_choice', true);

        if ($choices->isEmpty()) {
            return null;
        }

        return $choices->groupBy('choice_group')->map(function ($group, $groupName) {
            $first = $group->first();

            return [
                'choice_group' => $groupName,
                'choice_count' => $first->choice_count,
                'max_level' => $first->max_level,
                'is_ritual_only' => $first->is_ritual_only,
                'allowed_schools' => $group
                    ->filter(fn ($s) => $s->school_id !== null)
                    ->map(fn ($s) => [
                        'id' => $s->school_id,
                        'name' => $s->school?->name,
                    ])
                    ->values()
                    ->all(),
                'allowed_class' => $first->class_id ? [
                    'id' => $first->class_id,
                    'name' => $first->characterClass?->name,
                ] : null,
            ];
        })->values()->all();
    }
}
```

**Step 2: Update Feat model to eager load school and characterClass**

Update `app/Models/Feat.php` to include in `searchableWith()`:

```php
public function searchableWith(): array
{
    return ['sources.source', 'tags', 'prerequisites.prerequisite', 'modifiers.abilityScore', 'proficiencies', 'spells.school', 'spells.characterClass'];
}
```

**Step 3: Commit**

```bash
git add app/Http/Resources/FeatResource.php app/Models/Feat.php
git commit -m "feat: add spell_choices grouping to FeatResource

Groups spell choices by choice_group with allowed_schools and
allowed_class for easier frontend consumption (Issue #64)"
```

---

## Task 10: Re-import Feats and Verify

**Step 1: Run feat import**

```bash
docker compose exec php php artisan import:feats
```

Expected: Feats import successfully.

**Step 2: Verify Shadow Touched data**

```bash
docker compose exec php php artisan tinker --execute="
\$feat = App\Models\Feat::where('slug', 'shadow-touched-charisma')
    ->with(['spells.school', 'spells.characterClass'])
    ->first();
echo 'Feat: ' . \$feat->name . \"\n\";
echo 'Spells: ' . \$feat->spells->count() . \"\n\";
foreach (\$feat->spells as \$s) {
    if (\$s->is_choice) {
        echo \"  Choice: group={\$s->choice_group} count={\$s->choice_count} level={\$s->max_level} school=\" . (\$s->school?->name ?? 'null') . \"\n\";
    } else {
        echo \"  Fixed: \" . \$s->spell?->name . \"\n\";
    }
}
"
```

Expected output:
```
Feat: Shadow Touched (Charisma)
Spells: 3
  Fixed: Invisibility
  Choice: group=spell_choice_1 count=1 level=1 school=Illusion
  Choice: group=spell_choice_1 count=1 level=1 school=Necromancy
```

**Step 3: Verify API response**

```bash
curl -s "http://localhost:8080/api/v1/feats?filter=slug%20%3D%20shadow-touched-charisma&include=spells" | jq '.data[0] | {name, spells, spell_choices}'
```

Expected: Shows `spells` array with fixed + choice entries, and `spell_choices` with grouped data.

**Step 4: Commit verification**

```bash
git add -A
git commit -m "chore: verify feat spell choice import

Shadow Touched and Magic Initiate feats now have properly structured
spell choices in entity_spells table (Issue #64)"
```

---

## Task 11: Feature Test - API Response

**Files:**
- Create: `tests/Feature/Api/FeatSpellChoicesApiTest.php`

**Step 1: Write API feature test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class FeatSpellChoicesApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_spell_choices_for_feat_with_school_constraint()
    {
        // Create test data
        $illusion = SpellSchool::factory()->create(['name' => 'Illusion']);
        $necromancy = SpellSchool::factory()->create(['name' => 'Necromancy']);
        $invisibility = Spell::factory()->create(['name' => 'Invisibility']);

        $feat = Feat::factory()->create([
            'name' => 'Shadow Touched (Charisma)',
            'slug' => 'shadow-touched-charisma',
        ]);

        // Fixed spell
        $feat->spells()->create([
            'spell_id' => $invisibility->id,
            'is_choice' => false,
            'usage_limit' => 'long_rest',
        ]);

        // Spell choices (school-constrained)
        $feat->spells()->create([
            'spell_id' => null,
            'is_choice' => true,
            'choice_count' => 1,
            'choice_group' => 'spell_choice_1',
            'max_level' => 1,
            'school_id' => $illusion->id,
        ]);
        $feat->spells()->create([
            'spell_id' => null,
            'is_choice' => true,
            'choice_count' => 1,
            'choice_group' => 'spell_choice_1',
            'max_level' => 1,
            'school_id' => $necromancy->id,
        ]);

        $response = $this->getJson('/api/v1/feats/' . $feat->slug . '?include=spells');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Shadow Touched (Charisma)')
            ->assertJsonCount(3, 'data.spells')
            ->assertJsonPath('data.spell_choices.0.choice_group', 'spell_choice_1')
            ->assertJsonPath('data.spell_choices.0.choice_count', 1)
            ->assertJsonPath('data.spell_choices.0.max_level', 1)
            ->assertJsonCount(2, 'data.spell_choices.0.allowed_schools');
    }

    #[Test]
    public function it_returns_spell_choices_for_feat_with_class_constraint()
    {
        $bard = CharacterClass::factory()->create(['name' => 'Bard']);

        $feat = Feat::factory()->create([
            'name' => 'Magic Initiate (Bard)',
            'slug' => 'magic-initiate-bard',
        ]);

        // Cantrip choice
        $feat->spells()->create([
            'spell_id' => null,
            'is_choice' => true,
            'choice_count' => 2,
            'choice_group' => 'spell_choice_1',
            'max_level' => 0,
            'class_id' => $bard->id,
        ]);

        // 1st-level spell choice
        $feat->spells()->create([
            'spell_id' => null,
            'is_choice' => true,
            'choice_count' => 1,
            'choice_group' => 'spell_choice_2',
            'max_level' => 1,
            'class_id' => $bard->id,
        ]);

        $response = $this->getJson('/api/v1/feats/' . $feat->slug . '?include=spells');

        $response->assertOk()
            ->assertJsonCount(2, 'data.spells')
            ->assertJsonCount(2, 'data.spell_choices')
            ->assertJsonPath('data.spell_choices.0.allowed_class.name', 'Bard');
    }
}
```

**Step 2: Run feature test**

```bash
docker compose exec php php artisan test --testsuite=Feature-DB --filter=FeatSpellChoicesApiTest
```

Expected: Tests PASS.

**Step 3: Commit**

```bash
git add tests/Feature/Api/FeatSpellChoicesApiTest.php
git commit -m "test: add feature tests for feat spell choices API

Verifies API returns school-constrained and class-constrained spell
choices correctly (Issue #64)"
```

---

## Task 12: Run Full Test Suite and Final Commit

**Step 1: Run Unit-Pure suite**

```bash
docker compose exec php php artisan test --testsuite=Unit-Pure
```

Expected: All tests PASS.

**Step 2: Run Unit-DB suite**

```bash
docker compose exec php php artisan test --testsuite=Unit-DB
```

Expected: All tests PASS.

**Step 3: Run Feature-DB suite**

```bash
docker compose exec php php artisan test --testsuite=Feature-DB
```

Expected: All tests PASS.

**Step 4: Run Pint**

```bash
docker compose exec php ./vendor/bin/pint
```

**Step 5: Update CHANGELOG.md**

Add under `[Unreleased]`:

```markdown
### Added
- Spell choice support for feats (Issue #64)
  - `entity_spells` table extended with `is_choice`, `choice_count`, `choice_group`, `max_level`, `school_id`, `class_id`, `is_ritual_only`
  - Parser detects school-constrained (Shadow/Fey Touched), class-constrained (Magic Initiate), and ritual-constrained (Ritual Caster) patterns
  - API returns grouped `spell_choices` for frontend consumption
```

**Step 6: Final commit**

```bash
git add -A
git commit -m "feat: complete feat spell choice support (Issue #64)

- Migration adds choice columns to entity_spells
- EntitySpell model updated with relationships
- FeatXmlParser detects choice patterns
- ImportsEntitySpells handles choice data
- FeatResource groups choices for API
- Full test coverage added

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

**Step 7: Push**

```bash
git push origin main
```

**Step 8: Close GitHub issue**

```bash
gh issue comment 64 --repo dfox288/dnd-rulebook-project --body "Spell choices component of this issue has been completed.

### Implemented:
- Schema extended with choice fields
- Parser detects Shadow Touched, Fey Touched, Magic Initiate, Ritual Caster patterns
- API returns grouped spell choices

### Remaining (separate issues):
- Passive score modifiers (Observant)
- Skill-linked advantages (Actor)

Closing spell choices portion. Separate issues can track the other components."
```

---

## Summary

| Task | Description | Files |
|------|-------------|-------|
| 1 | Migration | `database/migrations/*add_spell_choice_support*` |
| 2 | Model | `app/Models/EntitySpell.php` |
| 3-5 | Parser tests | `tests/Unit/Parsers/FeatXmlParserTest.php` |
| 6 | Parser impl | `app/Services/Parsers/FeatXmlParser.php` |
| 7 | Importer | `app/Services/Importers/Concerns/ImportsEntitySpells.php` |
| 8 | EntitySpellResource | `app/Http/Resources/EntitySpellResource.php` |
| 9 | FeatResource | `app/Http/Resources/FeatResource.php` |
| 10 | Re-import & verify | - |
| 11 | Feature tests | `tests/Feature/Api/FeatSpellChoicesApiTest.php` |
| 12 | Full suite & commit | - |

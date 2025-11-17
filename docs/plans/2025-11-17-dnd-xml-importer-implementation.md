# D&D 5e XML Importer Implementation Plan (v2 - Aligned with Design)

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a complete Laravel-based command-line tool that parses D&D 5e XML files and imports them into a relational database following the **approved database design document** exactly.

**Architecture:** Laravel application with Artisan commands for importing, XML parsing service classes for each content type, database migrations matching the approved schema design (no timestamps, comprehensive columns), and comprehensive test coverage using TDD.

**Tech Stack:** Laravel 11.x, PHP 8.4, MySQL 8.0, Docker & Docker Compose, Nginx, PHP-FPM, PHPUnit for testing, Symfony Console for CLI, SimpleXML for XML parsing

**Design Document Reference:** `docs/plans/2025-11-17-dnd-compendium-database-design.md`

---

## Critical Alignment Notes

This plan implements the **approved database design** with these key principles:

1. ✅ **NO timestamps** - Static compendium data doesn't need `created_at`/`updated_at`
2. ✅ **NO soft deletes** - Hard deletes only
3. ✅ Table name: `sources` (not `source_books`)
4. ✅ FK pattern: `source_id` (not `source_book_id`)
5. ✅ Multi-page support: `source_pages` as text (not single integer)
6. ✅ Comprehensive items table with weapon/armor/magic item columns
7. ✅ All tables from design document including classes, monsters, etc.

---

## Prerequisites

Before starting implementation, ensure you have:
- Docker Desktop or Docker Engine installed
- Docker Compose 2.x installed
- Git for version control
- The database schema design document (docs/plans/2025-11-17-dnd-compendium-database-design.md)

**Note:** PHP and Composer will run inside Docker containers, so local installation is not required.

---

## Phase 1: Foundation (Tasks 1-2)

### Task 1: Initialize Laravel Project

**Files:**
- Create: entire Laravel project structure via Composer

**Step 1: Create new Laravel project**

Since we already have a Laravel installation from the previous implementation, we'll work with it but update the migrations.

Run: `git status` to see current state
Expected: On main branch with previous implementation

**Step 2: Create new branch for redesign**

```bash
git checkout -b schema-redesign
```

**Step 3: Document the redesign**

Create note about this being aligned with approved design:
```bash
echo "This branch realigns implementation with approved database design document" > REDESIGN_NOTES.md
git add REDESIGN_NOTES.md
git commit -m "docs: note schema redesign to align with approved design"
```

---

### Task 2: Update Docker Environment (Already Complete)

The Docker environment from the previous implementation is correct and can be reused.

**Verification:**
```bash
docker-compose ps
```
Expected: All containers running (php, nginx, mysql)

---

## Phase 2: Core Schema - Lookup Tables (Tasks 3-6)

### Task 3: Create Sources Table Migration

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000001_create_sources_table.php`
- Create: `tests/Unit/Migrations/SourcesTableTest.php`

**Step 1: Write test**

```php
<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SourcesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_sources_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('sources'));
        $this->assertTrue(Schema::hasColumns('sources', [
            'id', 'code', 'name', 'publisher', 'publication_year', 'edition'
        ]));
    }

    public function test_sources_table_has_no_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('sources', 'created_at'));
        $this->assertFalse(Schema::hasColumn('sources', 'updated_at'));
    }

    public function test_sources_table_has_seed_data(): void
    {
        $count = DB::table('sources')->count();
        $this->assertGreaterThanOrEqual(6, $count);

        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $this->assertEquals("Player's Handbook", $phb->name);
        $this->assertEquals('5e', $phb->edition);
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SourcesTableTest`
Expected: FAIL

**Step 3: Create migration**

Run: `docker-compose exec php php artisan make:migration create_sources_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // PHB, XGE, DMG, etc.
            $table->string('name', 255); // Full book name
            $table->string('publisher', 100)->default('Wizards of the Coast');
            $table->unsignedSmallInteger('publication_year'); // 2014, 2024, etc.
            $table->string('edition', 20); // '5e', '2024', '5.5e', etc.

            // NO timestamps - static compendium data
        });

        // Seed with common source books
        DB::table('sources')->insert([
            ['code' => 'PHB', 'name' => "Player's Handbook", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e'],
            ['code' => 'DMG', 'name' => "Dungeon Master's Guide", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e'],
            ['code' => 'MM', 'name' => 'Monster Manual', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e'],
            ['code' => 'XGE', 'name' => "Xanathar's Guide to Everything", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2017, 'edition' => '5e'],
            ['code' => 'TCE', 'name' => "Tasha's Cauldron of Everything", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2020, 'edition' => '5e'],
            ['code' => 'VGTM', 'name' => "Volo's Guide to Monsters", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2016, 'edition' => '5e'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan migrate:fresh && docker-compose exec php php artisan test --filter=SourcesTableTest`
Expected: PASS

**Step 5: Commit**

```bash
git add database/migrations/*_create_sources_table.php
git add tests/Unit/Migrations/SourcesTableTest.php
git commit -m "feat: add sources table (no timestamps, includes edition field)"
```

---

### Task 4: Create Lookup Tables (Spell Schools, Damage Types, Sizes)

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000002_create_spell_schools_table.php`
- Create: `database/migrations/YYYY_MM_DD_000003_create_damage_types_table.php`
- Create: `database/migrations/YYYY_MM_DD_000004_create_sizes_table.php`
- Create: `database/migrations/YYYY_MM_DD_000005_create_ability_scores_table.php`
- Create: `database/migrations/YYYY_MM_DD_000006_create_skills_table.php`
- Create: `database/migrations/YYYY_MM_DD_000007_create_item_types_table.php`
- Create: `tests/Unit/Migrations/LookupTablesTest.php`

**Implementation:** Follow same TDD pattern as Task 3. All lookup tables:
- NO timestamps
- Include seed data in migration
- Use codes consistently (spell schools: 'A', 'C', etc.)
- Sizes: 'T', 'S', 'M', 'L', 'H', 'G'
- Ability scores: 'STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA'
- Skills: All 18 core D&D skills

**Commit message:** "feat: add all lookup tables (no timestamps, with seed data)"

---

## Phase 3: Core Entity Tables (Tasks 5-11)

### Task 5: Create Spells Table with Full Schema

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000020_create_spells_table.php`
- Create: `tests/Unit/Migrations/SpellsTableTest.php`

**Critical:** Must include ALL columns from design document

```php
Schema::create('spells', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->unsignedTinyInteger('level'); // 0-9
    $table->foreignId('school_id')->constrained('spell_schools')->onDelete('restrict');
    $table->boolean('is_ritual')->default(false);
    $table->boolean('needs_concentration')->default(false); // CRITICAL: Don't miss this!

    // Casting details
    $table->string('casting_time', 100);
    $table->string('range', 100);

    // Components
    $table->boolean('has_verbal_component')->default(false);
    $table->boolean('has_somatic_component')->default(false);
    $table->boolean('has_material_component')->default(false);
    $table->string('material_description', 500)->nullable();
    $table->decimal('material_cost_gp', 10, 2)->nullable();
    $table->boolean('material_consumed')->default(false);

    $table->string('duration', 100);
    $table->text('description');

    // Source tracking - NOTE: source_id not source_book_id
    $table->foreignId('source_id')->constrained('sources')->onDelete('cascade');
    $table->string('source_pages', 50)->nullable(); // "148", "148, 150", "211-213"

    // NO timestamps

    // Indexes
    $table->index('school_id');
    $table->index('level');
    $table->index('source_id');
    $table->index('material_cost_gp');
});
```

**Commit message:** "feat: add spells table with concentration and multi-page support"

---

### Task 6: Create Items Table with Comprehensive Schema

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000030_create_items_table.php`
- Create: `tests/Unit/Migrations/ItemsTableTest.php`

**Critical:** Must include ALL weapon/armor/magic item columns

```php
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('item_type_id')->constrained('item_types')->onDelete('restrict');
    $table->boolean('is_magic_item')->default(false); // CRITICAL: Distinguish mundane from magic
    $table->string('rarity', 20)->nullable(); // "common", "uncommon", "rare", "very rare", "legendary", "artifact"
    $table->decimal('weight', 8, 2)->nullable(); // In pounds
    $table->decimal('value_gp', 10, 2)->nullable();

    // Armor properties
    $table->unsignedTinyInteger('armor_class')->nullable();
    $table->unsignedTinyInteger('strength_requirement')->nullable();
    $table->boolean('has_stealth_disadvantage')->default(false);

    // Weapon properties
    $table->string('damage_dice_1', 20)->nullable(); // "1d8"
    $table->string('damage_dice_2', 20)->nullable(); // "1d10" for versatile
    $table->foreignId('damage_type_id')->nullable()->constrained('damage_types')->onDelete('set null');
    $table->string('range', 20)->nullable(); // "25/100"

    // Magic item properties
    $table->unsignedSmallInteger('max_charges')->nullable();
    $table->string('recharge_formula', 50)->nullable(); // "1d6+4"
    $table->string('recharge_timing', 50)->nullable(); // "at dawn"
    $table->boolean('requires_attunement')->default(false);
    $table->string('attunement_requirement', 500)->nullable(); // "by a wizard"

    $table->text('description');
    $table->foreignId('source_id')->constrained('sources')->onDelete('cascade');
    $table->string('source_pages', 50)->nullable();

    // NO timestamps

    // Indexes
    $table->index('item_type_id');
    $table->index('is_magic_item');
    $table->index('requires_attunement');
    $table->index('source_id');
});
```

**Commit message:** "feat: add comprehensive items table with weapon/armor/magic item columns"

---

### Task 7: Create Races, Backgrounds, Feats Tables

**Implementation:** Create migrations for races, backgrounds, feats following design document exactly.

**Races table must include:**
- `size` as CHAR (not FK) - 'S', 'M', 'L'
- `description` field (don't miss this!)
- `source_pages` as text

**Commit message:** "feat: add races, backgrounds, feats tables per design document"

---

### Task 8: Create Classes Table with Subclass Support

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000050_create_classes_table.php`

```php
Schema::create('classes', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->foreignId('parent_class_id')->nullable()->constrained('classes')->onDelete('cascade'); // Self-referencing
    $table->unsignedTinyInteger('hit_dice')->nullable(); // 6, 8, 10, 12
    $table->unsignedTinyInteger('num_skill_choices')->nullable();
    $table->text('available_skills')->nullable(); // Comma-separated
    $table->string('starting_wealth', 50)->nullable(); // "5d4x10"
    $table->string('spellcasting_ability', 20)->nullable(); // "Intelligence"
    $table->char('spell_slots_reset', 1)->nullable(); // 'L' or 'S'
    $table->text('description');
    $table->foreignId('source_id')->constrained('sources')->onDelete('cascade');
    $table->string('source_pages', 50)->nullable();

    // NO timestamps

    $table->index('parent_class_id');
    $table->index('source_id');
});
```

**Commit message:** "feat: add classes table with self-referencing subclass support"

---

### Task 9: Create Monsters Table

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000060_create_monsters_table.php`

Follow design document schema exactly - this is a large table with 30+ columns for stat blocks.

**Commit message:** "feat: add monsters table with complete stat block schema"

---

## Phase 4: Relationship Tables (Tasks 10-15)

### Task 10: Create Polymorphic Tables (Traits, Modifiers, Proficiencies)

**Critical differences from old implementation:**

**Modifiers table:**
```php
Schema::create('modifiers', function (Blueprint $table) {
    $table->id();
    $table->string('reference_type'); // 'Race', 'Feat', 'Class', etc.
    $table->unsignedBigInteger('reference_id');
    $table->string('modifier_category', 50); // 'ability_score', 'skill', 'damage'
    $table->foreignId('ability_score_id')->nullable()->constrained('ability_scores');
    $table->foreignId('skill_id')->nullable()->constrained('skills');
    $table->foreignId('damage_type_id')->nullable()->constrained('damage_types');
    $table->string('value', 20); // "+2", "+1d4"
    $table->string('condition', 255)->nullable(); // "when raging"

    // NO timestamps

    $table->index(['reference_type', 'reference_id']);
});
```

**Proficiencies table:**
```php
Schema::create('proficiencies', function (Blueprint $table) {
    $table->id();
    $table->string('reference_type');
    $table->unsignedBigInteger('reference_id');
    $table->string('proficiency_type', 50); // 'skill', 'tool', 'weapon', 'armor', 'language', 'saving_throw'
    $table->foreignId('skill_id')->nullable()->constrained('skills');
    $table->foreignId('item_id')->nullable()->constrained('items'); // For weapon/armor proficiency
    $table->foreignId('ability_score_id')->nullable()->constrained('ability_scores'); // For saving throws
    $table->string('proficiency_name', 100)->nullable(); // For tools, languages

    // NO timestamps

    $table->index(['reference_type', 'reference_id']);
});
```

**Note:** These use FKs, not just text fields!

**Commit message:** "feat: add polymorphic tables with FK-based relationships"

---

### Task 11: Create Class-Spell Junction Table

```php
Schema::create('class_spell', function (Blueprint $table) {
    $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
    $table->foreignId('spell_id')->constrained('spells')->onDelete('cascade');

    // Composite primary key
    $table->primary(['class_id', 'spell_id']);

    // NO timestamps
    // NO surrogate id
});
```

**Commit message:** "feat: add class_spell junction table with composite PK"

---

### Task 12: Create Spell Effects Table (Detailed)

```php
Schema::create('spell_effects', function (Blueprint $table) {
    $table->id();
    $table->foreignId('spell_id')->constrained('spells')->onDelete('cascade');
    $table->string('effect_type', 20); // 'damage', 'healing', 'duration', 'targets', 'range', 'other'
    $table->string('description', 100); // "Acid Damage", "Duration"
    $table->string('dice_formula', 50)->nullable(); // "1d6", "2d8"
    $table->string('base_value', 50)->nullable(); // "1 minute", "3 targets"
    $table->string('scaling_type', 20)->default('none'); // 'none', 'character_level', 'spell_slot'
    $table->unsignedTinyInteger('min_character_level')->nullable(); // For cantrip scaling
    $table->unsignedTinyInteger('min_spell_slot')->nullable(); // For upcasting
    $table->string('scaling_increment', 50)->nullable(); // "+1d8 per slot"

    // NO timestamps

    $table->index('spell_id');
});
```

**Commit message:** "feat: add detailed spell_effects table with comprehensive scaling"

---

### Task 13: Create Item-Related Tables

- `item_property` (junction table for weapon properties)
- `item_abilities` (magic item spells and special abilities)

**Commit message:** "feat: add item relationship tables"

---

### Task 14: Create Class-Related Tables

- `class_level_progression` (spell slots per level)
- `class_features` (features gained at each level)
- `class_counters` (Ki, Rage, etc.)

**Commit message:** "feat: add class progression tables"

---

### Task 15: Create Monster-Related Tables

- `monster_traits`
- `monster_actions`
- `monster_legendary_actions`
- `monster_spellcasting`
- `monster_spells`

**Commit message:** "feat: add monster relationship tables"

---

## Phase 5: Models and Eloquent (Tasks 16-20)

### Task 16: Create Source and Lookup Models

Create Eloquent models for:
- Source
- SpellSchool
- DamageType
- Size
- AbilityScore
- Skill
- ItemType

**Important:** Configure models to NOT use timestamps:

```php
class Source extends Model
{
    public $timestamps = false; // CRITICAL!

    protected $fillable = ['code', 'name', 'publisher', 'publication_year', 'edition'];
}
```

**Commit message:** "feat: add source and lookup models (timestamps disabled)"

---

### Task 17: Create Core Entity Models

Create models for Spell, Item, Race, Background, Feat, Class, Monster

**All models must:**
- Set `public $timestamps = false;`
- Define relationships correctly (use `source_id` not `source_book_id`)
- Include proper fillable attributes

**Example:**

```php
class Spell extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name', 'level', 'school_id', 'is_ritual', 'needs_concentration',
        'casting_time', 'range', 'has_verbal_component', 'has_somatic_component',
        'has_material_component', 'material_description', 'material_cost_gp',
        'material_consumed', 'duration', 'description', 'source_id', 'source_pages'
    ];

    protected $casts = [
        'level' => 'integer',
        'is_ritual' => 'boolean',
        'needs_concentration' => 'boolean',
        'has_verbal_component' => 'boolean',
        'has_somatic_component' => 'boolean',
        'has_material_component' => 'boolean',
        'material_cost_gp' => 'decimal:2',
        'material_consumed' => 'boolean',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(SpellSchool::class, 'school_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class, 'source_id');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassModel::class, 'class_spell');
    }
}
```

**Commit message:** "feat: add core entity models with source_id relationships"

---

### Task 18: Create Polymorphic Models

Create models for CharacterTrait, Modifier, Proficiency with morphTo relationships

**Commit message:** "feat: add polymorphic relationship models"

---

## Phase 6: Parsers and Importers (Tasks 19-25)

### Task 19: Create SpellXmlParser

Parse spell XML with ALL fields including:
- `needs_concentration` - check duration for "Concentration"
- `source_pages` - support multiple pages "148, 150"
- Extract edition from source book

**Commit message:** "feat: add SpellXmlParser with concentration and multi-page support"

---

### Task 20: Create SpellImporter

Import spells using:
- `source_id` (lookup Source by code)
- Store `source_pages` as text
- Handle concentration flag
- Create class associations via `class_spell` junction

**Commit message:** "feat: add SpellImporter using source_id and class junction table"

---

### Task 21: Create ItemXmlParser and ItemImporter

Parse ALL item fields:
- Weapon stats (damage, range)
- Armor stats (AC, strength req, stealth)
- Magic item properties (charges, attunement)
- Set `is_magic_item` flag based on rarity

**Commit message:** "feat: add comprehensive item parser and importer"

---

### Task 22: Create Race, Background, Feat Parsers/Importers

Use polymorphic tables with FK relationships:
- Parse ability modifiers → create Modifier records with `ability_score_id`
- Parse skill proficiencies → create Proficiency records with `skill_id`
- Store description text (don't forget!)

**Commit message:** "feat: add race/background/feat parsers with FK-based polymorphism"

---

### Task 23: Create Class Parser and Importer

Parse class data including:
- Subclass relationships (set `parent_class_id`)
- Features, spell progression, counters
- Store in appropriate tables

**Commit message:** "feat: add class parser and importer with subclass support"

---

### Task 24: Create Monster Parser and Importer

Parse monster stat blocks - this is complex with many fields

**Commit message:** "feat: add monster parser and importer"

---

### Task 25: Create Import Commands

Create Artisan commands:
- `import:spells`
- `import:items`
- `import:races`
- `import:backgrounds`
- `import:feats`
- `import:classes`
- `import:monsters`
- `import:all`

**Commit message:** "feat: add all import Artisan commands"

---

## Phase 7: Testing and Documentation (Tasks 26-28)

### Task 26: Create Integration Tests

Test actual imports from XML files, verify data accuracy

**Commit message:** "test: add integration tests with real XML files"

---

### Task 27: Create README

Document:
- NO timestamps in database (design decision)
- `source_id` and `sources` table usage
- Multi-page reference support
- Comprehensive items schema
- Edition tracking

**Commit message:** "docs: add README documenting schema design decisions"

---

### Task 28: Final Verification

Run all tests, verify schema matches design document exactly

**Checklist:**
- [ ] NO timestamps on any table
- [ ] Uses `source_id` not `source_book_id`
- [ ] `source_pages` is text not integer
- [ ] Spells has `needs_concentration`
- [ ] Items has weapon/armor/magic columns
- [ ] Sources has `edition` field
- [ ] All 31 tables from design exist
- [ ] Polymorphic tables use FKs
- [ ] Class-spell junction uses composite PK

**Commit message:** "chore: final verification - schema matches design document"

---

## Summary

This plan creates a **complete D&D 5e compendium database** that exactly matches the approved design document:

- ✅ 31 tables (vs 15 in old plan)
- ✅ NO timestamps (static data)
- ✅ Correct naming (`source_id`, `sources`)
- ✅ Multi-page references
- ✅ Comprehensive items schema
- ✅ Edition tracking
- ✅ Concentration tracking for spells
- ✅ Full class and monster systems
- ✅ FK-based polymorphic relationships

**Total Tasks:** 28 (vs 21 in old plan)
**Estimated Implementation:** 2-3 days with subagent-driven development

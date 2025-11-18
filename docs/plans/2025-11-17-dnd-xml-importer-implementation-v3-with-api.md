# D&D 5e XML Importer Implementation Plan (v3 - With API Layer)

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a complete Laravel-based system that provides a RESTful API for D&D 5e compendium data, with command-line tools to import from XML files into a relational database following the approved database design document exactly.

**Architecture:** Laravel application with RESTful API endpoints for all entities, Eloquent models with proper relationships, Artisan commands for importing, XML parsing service classes, comprehensive test coverage using TDD, and API resource transformers following Laravel best practices.

**Tech Stack:** Laravel 11.x, PHP 8.4, MySQL 8.0, Docker & Docker Compose, Nginx, PHP-FPM, PHPUnit for testing, Laravel API Resources, Symfony Console for CLI, SimpleXML for XML parsing

**Design Document Reference:** `docs/plans/2025-11-17-dnd-compendium-database-design.md`

---

## Changes from v2

**v3 adds comprehensive API layer:**
- ✅ RESTful API endpoints for all entities (spells, items, races, classes, monsters, etc.)
- ✅ Laravel API Resources for consistent JSON responses
- ✅ Query filtering, pagination, sorting using Eloquent
- ✅ Full-text search using MySQL FULLTEXT indexes
- ✅ CORS configuration for frontend integration
- ✅ Comprehensive API tests (Feature & Unit)
- ✅ API layer implemented BEFORE importers
- ✅ Leverage Eloquent relationships for nested data

**Future features (not in v3):**
- GraphQL API layer (alternative to REST)
- Authentication and authorization
- Rate limiting
- Response caching

---

## Prerequisites

Before starting implementation, ensure you have:
- Docker Desktop or Docker Engine installed
- Docker Compose 2.x installed
- Git for version control
- The database schema design document (docs/plans/2025-11-17-dnd-compendium-database-design.md)

**Note:** PHP and Composer will run inside Docker containers, so local installation is not required.

---

## Phase 1-4: Database Schema (COMPLETE)

Tasks 1-15 have been completed implementing all 31 database tables with:
- ✅ NO timestamps on any table
- ✅ Correct naming (`source_id`, `sources` table)
- ✅ Multi-page reference support
- ✅ Comprehensive items schema with weapon/armor/magic columns
- ✅ FK-based polymorphic relationships
- ✅ All tests passing (116 tests, 473 assertions)

See original plan for details on Tasks 1-15.

---

## Phase 5: Models and Eloquent Relationships (Tasks 16-18)

### Task 16: Create Source and Lookup Models

**Files:**
- Create: `app/Models/Source.php`
- Create: `app/Models/SpellSchool.php`
- Create: `app/Models/DamageType.php`
- Create: `app/Models/Size.php`
- Create: `app/Models/AbilityScore.php`
- Create: `app/Models/Skill.php`
- Create: `app/Models/ItemType.php`
- Create: `app/Models/ItemProperty.php`
- Create: `tests/Unit/Models/SourceTest.php`

**Step 1: Write failing test for Source model**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Source;
use Tests\TestCase;

class SourceTest extends TestCase
{
    public function test_source_model_exists(): void
    {
        $source = new Source();
        $this->assertInstanceOf(Source::class, $source);
    }

    public function test_source_does_not_use_timestamps(): void
    {
        $source = new Source();
        $this->assertFalse($source->timestamps);
    }

    public function test_source_has_fillable_attributes(): void
    {
        $source = new Source();
        $fillable = ['code', 'name', 'publisher', 'publication_year', 'edition'];
        $this->assertEquals($fillable, $source->getFillable());
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SourceTest`
Expected: FAIL (class doesn't exist)

**Step 3: Create Source model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Source extends Model
{
    public $timestamps = false; // CRITICAL: No timestamps on static data

    protected $fillable = [
        'code',
        'name',
        'publisher',
        'publication_year',
        'edition',
    ];

    protected $casts = [
        'publication_year' => 'integer',
    ];

    // Relationships
    public function spells(): HasMany
    {
        return $this->hasMany(Spell::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function races(): HasMany
    {
        return $this->hasMany(Race::class);
    }

    public function backgrounds(): HasMany
    {
        return $this->hasMany(Background::class);
    }

    public function feats(): HasMany
    {
        return $this->hasMany(Feat::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class);
    }

    public function monsters(): HasMany
    {
        return $this->hasMany(Monster::class);
    }
}
```

**Step 4: Create lookup models with same pattern**

All lookup models (SpellSchool, DamageType, Size, AbilityScore, Skill, ItemType, ItemProperty):
- `public $timestamps = false;`
- Proper `$fillable` arrays
- Relationships to entities that reference them

**Step 5: Run tests**

Run: `docker-compose exec php php artisan test --filter=SourceTest`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Models/Source.php app/Models/SpellSchool.php app/Models/DamageType.php app/Models/Size.php app/Models/AbilityScore.php app/Models/Skill.php app/Models/ItemType.php app/Models/ItemProperty.php tests/Unit/Models/SourceTest.php
git commit -m "feat: add source and lookup models (timestamps disabled)"
```

---

### Task 17: Create Core Entity Models

**Files:**
- Create: `app/Models/Spell.php`
- Create: `app/Models/Item.php`
- Create: `app/Models/Race.php`
- Create: `app/Models/Background.php`
- Create: `app/Models/Feat.php`
- Create: `app/Models/ClassModel.php`
- Create: `app/Models/Monster.php`
- Create: `tests/Unit/Models/SpellTest.php`

**Step 1: Write failing test for Spell model**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\Source;
use Tests\TestCase;

class SpellTest extends TestCase
{
    public function test_spell_belongs_to_spell_school(): void
    {
        $spell = new Spell();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $spell->spellSchool());
    }

    public function test_spell_belongs_to_source(): void
    {
        $spell = new Spell();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $spell->source());
    }

    public function test_spell_belongs_to_many_classes(): void
    {
        $spell = new Spell();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class, $spell->classes());
    }

    public function test_spell_does_not_use_timestamps(): void
    {
        $spell = new Spell();
        $this->assertFalse($spell->timestamps);
    }
}
```

**Step 2: Create Spell model with relationships**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Spell extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'level',
        'spell_school_id',
        'casting_time',
        'range',
        'components',
        'material_components',
        'duration',
        'needs_concentration',
        'is_ritual',
        'description',
        'higher_levels',
        'source_id',
        'source_pages',
    ];

    protected $casts = [
        'level' => 'integer',
        'needs_concentration' => 'boolean',
        'is_ritual' => 'boolean',
    ];

    // Relationships
    public function spellSchool(): BelongsTo
    {
        return $this->belongsTo(SpellSchool::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(ClassModel::class, 'class_spells');
    }

    public function effects(): HasMany
    {
        return $this->hasMany(SpellEffect::class);
    }

    // Scopes for API filtering
    public function scopeLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeSchool($query, $schoolId)
    {
        return $query->where('spell_school_id', $schoolId);
    }

    public function scopeConcentration($query, $needsConcentration)
    {
        return $query->where('needs_concentration', $needsConcentration);
    }

    public function scopeRitual($query, $isRitual)
    {
        return $query->where('is_ritual', $isRitual);
    }
}
```

**Step 3: Create Item model with comprehensive relationships**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'name',
        'item_type_id',
        'description',
        'weight',
        'cost_cp',
        'rarity',
        'damage_dice',
        'damage_type_id',
        'weapon_range',
        'versatile_damage',
        'weapon_properties',
        'armor_class',
        'strength_requirement',
        'stealth_disadvantage',
        'requires_attunement',
        'source_id',
        'source_pages',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'cost_cp' => 'integer',
        'armor_class' => 'integer',
        'strength_requirement' => 'integer',
        'stealth_disadvantage' => 'boolean',
        'requires_attunement' => 'boolean',
        'weapon_properties' => 'array', // JSON cast
    ];

    // Relationships
    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class);
    }

    public function damageType(): BelongsTo
    {
        return $this->belongsTo(DamageType::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(ItemProperty::class, 'item_property');
    }

    public function abilities(): HasMany
    {
        return $this->hasMany(ItemAbility::class);
    }

    // Scopes
    public function scopeType($query, $typeId)
    {
        return $query->where('item_type_id', $typeId);
    }

    public function scopeRarity($query, $rarity)
    {
        return $query->where('rarity', $rarity);
    }

    public function scopeAttunement($query, $requiresAttunement)
    {
        return $query->where('requires_attunement', $requiresAttunement);
    }
}
```

**Step 4: Create remaining core models** (Race, Background, Feat, ClassModel, Monster) following same pattern

**Step 5: Run tests**

Run: `docker-compose exec php php artisan test tests/Unit/Models`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Models/*.php tests/Unit/Models/*.php
git commit -m "feat: add core entity models with relationships and scopes"
```

---

### Task 18: Create Relationship Models

**Files:**
- Create: `app/Models/AbilityScoreBonus.php`
- Create: `app/Models/SkillProficiency.php`
- Create: `app/Models/SpellEffect.php`
- Create: `app/Models/ItemAbility.php`
- Create: `app/Models/ClassLevelProgression.php`
- Create: `app/Models/ClassFeature.php`
- Create: `app/Models/ClassCounter.php`
- Create: `app/Models/MonsterTrait.php`
- Create: `app/Models/MonsterAction.php`
- Create: `app/Models/MonsterLegendaryAction.php`
- Create: `app/Models/MonsterSpellcasting.php`

All models follow same pattern:
- `public $timestamps = false;`
- Proper `$fillable` arrays
- BelongsTo relationships to parent entities

**Commit message:** "feat: add relationship models for spell effects, class progression, monster traits"

---

## Phase 6: API Layer (Tasks 19-26) - NEW!

### Task 19: Configure CORS for API

**Files:**
- Update: `config/cors.php`
- Update: `bootstrap/app.php`
- Create: `tests/Feature/Api/CorsTest.php`

**Step 1: Write failing CORS test**

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_api_returns_cors_headers(): void
    {
        $response = $this->getJson('/api/spells');

        $response->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
    }

    public function test_preflight_request_succeeds(): void
    {
        $response = $this->options('/api/spells', [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=CorsTest`
Expected: FAIL (CORS not configured)

**Step 3: Configure CORS**

Update `config/cors.php`:

```php
<?php

return [
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'], // For development; restrict in production

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
```

**Step 4: Enable CORS middleware in application bootstrap**

Update `bootstrap/app.php` to ensure CORS middleware is applied:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \Illuminate\Http\Middleware\HandleCors::class,
    ]);
})
```

**Step 5: Run tests**

Run: `docker-compose exec php php artisan test --filter=CorsTest`
Expected: PASS

**Step 6: Commit**

```bash
git add config/cors.php bootstrap/app.php tests/Feature/Api/CorsTest.php
git commit -m "feat: configure CORS for API endpoints"
```

---

### Task 20: Create API Resources for Spells with Full-Text Search

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_fulltext_indexes.php`
- Create: `app/Http/Resources/SpellResource.php`
- Create: `app/Http/Resources/SpellCollection.php`
- Create: `app/Http/Controllers/Api/SpellController.php`
- Create: `routes/api.php` (add routes)
- Create: `tests/Feature/Api/SpellApiTest.php`

**Step 1: Create FULLTEXT index migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add FULLTEXT indexes for searchable fields
        DB::statement('ALTER TABLE spells ADD FULLTEXT INDEX spells_search (name, description)');
        DB::statement('ALTER TABLE items ADD FULLTEXT INDEX items_search (name, description)');
        DB::statement('ALTER TABLE races ADD FULLTEXT INDEX races_search (name, description)');
        DB::statement('ALTER TABLE backgrounds ADD FULLTEXT INDEX backgrounds_search (name, description)');
        DB::statement('ALTER TABLE feats ADD FULLTEXT INDEX feats_search (name, description)');
        DB::statement('ALTER TABLE classes ADD FULLTEXT INDEX classes_search (name, description)');
        DB::statement('ALTER TABLE monsters ADD FULLTEXT INDEX monsters_search (name, description)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE spells DROP INDEX spells_search');
        DB::statement('ALTER TABLE items DROP INDEX items_search');
        DB::statement('ALTER TABLE races DROP INDEX races_search');
        DB::statement('ALTER TABLE backgrounds DROP INDEX backgrounds_search');
        DB::statement('ALTER TABLE feats DROP INDEX feats_search');
        DB::statement('ALTER TABLE classes DROP INDEX classes_search');
        DB::statement('ALTER TABLE monsters DROP INDEX monsters_search');
    }
};
```

**Step 2: Run migration**

Run: `docker-compose exec php php artisan migrate`
Expected: FULLTEXT indexes created on all searchable tables

**Step 3: Write failing API test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_all_spells(): void
    {
        // Create test data
        $school = SpellSchool::create(['code' => 'EV', 'name' => 'Evocation', 'description' => 'Test']);
        $source = Source::create(['code' => 'PHB', 'name' => "Player's Handbook", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e']);

        Spell::create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes...',
            'source_id' => $source->id,
            'source_pages' => '241',
        ]);

        $response = $this->getJson('/api/spells');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'level',
                        'school' => ['id', 'name', 'code'],
                        'casting_time',
                        'range',
                        'components',
                        'duration',
                        'needs_concentration',
                        'is_ritual',
                        'description',
                        'source' => ['id', 'code', 'name'],
                        'source_pages',
                    ]
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(1, 'data');
    }

    public function test_can_get_single_spell(): void
    {
        $school = SpellSchool::create(['code' => 'EV', 'name' => 'Evocation', 'description' => 'Test']);
        $source = Source::create(['code' => 'PHB', 'name' => "Player's Handbook", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e']);

        $spell = Spell::create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes...',
            'source_id' => $source->id,
            'source_pages' => '241',
        ]);

        $response = $this->getJson("/api/spells/{$spell->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $spell->id,
                    'name' => 'Fireball',
                    'level' => 3,
                ]
            ]);
    }

    public function test_can_filter_spells_by_level(): void
    {
        $school = SpellSchool::create(['code' => 'EV', 'name' => 'Evocation', 'description' => 'Test']);
        $source = Source::create(['code' => 'PHB', 'name' => "Player's Handbook", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e']);

        // Create level 0 cantrip
        Spell::create([
            'name' => 'Fire Bolt',
            'level' => 0,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '120 feet',
            'components' => 'V, S',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'You hurl a mote of fire...',
            'source_id' => $source->id,
            'source_pages' => '242',
        ]);

        // Create level 3 spell
        Spell::create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes...',
            'source_id' => $source->id,
            'source_pages' => '241',
        ]);

        $response = $this->getJson('/api/spells?level=3');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fireball');
    }

    public function test_can_filter_spells_by_school(): void
    {
        $evocation = SpellSchool::create(['code' => 'EV', 'name' => 'Evocation', 'description' => 'Test']);
        $illusion = SpellSchool::create(['code' => 'I', 'name' => 'Illusion', 'description' => 'Test']);
        $source = Source::create(['code' => 'PHB', 'name' => "Player's Handbook", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e']);

        Spell::create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $evocation->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes...',
            'source_id' => $source->id,
            'source_pages' => '241',
        ]);

        Spell::create([
            'name' => 'Invisibility',
            'level' => 2,
            'spell_school_id' => $illusion->id,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'components' => 'V, S, M',
            'duration' => 'Concentration, up to 1 hour',
            'needs_concentration' => true,
            'is_ritual' => false,
            'description' => 'A creature you touch becomes invisible...',
            'source_id' => $source->id,
            'source_pages' => '254',
        ]);

        $response = $this->getJson("/api/spells?school={$evocation->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fireball');
    }

    public function test_can_filter_spells_by_concentration(): void
    {
        $school = SpellSchool::create(['code' => 'I', 'name' => 'Illusion', 'description' => 'Test']);
        $source = Source::create(['code' => 'PHB', 'name' => "Player's Handbook", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e']);

        Spell::create([
            'name' => 'Invisibility',
            'level' => 2,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'components' => 'V, S, M',
            'duration' => 'Concentration, up to 1 hour',
            'needs_concentration' => true,
            'is_ritual' => false,
            'description' => 'A creature you touch becomes invisible...',
            'source_id' => $source->id,
            'source_pages' => '254',
        ]);

        $response = $this->getJson('/api/spells?concentration=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.needs_concentration', true);
    }

    public function test_spells_are_paginated(): void
    {
        $school = SpellSchool::create(['code' => 'EV', 'name' => 'Evocation', 'description' => 'Test']);
        $source = Source::create(['code' => 'PHB', 'name' => "Player's Handbook", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e']);

        // Create 20 spells
        for ($i = 1; $i <= 20; $i++) {
            Spell::create([
                'name' => "Spell $i",
                'level' => 1,
                'spell_school_id' => $school->id,
                'casting_time' => '1 action',
                'range' => 'Self',
                'components' => 'V',
                'duration' => 'Instantaneous',
                'needs_concentration' => false,
                'is_ritual' => false,
                'description' => "Test spell $i",
                'source_id' => $source->id,
                'source_pages' => '100',
            ]);
        }

        $response = $this->getJson('/api/spells?per_page=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'meta' => ['total', 'per_page', 'current_page', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_can_search_spells_by_name(): void
    {
        $school = SpellSchool::create(['code' => 'EV', 'name' => 'Evocation', 'description' => 'Test']);
        $source = Source::create(['code' => 'PHB', 'name' => "Player's Handbook", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e']);

        Spell::create([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes from your pointing finger...',
            'source_id' => $source->id,
            'source_pages' => '241',
        ]);

        Spell::create([
            'name' => 'Ice Storm',
            'level' => 4,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => '300 feet',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A hail of rock-hard ice pounds to the ground...',
            'source_id' => $source->id,
            'source_pages' => '252',
        ]);

        $response = $this->getJson('/api/spells?search=fireball');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fireball');
    }

    public function test_can_search_spells_by_description(): void
    {
        $school = SpellSchool::create(['code' => 'EV', 'name' => 'Evocation', 'description' => 'Test']);
        $source = Source::create(['code' => 'PHB', 'name' => "Player's Handbook", 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e']);

        Spell::create([
            'name' => 'Lightning Bolt',
            'level' => 3,
            'spell_school_id' => $school->id,
            'casting_time' => '1 action',
            'range' => 'Self',
            'components' => 'V, S, M',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A stroke of lightning forming a line 100 feet long...',
            'source_id' => $source->id,
            'source_pages' => '255',
        ]);

        $response = $this->getJson('/api/spells?search=lightning');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Lightning Bolt');
    }
}
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellApiTest`
Expected: FAIL (routes and controller don't exist)

**Step 5: Update Spell model with search scope**

Add to existing `app/Models/Spell.php` (created in Task 17):

```php
// Add to existing scopes section
public function scopeSearch($query, $searchTerm)
{
    return $query->whereRaw(
        "MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)",
        [$searchTerm]
    );
}
```

**Step 6: Create SpellResource**

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpellResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $this->level,
            'school' => [
                'id' => $this->spellSchool->id,
                'code' => $this->spellSchool->code,
                'name' => $this->spellSchool->name,
            ],
            'casting_time' => $this->casting_time,
            'range' => $this->range,
            'components' => $this->components,
            'material_components' => $this->material_components,
            'duration' => $this->duration,
            'needs_concentration' => $this->needs_concentration,
            'is_ritual' => $this->is_ritual,
            'description' => $this->description,
            'higher_levels' => $this->higher_levels,
            'source' => [
                'id' => $this->source->id,
                'code' => $this->source->code,
                'name' => $this->source->name,
            ],
            'source_pages' => $this->source_pages,
            'classes' => ClassResource::collection($this->whenLoaded('classes')),
            'effects' => SpellEffectResource::collection($this->whenLoaded('effects')),
        ];
    }
}
```

**Step 7: Create SpellController with search support**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpellResource;
use App\Models\Spell;
use Illuminate\Http\Request;

class SpellController extends Controller
{
    public function index(Request $request)
    {
        $query = Spell::with(['spellSchool', 'source']);

        // Apply search filter (FULLTEXT search)
        if ($request->has('search')) {
            $query->search($request->search);
        }

        // Apply filters
        if ($request->has('level')) {
            $query->level($request->level);
        }

        if ($request->has('school')) {
            $query->school($request->school);
        }

        if ($request->has('concentration')) {
            $query->concentration($request->boolean('concentration'));
        }

        if ($request->has('ritual')) {
            $query->ritual($request->boolean('ritual'));
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortBy, $sortDirection);

        // Paginate
        $perPage = $request->get('per_page', 15);
        $spells = $query->paginate($perPage);

        return SpellResource::collection($spells);
    }

    public function show(Spell $spell)
    {
        $spell->load(['spellSchool', 'source', 'classes', 'effects']);

        return new SpellResource($spell);
    }
}
```

**Step 8: Add API routes**

```php
// routes/api.php

use App\Http\Controllers\Api\SpellController;

Route::prefix('v1')->group(function () {
    Route::apiResource('spells', SpellController::class)->only(['index', 'show']);
});
```

**Step 9: Run tests**

Run: `docker-compose exec php php artisan test --filter=SpellApiTest`
Expected: PASS

**Step 10: Commit**

```bash
git add database/migrations/*_add_fulltext_indexes.php app/Models/Spell.php app/Http/Resources/SpellResource.php app/Http/Controllers/Api/SpellController.php routes/api.php tests/Feature/Api/SpellApiTest.php
git commit -m "feat: add Spell API with full-text search, filtering, and pagination"
```

---

### Task 21: Create API Resources for Items with Full-Text Search

**Files:**
- Create: `app/Http/Resources/ItemResource.php`
- Create: `app/Http/Controllers/Api/ItemController.php`
- Create: `tests/Feature/Api/ItemApiTest.php`

**Implementation:** Follow same pattern as Task 20

**Features:**
- Full-text search on name and description
- Filter by item type, rarity, attunement
- Include related data: itemType, damageType, properties, abilities
- Pagination and sorting
- Comprehensive tests including search

**Note:** FULLTEXT index already created in Task 20

**Commit message:** "feat: add Item API with full-text search and weapon/armor/magic item filtering"

---

### Task 22: Create API Resources for Races, Backgrounds, Feats with Full-Text Search

**Files:**
- Create: `app/Http/Resources/RaceResource.php`
- Create: `app/Http/Resources/BackgroundResource.php`
- Create: `app/Http/Resources/FeatResource.php`
- Create: `app/Http/Controllers/Api/RaceController.php`
- Create: `app/Http/Controllers/Api/BackgroundController.php`
- Create: `app/Http/Controllers/Api/FeatController.php`
- Create: `tests/Feature/Api/RaceApiTest.php`
- Create: `tests/Feature/Api/BackgroundApiTest.php`
- Create: `tests/Feature/Api/FeatApiTest.php`

**Features:**
- Full-text search on name and description
- Include ability score bonuses
- Include skill proficiencies
- Filter by size (races)
- Filter by prerequisites (feats)

**Note:** FULLTEXT indexes already created in Task 20

**Commit message:** "feat: add Race, Background, Feat APIs with full-text search and polymorphic relationships"

---

### Task 23: Create API Resources for Classes with Full-Text Search

**Files:**
- Create: `app/Http/Resources/ClassResource.php`
- Create: `app/Http/Controllers/Api/ClassController.php`
- Create: `tests/Feature/Api/ClassApiTest.php`

**Features:**
- Full-text search on name and description
- Include subclasses (via parent_class_id)
- Include level progression, features, counters
- Include spell list
- Filter by hit die, spellcasting ability

**Note:** FULLTEXT index already created in Task 20

**Commit message:** "feat: add Class API with full-text search, subclasses, progression, and spell lists"

---

### Task 24: Create API Resources for Monsters with Full-Text Search

**Files:**
- Create: `app/Http/Resources/MonsterResource.php`
- Create: `app/Http/Controllers/Api/MonsterController.php`
- Create: `tests/Feature/Api/MonsterApiTest.php`

**Features:**
- Full-text search on name and description
- Complete stat block in JSON
- Include traits, actions, legendary actions, spellcasting
- Filter by size, type, CR, environment
- Sort by CR, name

**Note:** FULLTEXT index already created in Task 20

**Commit message:** "feat: add Monster API with full-text search, complete stat block, and filtering"

---

### Task 25: Create Lookup Table APIs

**Files:**
- Create: `app/Http/Controllers/Api/SpellSchoolController.php`
- Create: `app/Http/Controllers/Api/DamageTypeController.php`
- Create: `app/Http/Controllers/Api/SizeController.php`
- Create: `app/Http/Controllers/Api/AbilityScoreController.php`
- Create: `app/Http/Controllers/Api/SkillController.php`
- Create: `app/Http/Controllers/Api/ItemTypeController.php`
- Create: `app/Http/Controllers/Api/SourceController.php`

**Implementation:** Simple index/show endpoints for lookup data

**Commit message:** "feat: add lookup table APIs for reference data"

---

### Task 26: API Documentation and OpenAPI Spec

**Files:**
- Create: `docs/api/openapi.yaml`
- Create: `docs/api/README.md`

**Content:**
- Document all endpoints
- Query parameters for filtering (including search)
- Response structures
- Examples for each entity type
- Full-text search usage examples

**Commit message:** "docs: add API documentation and OpenAPI specification"

---

## Phase 7: API Testing (Tasks 27-28) - NEW!

### Task 27: Comprehensive API Integration Tests

**Files:**
- Create: `tests/Feature/Api/SpellFilteringTest.php`
- Create: `tests/Feature/Api/ItemFilteringTest.php`
- Create: `tests/Feature/Api/PaginationTest.php`
- Create: `tests/Feature/Api/RelationshipLoadingTest.php`

**Tests to add:**
- Full-text search functionality
- Complex filtering combinations
- Sorting with multiple fields
- Pagination edge cases
- N+1 query prevention (relationship eager loading)
- Error responses (404, 422)

**Commit message:** "test: add comprehensive API integration tests with search coverage"

---

### Task 28: API Performance Tests

**Files:**
- Create: `tests/Performance/ApiResponseTimeTest.php`

**Tests:**
- Response time for paginated lists
- Response time with complex filters
- Response time with full-text search
- Response time with nested relationships
- Database query count assertions

**Commit message:** "test: add API performance tests with search benchmarks"

---

## Phase 8: Parsers and Importers (Tasks 29-35) - MOVED FROM PHASE 6

### Task 29: Create SpellXmlParser

Parse spell XML with ALL fields including:
- `needs_concentration` - check duration for "Concentration"
- `source_pages` - support multiple pages "148, 150"
- Extract edition from source book

**Commit message:** "feat: add SpellXmlParser with concentration and multi-page support"

---

### Task 30: Create SpellImporter

Import spells using:
- `source_id` (lookup Source by code)
- Store `source_pages` as text
- Handle concentration flag
- Create class associations via `class_spells` junction

**Verification:** Import test spell, fetch via API, verify JSON matches XML data

**Commit message:** "feat: add SpellImporter using source_id and class junction table"

---

### Task 31: Create ItemXmlParser and ItemImporter

Parse ALL item fields:
- Weapon stats (damage, range)
- Armor stats (AC, strength req, stealth)
- Magic item properties (charges, attunement)

**Verification:** Import test item, fetch via API, verify weapon/armor/magic properties

**Commit message:** "feat: add comprehensive item parser and importer"

---

### Task 32: Create Race, Background, Feat Parsers/Importers

Use polymorphic tables with FK relationships:
- Parse ability modifiers → create AbilityScoreBonus records
- Parse skill proficiencies → create SkillProficiency records

**Verification:** Import race, fetch via API, verify bonuses and proficiencies

**Commit message:** "feat: add race/background/feat parsers with FK-based polymorphism"

---

### Task 33: Create Class Parser and Importer

Parse class data including:
- Subclass relationships (set `parent_class_id`)
- Features, spell progression, counters

**Verification:** Import class with subclass, fetch via API, verify hierarchy

**Commit message:** "feat: add class parser and importer with subclass support"

---

### Task 34: Create Monster Parser and Importer

Parse monster stat blocks - this is complex with many fields

**Verification:** Import monster, fetch via API, verify complete stat block

**Commit message:** "feat: add monster parser and importer"

---

### Task 35: Create Import Commands

Create Artisan commands:
- `import:spells`
- `import:items`
- `import:races`
- `import:backgrounds`
- `import:feats`
- `import:classes`
- `import:monsters`
- `import:all`

Each command should:
- Show progress bar
- Validate XML before import
- Report errors
- Show API verification link after import

**Commit message:** "feat: add all import Artisan commands"

---

## Phase 9: Integration Testing and Documentation (Tasks 36-38) - MOVED FROM PHASE 7

### Task 36: End-to-End Integration Tests

**Files:**
- Create: `tests/Integration/SpellImportToApiTest.php`
- Create: `tests/Integration/ItemImportToApiTest.php`

**Tests:**
- Import XML → Verify database → Verify API response
- Test data transformations through entire pipeline
- Verify relationships are correctly established

**Commit message:** "test: add end-to-end import-to-API integration tests"

---

### Task 37: Create Comprehensive README

**Files:**
- Update: `README.md`

**Document:**
- API endpoints and usage
- Full-text search examples
- Filtering, sorting, pagination examples
- CORS configuration
- Import command usage
- Database schema decisions (no timestamps, source_id, etc.)
- Frontend integration guide

**Commit message:** "docs: add comprehensive README with API and import documentation"

---

### Task 38: Final Verification

Run all tests, verify API responses match XML data

**Checklist:**
- [ ] All database migrations complete (31 tables + FULLTEXT indexes)
- [ ] All models have `timestamps = false`
- [ ] CORS configured and tested
- [ ] Full-text search working on all entities
- [ ] All API endpoints functional
- [ ] All filters working correctly
- [ ] Pagination working on all endpoints
- [ ] Relationships properly eager-loaded
- [ ] Import commands working
- [ ] API responses match XML source data
- [ ] No N+1 query issues
- [ ] All tests passing (200+ tests expected)

**Commit message:** "chore: final verification - API layer complete with CORS and search"

---

## Summary

This plan creates a **complete D&D 5e compendium system** with:

**Database:**
- ✅ 31 tables (all migrations complete)
- ✅ NO timestamps (static data)
- ✅ Correct naming (`source_id`, `sources`)
- ✅ Multi-page references
- ✅ Comprehensive items schema

**API Layer (NEW):**
- ✅ RESTful endpoints for all entities
- ✅ Laravel API Resources for consistent JSON
- ✅ Full-text search using MySQL FULLTEXT indexes
- ✅ CORS configuration for frontend integration
- ✅ Query filtering, sorting, pagination
- ✅ Relationship eager-loading
- ✅ Comprehensive test coverage
- ✅ OpenAPI documentation

**Import System:**
- ✅ XML parsers for all entity types
- ✅ Importers using Eloquent models
- ✅ Artisan commands
- ✅ End-to-end verification via API

**Total Tasks:** 38 (was 28 in v2, 37 in early v3)
- Tasks 1-15: Database (COMPLETE)
- Tasks 16-18: Models (3 tasks)
- Task 19: CORS Configuration (1 task) - NEW
- Tasks 20-26: API Layer with Search (7 tasks) - NEW
- Tasks 27-28: API Testing (2 tasks) - NEW
- Tasks 29-35: Importers (7 tasks)
- Tasks 36-38: Integration & Docs (3 tasks)

**Estimated Implementation:** 4-5 days with subagent-driven development

**Next Steps:** Start with Task 16 (Models) since database is complete

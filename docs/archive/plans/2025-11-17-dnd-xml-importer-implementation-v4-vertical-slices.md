# D&D 5e XML Importer Implementation Plan (v4 - Vertical Slices)

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a complete Laravel-based system that provides a RESTful API for D&D 5e compendium data, with command-line tools to import from XML files. Implement **one entity at a time, end-to-end** (Model → API → Parser → Importer → Tests) to reach useful state faster and validate the pipeline early.

**Architecture:** Laravel application with vertical slices per entity. Each entity (Spells, Items, Races, etc.) is fully implemented from XML import through to API before moving to the next. Foundation setup (CORS, search indexes, lookups) done once upfront. This approach validates the entire data pipeline early and provides incremental value.

**Tech Stack:** Laravel 11.x, PHP 8.4, MySQL 8.0, Docker & Docker Compose, Nginx, PHP-FPM, PHPUnit for testing, Laravel API Resources, SimpleXML for XML parsing, MySQL FULLTEXT search

**Design Document Reference:** `docs/plans/2025-11-17-dnd-compendium-database-design.md`

---

## Why v4? (Vertical Slices)

**v3 problem:** Horizontal slicing meant building all models, then all APIs, then all importers. We wouldn't discover XML/schema mismatches until the end.

**v4 solution:** Vertical slicing - complete one entity end-to-end before the next:
- ✅ Spells working after Phase 6 (~1 day)
- ✅ Items working after Phase 7 (~1.5 days)
- ✅ Early validation of XML → Database → API pipeline
- ✅ Incremental value - each phase delivers working functionality
- ✅ Catch schema mismatches on first entity, not after all prep work

---

## Prerequisites

Before starting implementation, ensure you have:
- Docker Desktop or Docker Engine installed
- Docker Compose 2.x installed
- Git for version control
- The database schema design document (docs/plans/2025-11-17-dnd-compendium-database-design.md)

**Note:** PHP and Composer will run inside Docker containers, so local installation is not required.

---

## Phase 1-4: Database Schema (COMPLETE ✅)

Tasks 1-15 have been completed implementing all 31 database tables with:
- ✅ NO timestamps on any table
- ✅ Correct naming (`source_id`, `sources` table)
- ✅ Multi-page reference support
- ✅ Comprehensive items schema with weapon/armor/magic columns
- ✅ FK-based polymorphic relationships
- ✅ All tests passing (116 tests, 473 assertions)

See previous plan for details on Tasks 1-15.

---

## Phase 5: Foundation Setup (Tasks 16-18)

### Task 16: Configure CORS and FULLTEXT Indexes

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_fulltext_indexes.php`
- Update: `config/cors.php`
- Update: `bootstrap/app.php`
- Create: `tests/Feature/Api/CorsTest.php`

**Step 1: Create FULLTEXT index migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
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

**Step 3: Write failing CORS test**

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_api_returns_cors_headers(): void
    {
        $response = $this->options('/api/spells', [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'GET',
        ]);

        $response->assertStatus(200)
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Allow-Methods');
    }
}
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=CorsTest`
Expected: FAIL (CORS not configured)

**Step 5: Configure CORS**

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

Update `bootstrap/app.php` to ensure CORS middleware is applied:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \Illuminate\Http\Middleware\HandleCors::class,
    ]);
})
```

**Step 6: Run test**

Run: `docker-compose exec php php artisan test --filter=CorsTest`
Expected: PASS

**Step 7: Commit**

```bash
git add database/migrations/*_add_fulltext_indexes.php config/cors.php bootstrap/app.php tests/Feature/Api/CorsTest.php
git commit -m "feat: add FULLTEXT indexes and configure CORS for API"
```

---

### Task 17: Create Source and Lookup Models

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

    // Relationships (will be used by entities)
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

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=SourceTest`
Expected: PASS

**Step 5: Create lookup models**

Create the following models with same pattern (no timestamps, fillable arrays):
- `app/Models/SpellSchool.php`
- `app/Models/DamageType.php`
- `app/Models/Size.php`
- `app/Models/AbilityScore.php`
- `app/Models/Skill.php`
- `app/Models/ItemType.php`
- `app/Models/ItemProperty.php`

Each should have:
- `public $timestamps = false;`
- Proper `$fillable` arrays
- Relationships to entities that reference them (where applicable)

**Step 6: Commit**

```bash
git add app/Models/Source.php app/Models/SpellSchool.php app/Models/DamageType.php app/Models/Size.php app/Models/AbilityScore.php app/Models/Skill.php app/Models/ItemType.php app/Models/ItemProperty.php tests/Unit/Models/SourceTest.php
git commit -m "feat: add source and lookup models (timestamps disabled)"
```

---

### Task 18: Create Lookup Table APIs

**Files:**
- Create: `app/Http/Controllers/Api/SourceController.php`
- Create: `app/Http/Controllers/Api/SpellSchoolController.php`
- Update: `routes/api.php`
- Create: `tests/Feature/Api/LookupApiTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Source;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_all_sources(): void
    {
        $response = $this->getJson('/api/sources');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'code', 'name', 'publisher', 'publication_year', 'edition']
            ])
            ->assertJsonCount(6); // We have 6 sources seeded
    }

    public function test_can_get_all_spell_schools(): void
    {
        $response = $this->getJson('/api/spell-schools');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'code', 'name', 'description']
            ])
            ->assertJsonCount(8); // We have 8 schools seeded
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=LookupApiTest`
Expected: FAIL (routes/controllers don't exist)

**Step 3: Create controllers**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Source;

class SourceController extends Controller
{
    public function index()
    {
        return Source::all();
    }

    public function show(Source $source)
    {
        return $source;
    }
}
```

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SpellSchool;

class SpellSchoolController extends Controller
{
    public function index()
    {
        return SpellSchool::all();
    }

    public function show(SpellSchool $spellSchool)
    {
        return $spellSchool;
    }
}
```

Create similar controllers for: DamageType, Size, AbilityScore, Skill, ItemType, ItemProperty

**Step 4: Add routes**

Update `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\SourceController;
use App\Http\Controllers\Api\SpellSchoolController;
use App\Http\Controllers\Api\DamageTypeController;
use App\Http\Controllers\Api\SizeController;
use App\Http\Controllers\Api\AbilityScoreController;
use App\Http\Controllers\Api\SkillController;
use App\Http\Controllers\Api\ItemTypeController;

Route::prefix('v1')->group(function () {
    // Lookup tables
    Route::apiResource('sources', SourceController::class)->only(['index', 'show']);
    Route::apiResource('spell-schools', SpellSchoolController::class)->only(['index', 'show']);
    Route::apiResource('damage-types', DamageTypeController::class)->only(['index', 'show']);
    Route::apiResource('sizes', SizeController::class)->only(['index', 'show']);
    Route::apiResource('ability-scores', AbilityScoreController::class)->only(['index', 'show']);
    Route::apiResource('skills', SkillController::class)->only(['index', 'show']);
    Route::apiResource('item-types', ItemTypeController::class)->only(['index', 'show']);
});
```

**Step 5: Run test**

Run: `docker-compose exec php php artisan test --filter=LookupApiTest`
Expected: PASS

**Step 6: Commit**

```bash
git add app/Http/Controllers/Api/*Controller.php routes/api.php tests/Feature/Api/LookupApiTest.php
git commit -m "feat: add lookup table APIs for reference data"
```

---

## Phase 6: Spells - Complete Vertical Slice (Tasks 19-23)

### Task 19: Create Spell Model

**Files:**
- Create: `app/Models/Spell.php`
- Create: `tests/Unit/Models/SpellTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Spell;
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

    public function test_spell_does_not_use_timestamps(): void
    {
        $spell = new Spell();
        $this->assertFalse($spell->timestamps);
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellTest`
Expected: FAIL (class doesn't exist)

**Step 3: Create Spell model**

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

    public function scopeSearch($query, $searchTerm)
    {
        return $query->whereRaw(
            "MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)",
            [$searchTerm]
        );
    }
}
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/Spell.php tests/Unit/Models/SpellTest.php
git commit -m "feat: add Spell model with relationships and scopes"
```

---

### Task 20: Create Spell API

**Files:**
- Create: `app/Http/Resources/SpellResource.php`
- Create: `app/Http/Controllers/Api/SpellController.php`
- Update: `routes/api.php`
- Create: `tests/Feature/Api/SpellApiTest.php`

**Step 1: Write failing API test**

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
        $school = SpellSchool::first();
        $source = Source::first();

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

    public function test_can_search_spells(): void
    {
        $school = SpellSchool::first();
        $source = Source::first();

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
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellApiTest`
Expected: FAIL (routes/controllers don't exist)

**Step 3: Create SpellResource**

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
        ];
    }
}
```

**Step 4: Create SpellController**

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
        $spell->load(['spellSchool', 'source']);

        return new SpellResource($spell);
    }
}
```

**Step 5: Add routes**

Update `routes/api.php`:

```php
// Add after lookup routes
Route::apiResource('spells', SpellController::class)->only(['index', 'show']);
```

**Step 6: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellApiTest`
Expected: PASS

**Step 7: Commit**

```bash
git add app/Http/Resources/SpellResource.php app/Http/Controllers/Api/SpellController.php routes/api.php tests/Feature/Api/SpellApiTest.php
git commit -m "feat: add Spell API with full-text search and filtering"
```

---

### Task 21: Create Spell XML Parser

**Files:**
- Create: `app/Services/Parsers/SpellXmlParser.php`
- Create: `tests/Unit/Parsers/SpellXmlParserTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\SpellXmlParser;
use Tests\TestCase;

class SpellXmlParserTest extends TestCase
{
    public function test_parses_basic_spell_data(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Fireball</name>
        <level>3</level>
        <school>EV</school>
        <time>1 action</time>
        <range>150 feet</range>
        <components>V, S, M (a tiny ball of bat guano and sulfur)</components>
        <duration>Instantaneous</duration>
        <classes>Wizard, Sorcerer</classes>
        <text>A bright streak flashes from your pointing finger to a point you choose within range and then blossoms with a low roar into an explosion of flame.</text>
        <text>Source: Player's Handbook p. 241</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertCount(1, $spells);
        $this->assertEquals('Fireball', $spells[0]['name']);
        $this->assertEquals(3, $spells[0]['level']);
        $this->assertEquals('EV', $spells[0]['school']);
        $this->assertEquals('1 action', $spells[0]['casting_time']);
        $this->assertEquals('150 feet', $spells[0]['range']);
        $this->assertEquals('V, S, M', $spells[0]['components']);
        $this->assertEquals('a tiny ball of bat guano and sulfur', $spells[0]['material_components']);
        $this->assertEquals('Instantaneous', $spells[0]['duration']);
        $this->assertFalse($spells[0]['needs_concentration']);
        $this->assertFalse($spells[0]['is_ritual']);
        $this->assertEquals(['Wizard', 'Sorcerer'], $spells[0]['classes']);
        $this->assertEquals('PHB', $spells[0]['source_code']);
        $this->assertEquals('241', $spells[0]['source_pages']);
    }

    public function test_detects_concentration(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Invisibility</name>
        <level>2</level>
        <school>I</school>
        <time>1 action</time>
        <range>Touch</range>
        <components>V, S, M</components>
        <duration>Concentration, up to 1 hour</duration>
        <classes>Wizard</classes>
        <text>A creature you touch becomes invisible.</text>
        <text>Source: Player's Handbook p. 254</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertTrue($spells[0]['needs_concentration']);
    }

    public function test_detects_ritual(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Detect Magic</name>
        <level>1</level>
        <school>D</school>
        <ritual>YES</ritual>
        <time>1 action</time>
        <range>Self</range>
        <components>V, S</components>
        <duration>Concentration, up to 10 minutes</duration>
        <classes>Wizard</classes>
        <text>You sense the presence of magic.</text>
        <text>Source: Player's Handbook p. 231</text>
    </spell>
</compendium>
XML;

        $parser = new SpellXmlParser();
        $spells = $parser->parse($xml);

        $this->assertTrue($spells[0]['is_ritual']);
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellXmlParserTest`
Expected: FAIL (class doesn't exist)

**Step 3: Create SpellXmlParser**

```php
<?php

namespace App\Services\Parsers;

use SimpleXMLElement;

class SpellXmlParser
{
    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $spells = [];

        foreach ($xml->spell as $spellElement) {
            $spells[] = $this->parseSpell($spellElement);
        }

        return $spells;
    }

    private function parseSpell(SimpleXMLElement $element): array
    {
        $components = (string) $element->components;
        $materialComponents = null;

        // Extract material components from "V, S, M (materials)"
        if (preg_match('/M \(([^)]+)\)/', $components, $matches)) {
            $materialComponents = $matches[1];
            $components = preg_replace('/\s*\([^)]+\)/', '', $components);
        }

        $duration = (string) $element->duration;
        $needsConcentration = stripos($duration, 'concentration') !== false;

        $isRitual = isset($element->ritual) && strtoupper((string) $element->ritual) === 'YES';

        // Parse classes
        $classesString = (string) $element->classes;
        $classes = array_map('trim', explode(',', $classesString));

        // Parse description and source from text elements
        $description = '';
        $sourceCode = '';
        $sourcePages = '';

        foreach ($element->text as $text) {
            $textContent = (string) $text;
            if (preg_match('/Source:\s*([^p]+)\s*p\.\s*([\d,\s]+)/', $textContent, $matches)) {
                // Extract source book name and pages
                $sourceName = trim($matches[1]);
                $sourcePages = trim($matches[2]);

                // Map source name to code
                $sourceCode = $this->getSourceCode($sourceName);
            } else {
                $description .= $textContent . "\n\n";
            }
        }

        return [
            'name' => (string) $element->name,
            'level' => (int) $element->level,
            'school' => (string) $element->school,
            'casting_time' => (string) $element->time,
            'range' => (string) $element->range,
            'components' => $components,
            'material_components' => $materialComponents,
            'duration' => $duration,
            'needs_concentration' => $needsConcentration,
            'is_ritual' => $isRitual,
            'description' => trim($description),
            'higher_levels' => null, // TODO: Parse from description if present
            'classes' => $classes,
            'source_code' => $sourceCode,
            'source_pages' => $sourcePages,
        ];
    }

    private function getSourceCode(string $sourceName): string
    {
        $mapping = [
            "Player's Handbook" => 'PHB',
            'Dungeon Master\'s Guide' => 'DMG',
            'Monster Manual' => 'MM',
            'Xanathar\'s Guide to Everything' => 'XGE',
            'Tasha\'s Cauldron of Everything' => 'TCE',
            'Volo\'s Guide to Monsters' => 'VGTM',
        ];

        return $mapping[$sourceName] ?? 'PHB';
    }
}
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellXmlParserTest`
Expected: PASS

**Step 5: Commit**

```bash
git add app/Services/Parsers/SpellXmlParser.php tests/Unit/Parsers/SpellXmlParserTest.php
git commit -m "feat: add SpellXmlParser with concentration and ritual detection"
```

---

### Task 22: Create Spell Importer

**Files:**
- Create: `app/Services/Importers/SpellImporter.php`
- Create: `app/Console/Commands/ImportSpells.php`
- Create: `tests/Feature/Importers/SpellImporterTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Importers;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\Source;
use App\Services\Importers\SpellImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_spell_from_parsed_data(): void
    {
        $school = SpellSchool::where('code', 'EV')->first();
        $source = Source::where('code', 'PHB')->first();

        $this->assertNotNull($school);
        $this->assertNotNull($source);

        $spellData = [
            'name' => 'Fireball',
            'level' => 3,
            'school' => 'EV',
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'material_components' => 'a tiny ball of bat guano and sulfur',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes...',
            'higher_levels' => null,
            'classes' => ['Wizard', 'Sorcerer'],
            'source_code' => 'PHB',
            'source_pages' => '241',
        ];

        $importer = new SpellImporter();
        $spell = $importer->import($spellData);

        $this->assertInstanceOf(Spell::class, $spell);
        $this->assertEquals('Fireball', $spell->name);
        $this->assertEquals(3, $spell->level);
        $this->assertEquals($school->id, $spell->spell_school_id);
        $this->assertEquals($source->id, $spell->source_id);
        $this->assertEquals('241', $spell->source_pages);
        $this->assertFalse($spell->needs_concentration);
        $this->assertFalse($spell->is_ritual);

        $this->assertDatabaseHas('spells', [
            'name' => 'Fireball',
            'level' => 3,
            'source_id' => $source->id,
        ]);
    }
}
```

**Step 2: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellImporterTest`
Expected: FAIL (class doesn't exist)

**Step 3: Create SpellImporter**

```php
<?php

namespace App\Services\Importers;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\Source;
use Illuminate\Support\Facades\DB;

class SpellImporter
{
    public function import(array $spellData): Spell
    {
        // Lookup spell school by code
        $spellSchool = SpellSchool::where('code', $spellData['school'])->firstOrFail();

        // Lookup source by code
        $source = Source::where('code', $spellData['source_code'])->firstOrFail();

        // Create or update spell
        $spell = Spell::updateOrCreate(
            ['name' => $spellData['name']],
            [
                'level' => $spellData['level'],
                'spell_school_id' => $spellSchool->id,
                'casting_time' => $spellData['casting_time'],
                'range' => $spellData['range'],
                'components' => $spellData['components'],
                'material_components' => $spellData['material_components'],
                'duration' => $spellData['duration'],
                'needs_concentration' => $spellData['needs_concentration'],
                'is_ritual' => $spellData['is_ritual'],
                'description' => $spellData['description'],
                'higher_levels' => $spellData['higher_levels'],
                'source_id' => $source->id,
                'source_pages' => $spellData['source_pages'],
            ]
        );

        // TODO: Import class associations and spell effects in later tasks

        return $spell;
    }

    public function importFromFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $xmlContent = file_get_contents($filePath);
        $parser = new \App\Services\Parsers\SpellXmlParser();
        $spells = $parser->parse($xmlContent);

        $count = 0;
        foreach ($spells as $spellData) {
            $this->import($spellData);
            $count++;
        }

        return $count;
    }
}
```

**Step 4: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellImporterTest`
Expected: PASS

**Step 5: Create Artisan command**

```php
<?php

namespace App\Console\Commands;

use App\Services\Importers\SpellImporter;
use Illuminate\Console\Command;

class ImportSpells extends Command
{
    protected $signature = 'import:spells {file}';
    protected $description = 'Import spells from XML file';

    public function handle(SpellImporter $importer): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return self::FAILURE;
        }

        $this->info("Importing spells from {$file}...");

        try {
            $count = $importer->importFromFile($file);
            $this->info("Successfully imported {$count} spells.");
            $this->info("View via API: http://localhost/api/spells");
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
```

**Step 6: Run test**

Run: `docker-compose exec php php artisan test --filter=SpellImporterTest`
Expected: PASS

**Step 7: Commit**

```bash
git add app/Services/Importers/SpellImporter.php app/Console/Commands/ImportSpells.php tests/Feature/Importers/SpellImporterTest.php
git commit -m "feat: add SpellImporter and import:spells command"
```

---

### Task 23: Test Spell Import End-to-End

**Files:**
- Create: `tests/Integration/SpellImportToApiTest.php`

**Step 1: Write integration test**

```php
<?php

namespace Tests\Integration;

use App\Services\Importers\SpellImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellImportToApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_import_to_api_pipeline(): void
    {
        // Import from actual XML file
        $importer = new SpellImporter();
        $count = $importer->importFromFile(base_path('import-files/spells-phb.xml'));

        $this->assertGreaterThan(0, $count, 'Should import at least one spell');

        // Verify via API
        $response = $this->getJson('/api/spells');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'level',
                        'school',
                        'casting_time',
                        'range',
                        'components',
                        'duration',
                        'needs_concentration',
                        'is_ritual',
                        'description',
                        'source',
                        'source_pages',
                    ]
                ]
            ]);

        // Test search
        $searchResponse = $this->getJson('/api/spells?search=fireball');
        $searchResponse->assertStatus(200);

        if ($searchResponse->json('meta.total') > 0) {
            $spell = $searchResponse->json('data.0');
            $this->assertStringContainsStringIgnoringCase('fire', $spell['name'] . ' ' . $spell['description']);
        }
    }
}
```

**Step 2: Run integration test**

Run: `docker-compose exec php php artisan test --filter=SpellImportToApiTest`
Expected: PASS (validates entire spell pipeline)

**Step 3: Manually test import command**

Run: `docker-compose exec php php artisan import:spells import-files/spells-phb.xml`
Expected: Success message with count

**Step 4: Verify via API**

Run: `curl http://localhost/api/spells | jq`
Expected: JSON response with imported spells

**Step 5: Commit**

```bash
git add tests/Integration/SpellImportToApiTest.php
git commit -m "test: add spell import-to-API integration test"
```

---

## Phase 7: Items - Complete Vertical Slice (Tasks 24-28)

### Task 24: Create Item Model

Follow same pattern as Task 19 (Spell Model):
- Create `app/Models/Item.php`
- Relationships: itemType, damageType, source, properties, abilities
- Scopes: type, rarity, attunement, search
- Tests: `tests/Unit/Models/ItemTest.php`

**Commit message:** "feat: add Item model with comprehensive weapon/armor/magic fields"

---

### Task 25: Create Item API

Follow same pattern as Task 20 (Spell API):
- Create `app/Http/Resources/ItemResource.php`
- Create `app/Http/Controllers/Api/ItemController.php`
- Update `routes/api.php`
- Tests: `tests/Feature/Api/ItemApiTest.php`
- Filter by: type, rarity, attunement, search

**Commit message:** "feat: add Item API with search and filtering"

---

### Task 26: Create Item XML Parser

Follow same pattern as Task 21 (Spell Parser):
- Create `app/Services/Parsers/ItemXmlParser.php`
- Parse: weapon stats, armor stats, magic properties, cost, weight
- Tests: `tests/Unit/Parsers/ItemXmlParserTest.php`

**Commit message:** "feat: add ItemXmlParser for weapons, armor, and magic items"

---

### Task 27: Create Item Importer

Follow same pattern as Task 22 (Spell Importer):
- Create `app/Services/Importers/ItemImporter.php`
- Create `app/Console/Commands/ImportItems.php`
- Tests: `tests/Feature/Importers/ItemImporterTest.php`

**Commit message:** "feat: add ItemImporter and import:items command"

---

### Task 28: Test Item Import End-to-End

Follow same pattern as Task 23:
- Create `tests/Integration/ItemImportToApiTest.php`
- Import from `import-files/items-base-phb.xml`
- Verify via API
- Manual test: `php artisan import:items import-files/items-base-phb.xml`

**Commit message:** "test: add item import-to-API integration test"

---

## Phase 8: Races - Complete Vertical Slice (Tasks 29-33)

### Task 29: Create Race Model

- Create `app/Models/Race.php`
- Relationships: source, abilityScoreBonuses, skillProficiencies
- Scopes: size, search
- Tests: `tests/Unit/Models/RaceTest.php`

**Commit message:** "feat: add Race model with ability bonuses and proficiencies"

---

### Task 30: Create Race API

- Create `app/Http/Resources/RaceResource.php`
- Create `app/Http/Controllers/Api/RaceController.php`
- Include ability score bonuses and skill proficiencies in response
- Tests: `tests/Feature/Api/RaceApiTest.php`

**Commit message:** "feat: add Race API with nested bonuses and proficiencies"

---

### Task 31: Create Race XML Parser

- Create `app/Services/Parsers/RaceXmlParser.php`
- Parse ability modifiers, skill proficiencies, traits
- Tests: `tests/Unit/Parsers/RaceXmlParserTest.php`

**Commit message:** "feat: add RaceXmlParser with ability and skill parsing"

---

### Task 32: Create Race Importer

- Create `app/Services/Importers/RaceImporter.php`
- Create `app/Console/Commands/ImportRaces.php`
- Create polymorphic ability_score_bonuses and skill_proficiencies records
- Tests: `tests/Feature/Importers/RaceImporterTest.php`

**Commit message:** "feat: add RaceImporter with polymorphic relationships"

---

### Task 33: Test Race Import End-to-End

- Create `tests/Integration/RaceImportToApiTest.php`
- Import from `import-files/races-phb.xml`
- Verify bonuses and proficiencies via API

**Commit message:** "test: add race import-to-API integration test"

---

## Phase 9: Backgrounds - Complete Vertical Slice (Tasks 34-38)

### Task 34: Create Background Model

**Commit message:** "feat: add Background model with skill proficiencies"

### Task 35: Create Background API

**Commit message:** "feat: add Background API with search and filtering"

### Task 36: Create Background XML Parser

**Commit message:** "feat: add BackgroundXmlParser"

### Task 37: Create Background Importer

**Commit message:** "feat: add BackgroundImporter and import:backgrounds command"

### Task 38: Test Background Import End-to-End

**Commit message:** "test: add background import-to-API integration test"

---

## Phase 10: Feats - Complete Vertical Slice (Tasks 39-43)

Follow same vertical slice pattern as previous entities.

**Commit messages:**
- "feat: add Feat model with prerequisites"
- "feat: add Feat API with search and filtering"
- "feat: add FeatXmlParser"
- "feat: add FeatImporter and import:feats command"
- "test: add feat import-to-API integration test"

---

## Phase 11: Classes - Complete Vertical Slice (Tasks 44-48)

**Note:** Classes are more complex due to subclass relationships, level progression, features, and counters.

**Commit messages:**
- "feat: add ClassModel with subclass relationships and progression"
- "feat: add Class API with subclasses and features"
- "feat: add ClassXmlParser with subclass support"
- "feat: add ClassImporter with progression tables"
- "test: add class import-to-API integration test"

---

## Phase 12: Monsters - Complete Vertical Slice (Tasks 49-53)

**Note:** Monsters are the most complex with stat blocks, traits, actions, legendary actions, spellcasting.

**Commit messages:**
- "feat: add Monster model with complete stat block"
- "feat: add Monster API with traits and actions"
- "feat: add MonsterXmlParser with stat block parsing"
- "feat: add MonsterImporter with traits and actions"
- "test: add monster import-to-API integration test"

---

## Phase 13: Polish and Documentation (Tasks 54-56)

### Task 54: Create import:all Command

**Files:**
- Create: `app/Console/Commands/ImportAll.php`

Import all entities in correct order with progress reporting.

**Commit message:** "feat: add import:all command to import entire compendium"

---

### Task 55: API Documentation

**Files:**
- Create: `docs/api/openapi.yaml`
- Create: `docs/api/README.md`

Document all endpoints, query parameters, response structures, examples.

**Commit message:** "docs: add comprehensive API documentation with OpenAPI spec"

---

### Task 56: Update README

**Files:**
- Update: `README.md`

Document:
- Quick start guide
- Import commands usage
- API endpoints and examples
- Search functionality
- CORS configuration
- Database schema decisions

**Commit message:** "docs: add comprehensive README with usage examples"

---

## Summary

**Total Tasks:** 56

**Phases:**
- Phase 1-4: Database Schema (COMPLETE - 15 tasks)
- Phase 5: Foundation (3 tasks) - CORS, search, lookups
- Phase 6: Spells (5 tasks) - First complete vertical slice
- Phase 7: Items (5 tasks) - Second vertical slice
- Phase 8: Races (5 tasks) - Third vertical slice
- Phase 9: Backgrounds (5 tasks) - Fourth vertical slice
- Phase 10: Feats (5 tasks) - Fifth vertical slice
- Phase 11: Classes (5 tasks) - Complex vertical slice
- Phase 12: Monsters (5 tasks) - Most complex vertical slice
- Phase 13: Polish (3 tasks) - Commands and docs

**Key Benefits of Vertical Slicing:**
- ✅ Spells fully working after ~1 day (Task 23)
- ✅ Items fully working after ~2 days (Task 28)
- ✅ Early validation of XML → Database → API pipeline
- ✅ Incremental value delivery
- ✅ Catch schema mismatches immediately
- ✅ Each entity is complete and testable before moving on

**Estimated Implementation:** 5-6 days with vertical slices

**Next Steps:** Start with Task 16 (CORS and FULLTEXT indexes)

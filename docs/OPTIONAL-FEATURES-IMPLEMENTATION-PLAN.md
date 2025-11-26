# Optional Features Implementation Plan

**Date:** 2025-11-26
**Status:** Ready for Implementation
**Priority:** Critical

---

## Overview

Implement a new `OptionalFeature` entity to store class customization options from D&D 5e:

| Feature Type | Class | Example |
|-------------|-------|---------|
| Eldritch Invocation | Warlock | Agonizing Blast, Devil's Sight |
| Elemental Discipline | Monk (Way of Four Elements) | Breath of Winter, Water Whip |
| Maneuver | Fighter (Battle Master) | Riposte, Disarming Attack |
| Metamagic | Sorcerer | Twinned Spell, Quickened Spell |
| Fighting Style | Fighter, Paladin, Ranger | Archery, Defense, Dueling |
| Artificer Infusion | Artificer | Enhanced Weapon, Repeating Shot |
| Rune | Fighter (Rune Knight) | Fire Rune, Frost Rune |
| Arcane Shot | Fighter (Arcane Archer) | Shadow Arrow, Banishing Arrow |

**Source Files:**
- `import-files/optionalfeatures-phb.xml` (73 spell + 6 feat entries)
- `import-files/optionalfeatures-tce.xml` (39 spell + 7 feat entries)
- `import-files/optionalfeatures-xge.xml` (22 spell + 22 feat entries)

---

## Architecture Decisions

### 1. New Entity (Not Feat Extension)
Optional features are semantically different from Feats:
- **Feats:** Universal, replace ASI, any class can take
- **Optional Features:** Class-locked, part of class progression, don't replace anything

### 2. Simple N:M for Class Association (Not Polymorphic)
Optional features only belong to `CharacterClass` - no need for polymorphic complexity.

### 3. Reuse `random_tables` for Scaling Data
Add `resource_cost` column to `random_table_entries` instead of creating new table.
(See `docs/TECH-DEBT.md` for future rename consideration)

### 4. Reuse Existing Polymorphic Tables
- `entity_sources` - for source citations
- `entity_prerequisites` - for level/feature/spell prerequisites

### 5. Laravel Naming Conventions
Following Laravel's pivot table naming conventions:
- **Table name:** Alphabetical order of singular model names → `class_optional_feature`
- **Column order:** Follows table name → `class_id` before `optional_feature_id`
- **Pivot Model:** Named after table → `ClassOptionalFeature extends Pivot`

Existing project examples:
- `class_spells` (class < spell, singular)
- `item_property` (item < property, singular)

### 6. Reusable Concerns & Traits

**Importer Concerns (reuse directly):**
| Concern | Purpose | Usage |
|---------|---------|-------|
| `GeneratesSlugs` | Generate URL-friendly slugs | ✅ Use as-is |
| `CachesLookupTables` | Cache lookup queries (Source, CharacterClass) | ✅ Use as-is |
| `ImportsSources` | Import EntitySource records | ✅ Use as-is |
| `ImportsPrerequisites` | Import EntityPrerequisite records | ✅ Use as-is |
| `ImportsRandomTablesFromText` | Import RandomTable from text | ⚠️ Adapt for roll elements |

**Parser Concerns (reuse directly):**
| Concern | Purpose | Usage |
|---------|---------|-------|
| `ParsesSourceCitations` | Parse "Source: Book p. XX" text | ✅ Use as-is |
| `ParsesRolls` | Parse `<roll>` XML elements | ✅ Use as-is |

**Note:** We'll need to extend `ParsesRolls` or create a new method to handle resource_cost in roll descriptions.

---

## Database Schema

### Migration 1: `create_optional_features_table`

```php
Schema::create('optional_features', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('name');
    $table->string('feature_type');  // eldritch_invocation, maneuver, etc.

    // Requirements
    $table->unsignedTinyInteger('level_requirement')->nullable();
    $table->string('prerequisite_text')->nullable();  // Original text for display

    // Content
    $table->text('description');

    // Spell-like properties (for Elemental Disciplines, Arcane Shots)
    $table->string('casting_time')->nullable();
    $table->string('range')->nullable();
    $table->string('duration')->nullable();
    $table->string('school')->nullable();  // EV, EN, T, A, C, D, I, N

    // Resource costs
    $table->string('resource_type')->nullable();  // ki_points, sorcery_points, etc.
    $table->unsignedTinyInteger('resource_cost')->nullable();

    $table->timestamps();

    // Indexes
    $table->index('feature_type');
    $table->index('level_requirement');
    $table->index('resource_type');
});
```

### Migration 2: `create_class_optional_feature_table`

```php
// Laravel convention: alphabetical order, singular (class < optional_feature)
Schema::create('class_optional_feature', function (Blueprint $table) {
    $table->id();
    $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
    $table->foreignId('optional_feature_id')->constrained()->cascadeOnDelete();
    $table->string('subclass_name')->nullable();  // "Way of the Four Elements", "Battle Master"
    $table->timestamps();

    // Prevent duplicates
    $table->unique(
        ['class_id', 'optional_feature_id', 'subclass_name'],
        'class_opt_feat_subclass_unique'
    );

    // Indexes for lookups
    $table->index('class_id');
    $table->index('optional_feature_id');
    $table->index('subclass_name');
});
```

### Migration 3: `add_resource_cost_to_random_table_entries`

```php
Schema::table('random_table_entries', function (Blueprint $table) {
    $table->unsignedTinyInteger('resource_cost')->nullable()
        ->after('level')
        ->comment('Resource cost for this entry (ki points, sorcery points, etc.)');
});
```

---

## Enums

### `App\Enums\OptionalFeatureType`

```php
<?php

namespace App\Enums;

enum OptionalFeatureType: string
{
    case ELDRITCH_INVOCATION = 'eldritch_invocation';
    case ELEMENTAL_DISCIPLINE = 'elemental_discipline';
    case MANEUVER = 'maneuver';
    case METAMAGIC = 'metamagic';
    case FIGHTING_STYLE = 'fighting_style';
    case ARTIFICER_INFUSION = 'artificer_infusion';
    case RUNE = 'rune';
    case ARCANE_SHOT = 'arcane_shot';

    public function label(): string
    {
        return match ($this) {
            self::ELDRITCH_INVOCATION => 'Eldritch Invocation',
            self::ELEMENTAL_DISCIPLINE => 'Elemental Discipline',
            self::MANEUVER => 'Maneuver',
            self::METAMAGIC => 'Metamagic',
            self::FIGHTING_STYLE => 'Fighting Style',
            self::ARTIFICER_INFUSION => 'Artificer Infusion',
            self::RUNE => 'Rune',
            self::ARCANE_SHOT => 'Arcane Shot',
        };
    }

    public function defaultClassName(): ?string
    {
        return match ($this) {
            self::ELDRITCH_INVOCATION => 'Warlock',
            self::ELEMENTAL_DISCIPLINE => 'Monk',
            self::MANEUVER => 'Fighter',
            self::METAMAGIC => 'Sorcerer',
            self::FIGHTING_STYLE => null,  // Multiple classes
            self::ARTIFICER_INFUSION => 'Artificer',
            self::RUNE => 'Fighter',
            self::ARCANE_SHOT => 'Fighter',
        };
    }

    public function defaultSubclassName(): ?string
    {
        return match ($this) {
            self::ELEMENTAL_DISCIPLINE => 'Way of the Four Elements',
            self::MANEUVER => 'Battle Master',
            self::RUNE => 'Rune Knight',
            self::ARCANE_SHOT => 'Arcane Archer',
            default => null,
        };
    }
}
```

### `App\Enums\ResourceType`

```php
<?php

namespace App\Enums;

enum ResourceType: string
{
    case KI_POINTS = 'ki_points';
    case SORCERY_POINTS = 'sorcery_points';
    case SUPERIORITY_DIE = 'superiority_die';
    case CHARGES = 'charges';
    case SPELL_SLOT = 'spell_slot';

    public function label(): string
    {
        return match ($this) {
            self::KI_POINTS => 'Ki Points',
            self::SORCERY_POINTS => 'Sorcery Points',
            self::SUPERIORITY_DIE => 'Superiority Die',
            self::CHARGES => 'Charges',
            self::SPELL_SLOT => 'Spell Slot',
        };
    }
}
```

---

## Models

### `App\Models\OptionalFeature`

```php
<?php

namespace App\Models;

use App\Enums\OptionalFeatureType;
use App\Enums\ResourceType;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Scout\Searchable;
use Spatie\Tags\HasTags;

class OptionalFeature extends BaseModel
{
    use HasTags, Searchable;

    protected $fillable = [
        'slug',
        'name',
        'feature_type',
        'level_requirement',
        'prerequisite_text',
        'description',
        'casting_time',
        'range',
        'duration',
        'school',
        'resource_type',
        'resource_cost',
    ];

    protected $casts = [
        'feature_type' => OptionalFeatureType::class,
        'resource_type' => ResourceType::class,
        'level_requirement' => 'integer',
        'resource_cost' => 'integer',
    ];

    // Relationships
    public function classes(): BelongsToMany
    {
        // Laravel convention: alphabetical table name (class_optional_feature)
        // Column order follows table name: class_id first, optional_feature_id second
        return $this->belongsToMany(CharacterClass::class, 'class_optional_feature')
            ->withPivot('subclass_name')
            ->withTimestamps();
    }

    public function classPivots(): HasMany
    {
        return $this->hasMany(ClassOptionalFeature::class);
    }

    public function rolls(): MorphMany
    {
        return $this->morphMany(RandomTable::class, 'reference');
    }

    public function sources(): MorphMany
    {
        return $this->morphMany(EntitySource::class, 'reference');
    }

    public function prerequisites(): MorphMany
    {
        return $this->morphMany(EntityPrerequisite::class, 'reference');
    }

    // Scopes
    public function scopeOfType($query, OptionalFeatureType|string $type)
    {
        $value = $type instanceof OptionalFeatureType ? $type->value : $type;
        return $query->where('feature_type', $value);
    }

    public function scopeForClass($query, int|CharacterClass $class)
    {
        $classId = $class instanceof CharacterClass ? $class->id : $class;
        return $query->whereHas('classes', fn($q) => $q->where('classes.id', $classId));
    }

    // Computed
    public function getHasSpellMechanicsAttribute(): bool
    {
        return $this->casting_time !== null || $this->range !== null;
    }

    // Scout Searchable
    public function toSearchableArray(): array
    {
        $this->loadMissing(['classes', 'classPivots', 'sources.source', 'tags']);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'feature_type' => $this->feature_type?->value,
            'level_requirement' => $this->level_requirement,
            'prerequisite_text' => $this->prerequisite_text,
            'description' => $this->description,
            'resource_type' => $this->resource_type?->value,
            'resource_cost' => $this->resource_cost,
            'has_spell_mechanics' => $this->has_spell_mechanics,
            'class_slugs' => $this->classes->pluck('slug')->unique()->values()->all(),
            'class_names' => $this->classes->pluck('name')->unique()->values()->all(),
            'subclass_names' => $this->classPivots->pluck('subclass_name')->filter()->unique()->values()->all(),
            'source_codes' => $this->sources->pluck('source.code')->unique()->values()->all(),
            'tag_slugs' => $this->tags->pluck('slug')->all(),
        ];
    }

    public function searchableAs(): string
    {
        return config('scout.prefix') . 'optional_features';
    }

    public function searchableOptions(): array
    {
        return [
            'filterableAttributes' => [
                'id',
                'slug',
                'feature_type',
                'level_requirement',
                'resource_type',
                'resource_cost',
                'has_spell_mechanics',
                'class_slugs',
                'subclass_names',
                'source_codes',
                'tag_slugs',
            ],
            'sortableAttributes' => [
                'name',
                'level_requirement',
                'resource_cost',
            ],
            'searchableAttributes' => [
                'name',
                'description',
                'prerequisite_text',
                'class_names',
                'subclass_names',
            ],
        ];
    }
}
```

### `App\Models\ClassOptionalFeature` (Pivot Model)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot model for class_optional_feature table.
 *
 * Laravel convention: table name is alphabetical (class < optional_feature)
 * Model name follows table: ClassOptionalFeature
 */
class ClassOptionalFeature extends Pivot
{
    protected $table = 'class_optional_feature';

    public $incrementing = true;  // We have an id column

    protected $fillable = [
        'class_id',
        'optional_feature_id',
        'subclass_name',
    ];

    public function characterClass(): BelongsTo
    {
        return $this->belongsTo(CharacterClass::class, 'class_id');
    }

    public function optionalFeature(): BelongsTo
    {
        return $this->belongsTo(OptionalFeature::class);
    }
}
```

---

## Parser

### `App\Services\Parsers\OptionalFeatureXmlParser`

**Reuses existing parser concerns:**
```php
use App\Services\Parsers\Concerns\ParsesSourceCitations;  // parseSourceCitations()
use App\Services\Parsers\Concerns\ParsesRolls;            // parseRollElements()

class OptionalFeatureXmlParser
{
    use ParsesSourceCitations;  // Reuse source citation parsing
    use ParsesRolls;            // Reuse <roll> element parsing
    // ...
}
```

**Key responsibilities:**
1. Parse both `<spell>` and `<feat>` XML formats
2. Merge duplicate entries (same name appears in both formats)
3. Extract feature type from name prefix (`Invocation:`, `Maneuver:`, etc.)
4. Parse resource cost from `<components>` tag
5. Parse level requirement from prerequisite text
6. Parse class/subclass from `<classes>` tag
7. Use `ParsesRolls` trait for `<roll>` elements
8. Use `ParsesSourceCitations` trait for source parsing

**Feature Type Detection:**
```php
private function detectFeatureType(string $name): OptionalFeatureType
{
    return match (true) {
        str_starts_with($name, 'Invocation:') => OptionalFeatureType::ELDRITCH_INVOCATION,
        str_starts_with($name, 'Elemental Discipline:') => OptionalFeatureType::ELEMENTAL_DISCIPLINE,
        str_starts_with($name, 'Maneuver:') => OptionalFeatureType::MANEUVER,
        str_starts_with($name, 'Metamagic:') => OptionalFeatureType::METAMAGIC,
        str_starts_with($name, 'Fighting Style:') => OptionalFeatureType::FIGHTING_STYLE,
        str_starts_with($name, 'Infusion:') => OptionalFeatureType::ARTIFICER_INFUSION,
        str_starts_with($name, 'Rune:') => OptionalFeatureType::RUNE,
        str_starts_with($name, 'Arcane Shot:') => OptionalFeatureType::ARCANE_SHOT,
        default => throw new \InvalidArgumentException("Unknown feature type: {$name}"),
    };
}
```

**Resource Cost Parsing:**
```php
// From <components>V, S, M (2 ki points)</components>
// Or <components>(1 sorcery point)</components>
private function parseResourceCost(string $components): array
{
    if (preg_match('/\((\d+)\s+(ki points?|sorcery points?)\)/i', $components, $matches)) {
        $type = match (strtolower($matches[2])) {
            'ki point', 'ki points' => ResourceType::KI_POINTS,
            'sorcery point', 'sorcery points' => ResourceType::SORCERY_POINTS,
        };
        return ['type' => $type, 'cost' => (int) $matches[1]];
    }
    return ['type' => null, 'cost' => null];
}
```

**Roll Parsing (extends ParsesRolls trait):**
```php
/**
 * Parse roll elements with resource cost extraction.
 * Extends ParsesRolls::parseRollElements() to add resource_cost.
 */
private function parseRollsWithResourceCost(SimpleXMLElement $element): array
{
    // Use trait method for base parsing
    $rolls = $this->parseRollElements($element);

    // Enhance with resource_cost from description
    return array_map(function ($roll) {
        $resourceCost = null;
        if ($roll['description'] && preg_match('/(\d+)\s+Ki Points?/i', $roll['description'], $m)) {
            $resourceCost = (int) $m[1];
        }
        return array_merge($roll, [
            'dice' => $roll['formula'],
            'resource_cost' => $resourceCost,
        ]);
    }, $rolls);
}
```

**Class Association Parsing:**
```php
// From <classes>Monk (Way of the Four Elements)</classes>
// Or <classes>Fighter (Arcane Archer): Arcane Shot</classes>
// Or <classes>Eldritch Invocations</classes>
private function parseClassAssociation(string $classesTag): array
{
    // Handle pseudo-class names
    $classMap = [
        'Eldritch Invocations' => ['class' => 'Warlock', 'subclass' => null],
        'Maneuver Options' => ['class' => 'Fighter', 'subclass' => 'Battle Master'],
        'Metamagic Options' => ['class' => 'Sorcerer', 'subclass' => null],
        'Artificer Infusions' => ['class' => 'Artificer', 'subclass' => null],
    ];

    if (isset($classMap[$classesTag])) {
        return $classMap[$classesTag];
    }

    // Parse "Class (Subclass)" or "Class (Subclass): Feature Type"
    if (preg_match('/^(\w+)\s*\(([^)]+)\)/', $classesTag, $matches)) {
        return ['class' => $matches[1], 'subclass' => $matches[2]];
    }

    return ['class' => $classesTag, 'subclass' => null];
}
```

---

## Importer

### `App\Services\Importers\OptionalFeatureImporter`

```php
<?php

namespace App\Services\Importers;

use App\Enums\OptionalFeatureType;
use App\Models\CharacterClass;
use App\Models\OptionalFeature;
use App\Services\Importers\Concerns\CachesLookupTables;
use App\Services\Importers\Concerns\GeneratesSlugs;
use App\Services\Importers\Concerns\ImportsPrerequisites;
use App\Services\Importers\Concerns\ImportsSources;
use App\Services\Parsers\OptionalFeatureXmlParser;

class OptionalFeatureImporter extends BaseImporter
{
    // Reuse existing concerns - no custom code needed for these!
    use CachesLookupTables;      // cachedFind() for CharacterClass lookup
    use GeneratesSlugs;           // generateSlug() - inherited from BaseImporter but explicit is clearer
    use ImportsPrerequisites;     // importEntityPrerequisites()
    use ImportsSources;           // importEntitySources()

    protected function importEntity(array $data): OptionalFeature
    {
        // 1. Upsert optional feature using inherited generateSlug()
        $feature = OptionalFeature::updateOrCreate(
            ['slug' => $this->generateSlug($data['name'])],
            [
                'name' => $data['name'],
                'feature_type' => $data['feature_type'],
                'level_requirement' => $data['level_requirement'],
                'prerequisite_text' => $data['prerequisite_text'],
                'description' => $data['description'],
                'casting_time' => $data['casting_time'],
                'range' => $data['range'],
                'duration' => $data['duration'],
                'school' => $data['school'],
                'resource_type' => $data['resource_type'],
                'resource_cost' => $data['resource_cost'],
            ]
        );

        // 2. Clear existing relationships
        $feature->classes()->detach();
        $feature->rolls()->delete();
        // sources and prerequisites cleared by their respective trait methods

        // 3. Attach class associations using cached lookup
        $this->attachClasses($feature, $data['class_associations']);

        // 4. Import rolls (damage scaling) - custom for OptionalFeature
        $this->importRolls($feature, $data['rolls']);

        // 5. Import sources using ImportsSources trait
        $this->importEntitySources($feature, $data['sources']);

        // 6. Import structured prerequisites using ImportsPrerequisites trait
        $this->importEntityPrerequisites($feature, $data['prerequisites']);

        $feature->refresh();
        return $feature;
    }

    /**
     * Attach class associations to an optional feature.
     * Uses CachesLookupTables for efficient CharacterClass lookup.
     */
    private function attachClasses(OptionalFeature $feature, array $associations): void
    {
        foreach ($associations as $assoc) {
            // Use cachedFind from CachesLookupTables trait
            // Note: cachedFind normalizes to uppercase, so we query by name directly
            $class = CharacterClass::where('name', $assoc['class'])
                ->whereNull('parent_class_id')  // Base class only
                ->first();

            if ($class) {
                $feature->classes()->attach($class->id, [
                    'subclass_name' => $assoc['subclass'],
                ]);
            }
        }
    }

    /**
     * Import roll/damage scaling data for an optional feature.
     * Creates RandomTable + RandomTableEntry records with resource_cost.
     */
    private function importRolls(OptionalFeature $feature, array $rolls): void
    {
        foreach ($rolls as $rollData) {
            $table = $feature->rolls()->create([
                'table_name' => $rollData['description'],
                'dice_type' => $this->extractDiceType($rollData['dice']),
                'description' => null,
            ]);

            $table->entries()->create([
                'result_text' => $rollData['dice'],
                'level' => $rollData['level'] ?? null,
                'resource_cost' => $rollData['resource_cost'] ?? null,
                'sort_order' => 0,
            ]);
        }
    }

    /**
     * Extract dice type from formula (e.g., "8d8" -> "d8").
     */
    private function extractDiceType(string $dice): string
    {
        if (preg_match('/d(\d+)/', $dice, $matches)) {
            return 'd' . $matches[1];
        }
        return $dice;
    }

    protected function getParser(): object
    {
        return new OptionalFeatureXmlParser();
    }
}
```

---

## API Layer

### Controller: `App\Http\Controllers\Api\OptionalFeatureController`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\OptionalFeatureIndexRequest;
use App\Http\Requests\OptionalFeatureShowRequest;
use App\Http\Resources\OptionalFeatureResource;
use App\Models\OptionalFeature;
use App\Services\OptionalFeatureSearchService;

class OptionalFeatureController extends Controller
{
    /**
     * List optional features with filtering.
     *
     * @queryParam q string Search term. Example: "agonizing"
     * @queryParam filter string Meilisearch filter. Example: "feature_type = eldritch_invocation"
     * @queryParam sort string Sort field. Example: "name:asc"
     * @queryParam per_page int Results per page. Example: 20
     */
    public function index(OptionalFeatureIndexRequest $request, OptionalFeatureSearchService $service)
    {
        $dto = OptionalFeatureSearchDTO::fromRequest($request);
        $features = $service->search($dto);

        return OptionalFeatureResource::collection($features);
    }

    /**
     * Get a single optional feature.
     */
    public function show(OptionalFeatureShowRequest $request, OptionalFeature $optionalFeature)
    {
        $optionalFeature->load([
            'classes',
            'classPivots',
            'rolls.entries',
            'sources.source',
            'prerequisites.prerequisite',
            'tags',
        ]);

        return new OptionalFeatureResource($optionalFeature);
    }
}
```

### Routes

```php
// routes/api.php

// Primary endpoints
Route::get('/optional-features', [OptionalFeatureController::class, 'index']);
Route::get('/optional-features/{optionalFeature:slug}', [OptionalFeatureController::class, 'show']);

// Nested route (optional - adds convenience)
Route::get('/classes/{class:slug}/optional-features', [ClassOptionalFeatureController::class, 'index']);

// Lookup endpoint
Route::get('/lookups/optional-feature-types', [OptionalFeatureTypeController::class, 'index']);
```

---

## Fighting Style Class Associations

Fighting Styles work for multiple classes. Create these pivot entries:

| Fighting Style | Fighter | Paladin | Ranger |
|---------------|---------|---------|--------|
| Archery | Yes | No | Yes |
| Defense | Yes | Yes | Yes |
| Dueling | Yes | Yes | Yes |
| Great Weapon Fighting | Yes | Yes | No |
| Protection | Yes | Yes | No |
| Two-Weapon Fighting | Yes | No | Yes |
| Blind Fighting (TCE) | Yes | Yes | Yes |
| Interception (TCE) | Yes | Yes | No |
| Thrown Weapon Fighting (TCE) | Yes | No | Yes |
| Unarmed Fighting (TCE) | Yes | No | No |
| Blessed Warrior (TCE) | No | Yes | No |
| Druidic Warrior (TCE) | No | No | Yes |
| Superior Technique (TCE) | Yes | No | No |

---

## Prerequisite Mapping

Using `EntityPrerequisite` polymorphic table:

| Prerequisite Text | prerequisite_type | prerequisite_id | minimum_value |
|-------------------|-------------------|-----------------|---------------|
| "5th level Warlock" | `CharacterClass` | warlock.id | 5 |
| "7th level Warlock" | `CharacterClass` | warlock.id | 7 |
| "15th level" | `CharacterClass` | (same as feature's class) | 15 |
| "Pact of the Blade feature" | `ClassFeature` | pact_blade.id | null |
| "Pact of the Tome feature" | `ClassFeature` | pact_tome.id | null |
| "Eldritch Blast cantrip" | `Spell` | eldritch_blast.id | null |
| "hex spell or warlock curse" | `Spell` | hex.id | null |

---

## Implementation Phases

### Phase 1: Database & Models (~2 hours)
- [ ] Create migration: `optional_features` table
- [ ] Create migration: `class_optional_feature` pivot table (Laravel convention: alphabetical)
- [ ] Create migration: Add `resource_cost` to `random_table_entries`
- [ ] Create `OptionalFeatureType` enum
- [ ] Create `ResourceType` enum
- [ ] Create `OptionalFeature` model
- [ ] Create `ClassOptionalFeature` pivot model (extends Pivot)
- [ ] Run migrations

### Phase 2: Parser & Importer (~3 hours)
- [ ] Create `OptionalFeatureXmlParser`
- [ ] Create `OptionalFeatureImporter`
- [ ] Create `import:optional-features` Artisan command
- [ ] Test import with PHB file
- [ ] Test import with TCE file
- [ ] Test import with XGE file

### Phase 3: API Layer (~2 hours)
- [ ] Create `OptionalFeatureResource`
- [ ] Create `OptionalFeatureIndexRequest`
- [ ] Create `OptionalFeatureShowRequest`
- [ ] Create `OptionalFeatureSearchDTO`
- [ ] Create `OptionalFeatureSearchService`
- [ ] Create `OptionalFeatureController`
- [ ] Add routes
- [ ] Create lookup controller for feature types

### Phase 4: Meilisearch (~1 hour)
- [ ] Configure index settings
- [ ] Run `scout:import`
- [ ] Verify filtering works

### Phase 5: Tests (~3 hours)
- [ ] `OptionalFeatureTest` - Model relationships
- [ ] `OptionalFeatureApiTest` - API endpoints
- [ ] `OptionalFeatureImporterTest` - XML parsing
- [ ] `OptionalFeatureSearchTest` - Meilisearch filtering

### Phase 6: Documentation (~1 hour)
- [ ] Add PHPDoc to controller
- [ ] Update API-EXAMPLES.md
- [ ] Update PROJECT-STATUS.md
- [ ] Create session handover

---

## Estimated Total: 10-12 hours

---

## Success Criteria

1. All 3 XML files import successfully
2. ~130 optional features created
3. Fighting Styles linked to multiple classes
4. API returns proper filtering by type, class, level
5. Meilisearch indexes and filters work
6. All tests pass
7. Documentation updated

---

**Ready to implement!**

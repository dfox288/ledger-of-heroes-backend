# API Enhancements - Phase 1: Essential Filters

**Date:** 2025-11-20
**Branch:** `feature/api-filters-phase1` (or work on main)
**Status:** Ready for implementation
**Estimated Duration:** 8-12 hours
**Priority:** HIGH - Enables character builder applications

---

## Overview

Implement essential filtering capabilities that enable character builder applications to query entities by prerequisites, proficiencies, languages, and add nested class spell list endpoint.

**Current State:**
- Basic filtering exists (level, school, size, rarity)
- Advanced filters missing (prerequisites, proficiencies, languages)
- No nested resource endpoints
- Cannot answer queries like "feats for Dwarves" or "wizard spells"

**Target State:**
- ✅ Filter feats/items by prerequisites
- ✅ Filter entities by proficiencies granted
- ✅ Filter races/backgrounds by languages
- ✅ Nested class spell list endpoint
- ✅ All filters tested with feature tests
- ✅ Documentation updated

---

## Phase 1 Features

### 1. Filter by Prerequisites 🔥
**Endpoints:** `/api/v1/feats`, `/api/v1/items`
**Queries:**
- `?prerequisite_race=dwarf` - Feats requiring Dwarf race
- `?prerequisite_ability=strength&min_value=13` - Feats requiring STR 13+
- `?has_prerequisites=false` - Feats without prerequisites
- `?min_strength=15` - Items requiring STR 15+ (backward compat)

### 2. Filter by Proficiencies 🔥
**Endpoints:** `/api/v1/races`, `/api/v1/backgrounds`, `/api/v1/feats`, `/api/v1/classes`
**Queries:**
- `?grants_proficiency=longsword` - Entities granting longsword proficiency
- `?grants_skill=insight` - Entities granting Insight skill
- `?grants_proficiency_type=martial` - Entities granting martial weapon proficiency
- `?grants_saving_throw=dexterity` - Classes granting DEX saves

### 3. Filter by Languages 🔥
**Endpoints:** `/api/v1/races`, `/api/v1/backgrounds`
**Queries:**
- `?speaks_language=elvish` - Races/backgrounds with Elvish
- `?language_choice_count=2` - Entities granting 2 language choices
- `?grants_languages=true` - Entities granting any languages

### 4. Class Spell Lists 🔥
**Endpoint:** `/api/v1/classes/{class}/spells`
**Queries:**
- `/api/v1/classes/wizard/spells` - All wizard spells
- `/api/v1/classes/wizard/spells?level=3` - 3rd level wizard spells
- `/api/v1/classes/fighter-eldritch-knight/spells` - Subclass spells
- Supports all existing spell filters (school, concentration, etc.)

---

## Implementation Plan

### Scaffolding

#### Task 0.1: Create feature branch (Optional)
```bash
git checkout -b feature/api-filters-phase1
```

#### Task 0.2: Verify environment
```bash
docker compose exec php php artisan --version  # Laravel 12.x
docker compose exec php php -v                  # PHP 8.4
```

---

## Part 1: Model Scopes (Business Logic)

### Task 1.1: Add Feat model scopes
**File:** `app/Models/Feat.php`

**Add these scope methods:**

```php
/**
 * Scope: Filter by prerequisite race
 * Usage: Feat::wherePrerequisiteRace('dwarf')->get()
 */
public function scopeWherePrerequisiteRace($query, string $raceName)
{
    return $query->whereHas('prerequisites', function ($q) use ($raceName) {
        $q->where('prerequisite_type', Race::class)
          ->whereHas('prerequisite', function ($raceQuery) use ($raceName) {
              $raceQuery->where('name', 'LIKE', "%{$raceName}%");
          });
    });
}

/**
 * Scope: Filter by prerequisite ability score
 * Usage: Feat::wherePrerequisiteAbility('strength', 13)->get()
 */
public function scopeWherePrerequisiteAbility($query, string $abilityName, ?int $minValue = null)
{
    return $query->whereHas('prerequisites', function ($q) use ($abilityName, $minValue) {
        $q->where('prerequisite_type', AbilityScore::class)
          ->whereHas('prerequisite', function ($abilityQuery) use ($abilityName) {
              $abilityQuery->where('code', strtoupper($abilityName))
                           ->orWhere('name', 'LIKE', "%{$abilityName}%");
          });

        if ($minValue !== null) {
            $q->where('minimum_value', '>=', $minValue);
        }
    });
}

/**
 * Scope: Filter by presence of prerequisites
 * Usage: Feat::withOrWithoutPrerequisites(false)->get() // feats without prereqs
 */
public function scopeWithOrWithoutPrerequisites($query, bool $hasPrerequisites)
{
    if ($hasPrerequisites) {
        return $query->has('prerequisites');
    }

    return $query->doesntHave('prerequisites');
}

/**
 * Scope: Filter by prerequisite proficiency
 * Usage: Feat::wherePrerequisiteProficiency('medium armor')->get()
 */
public function scopeWherePrerequisiteProficiency($query, string $proficiencyName)
{
    return $query->whereHas('prerequisites', function ($q) use ($proficiencyName) {
        $q->where('prerequisite_type', ProficiencyType::class)
          ->whereHas('prerequisite', function ($profQuery) use ($proficiencyName) {
              $profQuery->where('name', 'LIKE', "%{$proficiencyName}%");
          });
    });
}
```

**Verification:**
```bash
docker compose exec php php artisan tinker
>>> Feat::wherePrerequisiteRace('dwarf')->count()
>>> Feat::wherePrerequisiteAbility('strength', 13)->count()
>>> Feat::withOrWithoutPrerequisites(false)->count()
```

---

### Task 1.2: Add Item model scopes
**File:** `app/Models/Item.php`

**Add these scope methods:**

```php
/**
 * Scope: Filter by minimum strength requirement
 * Usage: Item::whereMinStrength(15)->get()
 */
public function scopeWhereMinStrength($query, int $minStrength)
{
    // Support both old column and new prerequisite system
    return $query->where(function ($q) use ($minStrength) {
        $q->where('strength_requirement', '>=', $minStrength)
          ->orWhereHas('prerequisites', function ($prereqQuery) use ($minStrength) {
              $prereqQuery->where('prerequisite_type', AbilityScore::class)
                          ->whereHas('prerequisite', function ($abilityQuery) {
                              $abilityQuery->where('code', 'STR');
                          })
                          ->where('minimum_value', '>=', $minStrength);
          });
    });
}

/**
 * Scope: Filter by any prerequisite
 * Usage: Item::hasPrerequisites()->get()
 */
public function scopeHasPrerequisites($query)
{
    return $query->where(function ($q) {
        $q->whereNotNull('strength_requirement')
          ->orHas('prerequisites');
    });
}
```

**Verification:**
```bash
docker compose exec php php artisan tinker
>>> Item::whereMinStrength(15)->count()
>>> Item::hasPrerequisites()->count()
```

---

### Task 1.3: Add proficiency scopes to Race model
**File:** `app/Models/Race.php`

**Add these scope methods:**

```php
/**
 * Scope: Filter by granted proficiency name
 * Usage: Race::grantsProficiency('longsword')->get()
 */
public function scopeGrantsProficiency($query, string $proficiencyName)
{
    return $query->whereHas('proficiencies', function ($q) use ($proficiencyName) {
        $q->where('proficiency_name', 'LIKE', "%{$proficiencyName}%")
          ->orWhereHas('proficiencyType', function ($typeQuery) use ($proficiencyName) {
              $typeQuery->where('name', 'LIKE', "%{$proficiencyName}%");
          });
    });
}

/**
 * Scope: Filter by granted skill proficiency
 * Usage: Race::grantsSkill('insight')->get()
 */
public function scopeGrantsSkill($query, string $skillName)
{
    return $query->whereHas('proficiencies', function ($q) use ($skillName) {
        $q->where('proficiency_type', 'skill')
          ->whereHas('skill', function ($skillQuery) use ($skillName) {
              $skillQuery->where('name', 'LIKE', "%{$skillName}%");
          });
    });
}

/**
 * Scope: Filter by proficiency type category
 * Usage: Race::grantsProficiencyType('martial')->get()
 */
public function scopeGrantsProficiencyType($query, string $categoryOrName)
{
    return $query->whereHas('proficiencies', function ($q) use ($categoryOrName) {
        $q->whereHas('proficiencyType', function ($typeQuery) use ($categoryOrName) {
            $typeQuery->where('category', 'LIKE', "%{$categoryOrName}%")
                      ->orWhere('name', 'LIKE', "%{$categoryOrName}%");
        });
    });
}
```

**Verification:**
```bash
docker compose exec php php artisan tinker
>>> Race::grantsProficiency('longsword')->count()
>>> Race::grantsSkill('insight')->count()
>>> Race::grantsProficiencyType('martial')->count()
```

---

### Task 1.4: Add proficiency scopes to Background, Feat, CharacterClass models
**Files:**
- `app/Models/Background.php`
- `app/Models/Feat.php` (add to existing file)
- `app/Models/CharacterClass.php`

**Add same scope methods as Task 1.3** (copy-paste from Race model)

**Note:** These models use the same polymorphic `proficiencies` relationship, so the scope methods are identical.

---

### Task 1.5: Add language scopes to Race model
**File:** `app/Models/Race.php`

**Add these scope methods:**

```php
/**
 * Scope: Filter by spoken language
 * Usage: Race::speaksLanguage('elvish')->get()
 */
public function scopeSpeaksLanguage($query, string $languageName)
{
    return $query->whereHas('languages', function ($q) use ($languageName) {
        $q->where('is_choice', false)
          ->whereHas('language', function ($langQuery) use ($languageName) {
              $langQuery->where('name', 'LIKE', "%{$languageName}%");
          });
    });
}

/**
 * Scope: Filter by language choice count
 * Usage: Race::languageChoiceCount(2)->get()
 */
public function scopeLanguageChoiceCount($query, int $count)
{
    return $query->whereHas('languages', function ($q) use ($count) {
        $q->where('is_choice', true)
          ->where('choice_count', $count);
    });
}

/**
 * Scope: Filter entities that grant any languages
 * Usage: Race::grantsLanguages()->get()
 */
public function scopeGrantsLanguages($query)
{
    return $query->has('languages');
}
```

**Verification:**
```bash
docker compose exec php php artisan tinker
>>> Race::speaksLanguage('elvish')->count()
>>> Race::languageChoiceCount(2)->count()
>>> Race::grantsLanguages()->count()
```

---

### Task 1.6: Add language scopes to Background model
**File:** `app/Models/Background.php`

**Add same scope methods as Task 1.5** (copy-paste from Race model)

---

### Task 1.7: Add class spell relationship scope
**File:** `app/Models/CharacterClass.php`

**Add this method:**

```php
/**
 * Relationship: Spells available to this class
 */
public function spells()
{
    return $this->belongsToMany(Spell::class, 'class_spell', 'class_id', 'spell_id');
}
```

**Note:** This relationship should already exist. Verify it exists or add if missing.

**Verification:**
```bash
docker compose exec php php artisan tinker
>>> $wizard = CharacterClass::where('name', 'Wizard')->first()
>>> $wizard->spells()->count()
```

---

## Part 2: Controller Updates (API Layer)

### Task 2.1: Update FeatController with prerequisite filters
**File:** `app/Http/Controllers/Api/FeatController.php`

**Update `index()` method:**

```php
public function index(Request $request)
{
    $query = Feat::with(['sources.source', 'prerequisites.prerequisite']);

    // Existing search filter
    if ($request->has('search')) {
        $query->search($request->search);
    }

    // NEW: Filter by prerequisite race
    if ($request->has('prerequisite_race')) {
        $query->wherePrerequisiteRace($request->prerequisite_race);
    }

    // NEW: Filter by prerequisite ability score
    if ($request->has('prerequisite_ability')) {
        $minValue = $request->has('min_value') ? (int) $request->min_value : null;
        $query->wherePrerequisiteAbility($request->prerequisite_ability, $minValue);
    }

    // NEW: Filter by prerequisite proficiency
    if ($request->has('prerequisite_proficiency')) {
        $query->wherePrerequisiteProficiency($request->prerequisite_proficiency);
    }

    // NEW: Filter by presence of prerequisites
    if ($request->has('has_prerequisites')) {
        $query->withOrWithoutPrerequisites($request->boolean('has_prerequisites'));
    }

    // NEW: Filter by granted proficiency
    if ($request->has('grants_proficiency')) {
        $query->grantsProficiency($request->grants_proficiency);
    }

    // NEW: Filter by granted skill
    if ($request->has('grants_skill')) {
        $query->grantsSkill($request->grants_skill);
    }

    // Existing sorting
    $sortBy = $request->get('sort_by', 'name');
    $sortDirection = $request->get('sort_direction', 'asc');
    $query->orderBy($sortBy, $sortDirection);

    // Paginate
    $perPage = $request->get('per_page', 15);
    $feats = $query->paginate($perPage);

    return FeatResource::collection($feats);
}
```

---

### Task 2.2: Update ItemController with prerequisite filters
**File:** `app/Http/Controllers/Api/ItemController.php`

**Update `index()` method - add these filters:**

```php
// NEW: Filter by minimum strength requirement
if ($request->has('min_strength')) {
    $query->whereMinStrength((int) $request->min_strength);
}

// NEW: Filter by having any prerequisites
if ($request->has('has_prerequisites')) {
    if ($request->boolean('has_prerequisites')) {
        $query->hasPrerequisites();
    }
}
```

**Add after existing filters (rarity, magic, attunement), before sorting.**

---

### Task 2.3: Update RaceController with proficiency and language filters
**File:** `app/Http/Controllers/Api/RaceController.php`

**Update `index()` method - add these filters:**

```php
// NEW: Filter by granted proficiency
if ($request->has('grants_proficiency')) {
    $query->grantsProficiency($request->grants_proficiency);
}

// NEW: Filter by granted skill
if ($request->has('grants_skill')) {
    $query->grantsSkill($request->grants_skill);
}

// NEW: Filter by proficiency type/category
if ($request->has('grants_proficiency_type')) {
    $query->grantsProficiencyType($request->grants_proficiency_type);
}

// NEW: Filter by spoken language
if ($request->has('speaks_language')) {
    $query->speaksLanguage($request->speaks_language);
}

// NEW: Filter by language choice count
if ($request->has('language_choice_count')) {
    $query->languageChoiceCount((int) $request->language_choice_count);
}

// NEW: Filter entities granting any languages
if ($request->has('grants_languages')) {
    if ($request->boolean('grants_languages')) {
        $query->grantsLanguages();
    }
}
```

---

### Task 2.4: Update BackgroundController with proficiency and language filters
**File:** `app/Http/Controllers/Api/BackgroundController.php`

**Add same filters as Task 2.3** (proficiencies + languages)

**Current state:** BackgroundController may have minimal filtering. Add:
1. Search filter if missing
2. Proficiency filters (same as Race)
3. Language filters (same as Race)

---

### Task 2.5: Update ClassController with proficiency filters
**File:** `app/Http/Controllers/Api/ClassController.php`

**Update `index()` method - add these filters:**

```php
// NEW: Filter by granted proficiency
if ($request->has('grants_proficiency')) {
    $query->grantsProficiency($request->grants_proficiency);
}

// NEW: Filter by granted skill
if ($request->has('grants_skill')) {
    $query->grantsSkill($request->grants_skill);
}

// NEW: Filter by saving throw proficiency
if ($request->has('grants_saving_throw')) {
    $abilityName = $request->grants_saving_throw;
    $query->whereHas('proficiencies', function ($q) use ($abilityName) {
        $q->where('proficiency_type', 'saving_throw')
          ->whereHas('abilityScore', function ($abilityQuery) use ($abilityName) {
              $abilityQuery->where('code', strtoupper($abilityName))
                           ->orWhere('name', 'LIKE', "%{$abilityName}%");
          });
    });
}
```

---

### Task 2.6: Add nested class spell list endpoint
**File:** `app/Http/Controllers/Api/ClassController.php`

**Add new method:**

```php
/**
 * Get spells for a specific class
 *
 * @param CharacterClass $class
 * @param Request $request
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
public function spells(CharacterClass $class, Request $request)
{
    $query = $class->spells()
        ->with(['spellSchool', 'sources.source', 'effects.damageType', 'classes']);

    // Apply same filters as SpellController
    if ($request->has('search')) {
        $query->where(function ($q) use ($request) {
            $q->where('spells.name', 'LIKE', "%{$request->search}%")
              ->orWhere('spells.description', 'LIKE', "%{$request->search}%");
        });
    }

    if ($request->has('level')) {
        $query->where('spells.level', $request->level);
    }

    if ($request->has('school')) {
        $query->where('spells.spell_school_id', $request->school);
    }

    if ($request->has('concentration')) {
        $query->where('spells.needs_concentration', $request->boolean('concentration'));
    }

    if ($request->has('ritual')) {
        $query->where('spells.is_ritual', $request->boolean('ritual'));
    }

    // Sorting
    $sortBy = $request->get('sort_by', 'spells.name');
    $sortDirection = $request->get('sort_direction', 'asc');

    // Ensure we prefix with table name for pivot queries
    if (!str_contains($sortBy, '.')) {
        $sortBy = 'spells.' . $sortBy;
    }

    $query->orderBy($sortBy, $sortDirection);

    // Paginate
    $perPage = $request->get('per_page', 15);
    $spells = $query->paginate($perPage);

    return SpellResource::collection($spells);
}
```

---

### Task 2.7: Add route for class spell list
**File:** `routes/api.php`

**Add after existing class route:**

```php
// Classes
Route::apiResource('classes', ClassController::class)->only(['index', 'show']);

// NEW: Class spell list endpoint
Route::get('classes/{class}/spells', [ClassController::class, 'spells'])
    ->name('classes.spells');
```

**Note:** Route supports both ID and slug: `/classes/wizard/spells` or `/classes/12/spells`

---

## Part 3: Testing (TDD)

### Task 3.1: Test Feat prerequisite filters
**File:** `tests/Feature/Api/FeatFilterTest.php` (NEW)

**Create comprehensive feature tests:**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Feat;
use App\Models\ProficiencyType;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_filters_feats_by_prerequisite_race()
    {
        // Create Dwarf race
        $dwarf = Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);

        // Create feat requiring Dwarf
        $featWithPrereq = Feat::factory()->create(['name' => 'Dwarven Fortitude']);
        $featWithPrereq->prerequisites()->create([
            'prerequisite_type' => Race::class,
            'prerequisite_id' => $dwarf->id,
            'group_id' => 1,
        ]);

        // Create feat without prerequisites
        $featWithout = Feat::factory()->create(['name' => 'Alert']);

        // Test filter
        $response = $this->getJson('/api/v1/feats?prerequisite_race=dwarf');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Dwarven Fortitude');
    }

    #[Test]
    public function it_filters_feats_by_prerequisite_ability_score()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        $featWithStrPrereq = Feat::factory()->create(['name' => 'Grappler']);
        $featWithStrPrereq->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        $featWithout = Feat::factory()->create(['name' => 'Alert']);

        // Test filter by ability
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=strength');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // Test filter by ability + minimum value
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=strength&min_value=13');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // Test with too high minimum
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=strength&min_value=15');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_filters_feats_without_prerequisites()
    {
        $featWithPrereq = Feat::factory()->create(['name' => 'Grappler', 'prerequisites_text' => 'Strength 13 or higher']);
        $featWithPrereq->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => AbilityScore::where('code', 'STR')->first()->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        $featWithout = Feat::factory()->create(['name' => 'Alert', 'prerequisites_text' => null]);

        // Filter for feats WITHOUT prerequisites
        $response = $this->getJson('/api/v1/feats?has_prerequisites=false');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Alert');

        // Filter for feats WITH prerequisites
        $response = $this->getJson('/api/v1/feats?has_prerequisites=true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Grappler');
    }

    #[Test]
    public function it_filters_feats_by_granted_proficiency()
    {
        $featGrantingProf = Feat::factory()->create(['name' => 'Weapon Master']);
        $featGrantingProf->proficiencies()->create([
            'proficiency_name' => 'Longsword',
            'proficiency_type' => 'weapon',
        ]);

        $featWithout = Feat::factory()->create(['name' => 'Alert']);

        $response = $this->getJson('/api/v1/feats?grants_proficiency=longsword');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Weapon Master');
    }
}
```

---

### Task 3.2: Test Item prerequisite filters
**File:** `tests/Feature/Api/ItemFilterTest.php` (NEW)

**Create tests:**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\AbilityScore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_filters_items_by_minimum_strength_requirement()
    {
        $strength = AbilityScore::where('code', 'STR')->first();

        // Item with strength_requirement column (legacy)
        $plateArmor = Item::factory()->create([
            'name' => 'Plate Armor',
            'strength_requirement' => 15,
        ]);

        // Item with EntityPrerequisite (new system)
        $heavyShield = Item::factory()->create(['name' => 'Heavy Shield']);
        $heavyShield->prerequisites()->create([
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strength->id,
            'minimum_value' => 13,
            'group_id' => 1,
        ]);

        // Item without strength requirement
        $leatherArmor = Item::factory()->create([
            'name' => 'Leather Armor',
            'strength_requirement' => null,
        ]);

        // Test min_strength=15 (should get plate armor)
        $response = $this->getJson('/api/v1/items?min_strength=15');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Plate Armor');

        // Test min_strength=13 (should get both)
        $response = $this->getJson('/api/v1/items?min_strength=13');
        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_filters_items_with_prerequisites()
    {
        $itemWithPrereq = Item::factory()->create([
            'name' => 'Plate Armor',
            'strength_requirement' => 15,
        ]);

        $itemWithout = Item::factory()->create([
            'name' => 'Leather Armor',
            'strength_requirement' => null,
        ]);

        $response = $this->getJson('/api/v1/items?has_prerequisites=true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Plate Armor');
    }
}
```

---

### Task 3.3: Test Race proficiency and language filters
**File:** `tests/Feature/Api/RaceFilterTest.php` (NEW)

**Create tests:**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Language;
use App\Models\ProficiencyType;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RaceFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_filters_races_by_granted_proficiency()
    {
        $mountainDwarf = Race::factory()->create(['name' => 'Mountain Dwarf']);
        $mountainDwarf->proficiencies()->create([
            'proficiency_name' => 'Light Armor',
            'proficiency_type' => 'armor',
        ]);

        $elf = Race::factory()->create(['name' => 'Elf']);

        $response = $this->getJson('/api/v1/races?grants_proficiency=light armor');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Mountain Dwarf');
    }

    #[Test]
    public function it_filters_races_by_spoken_language()
    {
        $elvish = Language::where('name', 'Elvish')->first();

        $elf = Race::factory()->create(['name' => 'Elf']);
        $elf->languages()->create([
            'language_id' => $elvish->id,
            'is_choice' => false,
        ]);

        $dwarf = Race::factory()->create(['name' => 'Dwarf']);

        $response = $this->getJson('/api/v1/races?speaks_language=elvish');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Elf');
    }

    #[Test]
    public function it_filters_races_by_language_choice_count()
    {
        $halfElf = Race::factory()->create(['name' => 'Half-Elf']);
        $halfElf->languages()->create([
            'language_id' => null,
            'is_choice' => true,
            'choice_count' => 1,
        ]);

        $human = Race::factory()->create(['name' => 'Human']);
        $human->languages()->create([
            'language_id' => null,
            'is_choice' => true,
            'choice_count' => 2,
        ]);

        $dwarf = Race::factory()->create(['name' => 'Dwarf']);

        // Filter for races granting 1 language choice
        $response = $this->getJson('/api/v1/races?language_choice_count=1');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Half-Elf');

        // Filter for races granting 2 language choices
        $response = $this->getJson('/api/v1/races?language_choice_count=2');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Human');
    }

    #[Test]
    public function it_filters_races_granting_any_languages()
    {
        $elf = Race::factory()->create(['name' => 'Elf']);
        $elf->languages()->create([
            'language_id' => Language::where('name', 'Elvish')->first()->id,
            'is_choice' => false,
        ]);

        $dwarf = Race::factory()->create(['name' => 'Dwarf']);
        // No languages

        $response = $this->getJson('/api/v1/races?grants_languages=true');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Elf');
    }
}
```

---

### Task 3.4: Test class spell list endpoint
**File:** `tests/Feature/Api/ClassSpellListTest.php` (NEW)

**Create tests:**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassSpellListTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_returns_spells_for_a_class()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $spell1 = Spell::factory()->create(['name' => 'Fireball', 'level' => 3]);
        $spell2 = Spell::factory()->create(['name' => 'Magic Missile', 'level' => 1]);
        $spell3 = Spell::factory()->create(['name' => 'Cure Wounds', 'level' => 1]);

        // Attach spells to wizard
        $wizard->spells()->attach([$spell1->id, $spell2->id]);

        // spell3 is NOT a wizard spell

        $response = $this->getJson('/api/v1/classes/wizard/spells');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.name', 'Fireball');
        $response->assertJsonPath('data.1.name', 'Magic Missile');
    }

    #[Test]
    public function it_filters_class_spells_by_level()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $spell1 = Spell::factory()->create(['name' => 'Fireball', 'level' => 3]);
        $spell2 = Spell::factory()->create(['name' => 'Magic Missile', 'level' => 1]);
        $spell3 = Spell::factory()->create(['name' => 'Fly', 'level' => 3]);

        $wizard->spells()->attach([$spell1->id, $spell2->id, $spell3->id]);

        // Filter for level 3 spells only
        $response = $this->getJson('/api/v1/classes/wizard/spells?level=3');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $this->assertTrue(
            collect($response->json('data'))->pluck('name')->contains('Fireball')
        );
        $this->assertTrue(
            collect($response->json('data'))->pluck('name')->contains('Fly')
        );
    }

    #[Test]
    public function it_filters_class_spells_by_school()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        $evocation = SpellSchool::where('code', 'EV')->first();

        $spell1 = Spell::factory()->create([
            'name' => 'Fireball',
            'spell_school_id' => $evocation->id,
        ]);
        $spell2 = Spell::factory()->create(['name' => 'Magic Missile']);

        $wizard->spells()->attach([$spell1->id, $spell2->id]);

        $response = $this->getJson("/api/v1/classes/wizard/spells?school={$evocation->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.name', 'Fireball');
    }

    #[Test]
    public function it_supports_slug_routing_for_class_spells()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);
        $spell = Spell::factory()->create(['name' => 'Fireball']);
        $wizard->spells()->attach($spell->id);

        // Test with slug
        $response = $this->getJson('/api/v1/classes/wizard/spells');
        $response->assertOk();

        // Test with ID
        $response = $this->getJson("/api/v1/classes/{$wizard->id}/spells");
        $response->assertOk();
    }

    #[Test]
    public function it_paginates_class_spell_lists()
    {
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'slug' => 'wizard']);

        $spells = Spell::factory()->count(30)->create();
        $wizard->spells()->attach($spells->pluck('id'));

        $response = $this->getJson('/api/v1/classes/wizard/spells?per_page=10');

        $response->assertOk();
        $response->assertJsonCount(10, 'data');
        $response->assertJsonPath('meta.total', 30);
        $response->assertJsonPath('meta.per_page', 10);
    }
}
```

---

### Task 3.5: Run all tests
**Command:**
```bash
docker compose exec php php artisan test --filter=FilterTest
docker compose exec php php artisan test --filter=ClassSpellListTest
```

**Success Criteria:**
- All new filter tests pass
- No regressions in existing tests
- Test coverage for all new scopes

---

## Part 4: Quality Gates

### Task 4.1: Run full test suite
```bash
docker compose exec php php artisan test
```

**Expected:** All tests pass (502+ tests)

---

### Task 4.2: Format code
```bash
docker compose exec php ./vendor/bin/pint
```

**Expected:** All files formatted

---

### Task 4.3: Static analysis (optional)
```bash
docker compose exec php ./vendor/bin/phpstan analyze  # if configured
```

---

## Part 5: Documentation & Handoff

### Task 5.1: Update CLAUDE.md with new API features
**File:** `CLAUDE.md`

**Add to API Endpoints section:**

```markdown
### Advanced Filtering (NEW 2025-11-20)

**Prerequisite Filters:**
- `GET /api/v1/feats?prerequisite_race=dwarf` - Feats requiring Dwarf race
- `GET /api/v1/feats?prerequisite_ability=strength&min_value=13` - Feats requiring STR 13+
- `GET /api/v1/feats?has_prerequisites=false` - Feats without prerequisites
- `GET /api/v1/items?min_strength=15` - Items requiring STR 15+

**Proficiency Filters:**
- `GET /api/v1/races?grants_proficiency=longsword` - Races granting proficiency
- `GET /api/v1/feats?grants_skill=insight` - Feats granting skill proficiency
- `GET /api/v1/classes?grants_saving_throw=dexterity` - Classes with DEX saves

**Language Filters:**
- `GET /api/v1/races?speaks_language=elvish` - Races speaking Elvish
- `GET /api/v1/races?language_choice_count=2` - Races granting 2 language choices
- `GET /api/v1/backgrounds?grants_languages=true` - Backgrounds granting languages

**Nested Endpoints:**
- `GET /api/v1/classes/wizard/spells` - All wizard spells
- `GET /api/v1/classes/wizard/spells?level=3` - 3rd level wizard spells
- `GET /api/v1/classes/fighter-eldritch-knight/spells` - Subclass spell lists
```

---

### Task 5.2: Create handover document
**File:** `docs/SESSION-HANDOVER-2025-11-20-API-FILTERS-PHASE1.md`

**Contents:**
- Summary of features added
- Filter parameters documentation
- Test results
- Performance notes
- Next phase recommendations

---

### Task 5.3: Git commit
```bash
git add app/Models/ app/Http/Controllers/Api/ routes/api.php tests/Feature/Api/
git commit -m "$(cat <<'EOF'
feat: add Phase 1 API filtering capabilities

- Add prerequisite filters for Feats and Items
  * Filter by race, ability score, proficiency prerequisites
  * Support has_prerequisites boolean filter
  * Backward compatible with strength_requirement column

- Add proficiency filters for Races, Backgrounds, Feats, Classes
  * Filter by granted proficiency name
  * Filter by granted skill proficiency
  * Filter by proficiency type/category
  * Filter by saving throw proficiency (Classes)

- Add language filters for Races and Backgrounds
  * Filter by spoken language (fixed languages)
  * Filter by language choice count
  * Filter entities granting any languages

- Add nested class spell list endpoint
  * GET /api/v1/classes/{class}/spells
  * Supports all spell filters (level, school, concentration, etc.)
  * Works with both ID and slug routing
  * Paginated results

Testing:
- Add 4 new feature test classes (45+ tests)
- FeatFilterTest (prerequisite filtering)
- ItemFilterTest (prerequisite filtering)
- RaceFilterTest (proficiency + language filtering)
- ClassSpellListTest (nested endpoint)

Impact:
- Enables character builder applications
- "Show feats my character qualifies for"
- "Show wizard spells" without client-side filtering
- Essential for character management UIs

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
EOF
)"
```

---

## Success Criteria

Before marking Phase 1 complete:
- [ ] All model scopes added and tested in tinker
- [ ] All controllers updated with new filters
- [ ] Class spell list endpoint working
- [ ] 45+ new tests passing
- [ ] Full test suite passes (no regressions)
- [ ] Code formatted with Pint
- [ ] Documentation updated (CLAUDE.md)
- [ ] Git committed with clear message
- [ ] Handover document created

---

## Performance Notes

**Expected query performance:**
- Prerequisite filters: 1-2 additional JOINs (acceptable)
- Language filters: 1 JOIN via entity_languages (fast with indexes)
- Class spell lists: 1 JOIN via class_spell pivot (very fast)

**Optimization opportunities (future):**
- Add indexes on prerequisite_type, prerequisite_id
- Add indexes on proficiency_name, proficiency_type
- Cache class spell lists (rarely change)

---

## Rollback Plan

If issues arise:
```bash
git revert HEAD
docker compose exec php php artisan migrate:fresh --seed
```

---

## Next Phase Preview (Phase 2)

After Phase 1:
- Response caching for GET endpoints
- Rate limiting configuration
- Count endpoints (`/api/v1/spells/count`)
- Field selection (sparse fieldsets)
- Bulk fetch endpoints

---

**Plan Status:** ✅ Ready for implementation
**Estimated Duration:** 8-12 hours
**Risk Level:** LOW (additive changes, no breaking changes)

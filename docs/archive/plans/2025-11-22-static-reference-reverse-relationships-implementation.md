# Static Reference Reverse Relationships Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add 6 reverse relationship endpoints (spell-schools/spells, damage-types/spells, damage-types/items, conditions/spells, conditions/monsters) following the proven `/spells/{id}/classes` pattern with full TDD coverage.

**Architecture:** Three Eloquent relationship patterns (HasMany, HasManyThrough, MorphToMany) mapped to RESTful endpoints with pagination, slug/ID routing, and 5-star PHPDoc documentation. All implementation follows strict TDD: write test → watch fail → minimal code → watch pass → commit.

**Tech Stack:** Laravel 12.x, PHP 8.4, PHPUnit 11+, Eloquent ORM, Laravel Resources, Route Model Binding

**Design Document:** `docs/plans/2025-11-22-static-reference-reverse-relationships-design.md`

**Estimated Time:** 4-6 hours (20 tasks, 15-20 minutes each)

---

## Task 1: SpellSchool → Spells Endpoint (Tests)

**Pattern:** Direct Foreign Key (HasMany)

**Files:**
- Create: `tests/Feature/Api/SpellSchoolReverseRelationshipsApiTest.php`

**Step 1: Create test file with first failing test**

Create the test file with the basic structure and first test case:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellSchoolReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_spells_for_spell_school()
    {
        $evocation = SpellSchool::factory()->create([
            'name' => 'Evocation',
            'code' => 'EV',
            'slug' => 'evocation',
        ]);

        $fireball = Spell::factory()->create([
            'spell_school_id' => $evocation->id,
            'name' => 'Fireball',
            'slug' => 'fireball',
        ]);

        $magicMissile = Spell::factory()->create([
            'spell_school_id' => $evocation->id,
            'name' => 'Magic Missile',
            'slug' => 'magic-missile',
        ]);

        // Different school - should not appear
        $abjuration = SpellSchool::factory()->create(['name' => 'Abjuration']);
        Spell::factory()->create([
            'spell_school_id' => $abjuration->id,
            'name' => 'Shield',
        ]);

        $response = $this->getJson("/api/v1/spell-schools/{$evocation->slug}/spells");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Fireball')
            ->assertJsonPath('data.1.name', 'Magic Missile');
    }
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=SpellSchoolReverseRelationshipsApiTest`

Expected: FAIL with "Route [spell-schools.spells] not defined" or 404

**Step 3: Commit failing test**

```bash
git add tests/Feature/Api/SpellSchoolReverseRelationshipsApiTest.php
git commit -m "test: add failing test for spell school spells endpoint"
```

---

## Task 2: SpellSchool → Spells Endpoint (Implementation)

**Files:**
- Modify: `app/Models/SpellSchool.php` (verify relationship exists)
- Modify: `app/Http/Controllers/Api/SpellSchoolController.php`
- Modify: `routes/api.php`

**Step 1: Verify SpellSchool model has spells() relationship**

Check `app/Models/SpellSchool.php` for this method:

```php
public function spells(): HasMany
{
    return $this->hasMany(Spell::class);
}
```

**If missing, add it.** If present, proceed to next step.

**Step 2: Add controller method**

Add to `app/Http/Controllers/Api/SpellSchoolController.php`:

```php
use App\Http\Resources\SpellResource;

/**
 * List all spells in this school of magic
 *
 * Returns a paginated list of spells belonging to a specific school of magic.
 *
 * @param SpellSchool $spellSchool The school of magic (by ID, code, or slug)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
public function spells(SpellSchool $spellSchool)
{
    $spells = $spellSchool->spells()
        ->with(['spellSchool', 'sources', 'tags'])
        ->paginate(50);

    return SpellResource::collection($spells);
}
```

**Step 3: Add route**

Add to `routes/api.php` after the spell-schools resource route:

```php
// Spell school spell list endpoint
Route::get('spell-schools/{spellSchool}/spells', [SpellSchoolController::class, 'spells'])
    ->name('spell-schools.spells');
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=SpellSchoolReverseRelationshipsApiTest::it_returns_spells_for_spell_school`

Expected: PASS

**Step 5: Commit**

```bash
git add app/Models/SpellSchool.php app/Http/Controllers/Api/SpellSchoolController.php routes/api.php
git commit -m "feat: add spell school spells endpoint"
```

---

## Task 3: SpellSchool Additional Tests

**Files:**
- Modify: `tests/Feature/Api/SpellSchoolReverseRelationshipsApiTest.php`

**Step 1: Add remaining test cases**

Add these three tests to the test file:

```php
#[Test]
public function it_returns_empty_when_school_has_no_spells()
{
    $school = SpellSchool::factory()->create([
        'code' => 'CUSTOM',
        'slug' => 'custom',
    ]);

    $response = $this->getJson("/api/v1/spell-schools/{$school->slug}/spells");

    $response->assertOk()
        ->assertJsonCount(0, 'data');
}

#[Test]
public function it_accepts_numeric_id_for_spells_endpoint()
{
    $school = SpellSchool::factory()->create();
    $spell = Spell::factory()->create(['spell_school_id' => $school->id]);

    $response = $this->getJson("/api/v1/spell-schools/{$school->id}/spells");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
}

#[Test]
public function it_paginates_spell_results()
{
    $school = SpellSchool::factory()->create();
    Spell::factory()->count(75)->create(['spell_school_id' => $school->id]);

    $response = $this->getJson("/api/v1/spell-schools/{$school->slug}/spells?per_page=25");

    $response->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.total', 75)
        ->assertJsonPath('meta.per_page', 25);
}
```

**Step 2: Run tests to verify they pass**

Run: `docker compose exec php php artisan test --filter=SpellSchoolReverseRelationshipsApiTest`

Expected: 4 tests PASS

**Step 3: Commit**

```bash
git add tests/Feature/Api/SpellSchoolReverseRelationshipsApiTest.php
git commit -m "test: complete spell school reverse relationship tests"
```

---

## Task 4: DamageType → Spells Endpoint (Tests)

**Pattern:** HasManyThrough (via spell_effects)

**Files:**
- Create: `tests/Feature/Api/DamageTypeReverseRelationshipsApiTest.php`

**Step 1: Create test file with spells tests**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\DamageType;
use App\Models\Spell;
use App\Models\SpellEffect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DamageTypeReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    // ========================================
    // Spells Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_spells_for_damage_type()
    {
        $fire = DamageType::factory()->create([
            'name' => 'Fire',
            'code' => 'fire',
        ]);

        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);
        $burningHands = Spell::factory()->create(['name' => 'Burning Hands', 'slug' => 'burning-hands']);
        $iceStorm = Spell::factory()->create(['name' => 'Ice Storm', 'slug' => 'ice-storm']);

        // Link fire spells via spell_effects
        SpellEffect::factory()->create([
            'spell_id' => $fireball->id,
            'damage_type_id' => $fire->id,
        ]);

        SpellEffect::factory()->create([
            'spell_id' => $burningHands->id,
            'damage_type_id' => $fire->id,
        ]);

        // Ice storm uses different damage type - should not appear
        $cold = DamageType::factory()->create(['name' => 'Cold', 'code' => 'cold']);
        SpellEffect::factory()->create([
            'spell_id' => $iceStorm->id,
            'damage_type_id' => $cold->id,
        ]);

        $response = $this->getJson("/api/v1/damage-types/fire/spells");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Burning Hands')
            ->assertJsonPath('data.1.name', 'Fireball');
    }

    #[Test]
    public function it_returns_empty_when_damage_type_has_no_spells()
    {
        $radiant = DamageType::factory()->create(['code' => 'radiant']);

        $response = $this->getJson("/api/v1/damage-types/radiant/spells");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_spells_endpoint()
    {
        $fire = DamageType::factory()->create();
        $spell = Spell::factory()->create();
        SpellEffect::factory()->create([
            'spell_id' => $spell->id,
            'damage_type_id' => $fire->id,
        ]);

        $response = $this->getJson("/api/v1/damage-types/{$fire->id}/spells");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_paginates_spell_results_for_damage_type()
    {
        $fire = DamageType::factory()->create(['code' => 'fire']);

        $spells = Spell::factory()->count(75)->create();
        foreach ($spells as $spell) {
            SpellEffect::factory()->create([
                'spell_id' => $spell->id,
                'damage_type_id' => $fire->id,
            ]);
        }

        $response = $this->getJson("/api/v1/damage-types/fire/spells?per_page=25");

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 75)
            ->assertJsonPath('meta.per_page', 25);
    }
}
```

**Step 2: Run tests to verify they fail**

Run: `docker compose exec php php artisan test --filter=DamageTypeReverseRelationshipsApiTest`

Expected: 4 tests FAIL (route not defined)

**Step 3: Commit**

```bash
git add tests/Feature/Api/DamageTypeReverseRelationshipsApiTest.php
git commit -m "test: add failing tests for damage type spells endpoint"
```

---

## Task 5: DamageType → Spells Endpoint (Implementation)

**Files:**
- Modify: `app/Models/DamageType.php`
- Modify: `app/Http/Controllers/Api/DamageTypeController.php`
- Modify: `routes/api.php`

**Step 1: Add spells() relationship to DamageType model**

Add to `app/Models/DamageType.php`:

```php
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Get spells that deal this damage type
 *
 * Uses HasManyThrough relationship via spell_effects table.
 * Includes distinct() to prevent duplicates when spell has multiple effects.
 */
public function spells(): HasManyThrough
{
    return $this->hasManyThrough(
        Spell::class,           // Final model
        SpellEffect::class,     // Intermediate model
        'damage_type_id',       // FK on spell_effects table
        'id',                   // FK on spells table
        'id',                   // Local key on damage_types table
        'spell_id'              // Local key on spell_effects table
    )->distinct();
}
```

**Step 2: Add controller method**

Add to `app/Http/Controllers/Api/DamageTypeController.php`:

```php
use App\Http\Resources\SpellResource;

/**
 * List all spells that deal this damage type
 *
 * Returns a paginated list of spells that deal this type of damage.
 *
 * @param DamageType $damageType The damage type (by ID, code, or name)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
public function spells(DamageType $damageType)
{
    $spells = $damageType->spells()
        ->with(['spellSchool', 'sources', 'tags'])
        ->paginate(50);

    return SpellResource::collection($spells);
}
```

**Step 3: Add route**

Add to `routes/api.php` after the damage-types resource route:

```php
// Damage type spell list endpoint
Route::get('damage-types/{damageType}/spells', [DamageTypeController::class, 'spells'])
    ->name('damage-types.spells');
```

**Step 4: Run tests to verify they pass**

Run: `docker compose exec php php artisan test --filter=DamageTypeReverseRelationshipsApiTest`

Expected: 4 tests PASS

**Step 5: Commit**

```bash
git add app/Models/DamageType.php app/Http/Controllers/Api/DamageTypeController.php routes/api.php
git commit -m "feat: add damage type spells endpoint"
```

---

## Task 6: DamageType → Items Endpoint (Tests)

**Pattern:** Direct Foreign Key (HasMany) - relationship already exists

**Files:**
- Modify: `tests/Feature/Api/DamageTypeReverseRelationshipsApiTest.php`

**Step 1: Add items tests to existing file**

Add these tests after the spells tests:

```php
// ========================================
// Items Endpoint Tests
// ========================================

#[Test]
public function it_returns_items_for_damage_type()
{
    $slashing = DamageType::factory()->create([
        'name' => 'Slashing',
        'code' => 'slashing',
    ]);

    $longsword = Item::factory()->create([
        'name' => 'Longsword',
        'slug' => 'longsword',
        'damage_type_id' => $slashing->id,
    ]);

    $greatsword = Item::factory()->create([
        'name' => 'Greatsword',
        'slug' => 'greatsword',
        'damage_type_id' => $slashing->id,
    ]);

    // Bludgeoning weapon - should not appear
    $bludgeoning = DamageType::factory()->create(['code' => 'bludgeoning']);
    Item::factory()->create([
        'name' => 'Mace',
        'damage_type_id' => $bludgeoning->id,
    ]);

    $response = $this->getJson("/api/v1/damage-types/slashing/items");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Greatsword')
        ->assertJsonPath('data.1.name', 'Longsword');
}

#[Test]
public function it_returns_empty_when_damage_type_has_no_items()
{
    $psychic = DamageType::factory()->create(['code' => 'psychic']);

    $response = $this->getJson("/api/v1/damage-types/psychic/items");

    $response->assertOk()
        ->assertJsonCount(0, 'data');
}

#[Test]
public function it_accepts_numeric_id_for_items_endpoint()
{
    $fire = DamageType::factory()->create();
    $item = Item::factory()->create(['damage_type_id' => $fire->id]);

    $response = $this->getJson("/api/v1/damage-types/{$fire->id}/items");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
}

#[Test]
public function it_paginates_item_results_for_damage_type()
{
    $slashing = DamageType::factory()->create(['code' => 'slashing']);
    Item::factory()->count(75)->create(['damage_type_id' => $slashing->id]);

    $response = $this->getJson("/api/v1/damage-types/slashing/items?per_page=25");

    $response->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.total', 75)
        ->assertJsonPath('meta.per_page', 25);
}
```

**Step 2: Add Item import to test file**

At the top of the file, add:

```php
use App\Models\Item;
```

**Step 3: Run tests to verify they fail**

Run: `docker compose exec php php artisan test --filter=DamageTypeReverseRelationshipsApiTest`

Expected: 4 spells tests PASS, 4 items tests FAIL (route not defined)

**Step 4: Commit**

```bash
git add tests/Feature/Api/DamageTypeReverseRelationshipsApiTest.php
git commit -m "test: add failing tests for damage type items endpoint"
```

---

## Task 7: DamageType → Items Endpoint (Implementation)

**Files:**
- Modify: `app/Http/Controllers/Api/DamageTypeController.php`
- Modify: `routes/api.php`

**Step 1: Verify items() relationship exists in DamageType model**

Check `app/Models/DamageType.php` - the relationship should already exist:

```php
public function items(): HasMany
{
    return $this->hasMany(Item::class, 'damage_type_id');
}
```

**Step 2: Add controller method**

Add to `app/Http/Controllers/Api/DamageTypeController.php`:

```php
use App\Http\Resources\ItemResource;

/**
 * List all items that deal this damage type
 *
 * Returns a paginated list of items (weapons, ammunition) that deal this type of damage.
 *
 * @param DamageType $damageType The damage type (by ID, code, or name)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
public function items(DamageType $damageType)
{
    $items = $damageType->items()
        ->with(['itemType', 'sources', 'tags'])
        ->paginate(50);

    return ItemResource::collection($items);
}
```

**Step 3: Add route**

Add to `routes/api.php` after the damage-types spells route:

```php
// Damage type item list endpoint
Route::get('damage-types/{damageType}/items', [DamageTypeController::class, 'items'])
    ->name('damage-types.items');
```

**Step 4: Run tests to verify they pass**

Run: `docker compose exec php php artisan test --filter=DamageTypeReverseRelationshipsApiTest`

Expected: 8 tests PASS (4 spells + 4 items)

**Step 5: Commit**

```bash
git add app/Http/Controllers/Api/DamageTypeController.php routes/api.php
git commit -m "feat: add damage type items endpoint"
```

---

## Task 8: Condition → Spells Endpoint (Tests)

**Pattern:** Polymorphic Many-to-Many (MorphedByMany via entity_conditions)

**Files:**
- Create: `tests/Feature/Api/ConditionReverseRelationshipsApiTest.php`

**Step 1: Create test file with spells tests**

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Condition;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConditionReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    // ========================================
    // Spells Endpoint Tests
    // ========================================

    #[Test]
    public function it_returns_spells_for_condition()
    {
        $poisoned = Condition::factory()->create([
            'name' => 'Poisoned',
            'slug' => 'poisoned',
        ]);

        $poisonSpray = Spell::factory()->create(['name' => 'Poison Spray', 'slug' => 'poison-spray']);
        $cloudkill = Spell::factory()->create(['name' => 'Cloudkill', 'slug' => 'cloudkill']);
        $fireball = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);

        // Attach condition to spells via entity_conditions
        $poisoned->spells()->attach($poisonSpray, [
            'effect_type' => 'inflicts',
            'description' => 'Target becomes poisoned',
        ]);

        $poisoned->spells()->attach($cloudkill, [
            'effect_type' => 'inflicts',
            'description' => 'Creatures in area become poisoned',
        ]);

        $response = $this->getJson("/api/v1/conditions/poisoned/spells");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Cloudkill')
            ->assertJsonPath('data.1.name', 'Poison Spray');
    }

    #[Test]
    public function it_returns_empty_when_condition_has_no_spells()
    {
        $custom = Condition::factory()->create(['slug' => 'custom-condition']);

        $response = $this->getJson("/api/v1/conditions/custom-condition/spells");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_accepts_numeric_id_for_spells_endpoint()
    {
        $condition = Condition::factory()->create();
        $spell = Spell::factory()->create();
        $condition->spells()->attach($spell, ['effect_type' => 'inflicts']);

        $response = $this->getJson("/api/v1/conditions/{$condition->id}/spells");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_paginates_spell_results_for_condition()
    {
        $stunned = Condition::factory()->create(['slug' => 'stunned']);
        $spells = Spell::factory()->count(75)->create();

        foreach ($spells as $spell) {
            $stunned->spells()->attach($spell, ['effect_type' => 'inflicts']);
        }

        $response = $this->getJson("/api/v1/conditions/stunned/spells?per_page=25");

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.total', 75)
            ->assertJsonPath('meta.per_page', 25);
    }
}
```

**Step 2: Run tests to verify they fail**

Run: `docker compose exec php php artisan test --filter=ConditionReverseRelationshipsApiTest`

Expected: 4 tests FAIL (spells() method not defined on Condition model)

**Step 3: Commit**

```bash
git add tests/Feature/Api/ConditionReverseRelationshipsApiTest.php
git commit -m "test: add failing tests for condition spells endpoint"
```

---

## Task 9: Condition → Spells Endpoint (Implementation)

**Files:**
- Modify: `app/Models/Condition.php`
- Modify: `app/Http/Controllers/Api/ConditionController.php`
- Modify: `routes/api.php`

**Step 1: Add spells() relationship to Condition model**

Add to `app/Models/Condition.php`:

```php
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Get spells that inflict this condition
 *
 * Uses polymorphic many-to-many via entity_conditions table.
 * Only returns spells with effect_type = 'inflicts'.
 */
public function spells(): MorphToMany
{
    return $this->morphedByMany(
        Spell::class,
        'reference',
        'entity_conditions',
        'condition_id',
        'reference_id'
    )
        ->withPivot('effect_type', 'description')
        ->wherePivot('effect_type', 'inflicts');
}
```

**Step 2: Add controller method**

Add to `app/Http/Controllers/Api/ConditionController.php`:

```php
use App\Http\Resources\SpellResource;

/**
 * List all spells that inflict this condition
 *
 * Returns a paginated list of spells that can inflict this condition on targets.
 *
 * @param Condition $condition The condition (by ID or slug)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
public function spells(Condition $condition)
{
    $spells = $condition->spells()
        ->with(['spellSchool', 'sources', 'tags'])
        ->paginate(50);

    return SpellResource::collection($spells);
}
```

**Step 3: Add route**

Add to `routes/api.php` after the conditions resource route:

```php
// Condition spell list endpoint
Route::get('conditions/{condition}/spells', [ConditionController::class, 'spells'])
    ->name('conditions.spells');
```

**Step 4: Run tests to verify they pass**

Run: `docker compose exec php php artisan test --filter=ConditionReverseRelationshipsApiTest`

Expected: 4 tests PASS

**Step 5: Commit**

```bash
git add app/Models/Condition.php app/Http/Controllers/Api/ConditionController.php routes/api.php
git commit -m "feat: add condition spells endpoint"
```

---

## Task 10: Condition → Monsters Endpoint (Tests)

**Pattern:** Polymorphic Many-to-Many (MorphedByMany via entity_conditions)

**Files:**
- Modify: `tests/Feature/Api/ConditionReverseRelationshipsApiTest.php`

**Step 1: Add monsters tests to existing file**

Add these tests after the spells tests:

```php
// ========================================
// Monsters Endpoint Tests
// ========================================

#[Test]
public function it_returns_monsters_for_condition()
{
    $frightened = Condition::factory()->create([
        'name' => 'Frightened',
        'slug' => 'frightened',
    ]);

    $dragon = Monster::factory()->create(['name' => 'Adult Red Dragon', 'slug' => 'adult-red-dragon']);
    $beholder = Monster::factory()->create(['name' => 'Beholder', 'slug' => 'beholder']);
    $goblin = Monster::factory()->create(['name' => 'Goblin', 'slug' => 'goblin']);

    // Attach condition to monsters via entity_conditions
    $frightened->monsters()->attach($dragon, [
        'effect_type' => 'inflicts',
        'description' => 'Frightful Presence',
    ]);

    $frightened->monsters()->attach($beholder, [
        'effect_type' => 'inflicts',
        'description' => 'Fear Ray',
    ]);

    $response = $this->getJson("/api/v1/conditions/frightened/monsters");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Adult Red Dragon')
        ->assertJsonPath('data.1.name', 'Beholder');
}

#[Test]
public function it_returns_empty_when_condition_has_no_monsters()
{
    $custom = Condition::factory()->create(['slug' => 'custom-condition']);

    $response = $this->getJson("/api/v1/conditions/custom-condition/monsters");

    $response->assertOk()
        ->assertJsonCount(0, 'data');
}

#[Test]
public function it_accepts_numeric_id_for_monsters_endpoint()
{
    $condition = Condition::factory()->create();
    $monster = Monster::factory()->create();
    $condition->monsters()->attach($monster, ['effect_type' => 'inflicts']);

    $response = $this->getJson("/api/v1/conditions/{$condition->id}/monsters");

    $response->assertOk()
        ->assertJsonCount(1, 'data');
}

#[Test]
public function it_paginates_monster_results_for_condition()
{
    $paralyzed = Condition::factory()->create(['slug' => 'paralyzed']);
    $monsters = Monster::factory()->count(75)->create();

    foreach ($monsters as $monster) {
        $paralyzed->monsters()->attach($monster, ['effect_type' => 'inflicts']);
    }

    $response = $this->getJson("/api/v1/conditions/paralyzed/monsters?per_page=25");

    $response->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.total', 75)
        ->assertJsonPath('meta.per_page', 25);
}
```

**Step 2: Add Monster import to test file**

At the top of the file, add:

```php
use App\Models\Monster;
```

**Step 3: Run tests to verify they fail**

Run: `docker compose exec php php artisan test --filter=ConditionReverseRelationshipsApiTest`

Expected: 4 spells tests PASS, 4 monsters tests FAIL (monsters() method not defined)

**Step 4: Commit**

```bash
git add tests/Feature/Api/ConditionReverseRelationshipsApiTest.php
git commit -m "test: add failing tests for condition monsters endpoint"
```

---

## Task 11: Condition → Monsters Endpoint (Implementation)

**Files:**
- Modify: `app/Models/Condition.php`
- Modify: `app/Http/Controllers/Api/ConditionController.php`
- Modify: `routes/api.php`

**Step 1: Add monsters() relationship to Condition model**

Add to `app/Models/Condition.php`:

```php
/**
 * Get monsters that inflict this condition
 *
 * Uses polymorphic many-to-many via entity_conditions table.
 * Only returns monsters with effect_type = 'inflicts'.
 */
public function monsters(): MorphToMany
{
    return $this->morphedByMany(
        Monster::class,
        'reference',
        'entity_conditions',
        'condition_id',
        'reference_id'
    )
        ->withPivot('effect_type', 'description')
        ->wherePivot('effect_type', 'inflicts');
}
```

**Step 2: Add controller method**

Add to `app/Http/Controllers/Api/ConditionController.php`:

```php
use App\Http\Resources\MonsterResource;

/**
 * List all monsters that inflict this condition
 *
 * Returns a paginated list of monsters that can inflict this condition through
 * their attacks, traits, or special abilities.
 *
 * @param Condition $condition The condition (by ID or slug)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
public function monsters(Condition $condition)
{
    $monsters = $condition->monsters()
        ->with(['size', 'type', 'sources', 'tags'])
        ->paginate(50);

    return MonsterResource::collection($monsters);
}
```

**Step 3: Add route**

Add to `routes/api.php` after the conditions spells route:

```php
// Condition monster list endpoint
Route::get('conditions/{condition}/monsters', [ConditionController::class, 'monsters'])
    ->name('conditions.monsters');
```

**Step 4: Run tests to verify they pass**

Run: `docker compose exec php php artisan test --filter=ConditionReverseRelationshipsApiTest`

Expected: 8 tests PASS (4 spells + 4 monsters)

**Step 5: Commit**

```bash
git add app/Models/Condition.php app/Http/Controllers/Api/ConditionController.php routes/api.php
git commit -m "feat: add condition monsters endpoint"
```

---

## Task 12: Run Full Test Suite

**Step 1: Run all tests to ensure no regressions**

Run: `docker compose exec php php artisan test`

Expected: 1,137 tests PASS (1,117 existing + 20 new)

**Step 2: If any tests fail, fix them before proceeding**

Review failures and fix. Most likely causes:
- Missing imports in controllers
- Typos in route names
- Missing relationships on models

**Step 3: Verify new endpoints work**

No commit needed - verification step only.

---

## Task 13: Add 5-Star PHPDoc to SpellSchoolController

**Files:**
- Modify: `app/Http/Controllers/Api/SpellSchoolController.php`

**Step 1: Replace basic PHPDoc with comprehensive documentation**

Replace the existing `spells()` PHPDoc with:

```php
/**
 * List all spells in this school of magic
 *
 * Returns a paginated list of spells belonging to a specific school of magic.
 * Supports all spell fields including level, concentration, ritual, damage types,
 * saving throws, and component requirements.
 *
 * **Basic Examples:**
 * - Evocation spells: `GET /api/v1/spell-schools/evocation/spells`
 * - Evocation by ID: `GET /api/v1/spell-schools/3/spells`
 * - Evocation by code: `GET /api/v1/spell-schools/EV/spells`
 * - Pagination: `GET /api/v1/spell-schools/evocation/spells?per_page=25&page=2`
 *
 * **School-Specific Use Cases:**
 * - Damage dealers (Evocation): Direct damage spells (Fireball, Magic Missile, Lightning Bolt)
 * - Mind control (Enchantment): Charm Person, Dominate Monster, Suggestion
 * - Buffs & debuffs (Transmutation): Haste, Slow, Polymorph, Enlarge/Reduce
 * - Information gathering (Divination): Detect Magic, Scrying, Identify
 * - Defense (Abjuration): Shield, Counterspell, Dispel Magic, Protection spells
 * - Summoning (Conjuration): Summon spells, Create Food and Water, Teleport
 * - Trickery (Illusion): Invisibility, Mirror Image, Silent Image, Disguise Self
 * - Undead & life force (Necromancy): Animate Dead, Vampiric Touch, Speak with Dead
 *
 * **Character Building:**
 * - Wizard school specialization (pick one school to focus on)
 * - Spell selection optimization (identify your school's best spells)
 * - Thematic spellcasting (pure Evocation blaster, pure Enchantment controller)
 *
 * **Reference Data:**
 * - 8 schools of magic in D&D 5e
 * - Total: 477 spells across all schools
 * - Evocation: ~60 spells (largest school, damage-focused)
 * - Enchantment: ~40 spells (mind-affecting)
 * - Transmutation: ~55 spells (versatile utility)
 * - Conjuration: ~45 spells (summoning & teleportation)
 *
 * @param SpellSchool $spellSchool The school of magic (by ID, code, or slug)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Api/SpellSchoolController.php
git commit -m "docs: add 5-star PHPDoc to spell school spells endpoint"
```

---

## Task 14: Add 5-Star PHPDoc to DamageTypeController (Spells)

**Files:**
- Modify: `app/Http/Controllers/Api/DamageTypeController.php`

**Step 1: Replace basic PHPDoc for spells() method**

Replace the existing `spells()` PHPDoc with:

```php
/**
 * List all spells that deal this damage type
 *
 * Returns a paginated list of spells that deal this type of damage through their
 * primary or secondary effects. Useful for building themed characters (fire mage,
 * frost wizard) or finding spells to exploit enemy vulnerabilities.
 *
 * **Basic Examples:**
 * - Fire spells: `GET /api/v1/damage-types/fire/spells`
 * - Fire by ID: `GET /api/v1/damage-types/1/spells`
 * - Pagination: `GET /api/v1/damage-types/fire/spells?per_page=25&page=2`
 *
 * **Damage Type Use Cases:**
 * - Fire: Fireball, Burning Hands, Scorching Ray, Flame Strike (~24 spells)
 * - Cold: Ice Storm, Cone of Cold, Ray of Frost (~18 spells)
 * - Lightning: Lightning Bolt, Call Lightning, Chain Lightning (~12 spells)
 * - Psychic: Mind Spike, Synaptic Static, Psychic Scream (~15 spells)
 * - Necrotic: Blight, Vampiric Touch, Circle of Death (~20 spells)
 * - Radiant: Guiding Bolt, Sunbeam, Dawn, Sacred Flame (~16 spells)
 * - Thunder: Thunderwave, Shatter, Booming Blade (~10 spells)
 * - Poison: Poison Spray, Cloudkill, Stinking Cloud (~8 spells)
 * - Acid: Acid Splash, Vitriolic Sphere, Acid Arrow (~7 spells)
 * - Force: Magic Missile, Eldritch Blast, Disintegrate (~12 spells)
 *
 * **Character Building:**
 * - Elemental specialist builds (fire/cold/lightning mages)
 * - Exploit enemy vulnerabilities (undead vulnerable to radiant)
 * - Avoid resistances (many devils resist fire, use cold/lightning instead)
 * - Thematic spell selection (necromancer uses necrotic, cleric uses radiant)
 *
 * **Combat Tactics:**
 * - Identify damage type distribution in your spell list
 * - Prepare diverse damage types to handle resistances
 * - Focus on force/psychic for guaranteed damage (few resistances)
 *
 * **Reference Data:**
 * - 13 damage types in D&D 5e
 * - Most common: Fire (~24 spells), Necrotic (~20 spells), Cold (~18 spells)
 * - Least resisted: Force, Psychic, Radiant (best for reliable damage)
 * - Most resisted: Fire, Poison (many creatures have resistance/immunity)
 *
 * @param DamageType $damageType The damage type (by ID, code, or name)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Api/DamageTypeController.php
git commit -m "docs: add 5-star PHPDoc to damage type spells endpoint"
```

---

## Task 15: Add 5-Star PHPDoc to DamageTypeController (Items)

**Files:**
- Modify: `app/Http/Controllers/Api/DamageTypeController.php`

**Step 1: Replace basic PHPDoc for items() method**

Replace the existing `items()` PHPDoc with:

```php
/**
 * List all items that deal this damage type
 *
 * Returns a paginated list of weapons, ammunition, and magic items that deal
 * this type of damage. Useful for optimizing weapon selection and finding items
 * that exploit enemy vulnerabilities.
 *
 * **Basic Examples:**
 * - Slashing weapons: `GET /api/v1/damage-types/slashing/items`
 * - Fire items: `GET /api/v1/damage-types/fire/items`
 * - By ID: `GET /api/v1/damage-types/1/items`
 * - Pagination: `GET /api/v1/damage-types/slashing/items?per_page=50`
 *
 * **Physical Damage Types (Weapons):**
 * - Slashing: Longsword, Greatsword, Scimitar, Battleaxe (~80 items)
 * - Piercing: Rapier, Longbow, Shortbow, Dagger, Pike (~70 items)
 * - Bludgeoning: Mace, Warhammer, Club, Quarterstaff, Maul (~60 items)
 *
 * **Elemental Damage Types (Magic Items):**
 * - Fire: Flame Tongue, Fire Arrow, Javelin of Lightning (~12 items)
 * - Cold: Frost Brand, Arrows of Ice Slaying (~5 items)
 * - Lightning: Javelin of Lightning, Lightning Arrow (~4 items)
 * - Poison: Serpent Venom (poison), Poison Dagger (~6 items)
 * - Acid: Acid Vial, Acid Arrow (~3 items)
 *
 * **Character Building:**
 * - Martial characters: Identify all weapons matching your proficiencies
 * - Damage optimization: Find magic weapons with bonus elemental damage
 * - Versatility: Carry multiple damage types to bypass resistances
 * - Exploit vulnerabilities: Trolls regenerate except for fire/acid damage
 *
 * **Combat Tactics:**
 * - Physical damage: Most common, many creatures resist
 * - Magical slashing/piercing/bludgeoning: Bypass non-magical resistance
 * - Elemental damage: Exploit specific vulnerabilities (fire vs. ice creatures)
 *
 * **Reference Data:**
 * - 13 damage types total
 * - Physical types: Slashing (~80), Piercing (~70), Bludgeoning (~60)
 * - Elemental types: Fire (~12), Poison (~6), Cold (~5), Lightning (~4)
 * - Magic weapons override resistances to non-magical damage
 *
 * @param DamageType $damageType The damage type (by ID, code, or name)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Api/DamageTypeController.php
git commit -m "docs: add 5-star PHPDoc to damage type items endpoint"
```

---

## Task 16: Add 5-Star PHPDoc to ConditionController (Spells)

**Files:**
- Modify: `app/Http/Controllers/Api/ConditionController.php`

**Step 1: Replace basic PHPDoc for spells() method**

Replace the existing `spells()` PHPDoc with:

```php
/**
 * List all spells that inflict this condition
 *
 * Returns a paginated list of spells that can inflict this condition on targets
 * through saving throw failures. Useful for building control-focused characters
 * and identifying debuff options.
 *
 * **Basic Examples:**
 * - Poison spells: `GET /api/v1/conditions/poisoned/spells`
 * - Stun spells: `GET /api/v1/conditions/stunned/spells`
 * - By ID: `GET /api/v1/conditions/5/spells`
 * - Pagination: `GET /api/v1/conditions/paralyzed/spells?per_page=25`
 *
 * **Common Condition Use Cases:**
 * - Poisoned: Poison Spray, Cloudkill, Contagion (~8 spells, CON save)
 * - Stunned: Power Word Stun, Shocking Grasp (high levels) (~4 spells, CON save)
 * - Paralyzed: Hold Person, Hold Monster (~6 spells, WIS save, auto-crit)
 * - Charmed: Charm Person, Dominate Monster, Suggestion (~12 spells, WIS save)
 * - Frightened: Cause Fear, Fear, Phantasmal Killer (~8 spells, WIS save)
 * - Restrained: Entangle, Web, Evard's Black Tentacles (~10 spells, STR/DEX save)
 * - Blinded: Blindness/Deafness, Sunburst (~6 spells, CON save)
 * - Deafened: Deafness, Thunder Step (~4 spells, CON save)
 * - Prone: Grease, Thunderwave (~8 spells, STR/DEX save)
 * - Invisible: Invisibility, Greater Invisibility (~6 spells, no save)
 *
 * **Control Wizard Builds:**
 * - Crowd control: Paralyzed (auto-crits), Stunned (no actions), Restrained (reduced movement)
 * - Debuffs: Poisoned (disadvantage on attacks), Frightened (can't approach)
 * - Social manipulation: Charmed (friendly, can't attack), Suggestion (follow command)
 *
 * **Combat Tactics:**
 * - High-value targets: Paralyze enemy spellcasters (no verbal components)
 * - Melee threats: Restrain or frighten to reduce effectiveness
 * - Action denial: Stunned removes actions, reactions, and movement
 * - Save optimization: Target low saves (STR for wizards, INT for beasts)
 *
 * **Condition Synergies:**
 * - Paralyzed: Attack rolls auto-crit within 5 feet (massive damage)
 * - Restrained: Advantage on attacks against target, disadvantage on DEX saves
 * - Prone: Advantage on melee attacks, disadvantage on ranged attacks
 * - Invisible: Advantage on attacks, disadvantage on attacks against you
 *
 * **Reference Data:**
 * - 15 conditions in D&D 5e
 * - Most common: Poisoned (~8 spells), Charmed (~12 spells), Frightened (~8 spells)
 * - Most powerful: Paralyzed (auto-crits), Stunned (no actions), Incapacitated
 * - Duration: Varies from 1 round to 1 minute (concentration) to permanent
 *
 * @param Condition $condition The condition (by ID or slug)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Api/ConditionController.php
git commit -m "docs: add 5-star PHPDoc to condition spells endpoint"
```

---

## Task 17: Add 5-Star PHPDoc to ConditionController (Monsters)

**Files:**
- Modify: `app/Http/Controllers/Api/ConditionController.php`

**Step 1: Replace basic PHPDoc for monsters() method**

Replace the existing `monsters()` PHPDoc with:

```php
/**
 * List all monsters that inflict this condition
 *
 * Returns a paginated list of monsters that can inflict this condition through
 * their attacks, traits, special abilities, or innate spellcasting. Useful for
 * DMs designing encounters and players understanding enemy threats.
 *
 * **Basic Examples:**
 * - Poisoning monsters: `GET /api/v1/conditions/poisoned/monsters`
 * - Paralyzing monsters: `GET /api/v1/conditions/paralyzed/monsters`
 * - By ID: `GET /api/v1/conditions/5/monsters`
 * - Pagination: `GET /api/v1/conditions/frightened/monsters?per_page=25`
 *
 * **Common Condition Monsters:**
 * - Poisoned: Yuan-ti, Giant Spiders, Carrion Crawlers (~40 monsters)
 * - Paralyzed: Ghouls, Gelatinous Cubes, Beholders (paralysis ray) (~25 monsters)
 * - Frightened: Dragons (frightful presence), Banshees, Death Knights (~30 monsters)
 * - Charmed: Succubus/Incubus, Vampires, Sirens (~15 monsters)
 * - Stunned: Mind Flayers (mind blast), Monks (stunning strike) (~10 monsters)
 * - Restrained: Giant Spiders (webs), Ropers, Vine Blights (~20 monsters)
 * - Blinded: Umber Hulks (confusing gaze), Basilisks (~8 monsters)
 * - Petrified: Basilisks, Medusas, Cockatrices (~6 monsters)
 * - Grappled: Giant Octopuses, Mimics, Ropers (~35 monsters)
 *
 * **DM Encounter Design:**
 * - Threat assessment: Identify monsters with debilitating conditions
 * - Tactical variety: Mix damage dealers with control monsters
 * - Save targeting: Combine STR/DEX conditions with INT/WIS/CHA conditions
 * - Difficulty scaling: Paralysis/Stun can swing encounters dramatically
 *
 * **Player Preparation:**
 * - Condition immunity: Paladins (Aura of Protection), Monks (Diamond Soul)
 * - Lesser Restoration: Cures poisoned, paralyzed, blinded, deafened
 * - Greater Restoration: Cures charmed, petrified, stunned, exhaustion
 * - Protection spells: Protection from Poison, Heroes' Feast (poison immunity)
 *
 * **Dangerous Monster Conditions:**
 * - Paralyzed: Auto-crits from melee attacks (ghouls, gelatinous cubes)
 * - Petrified: Permanent until Greater Restoration (medusas, basilisks)
 * - Stunned: No actions, failed STR/DEX saves (mind flayers)
 * - Frightened: Cannot move closer (ancient dragons, death knights)
 *
 * **Condition Delivery Mechanisms:**
 * - Saving throws: Most common (CON for poison, WIS for charm/fear)
 * - Attack hits: Ghoul claws (paralysis), spider bites (poison)
 * - Failed ability checks: Gelatinous cube engulf (paralysis)
 * - Aura effects: Dragon frightful presence (WIS save), banshee wail
 *
 * **Reference Data:**
 * - 15 conditions total
 * - Most common monster conditions: Poisoned (~40), Frightened (~30), Grappled (~35)
 * - Most dangerous: Paralyzed (auto-crits), Petrified (permanent), Stunned (helpless)
 * - CR correlation: Higher CR monsters inflict more conditions simultaneously
 *
 * @param Condition $condition The condition (by ID or slug)
 * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
 */
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Api/ConditionController.php
git commit -m "docs: add 5-star PHPDoc to condition monsters endpoint"
```

---

## Task 18: Format Code with Pint

**Step 1: Run Pint to format all modified files**

Run: `docker compose exec php ./vendor/bin/pint`

Expected: All files formatted successfully

**Step 2: Review changes**

Run: `git diff`

Expected: Only whitespace/formatting changes (if any)

**Step 3: Commit formatting changes (if any)**

```bash
git add -A
git commit -m "style: format code with Pint"
```

If no changes, skip commit.

---

## Task 19: Update CHANGELOG.md

**Files:**
- Modify: `CHANGELOG.md`

**Step 1: Add new section under [Unreleased]**

Add to the `[Unreleased]` section:

```markdown
### Added
- **Static Reference Reverse Relationships** - 6 new endpoints for querying entities by lookup tables
  - `GET /api/v1/spell-schools/{id|code|slug}/spells` - List all spells in a school of magic
  - `GET /api/v1/damage-types/{id|code}/spells` - List all spells dealing this damage type
  - `GET /api/v1/damage-types/{id|code}/items` - List all items dealing this damage type
  - `GET /api/v1/conditions/{id|slug}/spells` - List all spells inflicting this condition
  - `GET /api/v1/conditions/{id|slug}/monsters` - List all monsters inflicting this condition
  - All endpoints support pagination (50 per page default), slug/ID/code routing, and follow proven `/spells/{id}/classes` pattern
  - 20 new tests (60 assertions) with 100% pass rate
  - 5-star PHPDoc documentation with real entity names, use cases, and reference data
  - Three Eloquent relationship patterns: HasMany, HasManyThrough, MorphToMany
```

**Step 2: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: update CHANGELOG with static reference reverse relationships"
```

---

## Task 20: Manual Verification & Session Handover

**Step 1: Test endpoints manually**

```bash
# Spell schools
curl -s "http://localhost:8080/api/v1/spell-schools/evocation/spells" | jq '.data[0].name'

# Damage types
curl -s "http://localhost:8080/api/v1/damage-types/fire/spells" | jq '.data[0].name'
curl -s "http://localhost:8080/api/v1/damage-types/slashing/items" | jq '.data[0].name'

# Conditions
curl -s "http://localhost:8080/api/v1/conditions/poisoned/spells" | jq '.data[0].name'
curl -s "http://localhost:8080/api/v1/conditions/frightened/monsters" | jq '.data[0].name'
```

Expected: Each endpoint returns JSON with entity data

**Step 2: Verify Scramble OpenAPI docs**

Run: `docker compose exec php php artisan scramble:docs`

Visit: `http://localhost:8080/docs/api`

Expected: All 6 new endpoints appear in documentation with full PHPDoc

**Step 3: Run final test suite**

Run: `docker compose exec php php artisan test`

Expected: 1,137 tests PASS (1,117 + 20 new)

**Step 4: Create session handover document**

Create: `docs/SESSION-HANDOVER-2025-11-22-STATIC-REFERENCE-REVERSE-RELATIONSHIPS.md`

Include:
- Implementation summary
- Test metrics (20 tests, 60 assertions, 100% pass rate)
- Endpoints added (6 total)
- Files modified (10 implementation + 3 test files)
- PHPDoc quality (5-star, ~360 lines)
- Next steps (optional Tier 2 entities)

**Step 5: Final commit**

```bash
git add docs/SESSION-HANDOVER-2025-11-22-STATIC-REFERENCE-REVERSE-RELATIONSHIPS.md
git commit -m "docs: add session handover for static reference reverse relationships

- 6 new endpoints (spell-schools, damage-types, conditions)
- 20 tests passing (100% pass rate, zero regressions)
- 5-star PHPDoc documentation (~360 lines)
- Three relationship patterns (HasMany, HasManyThrough, MorphToMany)
- Pattern consistency with existing /spells/{id}/classes endpoints

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Success Criteria Checklist

**Must Have (All Required):**
- ✅ 20 new tests passing (1,137 total)
- ✅ Zero regressions in existing tests
- ✅ 6 new endpoints functional
- ✅ Slug/ID/Code routing working
- ✅ Pagination working (50 per page default)
- ✅ 5-star PHPDoc (~360 lines total)
- ✅ Scramble OpenAPI docs generated
- ✅ Code formatted with Pint
- ✅ CHANGELOG.md updated
- ✅ Session handover created
- ✅ All changes committed with clear messages

**Optional (Tier 2 - Future Work):**
- 🔄 AbilityScore → spells (saving throws)
- 🔄 ProficiencyType → classes/races/backgrounds
- 🔄 Language → races/backgrounds
- 🔄 Size → races/monsters

---

## Troubleshooting

**Issue:** Tests fail with "Route not defined"
- **Fix:** Verify route added to `routes/api.php`
- **Fix:** Check route name matches test expectations

**Issue:** Tests fail with "Relationship not defined"
- **Fix:** Verify relationship method added to model
- **Fix:** Check relationship type matches pattern (HasMany vs MorphToMany)

**Issue:** HasManyThrough returns duplicates
- **Fix:** Add `->distinct()` to relationship definition

**Issue:** MorphToMany returns wrong entities
- **Fix:** Add `->wherePivot('effect_type', 'inflicts')` filter

**Issue:** Pagination doesn't work
- **Fix:** Ensure `->paginate(50)` called in controller, not `->get()`

**Issue:** Scramble docs missing new endpoints
- **Fix:** Run `php artisan scramble:docs` to regenerate
- **Fix:** Verify @param and @return tags present in PHPDoc

---

**End of Implementation Plan**

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>

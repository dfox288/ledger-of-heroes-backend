# Character Equipment System - Implementation Plan

**Date:** 2025-12-01
**Issue:** #90 - Character Builder v2: Equipment System
**Branch:** `feature/issue-90-equipment-system`
**Estimated Effort:** 4-5 hours

---

## Overview

Add equipment management to Character Builder - add items to inventory, equip armor/weapons, calculate AC from equipped armor.

### Features
- Add/remove items from character inventory
- Equip/unequip armor, shields, and weapons
- Automatic AC calculation via accessor (armor + DEX + shield)
- Track quantity for stackable items
- Enforce single armor / single shield constraint

### D&D 5e AC Rules

| Armor Type | AC Calculation | DEX Limit |
|------------|----------------|-----------|
| Unarmored | 10 + DEX mod | None |
| Light (4) | Base + DEX mod | None |
| Medium (5) | Base + DEX mod | Max +2 |
| Heavy (6) | Base only | 0 |
| Shield (7) | +2 bonus | N/A |

---

## Pre-flight Checklist

- [ ] Runner: Docker Compose (`docker compose exec php ...`)
- [ ] Branch: Create `feature/issue-90-equipment-system` from main
- [ ] Existing tests passing
- [ ] Git status clean

---

## Phase 1: Data Model Updates (1 hour)

### Task 1.1: Add migration to rename armor_class to armor_class_override

The existing `armor_class` column will become an optional override. The computed AC comes from the accessor.

**File:** `database/migrations/xxxx_rename_armor_class_to_override_on_characters_table.php`

```php
public function up(): void
{
    Schema::table('characters', function (Blueprint $table) {
        $table->renameColumn('armor_class', 'armor_class_override');
    });
}

public function down(): void
{
    Schema::table('characters', function (Blueprint $table) {
        $table->renameColumn('armor_class_override', 'armor_class');
    });
}
```

**Verification:**
```bash
docker compose exec php php artisan migrate
docker compose exec php php artisan migrate:rollback
docker compose exec php php artisan migrate
```

---

### Task 1.2: Update Character model

**File:** `app/Models/Character.php`

Changes:
- Rename `armor_class` to `armor_class_override` in `$fillable`
- Remove `armor_class` from `$casts` (or rename)
- Add `armor_class` accessor that calculates from equipment
- Add `equippedArmor()` relationship helper
- Add `equippedShield()` relationship helper

```php
/**
 * Get equipped armor (Light, Medium, or Heavy).
 */
public function equippedArmor(): ?CharacterEquipment
{
    return $this->equipment()
        ->where('equipped', true)
        ->whereHas('item', function ($query) {
            $query->whereIn('item_type_id', [4, 5, 6]); // Light, Medium, Heavy
        })
        ->with('item')
        ->first();
}

/**
 * Get equipped shield.
 */
public function equippedShield(): ?CharacterEquipment
{
    return $this->equipment()
        ->where('equipped', true)
        ->whereHas('item', function ($query) {
            $query->where('item_type_id', 7); // Shield
        })
        ->with('item')
        ->first();
}

/**
 * Calculate armor class from equipped items.
 */
public function getArmorClassAttribute(): int
{
    // If override is set, use it
    if ($this->armor_class_override !== null) {
        return $this->armor_class_override;
    }

    return app(CharacterStatCalculator::class)->calculateArmorClass($this);
}
```

---

### Task 1.3: Update CharacterEquipment model

**File:** `app/Models/CharacterEquipment.php`

The model exists but needs enhancements:
- Add `item` relationship (BelongsTo)
- Add `character` relationship (BelongsTo)
- Add `isArmor()`, `isShield()`, `isWeapon()` helper methods
- Add scopes: `equipped()`, `armor()`, `shields()`, `weapons()`

```php
public function item(): BelongsTo
{
    return $this->belongsTo(Item::class);
}

public function character(): BelongsTo
{
    return $this->belongsTo(Character::class);
}

public function scopeEquipped($query)
{
    return $query->where('equipped', true);
}

public function scopeArmor($query)
{
    return $query->whereHas('item', fn($q) => $q->whereIn('item_type_id', [4, 5, 6]));
}

public function scopeShields($query)
{
    return $query->whereHas('item', fn($q) => $q->where('item_type_id', 7));
}

public function scopeWeapons($query)
{
    return $query->whereHas('item', fn($q) => $q->whereIn('item_type_id', [2, 3]));
}

public function isArmor(): bool
{
    return in_array($this->item->item_type_id, [4, 5, 6]);
}

public function isShield(): bool
{
    return $this->item->item_type_id === 7;
}

public function isWeapon(): bool
{
    return in_array($this->item->item_type_id, [2, 3]);
}
```

---

### Task 1.4: Create CharacterEquipmentFactory

**File:** `database/factories/CharacterEquipmentFactory.php`

```php
public function definition(): array
{
    return [
        'character_id' => Character::factory(),
        'item_id' => Item::inRandomOrder()->first()?->id ?? 1,
        'quantity' => 1,
        'equipped' => false,
        'location' => 'backpack',
    ];
}

public function equipped(): static
{
    return $this->state(['equipped' => true, 'location' => 'equipped']);
}

public function withItem(Item|int $item): static
{
    return $this->state([
        'item_id' => $item instanceof Item ? $item->id : $item,
    ]);
}
```

**Commit:** `feat(#90): update data model for equipment system`

---

## Phase 2: CharacterStatCalculator AC Method (1 hour)

### Task 2.1: Write failing unit tests for AC calculation

**File:** `tests/Unit/Services/CharacterStatCalculatorTest.php` (add to existing)

```php
// AC Calculation with Equipment Tests

#[Test]
public function it_calculates_ac_unarmored(): void
{
    // DEX 14 (+2), no armor: 10 + 2 = 12
    $character = $this->createCharacterWithDex(14);

    $ac = $this->calculator->calculateArmorClass($character);

    $this->assertEquals(12, $ac);
}

#[Test]
public function it_calculates_ac_with_light_armor(): void
{
    // Leather (AC 11) + DEX 16 (+3) = 14
    $character = $this->createCharacterWithDex(16);
    $this->equipArmor($character, 'leather-armor'); // AC 11, light

    $ac = $this->calculator->calculateArmorClass($character);

    $this->assertEquals(14, $ac);
}

#[Test]
public function it_calculates_ac_with_medium_armor_caps_dex(): void
{
    // Half Plate (AC 15) + DEX 18 (+4, capped to +2) = 17
    $character = $this->createCharacterWithDex(18);
    $this->equipArmor($character, 'half-plate'); // AC 15, medium

    $ac = $this->calculator->calculateArmorClass($character);

    $this->assertEquals(17, $ac);
}

#[Test]
public function it_calculates_ac_with_heavy_armor_ignores_dex(): void
{
    // Plate (AC 18) + DEX 16 (+3, ignored) = 18
    $character = $this->createCharacterWithDex(16);
    $this->equipArmor($character, 'plate-armor'); // AC 18, heavy

    $ac = $this->calculator->calculateArmorClass($character);

    $this->assertEquals(18, $ac);
}

#[Test]
public function it_adds_shield_bonus_to_ac(): void
{
    // Leather (11) + DEX 14 (+2) + Shield (+2) = 15
    $character = $this->createCharacterWithDex(14);
    $this->equipArmor($character, 'leather-armor');
    $this->equipShield($character);

    $ac = $this->calculator->calculateArmorClass($character);

    $this->assertEquals(15, $ac);
}

#[Test]
public function it_adds_shield_bonus_unarmored(): void
{
    // 10 + DEX 12 (+1) + Shield (+2) = 13
    $character = $this->createCharacterWithDex(12);
    $this->equipShield($character);

    $ac = $this->calculator->calculateArmorClass($character);

    $this->assertEquals(13, $ac);
}
```

---

### Task 2.2: Implement calculateArmorClass in CharacterStatCalculator

**File:** `app/Services/CharacterStatCalculator.php`

```php
/**
 * Item type IDs for armor categories.
 */
private const LIGHT_ARMOR = 4;
private const MEDIUM_ARMOR = 5;
private const HEAVY_ARMOR = 6;
private const SHIELD = 7;

/**
 * Calculate armor class from equipped items.
 */
public function calculateArmorClass(Character $character): int
{
    $dexMod = $this->abilityModifier($character->dexterity ?? 10);

    $equippedArmor = $character->equippedArmor();
    $equippedShield = $character->equippedShield();

    // Base AC calculation
    if ($equippedArmor === null) {
        // Unarmored: 10 + DEX
        $ac = 10 + $dexMod;
    } else {
        $armor = $equippedArmor->item;
        $baseAc = $armor->armor_class ?? 10;
        $armorType = $armor->item_type_id;

        $ac = match ($armorType) {
            self::LIGHT_ARMOR => $baseAc + $dexMod,
            self::MEDIUM_ARMOR => $baseAc + min($dexMod, 2),
            self::HEAVY_ARMOR => $baseAc,
            default => 10 + $dexMod,
        };
    }

    // Add shield bonus
    if ($equippedShield !== null) {
        $ac += $equippedShield->item->armor_class ?? 2;
    }

    return $ac;
}
```

**Commit:** `feat(#90): add AC calculation from equipped armor`

---

## Phase 3: Equipment Service (1 hour)

### Task 3.1: Write failing unit tests for EquipmentManagerService

**File:** `tests/Unit/Services/EquipmentManagerServiceTest.php`

```php
#[Test]
public function it_adds_item_to_character_inventory(): void
{
    $character = Character::factory()->create();
    $item = Item::where('slug', 'longsword')->first();

    $equipment = $this->service->addItem($character, $item);

    $this->assertDatabaseHas('character_equipment', [
        'character_id' => $character->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'equipped' => false,
    ]);
}

#[Test]
public function it_stacks_quantity_for_same_unequipped_item(): void
{
    $character = Character::factory()->create();
    $item = Item::where('slug', 'arrow')->first();

    $this->service->addItem($character, $item, 20);
    $this->service->addItem($character, $item, 10);

    $this->assertEquals(30, $character->equipment()->where('item_id', $item->id)->first()->quantity);
}

#[Test]
public function it_equips_armor(): void
{
    $character = Character::factory()->create();
    $armor = Item::where('slug', 'leather-armor')->first();
    $equipment = $this->service->addItem($character, $armor);

    $this->service->equipItem($equipment);

    $equipment->refresh();
    $this->assertTrue($equipment->equipped);
    $this->assertEquals('equipped', $equipment->location);
}

#[Test]
public function it_unequips_current_armor_when_equipping_new(): void
{
    $character = Character::factory()->create();
    $leather = Item::where('slug', 'leather-armor')->first();
    $chain = Item::where('slug', 'chain-mail')->first();

    $leatherEquip = $this->service->addItem($character, $leather);
    $this->service->equipItem($leatherEquip);

    $chainEquip = $this->service->addItem($character, $chain);
    $this->service->equipItem($chainEquip);

    $leatherEquip->refresh();
    $chainEquip->refresh();

    $this->assertFalse($leatherEquip->equipped);
    $this->assertTrue($chainEquip->equipped);
}

#[Test]
public function it_allows_armor_and_shield_together(): void
{
    $character = Character::factory()->create();
    $armor = Item::where('slug', 'leather-armor')->first();
    $shield = Item::where('slug', 'shield')->first();

    $armorEquip = $this->service->addItem($character, $armor);
    $shieldEquip = $this->service->addItem($character, $shield);

    $this->service->equipItem($armorEquip);
    $this->service->equipItem($shieldEquip);

    $this->assertTrue($armorEquip->refresh()->equipped);
    $this->assertTrue($shieldEquip->refresh()->equipped);
}

#[Test]
public function it_removes_item_from_inventory(): void
{
    $character = Character::factory()->create();
    $item = Item::where('slug', 'longsword')->first();
    $equipment = $this->service->addItem($character, $item);

    $this->service->removeItem($equipment);

    $this->assertDatabaseMissing('character_equipment', ['id' => $equipment->id]);
}

#[Test]
public function it_decreases_quantity_when_removing_partial(): void
{
    $character = Character::factory()->create();
    $item = Item::where('slug', 'arrow')->first();
    $equipment = $this->service->addItem($character, $item, 20);

    $this->service->removeItem($equipment, 5);

    $this->assertEquals(15, $equipment->refresh()->quantity);
}
```

---

### Task 3.2: Implement EquipmentManagerService

**File:** `app/Services/EquipmentManagerService.php`

```php
<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;

class EquipmentManagerService
{
    private const ARMOR_TYPES = [4, 5, 6]; // Light, Medium, Heavy
    private const SHIELD_TYPE = 7;

    /**
     * Add item to character inventory.
     */
    public function addItem(Character $character, Item $item, int $quantity = 1): CharacterEquipment
    {
        // Check for existing unequipped stack
        $existing = $character->equipment()
            ->where('item_id', $item->id)
            ->where('equipped', false)
            ->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);
            return $existing;
        }

        return $character->equipment()->create([
            'item_id' => $item->id,
            'quantity' => $quantity,
            'equipped' => false,
            'location' => 'backpack',
        ]);
    }

    /**
     * Remove item from inventory.
     */
    public function removeItem(CharacterEquipment $equipment, ?int $quantity = null): void
    {
        if ($quantity === null || $quantity >= $equipment->quantity) {
            $equipment->delete();
        } else {
            $equipment->decrement('quantity', $quantity);
        }
    }

    /**
     * Equip an item.
     */
    public function equipItem(CharacterEquipment $equipment): void
    {
        $item = $equipment->item;

        // Unequip conflicting items
        if ($this->isArmor($item)) {
            $this->unequipCurrentArmor($equipment->character);
        } elseif ($this->isShield($item)) {
            $this->unequipCurrentShield($equipment->character);
        }

        $equipment->update([
            'equipped' => true,
            'location' => 'equipped',
        ]);
    }

    /**
     * Unequip an item.
     */
    public function unequipItem(CharacterEquipment $equipment): void
    {
        $equipment->update([
            'equipped' => false,
            'location' => 'backpack',
        ]);
    }

    private function unequipCurrentArmor(Character $character): void
    {
        $character->equipment()
            ->where('equipped', true)
            ->whereHas('item', fn($q) => $q->whereIn('item_type_id', self::ARMOR_TYPES))
            ->update(['equipped' => false, 'location' => 'backpack']);
    }

    private function unequipCurrentShield(Character $character): void
    {
        $character->equipment()
            ->where('equipped', true)
            ->whereHas('item', fn($q) => $q->where('item_type_id', self::SHIELD_TYPE))
            ->update(['equipped' => false, 'location' => 'backpack']);
    }

    private function isArmor(Item $item): bool
    {
        return in_array($item->item_type_id, self::ARMOR_TYPES);
    }

    private function isShield(Item $item): bool
    {
        return $item->item_type_id === self::SHIELD_TYPE;
    }
}
```

**Commit:** `feat(#90): add EquipmentManagerService`

---

## Phase 4: API Endpoints (1 hour)

### Task 4.1: Write failing feature tests for equipment API

**File:** `tests/Feature/Api/CharacterEquipmentTest.php`

```php
#[Test]
public function it_lists_character_equipment(): void
{
    $character = Character::factory()->create();
    $item = Item::where('slug', 'longsword')->first();
    CharacterEquipment::factory()->withItem($item)->create(['character_id' => $character->id]);

    $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.item.slug', 'longsword');
}

#[Test]
public function it_adds_item_to_inventory(): void
{
    $character = Character::factory()->create();
    $item = Item::where('slug', 'longsword')->first();

    $response = $this->postJson("/api/v1/characters/{$character->id}/equipment", [
        'item_id' => $item->id,
        'quantity' => 1,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.item.slug', 'longsword')
        ->assertJsonPath('data.quantity', 1);
}

#[Test]
public function it_equips_armor_and_updates_ac(): void
{
    $character = Character::factory()->withAbilityScores([
        'dexterity' => 14, // +2 mod
    ])->create();

    $armor = Item::where('slug', 'leather-armor')->first(); // AC 11
    $equipment = CharacterEquipment::factory()
        ->withItem($armor)
        ->create(['character_id' => $character->id]);

    // Verify initial AC (unarmored: 10 + 2 = 12)
    $this->assertEquals(12, $character->armor_class);

    $response = $this->patchJson("/api/v1/characters/{$character->id}/equipment/{$equipment->id}", [
        'equipped' => true,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.equipped', true);

    // Verify new AC (leather: 11 + 2 = 13)
    $character->refresh();
    $this->assertEquals(13, $character->armor_class);
}

#[Test]
public function it_removes_item_from_inventory(): void
{
    $character = Character::factory()->create();
    $item = Item::where('slug', 'longsword')->first();
    $equipment = CharacterEquipment::factory()
        ->withItem($item)
        ->create(['character_id' => $character->id]);

    $response = $this->deleteJson("/api/v1/characters/{$character->id}/equipment/{$equipment->id}");

    $response->assertNoContent();
    $this->assertDatabaseMissing('character_equipment', ['id' => $equipment->id]);
}

#[Test]
public function it_validates_item_exists(): void
{
    $character = Character::factory()->create();

    $response = $this->postJson("/api/v1/characters/{$character->id}/equipment", [
        'item_id' => 999999,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['item_id']);
}
```

---

### Task 4.2: Create Form Requests

**Files:**
- `app/Http/Requests/CharacterEquipment/StoreEquipmentRequest.php`
- `app/Http/Requests/CharacterEquipment/UpdateEquipmentRequest.php`

---

### Task 4.3: Create CharacterEquipmentResource

**File:** `app/Http/Resources/CharacterEquipmentResource.php`

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'item' => [
            'id' => $this->item->id,
            'name' => $this->item->name,
            'slug' => $this->item->slug,
            'item_type' => $this->item->itemType?->name,
            'armor_class' => $this->item->armor_class,
            'damage_dice' => $this->item->damage_dice,
            'weight' => $this->item->weight,
        ],
        'quantity' => $this->quantity,
        'equipped' => $this->equipped,
        'location' => $this->location,
        'created_at' => $this->created_at?->toIso8601String(),
    ];
}
```

---

### Task 4.4: Create CharacterEquipmentController

**File:** `app/Http/Controllers/Api/CharacterEquipmentController.php`

```php
public function index(Character $character): AnonymousResourceCollection
{
    $equipment = $character->equipment()->with('item.itemType')->get();

    return CharacterEquipmentResource::collection($equipment);
}

public function store(StoreEquipmentRequest $request, Character $character): CharacterEquipmentResource
{
    $item = Item::findOrFail($request->item_id);
    $equipment = $this->equipmentManager->addItem($character, $item, $request->quantity ?? 1);
    $equipment->load('item.itemType');

    return (new CharacterEquipmentResource($equipment))
        ->response()
        ->setStatusCode(201);
}

public function update(UpdateEquipmentRequest $request, Character $character, CharacterEquipment $equipment): CharacterEquipmentResource
{
    if ($request->has('equipped')) {
        if ($request->equipped) {
            $this->equipmentManager->equipItem($equipment);
        } else {
            $this->equipmentManager->unequipItem($equipment);
        }
    }

    if ($request->has('quantity')) {
        $equipment->update(['quantity' => $request->quantity]);
    }

    $equipment->load('item.itemType');

    return new CharacterEquipmentResource($equipment);
}

public function destroy(Character $character, CharacterEquipment $equipment): Response
{
    $this->equipmentManager->removeItem($equipment);

    return response()->noContent();
}
```

---

### Task 4.5: Add routes

**File:** `routes/api.php`

```php
Route::prefix('characters/{character}')->group(function () {
    // ... existing routes ...

    Route::get('equipment', [CharacterEquipmentController::class, 'index']);
    Route::post('equipment', [CharacterEquipmentController::class, 'store']);
    Route::patch('equipment/{equipment}', [CharacterEquipmentController::class, 'update']);
    Route::delete('equipment/{equipment}', [CharacterEquipmentController::class, 'destroy']);
});
```

**Commit:** `feat(#90): add equipment API endpoints`

---

## Phase 5: Integration & Polish (30 min)

### Task 5.1: Update CharacterResource to include equipped items summary

```php
// In CharacterResource::toArray()
'equipped' => [
    'armor' => $this->when($this->equippedArmor(), fn() => [
        'name' => $this->equippedArmor()->item->name,
        'armor_class' => $this->equippedArmor()->item->armor_class,
    ]),
    'shield' => $this->when($this->equippedShield(), fn() => [
        'name' => $this->equippedShield()->item->name,
    ]),
],
```

### Task 5.2: Update CharacterFactory to handle armor_class_override

### Task 5.3: Update existing tests if needed

---

## Phase 6: Quality Gates

```bash
docker compose exec php ./vendor/bin/pint
docker compose exec php php artisan test --testsuite=Unit-Pure
docker compose exec php php artisan test --testsuite=Unit-DB
docker compose exec php php artisan test --testsuite=Feature-DB
```

### Task 6.1: Update CHANGELOG.md

```markdown
### Added
- **Character Equipment System** (Issue #90)
  - Add/remove items from character inventory
  - Equip/unequip armor, shields, weapons
  - Automatic AC calculation from equipped armor + DEX
  - Single armor / single shield constraint enforced
  - `EquipmentManagerService` for business logic
  - API endpoints: GET/POST/PATCH/DELETE /characters/{id}/equipment
```

**Commit:** `docs: update CHANGELOG for equipment system`

---

## Validation Checklist

Before marking complete:

- [ ] Migration runs cleanly (up and down)
- [ ] AC calculation tests pass (6+ tests)
- [ ] EquipmentManagerService tests pass (7+ tests)
- [ ] Feature tests pass (5+ tests)
- [ ] Character shows correct AC with different armor types
- [ ] Equipping new armor unequips old armor
- [ ] Shield stacks with armor
- [ ] Pint passes
- [ ] All test suites green

---

## Files to Create/Modify

### New Files
- `database/migrations/xxxx_rename_armor_class_to_override_on_characters_table.php`
- `database/factories/CharacterEquipmentFactory.php`
- `app/Services/EquipmentManagerService.php`
- `app/Http/Controllers/Api/CharacterEquipmentController.php`
- `app/Http/Requests/CharacterEquipment/StoreEquipmentRequest.php`
- `app/Http/Requests/CharacterEquipment/UpdateEquipmentRequest.php`
- `app/Http/Resources/CharacterEquipmentResource.php`
- `tests/Unit/Services/EquipmentManagerServiceTest.php`
- `tests/Feature/Api/CharacterEquipmentTest.php`

### Modified Files
- `app/Models/Character.php` - AC accessor, equippedArmor/Shield helpers
- `app/Models/CharacterEquipment.php` - relationships, scopes, helpers
- `app/Services/CharacterStatCalculator.php` - calculateArmorClass method
- `app/Http/Resources/CharacterResource.php` - equipped summary
- `routes/api.php` - equipment routes
- `CHANGELOG.md`

---

**Ready to execute with `laravel:executing-plans`**

<?php

namespace Tests\Unit\DTOs;

use App\DTOs\CharacterStatsDTO;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\Item;
use App\Models\ItemType;
use App\Services\CharacterStatCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for weapon stats calculations in CharacterStatsDTO.
 *
 * Issue #709: Clarify weapon attack_bonus/damage_bonus contract.
 * Option A: Backend provides pre-computed totals including:
 * - Ability modifier (STR/DEX based on weapon type and finesse)
 * - Proficiency bonus (if proficient)
 * - Fighting style bonuses (Archery +2 attack, Dueling +2 damage)
 * - Magic weapon bonuses (+1/+2/+3)
 */
class CharacterStatsDTOWeaponTest extends TestCase
{
    use RefreshDatabase;

    private CharacterStatCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(CharacterStatCalculator::class);
    }

    // === Basic Weapon Stats ===

    #[Test]
    public function it_calculates_attack_bonus_with_ability_modifier_only(): void
    {
        // STR 16 (+3), no proficiency, no fighting style
        $character = Character::factory()->create([
            'strength' => 16,
            'dexterity' => 10,
        ]);

        // Create a melee weapon
        $longsword = $this->createMeleeWeapon('Test Longsword');

        // Equip the weapon
        $character->equipment()->create([
            'item_slug' => $longsword->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Test Longsword');

        $this->assertNotNull($weapon);
        $this->assertEquals('STR', $weapon['ability_used']);
        // Attack = STR mod (+3), no proficiency
        $this->assertEquals(3, $weapon['attack_bonus']);
        // Damage = STR mod (+3)
        $this->assertEquals(3, $weapon['damage_bonus']);
    }

    #[Test]
    public function it_adds_proficiency_bonus_when_proficient(): void
    {
        // STR 14 (+2), level 5 (+3 prof), proficient with longswords
        $character = Character::factory()->create([
            'strength' => 14,
            'dexterity' => 10,
        ]);

        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-weapon-prof']);
        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 5,
            'order' => 1,
            'is_primary' => true,
        ]);

        // Add longsword proficiency
        $character->proficiencies()->create([
            'proficiency_type_slug' => 'core:longsword',
        ]);

        $longsword = $this->createMeleeWeapon('Longsword');

        $character->equipment()->create([
            'item_slug' => $longsword->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties', 'proficiencies']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Longsword');

        $this->assertTrue($weapon['is_proficient']);
        // Attack = STR mod (+2) + proficiency (+3) = +5
        $this->assertEquals(5, $weapon['attack_bonus']);
        // Damage = STR mod (+2) - proficiency doesn't add to damage
        $this->assertEquals(2, $weapon['damage_bonus']);
    }

    // === Ranged Weapons ===

    #[Test]
    public function it_uses_dexterity_for_ranged_weapons(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 16, // +3
        ]);

        $shortbow = $this->createRangedWeapon('Shortbow');

        $character->equipment()->create([
            'item_slug' => $shortbow->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Shortbow');

        $this->assertEquals('DEX', $weapon['ability_used']);
        $this->assertEquals(3, $weapon['attack_bonus']);
        $this->assertEquals(3, $weapon['damage_bonus']);
    }

    // === Fighting Style: Archery ===

    #[Test]
    public function it_adds_archery_bonus_to_ranged_weapon_attack(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 14, // +2
        ]);

        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-archery-weapon']);

        $archeryStyle = ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Fighting Style: Archery',
            'level' => 1,
        ]);

        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $archeryStyle->id,
            'feature_slug' => 'test:fighter-archery-weapon:fighting-style-archery',
            'source' => 'class',
        ]);

        $longbow = $this->createRangedWeapon('Longbow');

        $character->equipment()->create([
            'item_slug' => $longbow->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties', 'features.feature']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Longbow');

        // Attack = DEX mod (+2) + Archery (+2) = +4
        $this->assertEquals(4, $weapon['attack_bonus']);
        // Damage = DEX mod (+2) - Archery doesn't add to damage
        $this->assertEquals(2, $weapon['damage_bonus']);
    }

    #[Test]
    public function it_does_not_add_archery_bonus_to_melee_weapons(): void
    {
        $character = Character::factory()->create([
            'strength' => 14, // +2
            'dexterity' => 10,
        ]);

        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-archery-melee']);

        $archeryStyle = ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Fighting Style: Archery',
            'level' => 1,
        ]);

        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $archeryStyle->id,
            'feature_slug' => 'test:fighter-archery-melee:fighting-style-archery',
            'source' => 'class',
        ]);

        $longsword = $this->createMeleeWeapon('Longsword');

        $character->equipment()->create([
            'item_slug' => $longsword->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties', 'features.feature']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Longsword');

        // Attack = STR mod (+2) only - Archery doesn't apply to melee
        $this->assertEquals(2, $weapon['attack_bonus']);
    }

    // === Fighting Style: Dueling ===

    #[Test]
    public function it_adds_dueling_bonus_to_one_handed_melee_damage(): void
    {
        $character = Character::factory()->create([
            'strength' => 14, // +2
            'dexterity' => 10,
        ]);

        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-dueling-weapon']);

        $duelingStyle = ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Fighting Style: Dueling',
            'level' => 1,
        ]);

        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $duelingStyle->id,
            'feature_slug' => 'test:fighter-dueling-weapon:fighting-style-dueling',
            'source' => 'class',
        ]);

        $longsword = $this->createMeleeWeapon('Longsword');

        $character->equipment()->create([
            'item_slug' => $longsword->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties', 'features.feature']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Longsword');

        // Attack = STR mod (+2) - Dueling doesn't add to attack
        $this->assertEquals(2, $weapon['attack_bonus']);
        // Damage = STR mod (+2) + Dueling (+2) = +4
        $this->assertEquals(4, $weapon['damage_bonus']);
    }

    #[Test]
    public function it_does_not_add_dueling_bonus_to_ranged_weapons(): void
    {
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 14, // +2
        ]);

        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-dueling-ranged']);

        $duelingStyle = ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Fighting Style: Dueling',
            'level' => 1,
        ]);

        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $duelingStyle->id,
            'feature_slug' => 'test:fighter-dueling-ranged:fighting-style-dueling',
            'source' => 'class',
        ]);

        $shortbow = $this->createRangedWeapon('Shortbow');

        $character->equipment()->create([
            'item_slug' => $shortbow->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties', 'features.feature']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Shortbow');

        // Damage = DEX mod (+2) only - Dueling doesn't apply to ranged
        $this->assertEquals(2, $weapon['damage_bonus']);
    }

    // === Magic Weapon Bonuses ===

    #[Test]
    public function it_adds_magic_bonus_from_plus_one_weapon(): void
    {
        $character = Character::factory()->create([
            'strength' => 14, // +2
            'dexterity' => 10,
        ]);

        $magicSword = $this->createMeleeWeapon('Longsword +1', isMagic: true);

        $character->equipment()->create([
            'item_slug' => $magicSword->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Longsword +1');

        // Attack = STR mod (+2) + magic (+1) = +3
        $this->assertEquals(3, $weapon['attack_bonus']);
        // Damage = STR mod (+2) + magic (+1) = +3
        $this->assertEquals(3, $weapon['damage_bonus']);
    }

    #[Test]
    public function it_adds_magic_bonus_from_plus_two_weapon(): void
    {
        $character = Character::factory()->create([
            'strength' => 14, // +2
            'dexterity' => 10,
        ]);

        $magicSword = $this->createMeleeWeapon('Greatsword +2', isMagic: true);

        $character->equipment()->create([
            'item_slug' => $magicSword->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Greatsword +2');

        // Attack = STR mod (+2) + magic (+2) = +4
        $this->assertEquals(4, $weapon['attack_bonus']);
        // Damage = STR mod (+2) + magic (+2) = +4
        $this->assertEquals(4, $weapon['damage_bonus']);
    }

    #[Test]
    public function it_adds_magic_bonus_from_plus_three_weapon(): void
    {
        $character = Character::factory()->create([
            'strength' => 16, // +3
            'dexterity' => 10,
        ]);

        $magicSword = $this->createMeleeWeapon('Vorpal Sword +3', isMagic: true);

        $character->equipment()->create([
            'item_slug' => $magicSword->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Vorpal Sword +3');

        // Attack = STR mod (+3) + magic (+3) = +6
        $this->assertEquals(6, $weapon['attack_bonus']);
        // Damage = STR mod (+3) + magic (+3) = +6
        $this->assertEquals(6, $weapon['damage_bonus']);
    }

    // === Combined Bonuses ===

    #[Test]
    public function it_combines_all_bonuses_correctly(): void
    {
        // STR 16 (+3), level 5 (+3 prof), proficient, Dueling, +1 weapon
        $character = Character::factory()->create([
            'strength' => 16, // +3
            'dexterity' => 10,
        ]);

        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-all-bonuses']);

        $duelingStyle = ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Fighting Style: Dueling',
            'level' => 1,
        ]);

        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 5,
            'order' => 1,
            'is_primary' => true,
        ]);

        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $duelingStyle->id,
            'feature_slug' => 'test:fighter-all-bonuses:fighting-style-dueling',
            'source' => 'class',
        ]);

        // Add longsword proficiency
        $character->proficiencies()->create([
            'proficiency_type_slug' => 'core:longsword',
        ]);

        $magicSword = $this->createMeleeWeapon('Longsword +1', isMagic: true);

        $character->equipment()->create([
            'item_slug' => $magicSword->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties', 'features.feature', 'proficiencies']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Longsword +1');

        $this->assertTrue($weapon['is_proficient']);
        // Attack = STR mod (+3) + proficiency (+3) + magic (+1) = +7
        $this->assertEquals(7, $weapon['attack_bonus']);
        // Damage = STR mod (+3) + Dueling (+2) + magic (+1) = +6
        $this->assertEquals(6, $weapon['damage_bonus']);
    }

    #[Test]
    public function it_combines_archery_with_magic_ranged_weapon(): void
    {
        // DEX 16 (+3), level 5 (+3 prof), proficient, Archery, +2 bow
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 16, // +3
        ]);

        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-archery-magic']);

        $archeryStyle = ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Fighting Style: Archery',
            'level' => 1,
        ]);

        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 5,
            'order' => 1,
            'is_primary' => true,
        ]);

        $character->features()->create([
            'feature_type' => ClassFeature::class,
            'feature_id' => $archeryStyle->id,
            'feature_slug' => 'test:fighter-archery-magic:fighting-style-archery',
            'source' => 'class',
        ]);

        // Add longbow proficiency
        $character->proficiencies()->create([
            'proficiency_type_slug' => 'core:longbow',
        ]);

        $magicBow = $this->createRangedWeapon('Longbow +2', isMagic: true);

        $character->equipment()->create([
            'item_slug' => $magicBow->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load(['equipment.item.itemType', 'equipment.item.properties', 'features.feature', 'proficiencies']),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Longbow +2');

        $this->assertTrue($weapon['is_proficient']);
        // Attack = DEX mod (+3) + proficiency (+3) + Archery (+2) + magic (+2) = +10
        $this->assertEquals(10, $weapon['attack_bonus']);
        // Damage = DEX mod (+3) + magic (+2) = +5
        $this->assertEquals(5, $weapon['damage_bonus']);
    }

    // === Weapon Category Proficiency (Simple/Martial) ===

    #[Test]
    public function it_grants_proficiency_via_simple_weapons_class_proficiency(): void
    {
        // DEX 14 (+2), level 1 (+2 prof), class grants "Simple Weapons"
        $character = Character::factory()->create([
            'strength' => 10,
            'dexterity' => 14, // +2
        ]);

        // Create a class with "Simple Weapons" proficiency
        $artificer = CharacterClass::factory()->create(['slug' => 'test:artificer-simple-weapons']);

        // Add weapon proficiency to class
        $artificer->proficiencies()->create([
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Simple Weapons',
        ]);

        $character->characterClasses()->create([
            'class_slug' => $artificer->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        // Create a simple ranged weapon (no Martial property = simple)
        $crossbow = $this->createRangedWeapon('Light Crossbow');

        $character->equipment()->create([
            'item_slug' => $crossbow->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load([
                'equipment.item.itemType',
                'equipment.item.properties',
                'characterClasses.characterClass.proficiencies',
            ]),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Light Crossbow');

        // Should be proficient via class "Simple Weapons" proficiency
        $this->assertTrue($weapon['is_proficient']);
        // Attack = DEX mod (+2) + proficiency (+2) = +4
        $this->assertEquals(4, $weapon['attack_bonus']);
        // Damage = DEX mod (+2) - proficiency NOT added to damage (RAW)
        $this->assertEquals(2, $weapon['damage_bonus']);
    }

    #[Test]
    public function it_grants_proficiency_via_martial_weapons_class_proficiency(): void
    {
        // STR 16 (+3), level 1 (+2 prof), class grants "Martial Weapons"
        $character = Character::factory()->create([
            'strength' => 16, // +3
            'dexterity' => 10,
        ]);

        // Create a class with "Martial Weapons" proficiency
        $fighter = CharacterClass::factory()->create(['slug' => 'test:fighter-martial-weapons']);

        $fighter->proficiencies()->create([
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Martial Weapons',
        ]);

        $character->characterClasses()->create([
            'class_slug' => $fighter->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        // Create a martial melee weapon (has Martial property)
        $greatsword = $this->createMartialMeleeWeapon('Greatsword');

        $character->equipment()->create([
            'item_slug' => $greatsword->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load([
                'equipment.item.itemType',
                'equipment.item.properties',
                'characterClasses.characterClass.proficiencies',
            ]),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Greatsword');

        // Should be proficient via class "Martial Weapons" proficiency
        $this->assertTrue($weapon['is_proficient']);
        // Attack = STR mod (+3) + proficiency (+2) = +5
        $this->assertEquals(5, $weapon['attack_bonus']);
        // Damage = STR mod (+3) - proficiency NOT added to damage (RAW)
        $this->assertEquals(3, $weapon['damage_bonus']);
    }

    #[Test]
    public function it_does_not_grant_proficiency_when_class_lacks_weapon_category(): void
    {
        // STR 14 (+2), level 1, class has NO weapon proficiencies
        $character = Character::factory()->create([
            'strength' => 14, // +2
            'dexterity' => 10,
        ]);

        // Create a class WITHOUT weapon proficiencies
        $wizard = CharacterClass::factory()->create(['slug' => 'test:wizard-no-weapons']);
        // No proficiencies added

        $character->characterClasses()->create([
            'class_slug' => $wizard->slug,
            'level' => 1,
            'order' => 1,
            'is_primary' => true,
        ]);

        // Create a martial weapon
        $greatsword = $this->createMartialMeleeWeapon('Greatsword');

        $character->equipment()->create([
            'item_slug' => $greatsword->slug,
            'equipped' => true,
            'quantity' => 1,
        ]);

        $dto = CharacterStatsDTO::fromCharacter(
            $character->fresh()->load([
                'equipment.item.itemType',
                'equipment.item.properties',
                'characterClasses.characterClass.proficiencies',
            ]),
            $this->calculator
        );

        $weapon = collect($dto->weapons)->firstWhere('name', 'Greatsword');

        // Should NOT be proficient - class doesn't grant Martial Weapons
        $this->assertFalse($weapon['is_proficient']);
        // Attack = STR mod (+2) only - no proficiency
        $this->assertEquals(2, $weapon['attack_bonus']);
    }

    // === Helper Methods ===

    private function createMeleeWeapon(string $name, bool $isMagic = false): Item
    {
        $meleeType = ItemType::where('code', 'M')->first();

        return Item::factory()->create([
            'name' => $name,
            'slug' => 'test:'.strtolower(str_replace(' ', '-', $name)),
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d8',
            'is_magic' => $isMagic,
        ]);
    }

    private function createRangedWeapon(string $name, bool $isMagic = false): Item
    {
        $rangedType = ItemType::where('code', 'R')->first();

        return Item::factory()->create([
            'name' => $name,
            'slug' => 'test:'.strtolower(str_replace(' ', '-', $name)),
            'item_type_id' => $rangedType->id,
            'damage_dice' => '1d8',
            'range_normal' => 150,
            'range_long' => 600,
            'is_magic' => $isMagic,
        ]);
    }

    private function createMartialMeleeWeapon(string $name, bool $isMagic = false): Item
    {
        $meleeType = ItemType::where('code', 'M')->first();
        $martialProperty = \App\Models\ItemProperty::where('code', 'M')->first();

        $item = Item::factory()->create([
            'name' => $name,
            'slug' => 'test:'.strtolower(str_replace(' ', '-', $name)),
            'item_type_id' => $meleeType->id,
            'damage_dice' => '2d6',
            'is_magic' => $isMagic,
        ]);

        // Attach the Martial property
        if ($martialProperty) {
            $item->properties()->attach($martialProperty->id);
        }

        return $item;
    }
}

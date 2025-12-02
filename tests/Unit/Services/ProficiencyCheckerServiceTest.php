<?php

namespace Tests\Unit\Services;

use App\DTOs\ProficiencyStatus;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Proficiency;
use App\Models\Race;
use App\Services\ProficiencyCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProficiencyCheckerServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProficiencyCheckerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProficiencyCheckerService;
    }

    #[Test]
    public function it_returns_proficient_for_class_with_all_armor(): void
    {
        // Create a class with "all armor" proficiency (like Fighter)
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'armor',
            'proficiency_name' => 'all armor',
        ]);

        $character = Character::factory()->withClass($class)->create();

        // Create heavy armor
        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $plateArmor = Item::factory()->create([
            'name' => 'Plate',
            'item_type_id' => $heavyArmorType->id,
            'armor_class' => 18,
        ]);

        $status = $this->service->checkArmorProficiency($character, $plateArmor);

        $this->assertTrue($status->hasProficiency);
        $this->assertEmpty($status->penalties);
        $this->assertEquals('Fighter', $status->source);
    }

    #[Test]
    public function it_returns_proficient_for_class_with_specific_armor_type(): void
    {
        // Create a class with only Light Armor proficiency (like Rogue)
        $class = CharacterClass::factory()->create(['name' => 'Rogue']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'armor',
            'proficiency_name' => 'Light Armor',
        ]);

        $character = Character::factory()->withClass($class)->create();

        // Create light armor
        $lightArmorType = ItemType::where('code', 'LA')->first();
        $leatherArmor = Item::factory()->create([
            'name' => 'Leather',
            'item_type_id' => $lightArmorType->id,
            'armor_class' => 11,
        ]);

        $status = $this->service->checkArmorProficiency($character, $leatherArmor);

        $this->assertTrue($status->hasProficiency);
        $this->assertEmpty($status->penalties);
        $this->assertEquals('Rogue', $status->source);
    }

    #[Test]
    public function it_returns_not_proficient_for_class_without_armor_proficiency(): void
    {
        // Create a class with no armor proficiency (like Wizard)
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        // No armor proficiencies added

        $character = Character::factory()->withClass($class)->create();

        // Create heavy armor
        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $plateArmor = Item::factory()->create([
            'name' => 'Plate',
            'item_type_id' => $heavyArmorType->id,
            'armor_class' => 18,
        ]);

        $status = $this->service->checkArmorProficiency($character, $plateArmor);

        $this->assertFalse($status->hasProficiency);
        $this->assertNull($status->source);
    }

    #[Test]
    public function it_returns_armor_penalties_when_not_proficient(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($class)->create();

        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $plateArmor = Item::factory()->create([
            'name' => 'Plate',
            'item_type_id' => $heavyArmorType->id,
        ]);

        $status = $this->service->checkArmorProficiency($character, $plateArmor);

        $this->assertFalse($status->hasProficiency);
        $this->assertContains('disadvantage_str_dex_checks', $status->penalties);
        $this->assertContains('disadvantage_str_dex_saves', $status->penalties);
        $this->assertContains('disadvantage_attack_rolls', $status->penalties);
        $this->assertContains('cannot_cast_spells', $status->penalties);
    }

    #[Test]
    public function it_returns_proficient_for_class_with_martial_weapons(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Martial Weapons',
        ]);

        $character = Character::factory()->withClass($class)->create();

        // Create a martial weapon (longsword with Martial property)
        $meleeType = ItemType::where('code', 'M')->first();
        $martialProperty = ItemProperty::where('code', 'M')->first();

        $longsword = Item::factory()->create([
            'name' => 'Longsword',
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d8',
        ]);
        $longsword->properties()->attach($martialProperty->id);

        $status = $this->service->checkWeaponProficiency($character, $longsword);

        $this->assertTrue($status->hasProficiency);
        $this->assertEmpty($status->penalties);
        $this->assertEquals('Fighter', $status->source);
    }

    #[Test]
    public function it_returns_proficient_for_class_with_simple_weapons(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Simple Weapons',
        ]);

        $character = Character::factory()->withClass($class)->create();

        // Create a simple weapon (club - no Martial property)
        $meleeType = ItemType::where('code', 'M')->first();
        $club = Item::factory()->create([
            'name' => 'Club',
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d4',
        ]);
        // No martial property = simple weapon

        $status = $this->service->checkWeaponProficiency($character, $club);

        $this->assertTrue($status->hasProficiency);
        $this->assertEmpty($status->penalties);
        $this->assertEquals('Wizard', $status->source);
    }

    #[Test]
    public function it_returns_not_proficient_for_class_without_martial_weapons(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Simple Weapons',
        ]);

        $character = Character::factory()->withClass($class)->create();

        // Create a martial weapon
        $meleeType = ItemType::where('code', 'M')->first();
        $martialProperty = ItemProperty::where('code', 'M')->first();

        $longsword = Item::factory()->create([
            'name' => 'Longsword',
            'item_type_id' => $meleeType->id,
        ]);
        $longsword->properties()->attach($martialProperty->id);

        $status = $this->service->checkWeaponProficiency($character, $longsword);

        $this->assertFalse($status->hasProficiency);
        $this->assertNull($status->source);
    }

    #[Test]
    public function it_returns_weapon_penalty_when_not_proficient(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($class)->create();

        $meleeType = ItemType::where('code', 'M')->first();
        $martialProperty = ItemProperty::where('code', 'M')->first();

        $greatsword = Item::factory()->create([
            'name' => 'Greatsword',
            'item_type_id' => $meleeType->id,
        ]);
        $greatsword->properties()->attach($martialProperty->id);

        $status = $this->service->checkWeaponProficiency($character, $greatsword);

        $this->assertFalse($status->hasProficiency);
        $this->assertContains('no_proficiency_bonus_to_attack', $status->penalties);
    }

    #[Test]
    public function it_returns_proficient_for_specific_weapon_proficiency(): void
    {
        // Rogue with rapier proficiency
        $class = CharacterClass::factory()->create(['name' => 'Rogue']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Rapiers',
        ]);

        $character = Character::factory()->withClass($class)->create();

        // Create rapier (martial finesse weapon)
        $meleeType = ItemType::where('code', 'M')->first();
        $martialProperty = ItemProperty::where('code', 'M')->first();

        $rapier = Item::factory()->create([
            'name' => 'Rapier',
            'item_type_id' => $meleeType->id,
        ]);
        $rapier->properties()->attach($martialProperty->id);

        $status = $this->service->checkWeaponProficiency($character, $rapier);

        $this->assertTrue($status->hasProficiency);
        $this->assertEquals('Rogue', $status->source);
    }

    #[Test]
    public function it_checks_race_proficiencies(): void
    {
        // Create dwarf with battleaxe proficiency
        $race = Race::factory()->create(['name' => 'Dwarf']);
        Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'battleaxe',
        ]);

        // Wizard class (no martial weapons)
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);

        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->create();

        // Create battleaxe (martial)
        $meleeType = ItemType::where('code', 'M')->first();
        $martialProperty = ItemProperty::where('code', 'M')->first();

        $battleaxe = Item::factory()->create([
            'name' => 'Battleaxe',
            'item_type_id' => $meleeType->id,
        ]);
        $battleaxe->properties()->attach($martialProperty->id);

        $status = $this->service->checkWeaponProficiency($character, $battleaxe);

        $this->assertTrue($status->hasProficiency);
        $this->assertEquals('Dwarf', $status->source);
    }

    #[Test]
    public function it_returns_proficient_for_shield_with_shield_proficiency(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'armor',
            'proficiency_name' => 'Shields',
        ]);

        $character = Character::factory()->withClass($class)->create();

        $shieldType = ItemType::where('code', 'S')->first();
        $shield = Item::factory()->create([
            'name' => 'Shield',
            'item_type_id' => $shieldType->id,
            'armor_class' => 2,
        ]);

        $status = $this->service->checkShieldProficiency($character, $shield);

        $this->assertTrue($status->hasProficiency);
        $this->assertEquals('Fighter', $status->source);
    }

    #[Test]
    public function it_returns_not_proficient_for_shield_without_proficiency(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        // No shield proficiency

        $character = Character::factory()->withClass($class)->create();

        $shieldType = ItemType::where('code', 'S')->first();
        $shield = Item::factory()->create([
            'name' => 'Shield',
            'item_type_id' => $shieldType->id,
        ]);

        $status = $this->service->checkShieldProficiency($character, $shield);

        $this->assertFalse($status->hasProficiency);
        $this->assertContains('disadvantage_str_dex_checks', $status->penalties);
    }

    #[Test]
    public function it_checks_equipment_proficiency_for_armor(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'armor',
            'proficiency_name' => 'Heavy Armor',
        ]);

        $character = Character::factory()->withClass($class)->create();

        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $plate = Item::factory()->create([
            'name' => 'Plate',
            'item_type_id' => $heavyArmorType->id,
        ]);

        $status = $this->service->checkEquipmentProficiency($character, $plate);

        $this->assertTrue($status->hasProficiency);
    }

    #[Test]
    public function it_checks_equipment_proficiency_for_weapon(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Simple Weapons',
        ]);

        $character = Character::factory()->withClass($class)->create();

        $meleeType = ItemType::where('code', 'M')->first();
        $club = Item::factory()->create([
            'name' => 'Club',
            'item_type_id' => $meleeType->id,
        ]);

        $status = $this->service->checkEquipmentProficiency($character, $club);

        $this->assertTrue($status->hasProficiency);
    }

    #[Test]
    public function it_returns_proficient_for_non_equipment_items(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($class)->create();

        // Create adventuring gear (no proficiency required)
        $gearType = ItemType::where('code', 'G')->first();
        $rope = Item::factory()->create([
            'name' => 'Rope',
            'item_type_id' => $gearType->id,
        ]);

        $status = $this->service->checkEquipmentProficiency($character, $rope);

        $this->assertTrue($status->hasProficiency);
        $this->assertEmpty($status->penalties);
    }

    #[Test]
    public function it_handles_character_without_class(): void
    {
        // Character with no class selected yet (wizard-style creation)
        $character = Character::factory()->create();

        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $plate = Item::factory()->create([
            'name' => 'Plate',
            'item_type_id' => $heavyArmorType->id,
        ]);

        $status = $this->service->checkArmorProficiency($character, $plate);

        $this->assertFalse($status->hasProficiency);
        $this->assertNotEmpty($status->penalties);
    }

    #[Test]
    public function proficiency_status_dto_converts_to_array(): void
    {
        $status = new ProficiencyStatus(
            hasProficiency: true,
            penalties: [],
            source: 'Fighter'
        );

        $array = $status->toArray();

        $this->assertEquals([
            'has_proficiency' => true,
            'penalties' => [],
            'source' => 'Fighter',
        ], $array);
    }

    #[Test]
    public function proficiency_status_dto_with_penalties_converts_to_array(): void
    {
        $status = new ProficiencyStatus(
            hasProficiency: false,
            penalties: ['disadvantage_str_dex_checks', 'cannot_cast_spells'],
            source: null
        );

        $array = $status->toArray();

        $this->assertEquals([
            'has_proficiency' => false,
            'penalties' => ['disadvantage_str_dex_checks', 'cannot_cast_spells'],
            'source' => null,
        ], $array);
    }
}

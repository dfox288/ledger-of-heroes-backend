<?php

namespace Tests\Unit\Services;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\ItemType;
use App\Services\CharacterStatCalculator;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterStatCalculatorACTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private CharacterStatCalculator $calculator;

    private Item $leatherArmor;

    private Item $halfPlate;

    private Item $plateArmor;

    private Item $shield;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CharacterStatCalculator;
        $this->createArmorFixtures();
    }

    private function createArmorFixtures(): void
    {
        $lightArmorType = ItemType::where('code', 'LA')->first();
        $mediumArmorType = ItemType::where('code', 'MA')->first();
        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $shieldType = ItemType::where('code', 'S')->first();

        $this->leatherArmor = Item::create([
            'name' => 'Leather Armor',
            'slug' => 'leather-armor',
            'item_type_id' => $lightArmorType->id,
            'armor_class' => 11,
            'rarity' => 'common',
            'description' => 'Light armor made of leather.',
        ]);

        $this->halfPlate = Item::create([
            'name' => 'Half Plate Armor',
            'slug' => 'half-plate-armor',
            'item_type_id' => $mediumArmorType->id,
            'armor_class' => 15,
            'rarity' => 'common',
            'description' => 'Medium armor with metal plates.',
        ]);

        $this->plateArmor = Item::create([
            'name' => 'Plate Armor',
            'slug' => 'plate-armor',
            'item_type_id' => $heavyArmorType->id,
            'armor_class' => 18,
            'rarity' => 'common',
            'description' => 'Heavy armor made of metal plates.',
        ]);

        $this->shield = Item::create([
            'name' => 'Shield',
            'slug' => 'shield',
            'item_type_id' => $shieldType->id,
            'armor_class' => 2,
            'rarity' => 'common',
            'description' => 'A wooden or metal shield.',
        ]);
    }

    // ==========================================
    // AC Calculation from Equipped Items Tests
    // ==========================================

    #[Test]
    public function it_calculates_ac_unarmored_from_character(): void
    {
        // DEX 14 (+2), no armor: 10 + 2 = 12
        $character = Character::factory()
            ->withAbilityScores(['dexterity' => 14])
            ->create();

        $ac = $this->calculator->calculateArmorClass($character);

        $this->assertEquals(12, $ac);
    }

    #[Test]
    public function it_calculates_ac_with_light_armor_from_character(): void
    {
        // Leather (AC 11) + DEX 16 (+3) = 14
        $character = Character::factory()
            ->withAbilityScores(['dexterity' => 16])
            ->create();

        CharacterEquipment::factory()
            ->withItem($this->leatherArmor)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $ac = $this->calculator->calculateArmorClass($character);

        $this->assertEquals(14, $ac);
    }

    #[Test]
    public function it_calculates_ac_with_medium_armor_caps_dex(): void
    {
        // Half Plate (AC 15) + DEX 18 (+4, capped to +2) = 17
        $character = Character::factory()
            ->withAbilityScores(['dexterity' => 18])
            ->create();

        CharacterEquipment::factory()
            ->withItem($this->halfPlate)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $ac = $this->calculator->calculateArmorClass($character);

        $this->assertEquals(17, $ac);
    }

    #[Test]
    public function it_calculates_ac_with_heavy_armor_ignores_dex(): void
    {
        // Plate (AC 18) + DEX 16 (+3, ignored) = 18
        $character = Character::factory()
            ->withAbilityScores(['dexterity' => 16])
            ->create();

        CharacterEquipment::factory()
            ->withItem($this->plateArmor)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $ac = $this->calculator->calculateArmorClass($character);

        $this->assertEquals(18, $ac);
    }

    #[Test]
    public function it_adds_shield_bonus_to_ac_with_armor(): void
    {
        // Leather (11) + DEX 14 (+2) + Shield (+2) = 15
        $character = Character::factory()
            ->withAbilityScores(['dexterity' => 14])
            ->create();

        CharacterEquipment::factory()
            ->withItem($this->leatherArmor)
            ->equipped()
            ->create(['character_id' => $character->id]);

        CharacterEquipment::factory()
            ->withItem($this->shield)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $ac = $this->calculator->calculateArmorClass($character);

        $this->assertEquals(15, $ac);
    }

    #[Test]
    public function it_adds_shield_bonus_unarmored(): void
    {
        // 10 + DEX 12 (+1) + Shield (+2) = 13
        $character = Character::factory()
            ->withAbilityScores(['dexterity' => 12])
            ->create();

        CharacterEquipment::factory()
            ->withItem($this->shield)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $ac = $this->calculator->calculateArmorClass($character);

        $this->assertEquals(13, $ac);
    }

    #[Test]
    public function it_handles_null_dexterity_as_10(): void
    {
        // No DEX set (null), no armor: 10 + 0 = 10
        $character = Character::factory()->create(['dexterity' => null]);

        $ac = $this->calculator->calculateArmorClass($character);

        $this->assertEquals(10, $ac);
    }

    #[Test]
    public function it_ignores_unequipped_armor(): void
    {
        // DEX 14 (+2), unequipped armor should be ignored: 10 + 2 = 12
        $character = Character::factory()
            ->withAbilityScores(['dexterity' => 14])
            ->create();

        CharacterEquipment::factory()
            ->withItem($this->plateArmor)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,  // Not equipped!
            ]);

        $ac = $this->calculator->calculateArmorClass($character);

        $this->assertEquals(12, $ac);
    }

    #[Test]
    public function armor_class_override_takes_precedence_over_calculated_ac(): void
    {
        // Character with override set should use override, not calculated AC
        $character = Character::factory()
            ->withAbilityScores(['dexterity' => 18]) // Would give AC 14 unarmored
            ->create(['armor_class_override' => 20]);

        // Equip plate armor (AC 18) - should be ignored due to override
        CharacterEquipment::factory()
            ->withItem($this->plateArmor)
            ->equipped()
            ->create(['character_id' => $character->id]);

        // The accessor should return the override value
        $this->assertEquals(20, $character->armor_class);
    }

    #[Test]
    public function calculated_ac_is_used_when_override_is_null(): void
    {
        // Character without override should use calculated AC
        $character = Character::factory()
            ->withAbilityScores(['dexterity' => 14]) // +2 mod
            ->create(['armor_class_override' => null]);

        // Equip leather armor (AC 11 + 2 = 13)
        CharacterEquipment::factory()
            ->withItem($this->leatherArmor)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $this->assertEquals(13, $character->armor_class);
    }
}

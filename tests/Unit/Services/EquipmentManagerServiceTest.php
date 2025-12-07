<?php

namespace Tests\Unit\Services;

use App\Exceptions\ItemNotEquippableException;
use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\ItemType;
use App\Services\EquipmentManagerService;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EquipmentManagerServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private EquipmentManagerService $service;

    private Item $longsword;

    private Item $arrow;

    private Item $leatherArmor;

    private Item $chainMail;

    private Item $shield;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EquipmentManagerService;
        $this->createItemFixtures();
    }

    private function createItemFixtures(): void
    {
        $meleeWeaponType = ItemType::where('code', 'M')->first();
        $ammoType = ItemType::where('code', 'A')->first();
        $lightArmorType = ItemType::where('code', 'LA')->first();
        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $shieldType = ItemType::where('code', 'S')->first();

        $this->longsword = Item::create([
            'name' => 'Longsword',
            'slug' => 'longsword',
            'full_slug' => 'test:longsword',
            'item_type_id' => $meleeWeaponType->id,
            'rarity' => 'common',
            'description' => 'A versatile sword.',
        ]);

        $this->arrow = Item::create([
            'name' => 'Arrow',
            'slug' => 'arrow',
            'full_slug' => 'test:arrow',
            'item_type_id' => $ammoType->id,
            'rarity' => 'common',
            'description' => 'An arrow for a bow.',
        ]);

        $this->leatherArmor = Item::create([
            'name' => 'Leather Armor',
            'slug' => 'leather-armor',
            'full_slug' => 'test:leather-armor',
            'item_type_id' => $lightArmorType->id,
            'armor_class' => 11,
            'rarity' => 'common',
            'description' => 'Light armor made of leather.',
        ]);

        $this->chainMail = Item::create([
            'name' => 'Chain Mail',
            'slug' => 'chain-mail',
            'full_slug' => 'test:chain-mail',
            'item_type_id' => $heavyArmorType->id,
            'armor_class' => 16,
            'rarity' => 'common',
            'description' => 'Heavy armor made of chain links.',
        ]);

        $this->shield = Item::create([
            'name' => 'Shield',
            'slug' => 'shield',
            'full_slug' => 'test:shield',
            'item_type_id' => $shieldType->id,
            'armor_class' => 2,
            'rarity' => 'common',
            'description' => 'A wooden or metal shield.',
        ]);
    }

    // =============================
    // Add Item Tests
    // =============================

    #[Test]
    public function it_adds_item_to_character_inventory(): void
    {
        $character = Character::factory()->create();

        $equipment = $this->service->addItem($character, $this->longsword);

        $this->assertDatabaseHas('character_equipment', [
            'character_id' => $character->id,
            'item_slug' => $this->longsword->full_slug,
            'quantity' => 1,
            'equipped' => false,
        ]);
        $this->assertInstanceOf(CharacterEquipment::class, $equipment);
    }

    #[Test]
    public function it_adds_item_with_custom_quantity(): void
    {
        $character = Character::factory()->create();

        $equipment = $this->service->addItem($character, $this->arrow, 20);

        $this->assertEquals(20, $equipment->quantity);
    }

    #[Test]
    public function it_stacks_quantity_for_same_unequipped_item(): void
    {
        $character = Character::factory()->create();

        $this->service->addItem($character, $this->arrow, 20);
        $this->service->addItem($character, $this->arrow, 10);

        $equipment = $character->equipment()->where('item_slug', $this->arrow->full_slug)->first();
        $this->assertEquals(30, $equipment->quantity);
    }

    #[Test]
    public function it_does_not_stack_with_equipped_item(): void
    {
        $character = Character::factory()->create();

        // Add and equip first batch
        $equipped = $this->service->addItem($character, $this->longsword);
        $this->service->equipItem($equipped);

        // Add second item - should create new record
        $this->service->addItem($character, $this->longsword);

        $this->assertEquals(2, $character->equipment()->where('item_slug', $this->longsword->full_slug)->count());
    }

    // =============================
    // Equip Item Tests
    // =============================

    #[Test]
    public function it_equips_armor(): void
    {
        $character = Character::factory()->create();
        $equipment = $this->service->addItem($character, $this->leatherArmor);

        $this->service->equipItem($equipment);

        $equipment->refresh();
        $this->assertTrue($equipment->equipped);
        $this->assertEquals('equipped', $equipment->location);
    }

    #[Test]
    public function it_unequips_current_armor_when_equipping_new(): void
    {
        $character = Character::factory()->create();

        $leatherEquip = $this->service->addItem($character, $this->leatherArmor);
        $this->service->equipItem($leatherEquip);

        $chainEquip = $this->service->addItem($character, $this->chainMail);
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

        $armorEquip = $this->service->addItem($character, $this->leatherArmor);
        $shieldEquip = $this->service->addItem($character, $this->shield);

        $this->service->equipItem($armorEquip);
        $this->service->equipItem($shieldEquip);

        $this->assertTrue($armorEquip->refresh()->equipped);
        $this->assertTrue($shieldEquip->refresh()->equipped);
    }

    #[Test]
    public function it_unequips_current_shield_when_equipping_new(): void
    {
        $character = Character::factory()->create();

        // Create second shield
        $shieldType = ItemType::where('code', 'S')->first();
        $shield2 = Item::create([
            'name' => 'Tower Shield',
            'slug' => 'tower-shield',
            'full_slug' => 'test:tower-shield',
            'item_type_id' => $shieldType->id,
            'armor_class' => 2,
            'rarity' => 'common',
            'description' => 'A large tower shield.',
        ]);

        $shield1Equip = $this->service->addItem($character, $this->shield);
        $this->service->equipItem($shield1Equip);

        $shield2Equip = $this->service->addItem($character, $shield2);
        $this->service->equipItem($shield2Equip);

        $this->assertFalse($shield1Equip->refresh()->equipped);
        $this->assertTrue($shield2Equip->refresh()->equipped);
    }

    // =============================
    // Unequip Item Tests
    // =============================

    #[Test]
    public function it_unequips_item(): void
    {
        $character = Character::factory()->create();
        $equipment = $this->service->addItem($character, $this->leatherArmor);
        $this->service->equipItem($equipment);

        $this->service->unequipItem($equipment);

        $equipment->refresh();
        $this->assertFalse($equipment->equipped);
        $this->assertEquals('backpack', $equipment->location);
    }

    // =============================
    // Remove Item Tests
    // =============================

    #[Test]
    public function it_removes_item_from_inventory(): void
    {
        $character = Character::factory()->create();
        $equipment = $this->service->addItem($character, $this->longsword);

        $this->service->removeItem($equipment);

        $this->assertDatabaseMissing('character_equipment', ['id' => $equipment->id]);
    }

    #[Test]
    public function it_decreases_quantity_when_removing_partial(): void
    {
        $character = Character::factory()->create();
        $equipment = $this->service->addItem($character, $this->arrow, 20);

        $this->service->removeItem($equipment, 5);

        $this->assertEquals(15, $equipment->refresh()->quantity);
    }

    #[Test]
    public function it_removes_completely_when_quantity_reaches_zero(): void
    {
        $character = Character::factory()->create();
        $equipment = $this->service->addItem($character, $this->arrow, 20);

        $this->service->removeItem($equipment, 20);

        $this->assertDatabaseMissing('character_equipment', ['id' => $equipment->id]);
    }

    #[Test]
    public function it_removes_completely_when_quantity_exceeds_available(): void
    {
        $character = Character::factory()->create();
        $equipment = $this->service->addItem($character, $this->arrow, 10);

        $this->service->removeItem($equipment, 50);

        $this->assertDatabaseMissing('character_equipment', ['id' => $equipment->id]);
    }

    // =============================
    // Non-Equippable Items Tests
    // =============================

    #[Test]
    public function it_throws_exception_when_equipping_non_equippable_item(): void
    {
        $character = Character::factory()->create();

        // Create a potion (not equippable)
        $potionType = ItemType::where('code', 'P')->first();
        $potion = Item::create([
            'name' => 'Healing Potion',
            'slug' => 'healing-potion',
            'full_slug' => 'test:healing-potion',
            'item_type_id' => $potionType->id,
            'rarity' => 'common',
            'description' => 'A potion that heals.',
        ]);

        $equipment = $this->service->addItem($character, $potion);

        $this->expectException(ItemNotEquippableException::class);
        $this->expectExceptionMessage("Item 'Healing Potion' cannot be equipped");

        $this->service->equipItem($equipment);
    }

    #[Test]
    public function it_allows_equipping_weapons(): void
    {
        $character = Character::factory()->create();
        $equipment = $this->service->addItem($character, $this->longsword);

        $this->service->equipItem($equipment);

        $equipment->refresh();
        $this->assertTrue($equipment->equipped);
    }
}

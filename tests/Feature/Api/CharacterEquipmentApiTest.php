<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\ItemType;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterEquipmentApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private Item $longsword;

    private Item $leatherArmor;

    private Item $shield;

    private Item $potion;

    private Item $wand;

    private Item $backpack;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createItemFixtures();
    }

    private function createItemFixtures(): void
    {
        $meleeWeaponType = ItemType::where('code', 'M')->first();
        $lightArmorType = ItemType::where('code', 'LA')->first();
        $shieldType = ItemType::where('code', 'S')->first();

        $this->longsword = Item::create([
            'name' => 'Longsword',
            'slug' => 'test:longsword',
            'item_type_id' => $meleeWeaponType->id,
            'rarity' => 'common',
            'description' => 'A versatile sword.',
        ]);

        $this->leatherArmor = Item::create([
            'name' => 'Leather Armor',
            'slug' => 'test:leather-armor',
            'item_type_id' => $lightArmorType->id,
            'armor_class' => 11,
            'rarity' => 'common',
            'description' => 'Light armor made of leather.',
        ]);

        $this->shield = Item::create([
            'name' => 'Shield',
            'slug' => 'test:shield',
            'item_type_id' => $shieldType->id,
            'armor_class' => 2,
            'rarity' => 'common',
            'description' => 'A wooden or metal shield.',
        ]);

        $potionType = ItemType::where('code', 'P')->first();
        $this->potion = Item::create([
            'name' => 'Healing Potion',
            'slug' => 'test:healing-potion',
            'item_type_id' => $potionType->id,
            'rarity' => 'common',
            'description' => 'A potion that restores hit points.',
        ]);

        $wandType = ItemType::where('code', 'WD')->first();
        $this->wand = Item::create([
            'name' => 'Wand of Magic Missiles',
            'slug' => 'test:wand-of-magic-missiles',
            'item_type_id' => $wandType->id,
            'rarity' => 'uncommon',
            'description' => 'A wand that casts magic missile.',
        ]);

        $gearType = ItemType::where('code', 'G')->first();
        $this->backpack = Item::create([
            'name' => 'Backpack',
            'slug' => 'test:backpack',
            'item_type_id' => $gearType->id,
            'rarity' => 'common',
            'description' => 'A sturdy backpack.',
        ]);
    }

    // =============================
    // GET /characters/{id}/equipment
    // =============================

    #[Test]
    public function it_lists_character_equipment(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item.slug', $this->longsword->slug);
    }

    #[Test]
    public function it_returns_empty_array_for_character_with_no_equipment(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_returns_404_for_nonexistent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/99999/equipment');

        $response->assertNotFound();
    }

    #[Test]
    public function it_includes_group_field_for_weapons(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.group', 'Weapons');
    }

    #[Test]
    public function it_includes_group_field_for_armor(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->leatherArmor)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.group', 'Armor');
    }

    #[Test]
    public function it_includes_group_field_for_shield(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->shield)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.group', 'Armor');
    }

    #[Test]
    public function it_returns_miscellaneous_group_for_custom_items(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->custom('Magic Ring', 'A mysterious ring')
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.group', 'Miscellaneous');
    }

    #[Test]
    public function it_returns_miscellaneous_group_for_dangling_references(): void
    {
        $character = Character::factory()->create();

        // Create equipment with item_slug but no actual item (dangling reference)
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'nonexistent:item',
            'quantity' => 1,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.group', 'Miscellaneous')
            ->assertJsonPath('data.0.is_dangling', true);
    }

    #[Test]
    public function it_includes_group_field_for_consumables(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->potion)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.group', 'Consumables');
    }

    #[Test]
    public function it_includes_group_field_for_magic_items(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->wand)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.group', 'Magic Items');
    }

    #[Test]
    public function it_includes_group_field_for_gear(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->backpack)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.group', 'Gear');
    }

    // =============================
    // POST /characters/{id}/equipment
    // =============================

    #[Test]
    public function it_adds_item_to_inventory(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/equipment", [
            'item_slug' => $this->longsword->slug,
            'quantity' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.item.slug', $this->longsword->slug)
            ->assertJsonPath('data.quantity', 1)
            ->assertJsonPath('data.equipped', false);
    }

    #[Test]
    public function it_adds_item_with_default_quantity_of_1(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/equipment", [
            'item_slug' => $this->longsword->slug,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quantity', 1);
    }

    #[Test]
    public function it_allows_dangling_item_reference(): void
    {
        // Per #288, dangling references are allowed for portable character data
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/equipment", [
            'item_slug' => 'nonexistent:item',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.item_slug', 'nonexistent:item')
            ->assertJsonPath('data.item', null)
            ->assertJsonPath('data.is_dangling', true);
    }

    #[Test]
    public function it_validates_item_or_custom_name_is_required(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/equipment", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['item']);
    }

    // =============================
    // PATCH /characters/{id}/equipment/{equipment}
    // =============================

    #[Test]
    public function it_equips_armor_and_updates_ac(): void
    {
        $character = Character::factory()
            ->withAbilityScores(['dexterity' => 14]) // +2 mod
            ->create();

        $equipment = CharacterEquipment::factory()
            ->withItem($this->leatherArmor)
            ->create(['character_id' => $character->id]);

        // Verify initial AC (unarmored: 10 + 2 = 12)
        $this->assertEquals(12, $character->armor_class);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['equipped' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.equipped', true);

        // Verify new AC (leather: 11 + 2 = 13)
        $character->refresh();
        $this->assertEquals(13, $character->armor_class);
    }

    #[Test]
    public function it_unequips_item(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->leatherArmor)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['equipped' => false]
        );

        $response->assertOk()
            ->assertJsonPath('data.equipped', false)
            ->assertJsonPath('data.location', 'backpack');
    }

    #[Test]
    public function it_updates_quantity(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create([
                'character_id' => $character->id,
                'quantity' => 5,
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['quantity' => 10]
        );

        $response->assertOk()
            ->assertJsonPath('data.quantity', 10);
    }

    // =============================
    // DELETE /characters/{id}/equipment/{equipment}
    // =============================

    #[Test]
    public function it_removes_item_from_inventory(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create(['character_id' => $character->id]);

        $response = $this->deleteJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}"
        );

        $response->assertNoContent();
        $this->assertDatabaseMissing('character_equipment', ['id' => $equipment->id]);
    }

    #[Test]
    public function it_returns_404_when_deleting_nonexistent_equipment(): void
    {
        $character = Character::factory()->create();

        $response = $this->deleteJson("/api/v1/characters/{$character->id}/equipment/99999");

        $response->assertNotFound();
    }

    // =============================
    // Non-Equippable Items Tests
    // =============================

    #[Test]
    public function it_returns_422_when_equipping_non_equippable_item(): void
    {
        $character = Character::factory()->create();

        // Use the potion fixture (not equippable)
        $equipment = CharacterEquipment::factory()
            ->withItem($this->potion)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['equipped' => true]
        );

        $response->assertUnprocessable()
            ->assertJsonPath('item.name', 'Healing Potion');
    }

    // =============================
    // Custom Equipment Items Tests
    // =============================

    #[Test]
    public function it_adds_custom_item_with_name_and_description(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/equipment", [
            'custom_name' => 'Family Locket',
            'custom_description' => 'A worn silver locket containing a portrait of my parents.',
            'quantity' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.custom_name', 'Family Locket')
            ->assertJsonPath('data.custom_description', 'A worn silver locket containing a portrait of my parents.')
            ->assertJsonPath('data.quantity', 1)
            ->assertJsonPath('data.item', null);
    }

    #[Test]
    public function it_adds_custom_item_with_name_only(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/equipment", [
            'custom_name' => 'Lucky Coin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.custom_name', 'Lucky Coin')
            ->assertJsonPath('data.custom_description', null);
    }

    #[Test]
    public function it_validates_either_item_slug_or_custom_name_required(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/equipment", [
            'quantity' => 1,
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_validates_cannot_have_both_item_slug_and_custom_name(): void
    {
        $character = Character::factory()->create();

        $response = $this->postJson("/api/v1/characters/{$character->id}/equipment", [
            'item_slug' => $this->longsword->slug,
            'custom_name' => 'My Special Sword',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_lists_custom_items_alongside_database_items(): void
    {
        $character = Character::factory()->create();

        // Add database item
        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create(['character_id' => $character->id]);

        // Add custom item
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => null,
            'custom_name' => 'Mysterious Key',
            'custom_description' => 'A key that glows faintly in the dark.',
            'quantity' => 1,
            'equipped' => false,
            'location' => 'backpack',
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // Verify both types are present
        $data = $response->json('data');
        $hasDbItem = collect($data)->contains(fn ($item) => isset($item['item']['slug']) && $item['item']['slug'] === $this->longsword->slug);
        $hasCustomItem = collect($data)->contains(fn ($item) => $item['custom_name'] === 'Mysterious Key');

        $this->assertTrue($hasDbItem, 'Database item should be in response');
        $this->assertTrue($hasCustomItem, 'Custom item should be in response');
    }

    #[Test]
    public function it_returns_422_when_equipping_custom_item(): void
    {
        $character = Character::factory()->create();

        $equipment = CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => null,
            'custom_name' => 'Decorative Ring',
            'quantity' => 1,
            'equipped' => false,
            'location' => 'backpack',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['equipped' => true]
        );

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_updates_quantity_on_custom_item(): void
    {
        $character = Character::factory()->create();

        $equipment = CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => null,
            'custom_name' => 'Rations',
            'quantity' => 5,
            'equipped' => false,
            'location' => 'backpack',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['quantity' => 10]
        );

        $response->assertOk()
            ->assertJsonPath('data.quantity', 10)
            ->assertJsonPath('data.custom_name', 'Rations');
    }

    #[Test]
    public function it_deletes_custom_item(): void
    {
        $character = Character::factory()->create();

        $equipment = CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => null,
            'custom_name' => 'Old Map',
            'custom_description' => 'A weathered map of an unknown region.',
            'quantity' => 1,
            'equipped' => false,
            'location' => 'backpack',
        ]);

        $response = $this->deleteJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}"
        );

        $response->assertNoContent();
        $this->assertDatabaseMissing('character_equipment', ['id' => $equipment->id]);
    }

    // =============================
    // Issue #757: Enhanced Item Fields
    // =============================

    #[Test]
    public function it_returns_weapon_fields_for_equipped_weapon(): void
    {
        $character = Character::factory()->create();

        // Create a melee weapon with damage type and versatile damage
        $meleeType = ItemType::where('code', 'M')->first();
        $slashingType = \App\Models\DamageType::firstOrCreate(['name' => 'Slashing'], ['slug' => 'slashing']);

        $longsword = Item::create([
            'name' => 'Test Longsword',
            'slug' => 'test:longsword-757',
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d8',
            'versatile_damage' => '1d10',
            'damage_type_id' => $slashingType->id,
            'rarity' => 'common',
            'description' => 'A versatile sword.',
        ]);

        // Attach properties (Versatile)
        $versatileProperty = \App\Models\ItemProperty::firstOrCreate(
            ['code' => 'V'],
            ['name' => 'Versatile', 'description' => 'Can be used two-handed']
        );
        $longsword->properties()->attach($versatileProperty);

        CharacterEquipment::factory()
            ->withItem($longsword)
            ->equipped()
            ->create(['character_id' => $character->id, 'location' => 'main_hand']);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.item.damage_type', 'Slashing')
            ->assertJsonPath('data.0.item.versatile_damage', '1d10')
            ->assertJsonPath('data.0.item.properties.0.code', 'V')
            ->assertJsonPath('data.0.item.properties.0.name', 'Versatile')
            // Verify melee weapon has null range and armor fields
            ->assertJsonPath('data.0.item.range', null)
            ->assertJsonPath('data.0.item.armor_type', null)
            ->assertJsonPath('data.0.item.max_dex_bonus', null);
    }

    #[Test]
    public function it_returns_range_object_for_ranged_weapon(): void
    {
        $character = Character::factory()->create();

        $rangedType = ItemType::where('code', 'R')->first();
        $piercingType = \App\Models\DamageType::firstOrCreate(['name' => 'Piercing'], ['slug' => 'piercing']);

        $longbow = Item::create([
            'name' => 'Test Longbow',
            'slug' => 'test:longbow-757',
            'item_type_id' => $rangedType->id,
            'damage_dice' => '1d8',
            'damage_type_id' => $piercingType->id,
            'range_normal' => 150,
            'range_long' => 600,
            'rarity' => 'common',
            'description' => 'A ranged weapon.',
        ]);

        CharacterEquipment::factory()
            ->withItem($longbow)
            ->equipped()
            ->create(['character_id' => $character->id, 'location' => 'main_hand']);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.item.range.normal', 150)
            ->assertJsonPath('data.0.item.range.long', 600);
    }

    #[Test]
    public function it_returns_armor_fields_for_equipped_armor(): void
    {
        $character = Character::factory()->create();

        // Create heavy armor
        $heavyArmorType = ItemType::where('code', 'HA')->first();

        $chainMail = Item::create([
            'name' => 'Test Chain Mail',
            'slug' => 'test:chain-mail-757',
            'item_type_id' => $heavyArmorType->id,
            'armor_class' => 16,
            'strength_requirement' => 13,
            'stealth_disadvantage' => true,
            'rarity' => 'common',
            'description' => 'Heavy armor.',
        ]);

        CharacterEquipment::factory()
            ->withItem($chainMail)
            ->equipped()
            ->create(['character_id' => $character->id, 'location' => 'armor']);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.item.armor_type', 'heavy')
            ->assertJsonPath('data.0.item.max_dex_bonus', 0)
            ->assertJsonPath('data.0.item.stealth_disadvantage', true)
            ->assertJsonPath('data.0.item.strength_requirement', 13);
    }

    #[Test]
    public function it_returns_correct_max_dex_bonus_for_medium_armor(): void
    {
        $character = Character::factory()->create();

        $mediumArmorType = ItemType::where('code', 'MA')->first();

        $breastplate = Item::create([
            'name' => 'Test Breastplate',
            'slug' => 'test:breastplate-757',
            'item_type_id' => $mediumArmorType->id,
            'armor_class' => 14,
            'rarity' => 'common',
            'description' => 'Medium armor.',
        ]);

        CharacterEquipment::factory()
            ->withItem($breastplate)
            ->equipped()
            ->create(['character_id' => $character->id, 'location' => 'armor']);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.item.armor_type', 'medium')
            ->assertJsonPath('data.0.item.max_dex_bonus', 2);
    }

    #[Test]
    public function it_returns_null_max_dex_bonus_for_light_armor(): void
    {
        $character = Character::factory()->create();

        $lightArmorType = ItemType::where('code', 'LA')->first();

        $leatherArmor = Item::create([
            'name' => 'Test Leather',
            'slug' => 'test:leather-757',
            'item_type_id' => $lightArmorType->id,
            'armor_class' => 11,
            'rarity' => 'common',
            'description' => 'Light armor.',
        ]);

        CharacterEquipment::factory()
            ->withItem($leatherArmor)
            ->equipped()
            ->create(['character_id' => $character->id, 'location' => 'armor']);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.item.armor_type', 'light')
            ->assertJsonPath('data.0.item.max_dex_bonus', null);
    }

    #[Test]
    public function it_returns_magic_item_fields_for_magic_weapon(): void
    {
        $character = Character::factory()->create();

        $meleeType = ItemType::where('code', 'M')->first();
        $slashingType = \App\Models\DamageType::firstOrCreate(['name' => 'Slashing'], ['slug' => 'slashing']);

        $magicSword = Item::create([
            'name' => '+1 Longsword',
            'slug' => 'test:plus-one-longsword-757',
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d8',
            'damage_type_id' => $slashingType->id,
            'rarity' => 'uncommon',
            'is_magic' => true,
            'requires_attunement' => false,
            'description' => 'A magic longsword.',
        ]);

        // Add magic bonus via modifier
        $magicSword->modifiers()->create([
            'modifier_category' => 'weapon_attack',
            'value' => 1,
        ]);

        CharacterEquipment::factory()
            ->withItem($magicSword)
            ->equipped()
            ->create(['character_id' => $character->id, 'location' => 'main_hand']);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.item.is_magic', true)
            ->assertJsonPath('data.0.item.rarity', 'uncommon')
            ->assertJsonPath('data.0.item.magic_bonus', 1);
    }

    #[Test]
    public function it_returns_charge_capacity_for_charged_items(): void
    {
        $character = Character::factory()->create();

        $wandType = ItemType::where('code', 'WD')->first();

        $wand = Item::create([
            'name' => 'Wand of Magic Missiles',
            'slug' => 'test:wand-of-magic-missiles-757',
            'item_type_id' => $wandType->id,
            'rarity' => 'uncommon',
            'is_magic' => true,
            'requires_attunement' => false,
            'description' => 'A wand that casts magic missile.',
            'charges_max' => '7',
            'recharge_formula' => '1d6+1',
            'recharge_timing' => 'dawn',
        ]);

        CharacterEquipment::factory()
            ->withItem($wand)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.item.charges_max', '7')
            ->assertJsonPath('data.0.item.recharge_formula', '1d6+1')
            ->assertJsonPath('data.0.item.recharge_timing', 'dawn');
    }
}

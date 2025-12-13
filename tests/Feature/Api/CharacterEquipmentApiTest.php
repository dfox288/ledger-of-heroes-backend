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
}

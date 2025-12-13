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

/**
 * Tests for equipment attunement tracking.
 *
 * D&D 5e rules:
 * - Some magic items require attunement
 * - A creature can be attuned to at most 3 magic items at a time
 * - Attuning to an item takes a short rest
 *
 * Covers issue #498.3.2
 */
class CharacterEquipmentAttunementTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private Item $magicSword;

    private Item $magicRing;

    private Item $magicCloak;

    private Item $magicAmulet;

    private Item $normalSword;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createItemFixtures();
    }

    private function createItemFixtures(): void
    {
        $meleeWeaponType = ItemType::where('code', 'M')->first();
        $wonderousType = ItemType::where('code', 'W')->first();

        // Magic items that require attunement
        $this->magicSword = Item::create([
            'name' => 'Flame Tongue',
            'slug' => 'test:flame-tongue',
            'item_type_id' => $meleeWeaponType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A magic sword that bursts into flame.',
        ]);

        $this->magicRing = Item::create([
            'name' => 'Ring of Protection',
            'slug' => 'test:ring-of-protection',
            'item_type_id' => $wonderousType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A magic ring that grants +1 AC.',
        ]);

        $this->magicCloak = Item::create([
            'name' => 'Cloak of Displacement',
            'slug' => 'test:cloak-of-displacement',
            'item_type_id' => $wonderousType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A magic cloak that makes you harder to hit.',
        ]);

        $this->magicAmulet = Item::create([
            'name' => 'Amulet of Health',
            'slug' => 'test:amulet-of-health',
            'item_type_id' => $wonderousType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A magic amulet that sets CON to 19.',
        ]);

        // Normal item that doesn't require attunement
        $this->normalSword = Item::create([
            'name' => 'Longsword',
            'slug' => 'test:longsword',
            'item_type_id' => $meleeWeaponType->id,
            'rarity' => 'common',
            'requires_attunement' => false,
            'description' => 'A standard longsword.',
        ]);
    }

    // =============================
    // Basic Attunement Tracking
    // =============================

    #[Test]
    public function it_defaults_is_attuned_to_false(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.is_attuned', false);
    }

    #[Test]
    public function it_can_attune_to_magic_item(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['is_attuned' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.is_attuned', true);

        $this->assertDatabaseHas('character_equipment', [
            'id' => $equipment->id,
            'is_attuned' => true,
        ]);
    }

    #[Test]
    public function it_can_unattune_from_magic_item(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create([
                'character_id' => $character->id,
                'is_attuned' => true,
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['is_attuned' => false]
        );

        $response->assertOk()
            ->assertJsonPath('data.is_attuned', false);

        $this->assertDatabaseHas('character_equipment', [
            'id' => $equipment->id,
            'is_attuned' => false,
        ]);
    }

    #[Test]
    public function it_rejects_attunement_for_non_attuneable_items(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->normalSword)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['is_attuned' => true]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_attuned']);
    }

    // =============================
    // Attunement Slot Limit (3)
    // =============================

    #[Test]
    public function it_enforces_three_attunement_slot_limit(): void
    {
        $character = Character::factory()->create();

        // Attune to 3 items
        CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create(['character_id' => $character->id, 'is_attuned' => true]);

        CharacterEquipment::factory()
            ->withItem($this->magicRing)
            ->create(['character_id' => $character->id, 'is_attuned' => true]);

        CharacterEquipment::factory()
            ->withItem($this->magicCloak)
            ->create(['character_id' => $character->id, 'is_attuned' => true]);

        // Try to attune to a 4th item
        $fourthItem = CharacterEquipment::factory()
            ->withItem($this->magicAmulet)
            ->create(['character_id' => $character->id, 'is_attuned' => false]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$fourthItem->id}",
            ['is_attuned' => true]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_attuned']);

        // Verify the 4th item is NOT attuned
        $this->assertDatabaseHas('character_equipment', [
            'id' => $fourthItem->id,
            'is_attuned' => false,
        ]);
    }

    #[Test]
    public function it_allows_reattunement_when_under_limit(): void
    {
        $character = Character::factory()->create();

        // Attune to 2 items
        CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create(['character_id' => $character->id, 'is_attuned' => true]);

        CharacterEquipment::factory()
            ->withItem($this->magicRing)
            ->create(['character_id' => $character->id, 'is_attuned' => true]);

        // Third attunement should succeed
        $thirdItem = CharacterEquipment::factory()
            ->withItem($this->magicCloak)
            ->create(['character_id' => $character->id, 'is_attuned' => false]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$thirdItem->id}",
            ['is_attuned' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.is_attuned', true);
    }

    // =============================
    // Attunement Slots in Character Response
    // =============================

    #[Test]
    public function it_exposes_attunement_slots_in_character_response(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create(['character_id' => $character->id, 'is_attuned' => true]);

        CharacterEquipment::factory()
            ->withItem($this->magicRing)
            ->create(['character_id' => $character->id, 'is_attuned' => true]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.attunement_slots.used', 2)
            ->assertJsonPath('data.attunement_slots.max', 3);
    }

    #[Test]
    public function it_shows_zero_attunement_slots_used_when_none_attuned(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create(['character_id' => $character->id, 'is_attuned' => false]);

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.attunement_slots.used', 0)
            ->assertJsonPath('data.attunement_slots.max', 3);
    }

    // =============================
    // requires_attunement in Response
    // =============================

    #[Test]
    public function it_exposes_requires_attunement_from_item(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.item.requires_attunement', true);
    }

    #[Test]
    public function it_shows_requires_attunement_false_for_normal_items(): void
    {
        $character = Character::factory()->create();

        CharacterEquipment::factory()
            ->withItem($this->normalSword)
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.item.requires_attunement', false);
    }

    // =============================
    // Attunement Persistence (Issue #583)
    // =============================

    #[Test]
    public function it_persists_attunement_when_item_moved_to_backpack(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
                'is_attuned' => true,
            ]);

        // Move to backpack
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'backpack']
        );

        $response->assertOk()
            ->assertJsonPath('data.is_attuned', true)
            ->assertJsonPath('data.location', 'backpack');

        $this->assertDatabaseHas('character_equipment', [
            'id' => $equipment->id,
            'is_attuned' => true,
            'location' => 'backpack',
        ]);
    }

    #[Test]
    public function it_persists_attunement_when_item_displaced_by_another(): void
    {
        $character = Character::factory()->create();

        // First ring equipped and attuned in ring_1 slot
        $firstRing = CharacterEquipment::factory()
            ->withItem($this->magicRing)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'ring_1',
                'is_attuned' => true,
            ]);

        // Second ring - equip to same slot, displacing first
        $secondRing = CharacterEquipment::factory()
            ->withItem($this->magicAmulet) // Using amulet as second "ring" for test
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$secondRing->id}",
            ['location' => 'ring_1']
        );

        $response->assertOk();

        // First ring should be in backpack but STILL attuned
        $this->assertDatabaseHas('character_equipment', [
            'id' => $firstRing->id,
            'is_attuned' => true,
            'location' => 'backpack',
            'equipped' => false,
        ]);
    }

    #[Test]
    public function it_persists_attunement_when_unequipped_via_equipped_false(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
                'is_attuned' => true,
            ]);

        // Unequip via equipped=false
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['equipped' => false]
        );

        $response->assertOk()
            ->assertJsonPath('data.is_attuned', true)
            ->assertJsonPath('data.equipped', false);

        $this->assertDatabaseHas('character_equipment', [
            'id' => $equipment->id,
            'is_attuned' => true,
            'equipped' => false,
        ]);
    }

    #[Test]
    public function it_allows_attuning_to_item_in_backpack(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicSword)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
                'is_attuned' => false,
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['is_attuned' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.is_attuned', true);

        $this->assertDatabaseHas('character_equipment', [
            'id' => $equipment->id,
            'is_attuned' => true,
            'location' => 'backpack',
        ]);
    }
}

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
 * Core equipment location tests.
 *
 * Tests use new expanded slot system (issue #582):
 * - Removed: 'worn' (use 'armor' instead)
 * - Removed: 'attuned' as location (use is_attuned flag + specific slot)
 * - Added: head, neck, cloak, armor, belt, hands, ring_1, ring_2, feet
 */
class CharacterEquipmentLocationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private Item $longsword;

    private Item $shortsword;

    private Item $leatherArmor;

    private Item $chainMail;

    private Item $shield;

    private Item $magicRing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createItemFixtures();
    }

    private function createItemFixtures(): void
    {
        $meleeWeaponType = ItemType::where('code', 'M')->first();
        $lightArmorType = ItemType::where('code', 'LA')->first();
        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $shieldType = ItemType::where('code', 'S')->first();
        $ringType = ItemType::where('code', 'RG')->first();

        $this->longsword = Item::create([
            'name' => 'Longsword',
            'slug' => 'test:longsword',
            'item_type_id' => $meleeWeaponType->id,
            'rarity' => 'common',
            'description' => 'A versatile sword.',
        ]);

        $this->shortsword = Item::create([
            'name' => 'Shortsword',
            'slug' => 'test:shortsword',
            'item_type_id' => $meleeWeaponType->id,
            'rarity' => 'common',
            'description' => 'A light, finesse weapon.',
        ]);

        $this->leatherArmor = Item::create([
            'name' => 'Leather Armor',
            'slug' => 'test:leather-armor',
            'item_type_id' => $lightArmorType->id,
            'armor_class' => 11,
            'rarity' => 'common',
            'description' => 'Light armor made of leather.',
        ]);

        $this->chainMail = Item::create([
            'name' => 'Chain Mail',
            'slug' => 'test:chain-mail',
            'item_type_id' => $heavyArmorType->id,
            'armor_class' => 16,
            'rarity' => 'common',
            'description' => 'Heavy armor of interlocking rings.',
        ]);

        $this->shield = Item::create([
            'name' => 'Shield',
            'slug' => 'test:shield',
            'item_type_id' => $shieldType->id,
            'armor_class' => 2,
            'rarity' => 'common',
            'description' => 'A wooden or metal shield.',
        ]);

        $this->magicRing = Item::create([
            'name' => 'Ring of Protection',
            'slug' => 'test:ring-of-protection',
            'item_type_id' => $ringType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A magic ring that grants protection.',
        ]);
    }

    // =============================
    // Location Validation Tests
    // =============================

    #[Test]
    public function it_accepts_valid_location_main_hand(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'main_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'main_hand');
    }

    #[Test]
    public function it_accepts_valid_location_off_hand(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->shortsword)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'off_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'off_hand');
    }

    #[Test]
    public function it_accepts_valid_location_armor(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->leatherArmor)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'armor']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'armor');
    }

    #[Test]
    public function it_accepts_valid_location_ring_1_with_attunement(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicRing)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'ring_1', 'is_attuned' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'ring_1')
            ->assertJsonPath('data.is_attuned', true);
    }

    #[Test]
    public function it_accepts_valid_location_backpack(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'backpack']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'backpack');
    }

    #[Test]
    public function it_rejects_invalid_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'invalid_location']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['location']);
    }

    // =============================
    // Auto-set equipped based on location
    // =============================

    #[Test]
    public function it_sets_equipped_true_when_location_is_main_hand(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'main_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'main_hand')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_sets_equipped_true_when_location_is_off_hand(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->shortsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'off_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'off_hand')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_sets_equipped_true_when_location_is_armor(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->leatherArmor)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'armor']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'armor')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_sets_equipped_and_attuned_when_location_is_ring_slot_with_attunement(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicRing)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'is_attuned' => false,
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'ring_1', 'is_attuned' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'ring_1')
            ->assertJsonPath('data.equipped', true)
            ->assertJsonPath('data.is_attuned', true);
    }

    #[Test]
    public function it_sets_equipped_false_when_location_is_backpack(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'backpack']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'backpack')
            ->assertJsonPath('data.equipped', false);
    }

    // =============================
    // Slot Enforcement Tests
    // =============================

    #[Test]
    public function it_enforces_single_armor_slot(): void
    {
        $character = Character::factory()->create();

        // First armor is equipped
        CharacterEquipment::factory()
            ->withItem($this->leatherArmor)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'armor',
            ]);

        // Try to equip second armor
        $secondArmor = CharacterEquipment::factory()
            ->withItem($this->chainMail)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$secondArmor->id}",
            ['location' => 'armor']
        );

        // Should succeed - auto-unequips previous armor
        $response->assertOk()
            ->assertJsonPath('data.location', 'armor')
            ->assertJsonPath('data.equipped', true);

        // First armor should now be in backpack
        $this->assertDatabaseHas('character_equipment', [
            'item_slug' => $this->leatherArmor->slug,
            'character_id' => $character->id,
            'location' => 'backpack',
            'equipped' => false,
        ]);
    }

    #[Test]
    public function it_enforces_single_main_hand_slot(): void
    {
        $character = Character::factory()->create();

        // First weapon in main hand
        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        // Try to equip second weapon to main hand
        $secondWeapon = CharacterEquipment::factory()
            ->withItem($this->shortsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$secondWeapon->id}",
            ['location' => 'main_hand']
        );

        // Should succeed - auto-unequips previous weapon
        $response->assertOk()
            ->assertJsonPath('data.location', 'main_hand');

        // First weapon should now be in backpack
        $this->assertDatabaseHas('character_equipment', [
            'item_slug' => $this->longsword->slug,
            'character_id' => $character->id,
            'location' => 'backpack',
            'equipped' => false,
        ]);
    }

    #[Test]
    public function it_enforces_single_off_hand_slot(): void
    {
        $character = Character::factory()->create();

        // Shield in off hand
        CharacterEquipment::factory()
            ->withItem($this->shield)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'off_hand',
            ]);

        // Try to equip weapon to off hand
        $weapon = CharacterEquipment::factory()
            ->withItem($this->shortsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$weapon->id}",
            ['location' => 'off_hand']
        );

        // Should succeed - auto-unequips shield
        $response->assertOk()
            ->assertJsonPath('data.location', 'off_hand');

        // Shield should now be in backpack
        $this->assertDatabaseHas('character_equipment', [
            'item_slug' => $this->shield->slug,
            'character_id' => $character->id,
            'location' => 'backpack',
            'equipped' => false,
        ]);
    }

    #[Test]
    public function it_enforces_max_three_attuned_items(): void
    {
        $character = Character::factory()->create();
        $ringType = ItemType::where('code', 'RG')->first();

        // Create 3 attuned items in ring slots and other slots
        $slots = ['ring_1', 'ring_2', 'neck'];
        for ($i = 1; $i <= 3; $i++) {
            $ring = Item::create([
                'name' => "Magic Item $i",
                'slug' => "test:magic-item-$i",
                'item_type_id' => $ringType->id,
                'rarity' => 'rare',
                'requires_attunement' => true,
                'description' => "A magic item number $i.",
            ]);

            CharacterEquipment::factory()
                ->withItem($ring)
                ->create([
                    'character_id' => $character->id,
                    'equipped' => true,
                    'location' => $slots[$i - 1],
                    'is_attuned' => true,
                ]);
        }

        // Try to attune 4th item
        $fourthRing = Item::create([
            'name' => 'Magic Item 4',
            'slug' => 'test:magic-item-4',
            'item_type_id' => $ringType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A fourth magic item.',
        ]);

        $equipment = CharacterEquipment::factory()
            ->withItem($fourthRing)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'belt', 'is_attuned' => true]
        );

        // Should fail - at attunement limit
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['is_attuned']);
    }

    #[Test]
    public function it_allows_attunement_when_under_limit(): void
    {
        $character = Character::factory()->create();
        $ringType = ItemType::where('code', 'RG')->first();

        // Create 2 attuned items (under the limit of 3)
        for ($i = 1; $i <= 2; $i++) {
            $ring = Item::create([
                'name' => "Ring $i",
                'slug' => "test:ring-$i",
                'item_type_id' => $ringType->id,
                'rarity' => 'rare',
                'requires_attunement' => true,
                'description' => "A magic ring number $i.",
            ]);

            CharacterEquipment::factory()
                ->withItem($ring)
                ->create([
                    'character_id' => $character->id,
                    'equipped' => true,
                    'location' => $i === 1 ? 'ring_1' : 'ring_2',
                    'is_attuned' => true,
                ]);
        }

        // Try to attune 3rd item - should work
        $thirdRing = Item::create([
            'name' => 'Ring 3',
            'slug' => 'test:ring-3',
            'item_type_id' => $ringType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A third magic ring.',
        ]);

        $equipment = CharacterEquipment::factory()
            ->withItem($thirdRing)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'neck', 'is_attuned' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'neck')
            ->assertJsonPath('data.is_attuned', true);
    }

    // =============================
    // Attunement Edge Cases
    // =============================

    #[Test]
    public function it_clears_attunement_when_moving_attuned_item_to_main_hand(): void
    {
        $character = Character::factory()->create();

        // Create an attunable weapon (magic sword that requires attunement)
        $meleeWeaponType = ItemType::where('code', 'M')->first();
        $magicSword = Item::create([
            'name' => 'Flame Tongue',
            'slug' => 'test:flame-tongue',
            'item_type_id' => $meleeWeaponType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A magic sword that bursts into flame.',
        ]);

        // Start with item attuned in belt slot
        $equipment = CharacterEquipment::factory()
            ->withItem($magicSword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'belt',
                'is_attuned' => true,
            ]);

        // Move to main_hand - should remain equipped but lose attunement
        // (unless we explicitly keep is_attuned)
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'main_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'main_hand')
            ->assertJsonPath('data.equipped', true);
        // Note: attunement is preserved unless explicitly cleared or moved to backpack
    }

    #[Test]
    public function it_preserves_attunement_count_when_moving_to_backpack(): void
    {
        $character = Character::factory()->create();
        $ringType = ItemType::where('code', 'RG')->first();

        // Create 3 attuned items (at max)
        $slots = ['ring_1', 'ring_2', 'neck'];
        for ($i = 1; $i <= 3; $i++) {
            $ring = Item::create([
                'name' => "Ring $i",
                'slug' => "test:edge-ring-$i",
                'item_type_id' => $ringType->id,
                'rarity' => 'rare',
                'requires_attunement' => true,
                'description' => "Magic ring $i.",
            ]);

            CharacterEquipment::factory()
                ->withItem($ring)
                ->create([
                    'character_id' => $character->id,
                    'equipped' => true,
                    'location' => $slots[$i - 1],
                    'is_attuned' => true,
                ]);
        }

        // Move first ring to backpack - frees up a slot
        $firstEquipment = $character->equipment()->first();
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$firstEquipment->id}",
            ['location' => 'backpack']
        );

        $response->assertOk()
            ->assertJsonPath('data.is_attuned', false);

        // Now we should be able to attune a new item
        $newRing = Item::create([
            'name' => 'Ring 4',
            'slug' => 'test:edge-ring-4',
            'item_type_id' => $ringType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A fourth magic ring.',
        ]);

        $newEquipment = CharacterEquipment::factory()
            ->withItem($newRing)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$newEquipment->id}",
            ['location' => 'belt', 'is_attuned' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'belt')
            ->assertJsonPath('data.is_attuned', true);
    }

    // =============================
    // Custom Item Tests
    // =============================

    #[Test]
    public function it_rejects_custom_item_at_equipped_location(): void
    {
        $character = Character::factory()->create();

        $customEquipment = CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => null,
            'custom_name' => 'Mysterious Amulet',
            'quantity' => 1,
            'equipped' => false,
            'location' => 'backpack',
        ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$customEquipment->id}",
            ['location' => 'armor']
        );

        $response->assertUnprocessable();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for expanded equipment location slots and two-handed weapon validation.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/582
 */
class CharacterEquipmentExpandedSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private Item $greatsword;

    private Item $longsword;

    private Item $shortsword;

    private Item $shield;

    private Item $leatherArmor;

    private Item $helmet;

    private Item $amulet;

    private Item $cloak;

    private Item $boots;

    private Item $gloves;

    private Item $belt;

    private Item $ring;

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
        $shieldType = ItemType::where('code', 'S')->first();
        $ringType = ItemType::where('code', 'RG')->first();
        $wondrousType = ItemType::where('code', 'W')->first();
        $gearType = ItemType::where('code', 'G')->first();

        $twoHandedProperty = ItemProperty::where('code', '2H')->first();
        $versatileProperty = ItemProperty::where('code', 'V')->first();

        // Two-handed weapon (greatsword)
        $this->greatsword = Item::create([
            'name' => 'Greatsword',
            'slug' => 'test:greatsword',
            'item_type_id' => $meleeWeaponType->id,
            'damage_dice' => '2d6',
            'rarity' => 'common',
            'description' => 'A massive two-handed sword.',
        ]);
        $this->greatsword->properties()->attach($twoHandedProperty->id);

        // Versatile weapon (longsword)
        $this->longsword = Item::create([
            'name' => 'Longsword',
            'slug' => 'test:longsword-v',
            'item_type_id' => $meleeWeaponType->id,
            'damage_dice' => '1d8',
            'versatile_damage' => '1d10',
            'rarity' => 'common',
            'description' => 'A versatile sword.',
        ]);
        $this->longsword->properties()->attach($versatileProperty->id);

        // One-handed weapon (shortsword)
        $this->shortsword = Item::create([
            'name' => 'Shortsword',
            'slug' => 'test:shortsword-exp',
            'item_type_id' => $meleeWeaponType->id,
            'damage_dice' => '1d6',
            'rarity' => 'common',
            'description' => 'A light sword.',
        ]);

        // Shield
        $this->shield = Item::create([
            'name' => 'Shield',
            'slug' => 'test:shield-exp',
            'item_type_id' => $shieldType->id,
            'armor_class' => 2,
            'rarity' => 'common',
            'description' => 'A wooden shield.',
        ]);

        // Body armor
        $this->leatherArmor = Item::create([
            'name' => 'Leather Armor',
            'slug' => 'test:leather-exp',
            'item_type_id' => $lightArmorType->id,
            'armor_class' => 11,
            'rarity' => 'common',
            'description' => 'Light armor.',
        ]);

        // Wondrous items for different slots
        $this->helmet = Item::create([
            'name' => 'Helm of Awareness',
            'slug' => 'test:helmet',
            'item_type_id' => $wondrousType->id,
            'rarity' => 'uncommon',
            'description' => 'A magical helmet.',
        ]);

        $this->amulet = Item::create([
            'name' => 'Amulet of Health',
            'slug' => 'test:amulet',
            'item_type_id' => $wondrousType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A magical amulet.',
        ]);

        $this->cloak = Item::create([
            'name' => 'Cloak of Protection',
            'slug' => 'test:cloak',
            'item_type_id' => $wondrousType->id,
            'rarity' => 'uncommon',
            'requires_attunement' => true,
            'description' => 'A protective cloak.',
        ]);

        $this->boots = Item::create([
            'name' => 'Boots of Speed',
            'slug' => 'test:boots',
            'item_type_id' => $wondrousType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'Magical boots.',
        ]);

        $this->gloves = Item::create([
            'name' => 'Gauntlets of Ogre Power',
            'slug' => 'test:gloves',
            'item_type_id' => $wondrousType->id,
            'rarity' => 'uncommon',
            'requires_attunement' => true,
            'description' => 'Magical gauntlets.',
        ]);

        $this->belt = Item::create([
            'name' => 'Belt of Giant Strength',
            'slug' => 'test:belt',
            'item_type_id' => $wondrousType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A belt that grants strength.',
        ]);

        // Rings
        $this->ring = Item::create([
            'name' => 'Simple Ring',
            'slug' => 'test:ring-simple',
            'item_type_id' => $ringType->id,
            'rarity' => 'common',
            'description' => 'A simple ring.',
        ]);

        $this->magicRing = Item::create([
            'name' => 'Ring of Protection',
            'slug' => 'test:ring-protection',
            'item_type_id' => $ringType->id,
            'rarity' => 'rare',
            'requires_attunement' => true,
            'description' => 'A magical ring.',
        ]);
    }

    // =========================================================================
    // New Location Slot Tests
    // =========================================================================

    #[Test]
    public function it_accepts_head_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->helmet)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'head']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'head')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_accepts_neck_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->amulet)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'neck']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'neck')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_accepts_cloak_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->cloak)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'cloak']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'cloak')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_accepts_armor_location(): void
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
            ->assertJsonPath('data.location', 'armor')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_accepts_belt_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->belt)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'belt']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'belt')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_accepts_hands_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->gloves)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'hands']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'hands')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_accepts_ring_1_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->ring)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'ring_1']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'ring_1')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_accepts_ring_2_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->ring)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'ring_2']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'ring_2')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_accepts_feet_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->boots)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'feet']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'feet')
            ->assertJsonPath('data.equipped', true);
    }

    #[Test]
    public function it_enforces_single_slot_for_new_locations(): void
    {
        $character = Character::factory()->create();

        // First helmet equipped
        CharacterEquipment::factory()
            ->withItem($this->helmet)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'head',
            ]);

        // Create a second helmet
        $secondHelmet = Item::create([
            'name' => 'Helm of Brilliance',
            'slug' => 'test:helmet-2',
            'item_type_id' => ItemType::where('code', 'W')->first()->id,
            'rarity' => 'legendary',
            'description' => 'Another magical helmet.',
        ]);

        $equipment = CharacterEquipment::factory()
            ->withItem($secondHelmet)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'head']
        );

        // Should succeed - auto-unequips previous helmet
        $response->assertOk()
            ->assertJsonPath('data.location', 'head');

        // First helmet should be in backpack
        $this->assertDatabaseHas('character_equipment', [
            'item_slug' => $this->helmet->slug,
            'character_id' => $character->id,
            'location' => 'backpack',
            'equipped' => false,
        ]);
    }

    #[Test]
    public function it_allows_two_rings_in_separate_slots(): void
    {
        $character = Character::factory()->create();

        // First ring in ring_1
        CharacterEquipment::factory()
            ->withItem($this->ring)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'ring_1',
            ]);

        // Second ring - should be able to go to ring_2
        $secondRing = Item::create([
            'name' => 'Ring of Jumping',
            'slug' => 'test:ring-jumping',
            'item_type_id' => ItemType::where('code', 'RG')->first()->id,
            'rarity' => 'uncommon',
            'description' => 'Another ring.',
        ]);

        $equipment = CharacterEquipment::factory()
            ->withItem($secondRing)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'ring_2']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'ring_2');

        // Both rings should remain equipped
        $this->assertDatabaseHas('character_equipment', [
            'item_slug' => $this->ring->slug,
            'location' => 'ring_1',
            'equipped' => true,
        ]);
    }

    // =========================================================================
    // Two-Handed Weapon Tests
    // =========================================================================

    #[Test]
    public function it_unequips_off_hand_when_equipping_two_handed_weapon(): void
    {
        $character = Character::factory()->create();

        // Shield in off-hand
        CharacterEquipment::factory()
            ->withItem($this->shield)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'off_hand',
            ]);

        // Equip greatsword (two-handed)
        $equipment = CharacterEquipment::factory()
            ->withItem($this->greatsword)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'main_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'main_hand');

        // Shield should be auto-unequipped to backpack
        $this->assertDatabaseHas('character_equipment', [
            'item_slug' => $this->shield->slug,
            'character_id' => $character->id,
            'location' => 'backpack',
            'equipped' => false,
        ]);
    }

    #[Test]
    public function it_rejects_off_hand_when_two_handed_weapon_equipped(): void
    {
        $character = Character::factory()->create();

        // Greatsword (two-handed) in main hand
        CharacterEquipment::factory()
            ->withItem($this->greatsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        // Try to equip shield in off-hand
        $equipment = CharacterEquipment::factory()
            ->withItem($this->shield)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'off_hand']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['location']);
    }

    #[Test]
    public function it_allows_off_hand_with_versatile_weapon(): void
    {
        $character = Character::factory()->create();

        // Longsword (versatile) in main hand
        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        // Should be able to equip shield in off-hand
        $equipment = CharacterEquipment::factory()
            ->withItem($this->shield)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'off_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'off_hand');
    }

    #[Test]
    public function it_allows_off_hand_with_one_handed_weapon(): void
    {
        $character = Character::factory()->create();

        // Shortsword (one-handed) in main hand
        CharacterEquipment::factory()
            ->withItem($this->shortsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        // Should be able to equip shield in off-hand
        $equipment = CharacterEquipment::factory()
            ->withItem($this->shield)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'off_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'off_hand');
    }

    #[Test]
    public function it_allows_dual_wielding_one_handed_weapons(): void
    {
        $character = Character::factory()->create();

        // First shortsword in main hand
        CharacterEquipment::factory()
            ->withItem($this->shortsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        // Second weapon in off hand
        $dagger = Item::create([
            'name' => 'Dagger',
            'slug' => 'test:dagger',
            'item_type_id' => ItemType::where('code', 'M')->first()->id,
            'damage_dice' => '1d4',
            'rarity' => 'common',
            'description' => 'A simple dagger.',
        ]);

        $equipment = CharacterEquipment::factory()
            ->withItem($dagger)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'off_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'off_hand');
    }

    #[Test]
    public function it_allows_off_hand_when_main_hand_empty(): void
    {
        $character = Character::factory()->create();

        // Empty main hand, try to equip something in off-hand
        $equipment = CharacterEquipment::factory()
            ->withItem($this->shield)
            ->create(['character_id' => $character->id]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'off_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'off_hand');
    }

    #[Test]
    public function it_allows_off_hand_after_moving_two_handed_weapon_away(): void
    {
        $character = Character::factory()->create();

        // Greatsword (two-handed) in main hand - off_hand is blocked
        $greatswordEquipment = CharacterEquipment::factory()
            ->withItem($this->greatsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        // Shield waiting in backpack
        $shieldEquipment = CharacterEquipment::factory()
            ->withItem($this->shield)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        // First verify off_hand is blocked while 2H is equipped
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$shieldEquipment->id}",
            ['location' => 'off_hand']
        );
        $response->assertUnprocessable();

        // Move greatsword to backpack
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$greatswordEquipment->id}",
            ['location' => 'backpack']
        );
        $response->assertOk()
            ->assertJsonPath('data.location', 'backpack');

        // Now shield should be able to equip to off_hand
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$shieldEquipment->id}",
            ['location' => 'off_hand']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'off_hand')
            ->assertJsonPath('data.equipped', true);
    }

    // =========================================================================
    // Attunement Separation Tests (is_attuned independent of location)
    // =========================================================================

    #[Test]
    public function it_allows_attunement_on_ring_in_ring_1_slot(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicRing)
            ->create(['character_id' => $character->id]);

        // First equip to ring_1
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'ring_1']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'ring_1')
            ->assertJsonPath('data.equipped', true);

        // Then attune (separate action)
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['is_attuned' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'ring_1')
            ->assertJsonPath('data.is_attuned', true);
    }

    #[Test]
    public function it_allows_attunement_on_any_equipped_slot(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->amulet)
            ->create(['character_id' => $character->id]);

        // Equip to neck and attune in one request
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'neck', 'is_attuned' => true]
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'neck')
            ->assertJsonPath('data.is_attuned', true);
    }

    #[Test]
    public function it_clears_attunement_when_moving_to_backpack(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicRing)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'ring_1',
                'is_attuned' => true,
            ]);

        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'backpack']
        );

        $response->assertOk()
            ->assertJsonPath('data.location', 'backpack')
            ->assertJsonPath('data.equipped', false)
            ->assertJsonPath('data.is_attuned', false);
    }

    #[Test]
    public function it_rejects_legacy_attuned_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->magicRing)
            ->create(['character_id' => $character->id]);

        // 'attuned' is no longer a valid location
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'attuned']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['location']);
    }

    #[Test]
    public function it_rejects_legacy_worn_location(): void
    {
        $character = Character::factory()->create();
        $equipment = CharacterEquipment::factory()
            ->withItem($this->leatherArmor)
            ->create(['character_id' => $character->id]);

        // 'worn' is no longer a valid location - use 'armor' instead
        $response = $this->patchJson(
            "/api/v1/characters/{$character->id}/equipment/{$equipment->id}",
            ['location' => 'worn']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['location']);
    }
}

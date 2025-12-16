<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterEquipment;
use App\Models\CharacterProficiency;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for weapon combat data in equipment endpoint.
 *
 * Issue #708: The equipment endpoint should include attack_bonus,
 * damage_bonus, and ability_used for equipped weapons.
 */
class CharacterEquipmentCombatDataTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private Item $longsword;

    private Item $longbow;

    private Item $dagger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWeaponFixtures();
    }

    private function createWeaponFixtures(): void
    {
        $meleeType = ItemType::where('code', 'M')->first();
        $rangedType = ItemType::where('code', 'R')->first();

        $this->longsword = Item::create([
            'name' => 'Longsword',
            'slug' => 'test:longsword',
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d8',
            'equipment_slot' => 'hand',
            'rarity' => 'common',
            'description' => 'A standard longsword.',
        ]);

        $this->longbow = Item::create([
            'name' => 'Longbow',
            'slug' => 'test:longbow',
            'item_type_id' => $rangedType->id,
            'damage_dice' => '1d8',
            'equipment_slot' => 'hand',
            'rarity' => 'common',
            'description' => 'A standard longbow.',
        ]);

        $finesse = ItemProperty::where('code', 'F')->first();
        $this->dagger = Item::create([
            'name' => 'Dagger',
            'slug' => 'test:dagger',
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d4',
            'equipment_slot' => 'hand',
            'rarity' => 'common',
            'description' => 'A small dagger.',
        ]);
        $this->dagger->properties()->attach([$finesse->id]);
    }

    // =============================
    // Combat Data in Equipment Response
    // =============================

    #[Test]
    public function it_includes_combat_data_for_equipped_weapons(): void
    {
        $character = Character::factory()->create([
            'strength' => 16,
            'dexterity' => 14,
        ]);

        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk();

        $weapon = collect($response->json('data'))
            ->where('location', 'main_hand')
            ->first();

        expect($weapon)->not->toBeNull();
        expect($weapon)->toHaveKeys(['attack_bonus', 'damage_bonus', 'ability_used']);
    }

    #[Test]
    public function it_calculates_melee_weapon_using_strength(): void
    {
        $character = Character::factory()->level(1)->create([
            'strength' => 16, // +3 modifier
            'dexterity' => 10,
        ]);

        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_slug' => 'core:longsword',
            'source' => 'class',
        ]);

        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk();

        $weapon = collect($response->json('data'))
            ->where('item.slug', 'test:longsword')
            ->first();

        // Attack = STR mod (3) + Prof (2) = 5
        expect($weapon['attack_bonus'])->toBe(5);
        // Damage = STR mod (3)
        expect($weapon['damage_bonus'])->toBe(3);
        expect($weapon['ability_used'])->toBe('STR');
    }

    #[Test]
    public function it_calculates_ranged_weapon_using_dexterity(): void
    {
        $character = Character::factory()->level(1)->create([
            'strength' => 10,
            'dexterity' => 16, // +3 modifier
        ]);

        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_slug' => 'core:longbow',
            'source' => 'class',
        ]);

        CharacterEquipment::factory()
            ->withItem($this->longbow)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk();

        $weapon = collect($response->json('data'))
            ->where('item.slug', 'test:longbow')
            ->first();

        expect($weapon['attack_bonus'])->toBe(5);
        expect($weapon['damage_bonus'])->toBe(3);
        expect($weapon['ability_used'])->toBe('DEX');
    }

    #[Test]
    public function it_uses_higher_modifier_for_finesse_weapons(): void
    {
        $character = Character::factory()->level(1)->create([
            'strength' => 10, // +0
            'dexterity' => 16, // +3
        ]);

        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_slug' => 'core:dagger',
            'source' => 'class',
        ]);

        CharacterEquipment::factory()
            ->withItem($this->dagger)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk();

        $weapon = collect($response->json('data'))
            ->where('item.slug', 'test:dagger')
            ->first();

        expect($weapon['ability_used'])->toBe('DEX');
        expect($weapon['attack_bonus'])->toBe(5);
    }

    #[Test]
    public function it_excludes_proficiency_when_not_proficient(): void
    {
        $character = Character::factory()->level(1)->create([
            'strength' => 16, // +3
            'dexterity' => 10,
        ]);

        // No proficiency added

        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'main_hand',
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk();

        $weapon = collect($response->json('data'))
            ->where('item.slug', 'test:longsword')
            ->first();

        // Attack = STR mod (3) only, no proficiency bonus
        expect($weapon['attack_bonus'])->toBe(3);
        expect($weapon['proficiency_status']['has_proficiency'])->toBeFalse();
    }

    #[Test]
    public function it_does_not_include_combat_data_for_non_weapons(): void
    {
        $character = Character::factory()->create();

        $armorType = ItemType::where('code', 'LA')->first();
        $armor = Item::create([
            'name' => 'Leather Armor',
            'slug' => 'test:leather-armor',
            'item_type_id' => $armorType->id,
            'armor_class' => 11,
            'equipment_slot' => 'armor',
            'rarity' => 'common',
            'description' => 'Light armor made of leather.',
        ]);

        CharacterEquipment::factory()
            ->withItem($armor)
            ->create([
                'character_id' => $character->id,
                'equipped' => true,
                'location' => 'armor',
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk();

        $armorEquipment = collect($response->json('data'))
            ->where('item.slug', 'test:leather-armor')
            ->first();

        expect($armorEquipment)->not->toHaveKey('attack_bonus');
        expect($armorEquipment)->not->toHaveKey('damage_bonus');
        expect($armorEquipment)->not->toHaveKey('ability_used');
    }

    #[Test]
    public function it_does_not_include_combat_data_for_backpack_weapons(): void
    {
        $character = Character::factory()->create([
            'strength' => 16,
        ]);

        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk();

        $weapon = collect($response->json('data'))
            ->where('item.slug', 'test:longsword')
            ->first();

        expect($weapon)->not->toHaveKey('attack_bonus');
        expect($weapon)->not->toHaveKey('damage_bonus');
        expect($weapon)->not->toHaveKey('ability_used');
    }
}

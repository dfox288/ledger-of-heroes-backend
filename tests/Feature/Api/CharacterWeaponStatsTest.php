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
 * Tests for weapon attack and damage calculation.
 *
 * D&D 5e Rules:
 * - Attack bonus = ability modifier + proficiency bonus (if proficient) + magic bonus
 * - Damage bonus = ability modifier + magic bonus + feature bonuses
 * - Melee weapons use STR (or DEX if Finesse)
 * - Ranged weapons use DEX (or STR for thrown)
 *
 * Covers issue #498.3.1
 */
class CharacterWeaponStatsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected $seeder = LookupSeeder::class;

    private Item $longsword;

    private Item $longbow;

    private Item $dagger; // Finesse + Thrown

    private Item $magicSword; // +1 weapon

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWeaponFixtures();
    }

    private function createWeaponFixtures(): void
    {
        $meleeType = ItemType::where('code', 'M')->first();
        $rangedType = ItemType::where('code', 'R')->first();

        // Standard melee weapon
        $this->longsword = Item::create([
            'name' => 'Longsword',
            'slug' => 'test:longsword',
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d8',
            'rarity' => 'common',
            'description' => 'A standard longsword.',
        ]);

        // Standard ranged weapon
        $this->longbow = Item::create([
            'name' => 'Longbow',
            'slug' => 'test:longbow',
            'item_type_id' => $rangedType->id,
            'damage_dice' => '1d8',
            'range_normal' => 150,
            'range_long' => 600,
            'rarity' => 'common',
            'description' => 'A standard longbow.',
        ]);

        // Finesse + Thrown weapon (dagger)
        $finesse = ItemProperty::where('code', 'F')->first();
        $thrown = ItemProperty::where('code', 'T')->first();

        $this->dagger = Item::create([
            'name' => 'Dagger',
            'slug' => 'test:dagger',
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d4',
            'range_normal' => 20,
            'range_long' => 60,
            'rarity' => 'common',
            'description' => 'A small dagger.',
        ]);
        $this->dagger->properties()->attach([$finesse->id, $thrown->id]);

        // Magic weapon (+1)
        $this->magicSword = Item::create([
            'name' => 'Sword +1',
            'slug' => 'test:sword-plus-one',
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d8',
            'is_magic' => true,
            'rarity' => 'uncommon',
            'description' => 'A magic longsword with +1 bonus.',
        ]);
    }

    // =============================
    // Basic Weapon Stats Exposure
    // =============================

    #[Test]
    public function it_exposes_equipped_weapons_in_stats(): void
    {
        $character = Character::factory()->create([
            'strength' => 16,
            'dexterity' => 14,
        ]);

        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'weapons' => [
                        '*' => [
                            'name',
                            'damage_dice',
                            'attack_bonus',
                            'damage_bonus',
                            'ability_used',
                            'is_proficient',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_calculates_melee_weapon_attack_with_strength(): void
    {
        // STR 16 (+3 mod), Level 1 (+2 prof)
        $character = Character::factory()->level(1)->create([
            'strength' => 16,
            'dexterity' => 10,
        ]);

        // Add weapon proficiency using proficiency_type_slug
        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_slug' => 'core:longsword',
            'source' => 'class',
        ]);

        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();

        $weapons = $response->json('data.weapons');
        $longswordStats = collect($weapons)->firstWhere('name', 'Longsword');

        // Attack = STR mod (3) + Prof (2) = +5
        expect($longswordStats['attack_bonus'])->toBe(5);
        // Damage = STR mod (3)
        expect($longswordStats['damage_bonus'])->toBe(3);
        expect($longswordStats['ability_used'])->toBe('STR');
        expect($longswordStats['is_proficient'])->toBeTrue();
    }

    #[Test]
    public function it_calculates_ranged_weapon_attack_with_dexterity(): void
    {
        // DEX 16 (+3 mod), Level 1 (+2 prof)
        $character = Character::factory()->level(1)->create([
            'strength' => 10,
            'dexterity' => 16,
        ]);

        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_slug' => 'core:longbow',
            'source' => 'class',
        ]);

        CharacterEquipment::factory()
            ->withItem($this->longbow)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();

        $weapons = $response->json('data.weapons');
        $longbowStats = collect($weapons)->firstWhere('name', 'Longbow');

        // Attack = DEX mod (3) + Prof (2) = +5
        expect($longbowStats['attack_bonus'])->toBe(5);
        expect($longbowStats['damage_bonus'])->toBe(3);
        expect($longbowStats['ability_used'])->toBe('DEX');
    }

    #[Test]
    public function it_uses_dex_for_finesse_weapons_when_higher(): void
    {
        // DEX 16 (+3), STR 10 (0) - should use DEX for finesse
        $character = Character::factory()->level(1)->create([
            'strength' => 10,
            'dexterity' => 16,
        ]);

        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_slug' => 'core:dagger',
            'source' => 'class',
        ]);

        CharacterEquipment::factory()
            ->withItem($this->dagger)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();

        $weapons = $response->json('data.weapons');
        $daggerStats = collect($weapons)->firstWhere('name', 'Dagger');

        expect($daggerStats['ability_used'])->toBe('DEX');
        expect($daggerStats['attack_bonus'])->toBe(5); // DEX (3) + Prof (2)
        expect($daggerStats['damage_bonus'])->toBe(3); // DEX (3)
    }

    #[Test]
    public function it_uses_str_for_finesse_weapons_when_higher(): void
    {
        // STR 16 (+3), DEX 10 (0) - should use STR for finesse
        $character = Character::factory()->level(1)->create([
            'strength' => 16,
            'dexterity' => 10,
        ]);

        CharacterProficiency::create([
            'character_id' => $character->id,
            'proficiency_type_slug' => 'core:dagger',
            'source' => 'class',
        ]);

        CharacterEquipment::factory()
            ->withItem($this->dagger)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();

        $weapons = $response->json('data.weapons');
        $daggerStats = collect($weapons)->firstWhere('name', 'Dagger');

        expect($daggerStats['ability_used'])->toBe('STR');
        expect($daggerStats['attack_bonus'])->toBe(5); // STR (3) + Prof (2)
    }

    #[Test]
    public function it_excludes_proficiency_bonus_when_not_proficient(): void
    {
        // Not proficient with the weapon
        $character = Character::factory()->create([
            'strength' => 16,
            'dexterity' => 10,
        ]);

        // No proficiency added

        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();

        $weapons = $response->json('data.weapons');
        $longswordStats = collect($weapons)->firstWhere('name', 'Longsword');

        // Attack = STR mod (3) only, no proficiency
        expect($longswordStats['attack_bonus'])->toBe(3);
        expect($longswordStats['is_proficient'])->toBeFalse();
    }

    #[Test]
    public function it_shows_empty_weapons_when_none_equipped(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.weapons', []);
    }

    #[Test]
    public function it_only_shows_equipped_weapons_not_backpack(): void
    {
        $character = Character::factory()->create([
            'strength' => 16,
        ]);

        // One equipped, one in backpack
        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->equipped()
            ->create(['character_id' => $character->id]);

        CharacterEquipment::factory()
            ->withItem($this->dagger)
            ->create([
                'character_id' => $character->id,
                'equipped' => false,
                'location' => 'backpack',
            ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();

        $weapons = $response->json('data.weapons');
        expect($weapons)->toHaveCount(1);
        expect($weapons[0]['name'])->toBe('Longsword');
    }

    #[Test]
    public function it_includes_damage_dice_and_type(): void
    {
        $character = Character::factory()->create([
            'strength' => 16,
        ]);

        CharacterEquipment::factory()
            ->withItem($this->longsword)
            ->equipped()
            ->create(['character_id' => $character->id]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk();

        $weapons = $response->json('data.weapons');
        $longswordStats = collect($weapons)->firstWhere('name', 'Longsword');

        expect($longswordStats['damage_dice'])->toBe('1d8');
    }
}

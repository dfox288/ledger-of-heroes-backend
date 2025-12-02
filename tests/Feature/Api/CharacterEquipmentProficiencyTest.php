<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\ItemProperty;
use App\Models\ItemType;
use App\Models\Proficiency;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterEquipmentProficiencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_includes_proficiency_status_for_equipped_armor(): void
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
            'armor_class' => 18,
        ]);

        CharacterEquipment::factory()
            ->for($character)
            ->for($plate, 'item')
            ->equipped()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.equipped', true)
            ->assertJsonPath('data.0.proficiency_status.has_proficiency', true)
            ->assertJsonPath('data.0.proficiency_status.source', 'Fighter')
            ->assertJsonPath('data.0.proficiency_status.penalties', []);
    }

    #[Test]
    public function it_includes_proficiency_status_for_equipped_weapon(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Martial Weapons',
        ]);

        $character = Character::factory()->withClass($class)->create();

        $meleeType = ItemType::where('code', 'M')->first();
        $martialProperty = ItemProperty::where('code', 'M')->first();

        $longsword = Item::factory()->create([
            'name' => 'Longsword',
            'item_type_id' => $meleeType->id,
            'damage_dice' => '1d8',
        ]);
        $longsword->properties()->attach($martialProperty->id);

        CharacterEquipment::factory()
            ->for($character)
            ->for($longsword, 'item')
            ->equipped()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.equipped', true)
            ->assertJsonPath('data.0.proficiency_status.has_proficiency', true)
            ->assertJsonPath('data.0.proficiency_status.source', 'Fighter');
    }

    #[Test]
    public function it_does_not_include_proficiency_status_for_unequipped_items(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($class)->create();

        $gearType = ItemType::where('code', 'G')->first();
        $rope = Item::factory()->create([
            'name' => 'Rope',
            'item_type_id' => $gearType->id,
        ]);

        CharacterEquipment::factory()
            ->for($character)
            ->for($rope, 'item')
            ->create(); // Default is unequipped

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.equipped', false)
            ->assertJsonMissing(['proficiency_status']);
    }

    #[Test]
    public function it_shows_penalties_for_non_proficient_armor(): void
    {
        // Wizard with no armor proficiency
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($class)->create();

        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $plate = Item::factory()->create([
            'name' => 'Plate',
            'item_type_id' => $heavyArmorType->id,
        ]);

        CharacterEquipment::factory()
            ->for($character)
            ->for($plate, 'item')
            ->equipped()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.proficiency_status.has_proficiency', false)
            ->assertJsonPath('data.0.proficiency_status.source', null);

        $penalties = $response->json('data.0.proficiency_status.penalties');
        $this->assertContains('disadvantage_str_dex_checks', $penalties);
        $this->assertContains('cannot_cast_spells', $penalties);
    }

    #[Test]
    public function it_shows_penalties_for_non_proficient_weapon(): void
    {
        // Wizard with only simple weapons
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'Simple Weapons',
        ]);

        $character = Character::factory()->withClass($class)->create();

        // Martial weapon
        $meleeType = ItemType::where('code', 'M')->first();
        $martialProperty = ItemProperty::where('code', 'M')->first();

        $greatsword = Item::factory()->create([
            'name' => 'Greatsword',
            'item_type_id' => $meleeType->id,
        ]);
        $greatsword->properties()->attach($martialProperty->id);

        CharacterEquipment::factory()
            ->for($character)
            ->for($greatsword, 'item')
            ->equipped()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.proficiency_status.has_proficiency', false);

        $penalties = $response->json('data.0.proficiency_status.penalties');
        $this->assertContains('no_proficiency_bonus_to_attack', $penalties);
    }

    #[Test]
    public function it_shows_source_when_proficient_from_race(): void
    {
        // Dwarf race with battleaxe proficiency
        $race = Race::factory()->create(['name' => 'Dwarf']);
        Proficiency::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'proficiency_type' => 'weapon',
            'proficiency_name' => 'battleaxe',
        ]);

        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        $character = Character::factory()
            ->withClass($class)
            ->withRace($race)
            ->create();

        $meleeType = ItemType::where('code', 'M')->first();
        $martialProperty = ItemProperty::where('code', 'M')->first();

        $battleaxe = Item::factory()->create([
            'name' => 'Battleaxe',
            'item_type_id' => $meleeType->id,
        ]);
        $battleaxe->properties()->attach($martialProperty->id);

        CharacterEquipment::factory()
            ->for($character)
            ->for($battleaxe, 'item')
            ->equipped()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/equipment");

        $response->assertOk()
            ->assertJsonPath('data.0.proficiency_status.has_proficiency', true)
            ->assertJsonPath('data.0.proficiency_status.source', 'Dwarf');
    }

    #[Test]
    public function character_response_includes_proficiency_penalties_summary(): void
    {
        // Wizard with no armor proficiency
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);
        $character = Character::factory()->withClass($class)->create();

        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $plate = Item::factory()->create([
            'name' => 'Plate',
            'item_type_id' => $heavyArmorType->id,
        ]);

        CharacterEquipment::factory()
            ->for($character)
            ->for($plate, 'item')
            ->equipped()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.proficiency_penalties.has_armor_penalty', true)
            ->assertJsonPath('data.proficiency_penalties.has_weapon_penalty', false);

        $penalties = $response->json('data.proficiency_penalties.penalties');
        $this->assertContains('disadvantage_str_dex_checks', $penalties);
        $this->assertContains('cannot_cast_spells', $penalties);
    }

    #[Test]
    public function character_response_shows_no_penalties_when_proficient(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);
        Proficiency::create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'proficiency_type' => 'armor',
            'proficiency_name' => 'all armor',
        ]);

        $character = Character::factory()->withClass($class)->create();

        $heavyArmorType = ItemType::where('code', 'HA')->first();
        $plate = Item::factory()->create([
            'name' => 'Plate',
            'item_type_id' => $heavyArmorType->id,
        ]);

        CharacterEquipment::factory()
            ->for($character)
            ->for($plate, 'item')
            ->equipped()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.proficiency_penalties.has_armor_penalty', false)
            ->assertJsonPath('data.proficiency_penalties.has_weapon_penalty', false)
            ->assertJsonPath('data.proficiency_penalties.penalties', []);
    }

    #[Test]
    public function character_response_shows_weapon_penalty_when_not_proficient(): void
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

        CharacterEquipment::factory()
            ->for($character)
            ->for($greatsword, 'item')
            ->equipped()
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.proficiency_penalties.has_armor_penalty', false)
            ->assertJsonPath('data.proficiency_penalties.has_weapon_penalty', true);

        $penalties = $response->json('data.proficiency_penalties.penalties');
        $this->assertContains('no_proficiency_bonus_to_attack', $penalties);
    }
}

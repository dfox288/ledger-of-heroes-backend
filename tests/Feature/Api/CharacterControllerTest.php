<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterEquipment;
use App\Models\Item;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterControllerTest extends TestCase
{
    use RefreshDatabase;

    // =====================
    // Index Tests
    // =====================

    #[Test]
    public function it_can_list_characters(): void
    {
        Character::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'level', 'is_complete'],
                ],
            ]);
    }

    #[Test]
    public function it_returns_empty_array_when_no_characters(): void
    {
        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_filters_characters_by_name_with_q_parameter(): void
    {
        Character::factory()->create(['name' => 'Gandalf the Grey']);
        Character::factory()->create(['name' => 'Legolas']);
        Character::factory()->create(['name' => 'Aragorn']);

        $response = $this->getJson('/api/v1/characters?q=gandalf');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Gandalf the Grey');
    }

    #[Test]
    public function it_returns_all_characters_when_q_is_not_provided(): void
    {
        Character::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_searches_characters_case_insensitively(): void
    {
        Character::factory()->create(['name' => 'Gandalf']);
        Character::factory()->create(['name' => 'GANDALF']);
        Character::factory()->create(['name' => 'Other']);

        $response = $this->getJson('/api/v1/characters?q=gandalf');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_supports_partial_name_matching(): void
    {
        Character::factory()->create(['name' => 'Gandalf the Grey']);
        Character::factory()->create(['name' => 'Gandalf the White']);
        Character::factory()->create(['name' => 'Legolas']);

        $response = $this->getJson('/api/v1/characters?q=the');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_paginates_search_results(): void
    {
        // Create 5 characters with "Test" in name
        for ($i = 1; $i <= 5; $i++) {
            Character::factory()->create(['name' => "Test Character $i"]);
        }
        Character::factory()->create(['name' => 'Other']);

        $response = $this->getJson('/api/v1/characters?q=Test&per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2);
    }

    // =====================
    // Store Tests (Create)
    // =====================

    #[Test]
    public function it_creates_a_draft_character_with_just_name(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'shadow-warden-q3x9',
            'name' => 'Gandalf',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Gandalf')
            ->assertJsonPath('data.public_id', 'shadow-warden-q3x9')
            ->assertJsonPath('data.level', 0) // No class = no level
            ->assertJsonPath('data.is_complete', false)
            ->assertJsonPath('data.validation_status.missing', ['race', 'class', 'ability_scores']);

        $this->assertDatabaseHas('characters', ['name' => 'Gandalf', 'public_id' => 'shadow-warden-q3x9']);
    }

    #[Test]
    public function it_requires_name_to_create_character(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-ab12',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_can_create_character_with_race_and_class(): void
    {
        $race = Race::factory()->create();
        $class = CharacterClass::factory()->create();

        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'brave-archer-zx78',
            'name' => 'Legolas',
            'race_slug' => $race->full_slug,
            'class_slug' => $class->full_slug,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Legolas')
            ->assertJsonPath('data.race.id', $race->id)
            ->assertJsonPath('data.classes.0.class.id', $class->id);
    }

    #[Test]
    public function it_validates_ability_score_range_minimum(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-hero-ab12',
            'name' => 'Test',
            'strength' => 2, // Invalid - min is 3
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['strength']);
    }

    #[Test]
    public function it_validates_ability_score_range_maximum(): void
    {
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-hero-cd34',
            'name' => 'Test',
            'strength' => 25, // Invalid - max is 20
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['strength']);
    }

    #[Test]
    public function it_allows_dangling_race_reference(): void
    {
        // Per #288, dangling references are allowed for portable character data
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-hero-ef56',
            'name' => 'Test',
            'race_slug' => 'nonexistent:race',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Test')
            ->assertJsonPath('data.race', null); // Race is null because it doesn't exist
    }

    #[Test]
    public function it_allows_dangling_class_reference(): void
    {
        // Per #288, dangling references are allowed for portable character data
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-hero-gh78',
            'name' => 'Test',
            'class_slug' => 'nonexistent:class',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Test')
            ->assertJsonPath('data.class', null); // Class is null because it doesn't exist
    }

    // =====================
    // Show Tests
    // =====================

    #[Test]
    public function it_shows_a_character_with_full_details(): void
    {
        $character = Character::factory()->create(['name' => 'TestHero']);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $character->id)
            ->assertJsonPath('data.public_id', $character->public_id)
            ->assertJsonPath('data.name', 'TestHero')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'public_id',
                    'name',
                    'level',
                    'experience_points',
                    'is_complete',
                    'validation_status',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_character(): void
    {
        $response = $this->getJson('/api/v1/characters/nonexistent-slug-xxxx');

        $response->assertNotFound();
    }

    #[Test]
    public function it_includes_ability_scores_and_modifiers_in_show(): void
    {
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 18,
                'dexterity' => 14,
                'constitution' => 16,
                'intelligence' => 10,
                'wisdom' => 12,
                'charisma' => 8,
            ])
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.ability_scores.STR', 18)
            ->assertJsonPath('data.ability_scores.DEX', 14)
            ->assertJsonPath('data.modifiers.STR', 4)  // (18-10)/2 = 4
            ->assertJsonPath('data.modifiers.DEX', 2)  // (14-10)/2 = 2
            ->assertJsonPath('data.modifiers.CHA', -1); // (8-10)/2 = -1
    }

    #[Test]
    public function it_includes_proficiency_bonus_in_show(): void
    {
        $character = Character::factory()->level(5)->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.proficiency_bonus', 3); // Level 5 = +3
    }

    // =====================
    // Update Tests
    // =====================

    #[Test]
    public function it_can_update_character_name(): void
    {
        $character = Character::factory()->create(['name' => 'OldName']);

        $response = $this->patchJson("/api/v1/characters/{$character->public_id}", [
            'name' => 'NewName',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'NewName');

        $this->assertDatabaseHas('characters', ['id' => $character->id, 'name' => 'NewName']);
    }

    #[Test]
    public function it_can_update_character_ability_scores(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->public_id}", [
            'strength' => 18,
            'dexterity' => 14,
            'constitution' => 16,
            'intelligence' => 10,
            'wisdom' => 12,
            'charisma' => 8,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ability_scores.STR', 18)
            ->assertJsonPath('data.modifiers.STR', 4);
    }

    #[Test]
    public function it_validates_ability_scores_on_update(): void
    {
        $character = Character::factory()->create();

        $response = $this->patchJson("/api/v1/characters/{$character->public_id}", [
            'strength' => 25, // Invalid
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['strength']);
    }

    #[Test]
    public function it_shows_complete_status_when_all_required_fields_set(): void
    {
        $race = Race::factory()->create();
        $class = CharacterClass::factory()->create();

        $character = Character::factory()
            ->withRace($race)
            ->withClass($class)
            ->withAbilityScores([
                'strength' => 10, 'dexterity' => 10, 'constitution' => 10,
                'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10,
            ])
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.validation_status.is_complete', true)
            ->assertJsonPath('data.validation_status.missing', []);
    }

    // =====================
    // Delete Tests
    // =====================

    #[Test]
    public function it_can_delete_a_character(): void
    {
        $character = Character::factory()->create();

        $response = $this->deleteJson("/api/v1/characters/{$character->public_id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
    }

    #[Test]
    public function it_returns_404_when_deleting_non_existent_character(): void
    {
        $response = $this->deleteJson('/api/v1/characters/nonexistent-slug-xxxx');

        $response->assertNotFound();
    }

    // =====================
    // Relationship Tests
    // =====================

    #[Test]
    public function it_includes_race_details_when_present(): void
    {
        $race = Race::factory()->create();
        $character = Character::factory()->withRace($race)->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.race.id', $race->id)
            ->assertJsonPath('data.race.name', $race->name);
    }

    #[Test]
    public function it_includes_class_details_when_present(): void
    {
        $class = CharacterClass::factory()->create();
        $character = Character::factory()->withClass($class)->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.classes.0.class.id', $class->id)
            ->assertJsonPath('data.classes.0.class.name', $class->name);
    }

    #[Test]
    public function it_includes_background_details_when_present(): void
    {
        $background = Background::factory()->create();
        $character = Character::factory()->withBackground($background)->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.background.id', $background->id)
            ->assertJsonPath('data.background.name', $background->name);
    }

    // =====================
    // Currency Tests
    // =====================

    #[Test]
    public function it_returns_currency_from_inventory_items(): void
    {
        $character = Character::factory()->create();

        // Create currency items
        $gold = Item::factory()->create(['full_slug' => 'phb:gold-gp', 'name' => 'Gold (gp)']);
        $silver = Item::factory()->create(['full_slug' => 'phb:silver-sp', 'name' => 'Silver (sp)']);
        $copper = Item::factory()->create(['full_slug' => 'phb:copper-cp', 'name' => 'Copper (cp)']);

        // Add currency to character's inventory
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 25,
        ]);
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:silver-sp',
            'quantity' => 50,
        ]);
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:copper-cp',
            'quantity' => 100,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.currency.pp', 0)
            ->assertJsonPath('data.currency.gp', 25)
            ->assertJsonPath('data.currency.ep', 0)
            ->assertJsonPath('data.currency.sp', 50)
            ->assertJsonPath('data.currency.cp', 100);
    }

    #[Test]
    public function it_returns_zero_currency_when_no_coins_in_inventory(): void
    {
        $character = Character::factory()->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.currency.pp', 0)
            ->assertJsonPath('data.currency.gp', 0)
            ->assertJsonPath('data.currency.ep', 0)
            ->assertJsonPath('data.currency.sp', 0)
            ->assertJsonPath('data.currency.cp', 0);
    }

    #[Test]
    public function it_sums_multiple_stacks_of_same_coin_type(): void
    {
        $character = Character::factory()->create();

        Item::factory()->create(['full_slug' => 'phb:gold-gp', 'name' => 'Gold (gp)']);

        // Two separate stacks of gold (e.g., from different loot sources)
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 25,
        ]);
        CharacterEquipment::create([
            'character_id' => $character->id,
            'item_slug' => 'phb:gold-gp',
            'quantity' => 30,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.currency.gp', 55); // 25 + 30
    }
}

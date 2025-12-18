<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterAbilityScore;
use App\Models\CharacterClass;
use App\Models\CharacterEquipment;
use App\Models\EntityLanguage;
use App\Models\Item;
use App\Models\Language;
use App\Models\Modifier;
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
    // Status Filter Tests (#754)
    // =====================

    #[Test]
    public function it_filters_characters_by_complete_status(): void
    {
        $race = Race::factory()->create();
        $class = CharacterClass::factory()->create();

        // Complete characters (have race, class, and ability scores)
        Character::factory()
            ->withRace($race)
            ->withClass($class)
            ->withAbilityScores(['strength' => 10, 'dexterity' => 10, 'constitution' => 10, 'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10])
            ->create(['name' => 'Complete1']);
        Character::factory()
            ->withRace($race)
            ->withClass($class)
            ->withAbilityScores(['strength' => 10, 'dexterity' => 10, 'constitution' => 10, 'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10])
            ->create(['name' => 'Complete2']);

        // Incomplete character (missing race)
        Character::factory()->create(['name' => 'Draft']);

        $response = $this->getJson('/api/v1/characters?status=complete');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // All returned characters should be complete
        foreach ($response->json('data') as $char) {
            $this->assertTrue($char['is_complete']);
        }
    }

    #[Test]
    public function it_filters_characters_by_draft_status(): void
    {
        $race = Race::factory()->create();
        $class = CharacterClass::factory()->create();

        // Complete character
        Character::factory()
            ->withRace($race)
            ->withClass($class)
            ->withAbilityScores(['strength' => 10, 'dexterity' => 10, 'constitution' => 10, 'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10])
            ->create(['name' => 'Complete']);

        // Incomplete characters
        Character::factory()->create(['name' => 'Draft1']); // No race, class, or ability scores
        Character::factory()->withRace($race)->create(['name' => 'Draft2']); // Has race but no class

        $response = $this->getJson('/api/v1/characters?status=draft');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        // All returned characters should be incomplete (draft)
        foreach ($response->json('data') as $char) {
            $this->assertFalse($char['is_complete']);
        }
    }

    #[Test]
    public function it_rejects_invalid_status_filter_values(): void
    {
        $response = $this->getJson('/api/v1/characters?status=invalid');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    #[Test]
    public function it_returns_all_characters_when_status_not_provided(): void
    {
        $race = Race::factory()->create();
        $class = CharacterClass::factory()->create();

        // One complete
        Character::factory()
            ->withRace($race)
            ->withClass($class)
            ->withAbilityScores(['strength' => 10, 'dexterity' => 10, 'constitution' => 10, 'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10])
            ->create();

        // One draft
        Character::factory()->create();

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // =====================
    // Class Filter Tests (#754)
    // =====================

    #[Test]
    public function it_filters_characters_by_class_slug(): void
    {
        $fighter = CharacterClass::factory()->create(['slug' => 'phb:fighter', 'name' => 'Fighter']);
        $wizard = CharacterClass::factory()->create(['slug' => 'phb:wizard', 'name' => 'Wizard']);

        Character::factory()->withClass($fighter)->create(['name' => 'Conan']);
        Character::factory()->withClass($wizard)->create(['name' => 'Gandalf']);
        Character::factory()->create(['name' => 'NPC']); // No class

        $response = $this->getJson('/api/v1/characters?class=phb:fighter');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Conan');
    }

    #[Test]
    public function it_returns_all_characters_when_class_not_provided(): void
    {
        $fighter = CharacterClass::factory()->create();
        Character::factory()->withClass($fighter)->create();
        Character::factory()->create(); // No class

        $response = $this->getJson('/api/v1/characters');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_returns_empty_results_when_no_characters_have_specified_class(): void
    {
        $fighter = CharacterClass::factory()->create(['slug' => 'phb:fighter']);
        Character::factory()->withClass($fighter)->create();

        $response = $this->getJson('/api/v1/characters?class=phb:wizard');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_filters_multiclass_characters_by_any_class(): void
    {
        $fighter = CharacterClass::factory()->create(['slug' => 'phb:fighter']);
        $wizard = CharacterClass::factory()->create(['slug' => 'phb:wizard']);
        $rogue = CharacterClass::factory()->create(['slug' => 'phb:rogue']);

        // Create multiclass character: Fighter/Wizard
        $multiclass = Character::factory()->withClass($fighter)->create(['name' => 'Eldritch Knight']);
        $multiclass->characterClasses()->create([
            'class_slug' => $wizard->slug,
            'level' => 3,
            'is_primary' => false,
            'order' => 1,
        ]);

        // Create single-class character: Rogue
        Character::factory()->withClass($rogue)->create(['name' => 'Shadow']);

        // Filter by wizard should include the multiclass character
        $response = $this->getJson('/api/v1/characters?class=phb:wizard');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Eldritch Knight');
    }

    // =====================
    // Combined Filter Tests (#754)
    // =====================

    #[Test]
    public function it_combines_status_and_class_filters(): void
    {
        $race = Race::factory()->create();
        $fighter = CharacterClass::factory()->create(['slug' => 'phb:fighter']);
        $wizard = CharacterClass::factory()->create(['slug' => 'phb:wizard']);

        // Complete fighter (has race, class, ability scores)
        Character::factory()
            ->withRace($race)
            ->withClass($fighter)
            ->withAbilityScores(['strength' => 10, 'dexterity' => 10, 'constitution' => 10, 'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10])
            ->create(['name' => 'Conan']);

        // Draft fighter (no race or ability scores)
        Character::factory()
            ->withClass($fighter)
            ->create(['name' => 'Noob Fighter']);

        // Complete wizard
        Character::factory()
            ->withRace($race)
            ->withClass($wizard)
            ->withAbilityScores(['strength' => 10, 'dexterity' => 10, 'constitution' => 10, 'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10])
            ->create(['name' => 'Gandalf']);

        $response = $this->getJson('/api/v1/characters?status=complete&class=phb:fighter');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Conan');
    }

    #[Test]
    public function it_combines_status_class_and_name_search(): void
    {
        $race = Race::factory()->create();
        $fighter = CharacterClass::factory()->create(['slug' => 'phb:fighter']);

        // Complete fighter matching name
        Character::factory()
            ->withRace($race)
            ->withClass($fighter)
            ->withAbilityScores(['strength' => 10, 'dexterity' => 10, 'constitution' => 10, 'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10])
            ->create(['name' => 'Conan the Barbarian']);

        // Draft fighter matching name (no race/ability scores)
        Character::factory()
            ->withClass($fighter)
            ->create(['name' => 'Conan Junior']);

        // Complete fighter not matching name
        Character::factory()
            ->withRace($race)
            ->withClass($fighter)
            ->withAbilityScores(['strength' => 10, 'dexterity' => 10, 'constitution' => 10, 'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10])
            ->create(['name' => 'Aragorn']);

        $response = $this->getJson('/api/v1/characters?status=complete&class=phb:fighter&q=Conan');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Conan the Barbarian');
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
            'race_slug' => $race->slug,
            'class_slug' => $class->slug,
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
    public function it_includes_chosen_racial_ability_bonuses_in_show(): void
    {
        // Seed ability scores lookup
        $str = AbilityScore::firstOrCreate(['code' => 'STR'], ['name' => 'Strength']);
        $dex = AbilityScore::firstOrCreate(['code' => 'DEX'], ['name' => 'Dexterity']);
        $con = AbilityScore::firstOrCreate(['code' => 'CON'], ['name' => 'Constitution']);

        $race = Race::factory()->create(['name' => 'Half-Elf']);

        $character = Character::factory()
            ->withRace($race)
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 10,
                'constitution' => 10,
                'intelligence' => 10,
                'wisdom' => 10,
                'charisma' => 10,
            ])
            ->create();

        // Create a racial modifier for the choice (e.g., Half-Elf's +1 to two chosen abilities)
        $modifier = Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => null, // Choice-based, not fixed
            'value' => '1',
            'choice_count' => 2,
        ]);

        // Simulate the player's choice: +1 STR, +1 DEX
        CharacterAbilityScore::create([
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);
        CharacterAbilityScore::create([
            'character_id' => $character->id,
            'ability_score_code' => 'DEX',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            ->assertJsonPath('data.ability_scores.STR', 11) // 10 base + 1 chosen
            ->assertJsonPath('data.ability_scores.DEX', 11) // 10 base + 1 chosen
            ->assertJsonPath('data.ability_scores.CON', 10) // unchanged
            ->assertJsonPath('data.modifiers.STR', 0)  // (11-10)/2 = 0
            ->assertJsonPath('data.modifiers.DEX', 0); // (11-10)/2 = 0
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
        $gold = Item::factory()->create(['slug' => 'phb:gold-gp', 'name' => 'Gold (gp)']);
        $silver = Item::factory()->create(['slug' => 'phb:silver-sp', 'name' => 'Silver (sp)']);
        $copper = Item::factory()->create(['slug' => 'phb:copper-cp', 'name' => 'Copper (cp)']);

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

        Item::factory()->create(['slug' => 'phb:gold-gp', 'name' => 'Gold (gp)']);

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

    // =====================
    // Auto-Population Tests
    // =====================

    #[Test]
    public function it_auto_populates_languages_when_creating_character_with_race(): void
    {
        // Create a race with a fixed language grant
        $race = Race::factory()->create();
        $language = Language::factory()->create();

        // Add a fixed language grant to the race
        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $language->id,
            'is_choice' => false,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-ab12',
            'name' => 'Test Character',
            'race' => $race->slug,
        ]);

        $response->assertCreated();

        // Check that the character has the language
        $character = Character::where('public_id', 'test-char-ab12')->first();
        $this->assertDatabaseHas('character_languages', [
            'character_id' => $character->id,
            'language_slug' => $language->slug,
            'source' => 'race',
        ]);
    }

    #[Test]
    public function it_auto_populates_languages_when_updating_character_with_race(): void
    {
        // Create a character without a race
        $character = Character::factory()->create();

        // Create a race with a fixed language grant
        $race = Race::factory()->create();
        $language = Language::factory()->create();

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $language->id,
            'is_choice' => false,
            'quantity' => 1,
        ]);

        // Update character with race
        $response = $this->patchJson("/api/v1/characters/{$character->public_id}", [
            'race' => $race->slug,
        ]);

        $response->assertOk();

        // Check that the character has the language
        $this->assertDatabaseHas('character_languages', [
            'character_id' => $character->id,
            'language_slug' => $language->slug,
            'source' => 'race',
        ]);
    }

    #[Test]
    public function it_does_not_duplicate_languages_when_called_multiple_times(): void
    {
        // Create a race with a fixed language grant
        $race = Race::factory()->create();
        $language = Language::factory()->create();

        EntityLanguage::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'language_id' => $language->id,
            'is_choice' => false,
            'quantity' => 1,
        ]);

        // Create character with race
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-cd34',
            'name' => 'Test Character',
            'race' => $race->slug,
        ]);

        $response->assertCreated();

        $character = Character::where('public_id', 'test-char-cd34')->first();

        // Call sync endpoint (should not create duplicates)
        $this->postJson("/api/v1/characters/{$character->public_id}/languages/sync");

        // Verify only one language entry exists
        $languageCount = $character->languages()->where('language_slug', $language->slug)->count();
        $this->assertEquals(1, $languageCount);
    }

    #[Test]
    public function it_does_not_auto_populate_for_dangling_race_reference(): void
    {
        // Create character with a non-existent race slug (dangling reference)
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-char-ef56',
            'name' => 'Test Character',
            'race' => 'nonexistent:race',
        ]);

        $response->assertCreated();

        // Verify no languages were added
        $character = Character::where('public_id', 'test-char-ef56')->first();
        $this->assertDatabaseMissing('character_languages', [
            'character_id' => $character->id,
        ]);
    }

    // =====================
    // Base Ability Scores Tests (Issue #492)
    // =====================

    #[Test]
    public function it_returns_both_base_and_final_ability_scores(): void
    {
        // Create ability score record for racial modifier
        $chaId = AbilityScore::firstOrCreate(
            ['code' => 'CHA'],
            ['name' => 'Charisma']
        )->id;

        // Create a race with +2 CHA (like Half-Elf's fixed bonus)
        // Note: All entity_modifiers are now fixed (non-choice) by definition.
        // Choice-based modifiers are stored in entity_choices table.
        $race = Race::factory()->create(['name' => 'Half-Elf', 'slug' => 'half-elf']);
        $race->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $chaId,
            'value' => '2',
        ]);

        // Create character with base 10 CHA
        $character = Character::factory()
            ->withRace($race)
            ->withAbilityScores([
                'strength' => 14,
                'dexterity' => 12,
                'constitution' => 13,
                'intelligence' => 10,
                'wisdom' => 8,
                'charisma' => 10, // Base 10, should become 12 with racial +2
            ])
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            // Final ability scores include racial bonuses
            ->assertJsonPath('data.ability_scores.CHA', 12) // 10 base + 2 racial
            ->assertJsonPath('data.ability_scores.STR', 14) // No bonus
            // Base ability scores are the raw values before bonuses
            ->assertJsonPath('data.base_ability_scores.CHA', 10)
            ->assertJsonPath('data.base_ability_scores.STR', 14);
    }

    #[Test]
    public function it_returns_base_ability_scores_matching_final_when_no_race(): void
    {
        // Create character with no race
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 15,
                'dexterity' => 14,
                'constitution' => 13,
                'intelligence' => 12,
                'wisdom' => 10,
                'charisma' => 8,
            ])
            ->create(['race_slug' => null]);

        $response = $this->getJson("/api/v1/characters/{$character->public_id}");

        $response->assertOk()
            // With no race, base and final should match
            ->assertJsonPath('data.ability_scores.STR', 15)
            ->assertJsonPath('data.base_ability_scores.STR', 15)
            ->assertJsonPath('data.ability_scores.CHA', 8)
            ->assertJsonPath('data.base_ability_scores.CHA', 8);
    }
}

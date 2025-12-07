<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
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
}

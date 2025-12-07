<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\CharacterSpell;
use App\Models\Race;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration tests for the complete character creation flow.
 *
 * These tests verify that all character builder components work together
 * to create a fully-functional D&D 5e character.
 */
class CharacterCreationFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_complete_character_from_scratch(): void
    {
        // Setup: Create necessary entities
        $race = Race::factory()->create(['name' => 'High Elf']);
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);
        $background = Background::factory()->create(['name' => 'Sage']);

        // Create some wizard spells
        $spells = Spell::factory()->count(6)->create(['level' => 1]);
        $cantrips = Spell::factory()->count(3)->cantrip()->create();
        $wizardClass->spells()->attach($spells->pluck('id'));
        $wizardClass->spells()->attach($cantrips->pluck('id'));

        // Step 1: Create draft character (public_id and name required)
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'elara-wise-ab12',
            'name' => 'Elara the Wise',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Elara the Wise')
            ->assertJsonPath('data.is_complete', false);

        $characterId = $response->json('data.id');

        // Step 2: Choose race
        $response = $this->patchJson("/api/v1/characters/{$characterId}", [
            'race_slug' => $race->full_slug,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.race.name', 'High Elf')
            ->assertJsonPath('data.is_complete', false); // Still missing class and ability scores

        // Step 3: Choose class
        $response = $this->patchJson("/api/v1/characters/{$characterId}", [
            'class_slug' => $wizardClass->full_slug,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.classes.0.class.name', 'Wizard')
            ->assertJsonPath('data.is_complete', false); // Still missing ability scores

        // Step 4: Set ability scores (can be done in any order)
        $response = $this->patchJson("/api/v1/characters/{$characterId}", [
            'strength' => 8,
            'dexterity' => 14,
            'constitution' => 13,
            'intelligence' => 16, // Primary stat for wizard
            'wisdom' => 12,
            'charisma' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ability_scores.STR', 8)
            ->assertJsonPath('data.ability_scores.INT', 16)
            ->assertJsonPath('data.modifiers.INT', 3) // (16-10)/2 = 3
            ->assertJsonPath('data.is_complete', true); // Now complete!

        // Step 5: Optionally set background
        $response = $this->patchJson("/api/v1/characters/{$characterId}", [
            'background_slug' => $background->full_slug,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.background.name', 'Sage');

        // Step 6: Learn some spells
        foreach ($cantrips as $cantrip) {
            $this->postJson("/api/v1/characters/{$characterId}/spells", [
                'spell_slug' => $cantrip->full_slug,
            ])->assertCreated();
        }

        foreach ($spells->take(6) as $spell) {
            $this->postJson("/api/v1/characters/{$characterId}/spells", [
                'spell_slug' => $spell->full_slug,
            ])->assertCreated();
        }

        // Step 7: Prepare some spells (not cantrips)
        $this->patchJson("/api/v1/characters/{$characterId}/spells/{$spells[0]->id}/prepare")
            ->assertOk()
            ->assertJsonPath('data.preparation_status', 'prepared');

        $this->patchJson("/api/v1/characters/{$characterId}/spells/{$spells[1]->id}/prepare")
            ->assertOk();

        // Step 8: Verify final character state
        $response = $this->getJson("/api/v1/characters/{$characterId}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Elara the Wise')
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.race.name', 'High Elf')
            ->assertJsonPath('data.classes.0.class.name', 'Wizard')
            ->assertJsonPath('data.background.name', 'Sage')
            ->assertJsonPath('data.is_complete', true)
            ->assertJsonPath('data.proficiency_bonus', 2); // Level 1 = +2

        // Step 9: Verify spells
        $spellsResponse = $this->getJson("/api/v1/characters/{$characterId}/spells");
        $spellsResponse->assertOk()
            ->assertJsonCount(9, 'data'); // 3 cantrips + 6 spells

        // Step 10: Verify spell slots
        $slotsResponse = $this->getJson("/api/v1/characters/{$characterId}/spell-slots");
        $slotsResponse->assertOk()
            ->assertJsonStructure([
                'data' => ['slots', 'preparation_limit'],
            ]);
    }

    #[Test]
    public function it_allows_wizard_style_creation_in_any_order(): void
    {
        $race = Race::factory()->create();
        $class = CharacterClass::factory()->create();
        $background = Background::factory()->create();

        // Create character with public_id and name
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-hero-cd34',
            'name' => 'Test',
        ]);
        $characterId = $response->json('data.id');

        // Set background first (any order allowed)
        $this->patchJson("/api/v1/characters/{$characterId}", [
            'background_slug' => $background->full_slug,
        ])->assertOk();

        // Then ability scores
        $this->patchJson("/api/v1/characters/{$characterId}", [
            'strength' => 10, 'dexterity' => 10, 'constitution' => 10,
            'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10,
        ])->assertOk();

        // Then class
        $this->patchJson("/api/v1/characters/{$characterId}", [
            'class_slug' => $class->full_slug,
        ])->assertOk();

        // Then race last
        $response = $this->patchJson("/api/v1/characters/{$characterId}", [
            'race_slug' => $race->full_slug,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.is_complete', true);
    }

    #[Test]
    public function it_tracks_validation_status_throughout_creation(): void
    {
        $race = Race::factory()->create();
        $class = CharacterClass::factory()->create();

        // Start: missing everything except public_id and name
        $response = $this->postJson('/api/v1/characters', [
            'public_id' => 'test-hero-ef56',
            'name' => 'Test',
        ]);
        $response->assertCreated()
            ->assertJsonPath('data.validation_status.is_complete', false)
            ->assertJsonPath('data.validation_status.missing', ['race', 'class', 'ability_scores']);

        $characterId = $response->json('data.id');

        // Add race: still missing class and abilities
        $response = $this->patchJson("/api/v1/characters/{$characterId}", [
            'race_slug' => $race->full_slug,
        ]);
        $response->assertOk()
            ->assertJsonPath('data.validation_status.missing', ['class', 'ability_scores']);

        // Add class: still missing abilities
        $response = $this->patchJson("/api/v1/characters/{$characterId}", [
            'class_slug' => $class->full_slug,
        ]);
        $response->assertOk()
            ->assertJsonPath('data.validation_status.missing', ['ability_scores']);

        // Add abilities: complete!
        $response = $this->patchJson("/api/v1/characters/{$characterId}", [
            'strength' => 10, 'dexterity' => 10, 'constitution' => 10,
            'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10,
        ]);
        $response->assertOk()
            ->assertJsonPath('data.validation_status.is_complete', true)
            ->assertJsonPath('data.validation_status.missing', []);
    }

    #[Test]
    public function it_calculates_stats_correctly_for_complete_character(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);

        // Create a level 5 wizard with high INT
        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores([
                'strength' => 8,
                'dexterity' => 14,
                'constitution' => 14,
                'intelligence' => 18, // +4 modifier
                'wisdom' => 12,
                'charisma' => 10,
            ])
            ->level(5)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            // Ability modifiers
            ->assertJsonPath('data.modifiers.STR', -1)  // (8-10)/2 = -1
            ->assertJsonPath('data.modifiers.DEX', 2)   // (14-10)/2 = 2
            ->assertJsonPath('data.modifiers.CON', 2)   // (14-10)/2 = 2
            ->assertJsonPath('data.modifiers.INT', 4)   // (18-10)/2 = 4
            ->assertJsonPath('data.modifiers.WIS', 1)   // (12-10)/2 = 1
            ->assertJsonPath('data.modifiers.CHA', 0)   // (10-10)/2 = 0
            // Proficiency bonus (level 5 = +3)
            ->assertJsonPath('data.proficiency_bonus', 3);
    }

    #[Test]
    public function it_handles_multiclass_level_calculation(): void
    {
        // Future: When multiclass is implemented
        // For now, verify single-class level works correctly
        $character = Character::factory()->level(10)->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}");

        $response->assertOk()
            ->assertJsonPath('data.level', 10)
            ->assertJsonPath('data.proficiency_bonus', 4); // Level 9-12 = +4
    }

    #[Test]
    public function it_returns_computed_stats_via_stats_endpoint(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create([
            'name' => 'Wizard',
            'hit_die' => 6,
        ]);

        $character = Character::factory()
            ->withClass($wizardClass)
            ->withAbilityScores([
                'strength' => 8,
                'dexterity' => 14,
                'constitution' => 14,
                'intelligence' => 18,
                'wisdom' => 12,
                'charisma' => 10,
            ])
            ->level(5)
            ->create();

        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'character_id',
                    'level',
                    'proficiency_bonus',
                    'ability_scores',
                    'saving_throws',
                    'armor_class',
                    'hit_points',
                    // Derived combat stats
                    'initiative_bonus',
                    'passive_perception',
                    'passive_investigation',
                    'passive_insight',
                    'carrying_capacity',
                    'push_drag_lift',
                    // Spellcasting
                    'spellcasting',
                    'spell_slots',
                    'preparation_limit',
                    'prepared_spell_count',
                    // Issue #255: Enhanced stats
                    'skills',
                    'speed',
                    'passive',
                ],
            ])
            ->assertJsonPath('data.level', 5)
            ->assertJsonPath('data.proficiency_bonus', 3)
            ->assertJsonPath('data.ability_scores.INT.score', 18)
            ->assertJsonPath('data.ability_scores.INT.modifier', 4)
            // Derived combat stats
            ->assertJsonPath('data.initiative_bonus', 2) // DEX 14 (+2)
            ->assertJsonPath('data.passive_perception', 11) // 10 + WIS mod (+1)
            ->assertJsonPath('data.passive_investigation', 14) // 10 + INT mod (+4)
            ->assertJsonPath('data.passive_insight', 11) // 10 + WIS mod (+1)
            ->assertJsonPath('data.carrying_capacity', 120) // STR 8 × 15
            ->assertJsonPath('data.push_drag_lift', 240) // STR 8 × 30
            // Spellcasting
            ->assertJsonPath('data.spellcasting.ability', 'INT')
            ->assertJsonPath('data.spellcasting.spell_save_dc', 15) // 8 + 3 + 4
            ->assertJsonPath('data.spellcasting.spell_attack_bonus', 7) // 3 + 4
            // Issue #255: Enhanced stats - saving throws include proficiency
            ->assertJsonPath('data.saving_throws.INT.proficient', false) // Wizard doesn't have INT save by default
            ->assertJsonPath('data.saving_throws.INT.modifier', 4)
            // Skills array has 18 entries
            ->assertJsonCount(18, 'data.skills')
            // Speed has all movement types
            ->assertJsonPath('data.speed.walk', 30)
            // Passive is grouped
            ->assertJsonPath('data.passive.perception', 11);
    }

    #[Test]
    public function it_caches_stats_for_performance(): void
    {
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10, 'dexterity' => 10, 'constitution' => 10,
                'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10,
            ])
            ->create();

        // First request - cache miss
        $response1 = $this->getJson("/api/v1/characters/{$character->id}/stats");
        $response1->assertOk();

        // Second request - should hit cache (same response)
        $response2 = $this->getJson("/api/v1/characters/{$character->id}/stats");
        $response2->assertOk();

        // Responses should be identical
        $this->assertEquals(
            $response1->json('data'),
            $response2->json('data')
        );
    }

    #[Test]
    public function it_invalidates_cache_when_character_is_updated(): void
    {
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10, 'dexterity' => 10, 'constitution' => 10,
                'intelligence' => 10, 'wisdom' => 10, 'charisma' => 10,
            ])
            ->create();

        // Get initial stats (caches result)
        $response1 = $this->getJson("/api/v1/characters/{$character->id}/stats");
        $response1->assertOk()
            ->assertJsonPath('data.ability_scores.INT.score', 10)
            ->assertJsonPath('data.ability_scores.INT.modifier', 0);

        // Update intelligence
        $this->patchJson("/api/v1/characters/{$character->id}", [
            'intelligence' => 18,
        ])->assertOk();

        // Get stats again - should reflect new value (cache was invalidated)
        $response2 = $this->getJson("/api/v1/characters/{$character->id}/stats");
        $response2->assertOk()
            ->assertJsonPath('data.ability_scores.INT.score', 18)
            ->assertJsonPath('data.ability_scores.INT.modifier', 4);
    }

    #[Test]
    public function it_deletes_character_and_all_related_data(): void
    {
        $wizardClass = CharacterClass::factory()->spellcaster('INT')->create();
        $character = Character::factory()->withClass($wizardClass)->create();

        $spells = Spell::factory()->count(3)->create(['level' => 1]);
        $wizardClass->spells()->attach($spells->pluck('id'));

        // Add spells to character
        foreach ($spells as $spell) {
            CharacterSpell::create([
                'character_id' => $character->id,
                'spell_slug' => $spell->full_slug,
                'preparation_status' => 'known',
                'source' => 'class',
            ]);
        }

        $this->assertDatabaseCount('character_spells', 3);

        // Delete character
        $response = $this->deleteJson("/api/v1/characters/{$character->id}");
        $response->assertNoContent();

        // Verify cascade delete
        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
        $this->assertDatabaseCount('character_spells', 0); // Cascade deleted
    }
}

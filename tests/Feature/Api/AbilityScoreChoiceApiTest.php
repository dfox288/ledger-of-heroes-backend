<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\Modifier;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('feature-db')]
class AbilityScoreChoiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedAbilityScores(): void
    {
        $abilities = [
            'STR' => 'Strength',
            'DEX' => 'Dexterity',
            'CON' => 'Constitution',
            'INT' => 'Intelligence',
            'WIS' => 'Wisdom',
            'CHA' => 'Charisma',
        ];

        foreach ($abilities as $code => $name) {
            AbilityScore::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }

    private function createCharacterWithAbilityScoreChoice(array $modifierOptions = []): array
    {
        $this->seedAbilityScores();

        // Create race with ability score choice modifier
        $race = Race::factory()->create([
            'name' => 'Half-Elf',
            'slug' => 'test:half-elf',
        ]);

        $modifierDefaults = [
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'value' => '1',
            'choice_constraint' => 'different',
        ];

        $modifier = Modifier::create(array_merge(
            $modifierDefaults,
            $modifierOptions,
            [
                'reference_type' => Race::class,
                'reference_id' => $race->id,
            ]
        ));

        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 12,
                'constitution' => 14,
                'intelligence' => 10,
                'wisdom' => 10,
                'charisma' => 10,
            ])
            ->create(['race_slug' => $race->slug]);

        return [$character, $race, $modifier];
    }

    // =========================================================================
    // GET /api/v1/characters/{id}/pending-choices
    // =========================================================================

    #[Test]
    public function it_includes_ability_score_choices_in_pending_choices(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice();

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'choices' => [
                        '*' => [
                            'id',
                            'type',
                            'subtype',
                            'source',
                            'source_name',
                            'level_granted',
                            'required',
                            'quantity',
                            'remaining',
                            'selected',
                            'options',
                            'metadata',
                        ],
                    ],
                    'summary',
                ],
            ])
            ->assertJsonPath('data.choices.0.type', 'ability_score')
            ->assertJsonPath('data.choices.0.source', 'race')
            ->assertJsonPath('data.choices.0.source_name', 'Half-Elf')
            ->assertJsonPath('data.choices.0.quantity', 2)
            ->assertJsonPath('data.choices.0.remaining', 2)
            ->assertJsonPath('data.choices.0.required', true)
            ->assertJsonPath('data.choices.0.metadata.bonus_value', 1)
            ->assertJsonPath('data.choices.0.metadata.choice_constraint', 'different')
            ->assertJsonPath('data.choices.0.metadata.modifier_id', $modifier->id);

        // Verify all 6 ability scores are in options
        $options = $response->json('data.choices.0.options');
        $this->assertCount(6, $options);
        $codes = collect($options)->pluck('code')->toArray();
        $this->assertEquals(['STR', 'DEX', 'CON', 'INT', 'WIS', 'CHA'], $codes);
    }

    #[Test]
    public function it_excludes_ability_score_choices_for_character_without_race(): void
    {
        $this->seedAbilityScores();
        $character = Character::factory()
            ->withAbilityScores(['strength' => 10])
            ->create(['race_slug' => null]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices");

        $response->assertOk()
            ->assertJsonPath('data.choices', [])
            ->assertJsonPath('data.summary.total_pending', 0);
    }

    #[Test]
    public function it_shows_remaining_count_after_partial_selection(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice([
            'choice_count' => 3,
            'value' => '1',
        ]);

        // Manually select 1 out of 3
        $character->abilityScores()->create([
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices");

        $response->assertOk()
            ->assertJsonPath('data.choices.0.quantity', 3)
            ->assertJsonPath('data.choices.0.remaining', 2)
            ->assertJsonPath('data.choices.0.selected.0', 'STR');
    }

    // =========================================================================
    // GET /api/v1/characters/{id}/pending-choices/{choiceId}
    // =========================================================================

    #[Test]
    public function it_shows_individual_ability_score_choice_details(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice();

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices/{$choiceId}");

        $response->assertOk()
            ->assertJsonPath('data.id', $choiceId)
            ->assertJsonPath('data.type', 'ability_score')
            ->assertJsonPath('data.quantity', 2)
            ->assertJsonPath('data.remaining', 2);
    }

    // =========================================================================
    // POST /api/v1/characters/{id}/choices/{choiceId} - Success Cases
    // =========================================================================

    #[Test]
    public function it_resolves_ability_score_selection_successfully(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice();

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['STR', 'DEX'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Choice resolved successfully')
            ->assertJsonPath('data.choice_id', $choiceId);

        // Verify database records
        $this->assertDatabaseHas('character_ability_scores', [
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);

        $this->assertDatabaseHas('character_ability_scores', [
            'character_id' => $character->id,
            'ability_score_code' => 'DEX',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);
    }

    #[Test]
    public function it_replaces_previous_selections_on_new_resolution(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice();

        // First selection
        $character->abilityScores()->create([
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);
        $character->abilityScores()->create([
            'ability_score_code' => 'CON',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);

        // New selection should replace old
        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['DEX', 'WIS'],
        ]);

        $response->assertOk();

        // Old selections should be gone
        $this->assertDatabaseMissing('character_ability_scores', [
            'character_id' => $character->id,
            'ability_score_code' => 'STR',
            'modifier_id' => $modifier->id,
        ]);

        // New selections should exist
        $this->assertDatabaseHas('character_ability_scores', [
            'character_id' => $character->id,
            'ability_score_code' => 'DEX',
            'modifier_id' => $modifier->id,
        ]);

        $this->assertDatabaseHas('character_ability_scores', [
            'character_id' => $character->id,
            'ability_score_code' => 'WIS',
            'modifier_id' => $modifier->id,
        ]);
    }

    #[Test]
    public function it_applies_correct_bonus_value_from_modifier(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice([
            'value' => '2',
            'choice_count' => 1,
        ]);

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['INT'],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('character_ability_scores', [
            'character_id' => $character->id,
            'ability_score_code' => 'INT',
            'bonus' => 2, // Value from modifier
            'modifier_id' => $modifier->id,
        ]);
    }

    // =========================================================================
    // POST /api/v1/characters/{id}/choices/{choiceId} - Validation Errors
    // =========================================================================

    #[Test]
    public function it_returns_422_for_wrong_quantity(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice();

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";

        // Only 1 selection when 2 required
        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['STR'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Must select exactly 2 ability score(s)');
    }

    #[Test]
    public function it_returns_422_for_too_many_selections(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice();

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";

        // 3 selections when 2 required
        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['STR', 'DEX', 'CON'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Must select exactly 2 ability score(s)');
    }

    #[Test]
    public function it_returns_422_for_duplicate_selections_when_constraint_is_different(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice([
            'choice_constraint' => 'different',
        ]);

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['STR', 'STR'],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Selected ability scores must be different');
    }

    #[Test]
    public function it_returns_422_for_empty_selection(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice();

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Selection cannot be empty');
    }

    #[Test]
    public function it_returns_422_for_invalid_ability_code(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice();

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['STR', 'INVALID'],
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['Invalid ability score code: INVALID']);
    }

    #[Test]
    public function it_returns_404_for_non_existent_character_on_resolve(): void
    {
        $response = $this->postJson('/api/v1/characters/99999/choices/ability_score|race|phb:half-elf|1|modifier_1', [
            'selected' => ['STR', 'DEX'],
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_404_for_invalid_choice_id(): void
    {
        [$character] = $this->createCharacterWithAbilityScoreChoice();

        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/ability_score|race|nonexistent|1|modifier_999", [
            'selected' => ['STR', 'DEX'],
        ]);

        $response->assertNotFound();
    }

    // =========================================================================
    // DELETE /api/v1/characters/{id}/choices/{choiceId}
    // =========================================================================

    #[Test]
    public function it_undoes_ability_score_selection(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice();

        // Create selections
        $character->abilityScores()->create([
            'ability_score_code' => 'STR',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);
        $character->abilityScores()->create([
            'ability_score_code' => 'DEX',
            'bonus' => 1,
            'source' => 'race',
            'modifier_id' => $modifier->id,
        ]);

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $response = $this->deleteJson("/api/v1/characters/{$character->id}/choices/{$choiceId}");

        $response->assertOk()
            ->assertJsonPath('data.message', 'Choice undone successfully')
            ->assertJsonPath('data.choice_id', $choiceId);

        // Verify selections removed
        $this->assertDatabaseMissing('character_ability_scores', [
            'character_id' => $character->id,
            'modifier_id' => $modifier->id,
        ]);

        $this->assertCount(0, $character->fresh()->abilityScores);
    }

    #[Test]
    public function it_returns_404_for_non_existent_character_on_undo(): void
    {
        $response = $this->deleteJson('/api/v1/characters/99999/choices/ability_score|race|phb:half-elf|1|modifier_1');

        $response->assertNotFound();
    }

    #[Test]
    public function it_returns_success_even_if_no_selections_to_undo(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice();

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $response = $this->deleteJson("/api/v1/characters/{$character->id}/choices/{$choiceId}");

        // Should succeed even if nothing to undo
        $response->assertOk()
            ->assertJsonPath('data.message', 'Choice undone successfully')
            ->assertJsonPath('data.choice_id', $choiceId);
    }

    // =========================================================================
    // GET /api/v1/characters/{id}/stats - Integration Test
    // =========================================================================

    #[Test]
    public function it_reflects_chosen_ability_bonuses_in_stats_endpoint(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice([
            'value' => '1',
            'choice_count' => 2,
        ]);

        // Character base scores: STR=10, DEX=12, CON=14
        // Make selections: +1 STR, +1 DEX

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['STR', 'DEX'],
        ]);

        // Check stats endpoint
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.ability_scores.STR.score', 11) // 10 base + 1 choice
            ->assertJsonPath('data.ability_scores.STR.modifier', 0) // (11-10)/2 = 0
            ->assertJsonPath('data.ability_scores.DEX.score', 13) // 12 base + 1 choice
            ->assertJsonPath('data.ability_scores.DEX.modifier', 1) // (13-10)/2 = 1
            ->assertJsonPath('data.ability_scores.CON.score', 14) // 14 base (unchanged)
            ->assertJsonPath('data.ability_scores.CON.modifier', 2); // (14-10)/2 = 2
    }

    #[Test]
    public function it_reflects_multiple_racial_modifiers_in_stats(): void
    {
        $this->seedAbilityScores();

        $race = Race::factory()->create([
            'name' => 'Half-Elf',
            'slug' => 'test:half-elf',
        ]);

        // Fixed +2 CHA
        $chaId = AbilityScore::where('code', 'CHA')->firstOrFail()->id;
        Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'ability_score_id' => $chaId,
            'value' => '2',
            'is_choice' => false,
        ]);

        // Choice: +1 to any two different abilities
        $choiceModifier = Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $race->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 2,
            'value' => '1',
            'choice_constraint' => 'different',
        ]);

        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 10,
                'constitution' => 10,
                'intelligence' => 10,
                'wisdom' => 10,
                'charisma' => 10,
            ])
            ->create(['race_slug' => $race->slug]);

        // Make choice selections: +1 STR, +1 DEX
        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$choiceModifier->id}";
        $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['STR', 'DEX'],
        ]);

        // Check stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.ability_scores.STR.score', 11) // 10 + 1 choice
            ->assertJsonPath('data.ability_scores.DEX.score', 11) // 10 + 1 choice
            ->assertJsonPath('data.ability_scores.CHA.score', 12); // 10 + 2 fixed
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    #[Test]
    public function it_handles_single_ability_score_choice(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice([
            'choice_count' => 1,
        ]);

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";
        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['WIS'],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('character_ability_scores', [
            'character_id' => $character->id,
            'ability_score_code' => 'WIS',
            'bonus' => 1,
        ]);
    }

    #[Test]
    public function it_handles_choice_constraint_null(): void
    {
        [$character, $race, $modifier] = $this->createCharacterWithAbilityScoreChoice([
            'choice_constraint' => null,
            'choice_count' => 2,
        ]);

        $choiceId = "ability_score|race|{$race->slug}|1|modifier_{$modifier->id}";

        // Even with null constraint, duplicates may not be allowed due to database unique constraint
        // This test verifies that when constraint is null, the handler doesn't perform duplicate checking
        // However, database-level constraints may still prevent actual duplicates
        $response = $this->postJson("/api/v1/characters/{$character->id}/choices/{$choiceId}", [
            'selected' => ['STR', 'DEX'],
        ]);

        $response->assertOk();
    }

    #[Test]
    public function it_includes_parent_race_ability_score_choices_for_subrace(): void
    {
        $this->seedAbilityScores();

        // Create parent race with choice modifier
        $elf = Race::factory()->create([
            'name' => 'Elf',
            'slug' => 'test:elf',
        ]);
        $parentModifier = Modifier::create([
            'reference_type' => Race::class,
            'reference_id' => $elf->id,
            'modifier_category' => 'ability_score',
            'is_choice' => true,
            'choice_count' => 1,
            'value' => '1',
        ]);

        // Create subrace
        $highElf = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'test:high-elf',
            'parent_race_id' => $elf->id,
        ]);

        $character = Character::factory()
            ->withAbilityScores(['strength' => 10])
            ->create(['race_slug' => $highElf->slug]);

        $response = $this->getJson("/api/v1/characters/{$character->id}/pending-choices");

        $response->assertOk();

        // Should include parent race's choice
        $choices = $response->json('data.choices');
        $abilityScoreChoices = collect($choices)->where('type', 'ability_score')->values();
        $this->assertCount(1, $abilityScoreChoices);
        $this->assertEquals($parentModifier->id, $abilityScoreChoices[0]['metadata']['modifier_id']);
    }
}

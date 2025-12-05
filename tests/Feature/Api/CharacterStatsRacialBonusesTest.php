<?php

namespace Tests\Feature\Api;

use App\Models\AbilityScore;
use App\Models\Character;
use App\Models\Race;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test that the stats endpoint returns final ability scores with racial bonuses applied.
 *
 * Issue #223: Stats endpoint should return final ability scores with racial bonuses applied
 */
class CharacterStatsRacialBonusesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_includes_fixed_racial_bonuses_in_ability_scores(): void
    {
        // Get ability score ID from seeded data
        $chaId = AbilityScore::where('code', 'CHA')->firstOrFail()->id;

        // Create race with +2 CHA bonus (like Half-Elf)
        $race = Race::factory()->create(['name' => 'Half-Elf', 'slug' => 'half-elf']);
        $race->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $chaId,
            'value' => '2',
            'is_choice' => false,
        ]);

        // Create character with base 10 CHA
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 10,
                'constitution' => 10,
                'intelligence' => 10,
                'wisdom' => 10,
                'charisma' => 10, // Base 10
            ])
            ->create(['race_id' => $race->id]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        // CHA should be 12 (base 10 + racial 2)
        $response->assertOk()
            ->assertJsonPath('data.ability_scores.CHA.score', 12)
            ->assertJsonPath('data.ability_scores.CHA.modifier', 1); // (12-10)/2 = 1
    }

    #[Test]
    public function it_includes_fixed_subrace_bonuses_in_ability_scores(): void
    {
        // Get ability score IDs from seeded data
        $dexId = AbilityScore::where('code', 'DEX')->firstOrFail()->id;
        $intId = AbilityScore::where('code', 'INT')->firstOrFail()->id;

        // Create Elf race with +2 DEX
        $elf = Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        $elf->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $dexId,
            'value' => '2',
            'is_choice' => false,
        ]);

        // Create High Elf subrace with +1 INT
        $highElf = Race::factory()->create([
            'name' => 'High Elf',
            'slug' => 'high-elf',
            'parent_race_id' => $elf->id,
        ]);
        $highElf->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $intId,
            'value' => '1',
            'is_choice' => false,
        ]);

        // Create character with base scores
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 10, // Base 10
                'constitution' => 10,
                'intelligence' => 10, // Base 10
                'wisdom' => 10,
                'charisma' => 10,
            ])
            ->create(['race_id' => $highElf->id]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        // DEX should be 12 (base 10 + parent race 2)
        // INT should be 11 (base 10 + subrace 1)
        $response->assertOk()
            ->assertJsonPath('data.ability_scores.DEX.score', 12)
            ->assertJsonPath('data.ability_scores.DEX.modifier', 1) // (12-10)/2 = 1
            ->assertJsonPath('data.ability_scores.INT.score', 11)
            ->assertJsonPath('data.ability_scores.INT.modifier', 0); // (11-10)/2 = 0
    }

    #[Test]
    public function it_includes_multiple_fixed_racial_bonuses(): void
    {
        // Get ability score IDs from seeded data
        $strId = AbilityScore::where('code', 'STR')->firstOrFail()->id;
        $conId = AbilityScore::where('code', 'CON')->firstOrFail()->id;

        // Create Mountain Dwarf with +2 STR and +2 CON
        $race = Race::factory()->create(['name' => 'Mountain Dwarf', 'slug' => 'mountain-dwarf']);
        $race->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $strId,
            'value' => '2',
            'is_choice' => false,
        ]);
        $race->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $conId,
            'value' => '2',
            'is_choice' => false,
        ]);

        // Create character with base scores
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 14, // Base 14
                'dexterity' => 10,
                'constitution' => 12, // Base 12
                'intelligence' => 10,
                'wisdom' => 10,
                'charisma' => 8,
            ])
            ->create(['race_id' => $race->id]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        // STR should be 16 (base 14 + racial 2)
        // CON should be 14 (base 12 + racial 2)
        $response->assertOk()
            ->assertJsonPath('data.ability_scores.STR.score', 16)
            ->assertJsonPath('data.ability_scores.STR.modifier', 3) // (16-10)/2 = 3
            ->assertJsonPath('data.ability_scores.CON.score', 14)
            ->assertJsonPath('data.ability_scores.CON.modifier', 2); // (14-10)/2 = 2
    }

    #[Test]
    public function it_does_not_apply_racial_bonuses_when_no_race_assigned(): void
    {
        // Create character with no race
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 10,
                'constitution' => 10,
                'intelligence' => 10,
                'wisdom' => 10,
                'charisma' => 10,
            ])
            ->create(['race_id' => null]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        // All scores should be 10 (no bonuses)
        $response->assertOk()
            ->assertJsonPath('data.ability_scores.STR.score', 10)
            ->assertJsonPath('data.ability_scores.DEX.score', 10)
            ->assertJsonPath('data.ability_scores.CON.score', 10)
            ->assertJsonPath('data.ability_scores.INT.score', 10)
            ->assertJsonPath('data.ability_scores.WIS.score', 10)
            ->assertJsonPath('data.ability_scores.CHA.score', 10);
    }

    #[Test]
    public function it_does_not_apply_choice_based_racial_bonuses_without_selection(): void
    {
        // Get ability score ID from seeded data
        $chaId = AbilityScore::where('code', 'CHA')->firstOrFail()->id;

        // Create Half-Elf with fixed +2 CHA and choice-based +1 to any two
        $race = Race::factory()->create(['name' => 'Half-Elf', 'slug' => 'half-elf']);
        $race->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $chaId,
            'value' => '2',
            'is_choice' => false,
        ]);
        $race->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => null, // Choice
            'value' => '1',
            'is_choice' => true,
            'choice_count' => 2,
        ]);

        // Create character with base scores
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 10,
                'constitution' => 10,
                'intelligence' => 10,
                'wisdom' => 10,
                'charisma' => 10,
            ])
            ->create(['race_id' => $race->id]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        // Only fixed bonus should apply (CHA +2)
        // Choice-based bonuses are not implemented yet (Issue #223 note)
        $response->assertOk()
            ->assertJsonPath('data.ability_scores.CHA.score', 12) // Fixed bonus only
            ->assertJsonPath('data.ability_scores.STR.score', 10) // No choice bonus yet
            ->assertJsonPath('data.ability_scores.DEX.score', 10); // No choice bonus yet
    }

    #[Test]
    public function it_affects_derived_stats_based_on_racial_bonuses(): void
    {
        // Get ability score IDs from seeded data
        $dexId = AbilityScore::where('code', 'DEX')->firstOrFail()->id;
        $wisId = AbilityScore::where('code', 'WIS')->firstOrFail()->id;

        // Create race with +2 DEX and +1 WIS
        $race = Race::factory()->create(['name' => 'Wood Elf', 'slug' => 'wood-elf']);
        $race->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $dexId,
            'value' => '2',
            'is_choice' => false,
        ]);
        $race->modifiers()->create([
            'modifier_category' => 'ability_score',
            'ability_score_id' => $wisId,
            'value' => '1',
            'is_choice' => false,
        ]);

        // Create character with base scores
        $character = Character::factory()
            ->withAbilityScores([
                'strength' => 10,
                'dexterity' => 12, // Base 12
                'constitution' => 10,
                'intelligence' => 10,
                'wisdom' => 13, // Base 13
                'charisma' => 10,
            ])
            ->create(['race_id' => $race->id]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        // DEX should be 14 (base 12 + racial 2) = +2 modifier
        // WIS should be 14 (base 13 + racial 1) = +2 modifier
        $response->assertOk()
            ->assertJsonPath('data.ability_scores.DEX.score', 14)
            ->assertJsonPath('data.ability_scores.DEX.modifier', 2)
            ->assertJsonPath('data.ability_scores.WIS.score', 14)
            ->assertJsonPath('data.ability_scores.WIS.modifier', 2)
            // Initiative bonus should use DEX modifier (should be 2, not 1)
            ->assertJsonPath('data.initiative_bonus', 2)
            // Passive Perception should use WIS modifier (should be 12, not 11)
            ->assertJsonPath('data.passive_perception', 12); // 10 + 2 WIS mod
    }
}

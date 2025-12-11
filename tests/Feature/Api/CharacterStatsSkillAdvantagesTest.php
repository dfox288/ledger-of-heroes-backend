<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\Feat;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test that the stats endpoint returns skill check advantages.
 *
 * Issue #429: Parse and store skill check advantages from traits
 */
class CharacterStatsSkillAdvantagesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_includes_skill_advantages_from_race(): void
    {
        // Get History skill from seeded data
        $history = Skill::where('name', 'History')->first();
        $this->assertNotNull($history, 'History skill should be seeded');

        // Create race with skill advantage (like Dwarf's Stonecunning)
        $race = Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);
        $race->modifiers()->create([
            'modifier_category' => 'skill_advantage',
            'skill_id' => $history->id,
            'value' => 'advantage',
            'condition' => 'related to the origin of stonework',
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.skill_advantages.0.skill', 'History')
            ->assertJsonPath('data.skill_advantages.0.skill_slug', 'history')
            ->assertJsonPath('data.skill_advantages.0.condition', 'related to the origin of stonework')
            ->assertJsonPath('data.skill_advantages.0.source', 'Dwarf');
    }

    #[Test]
    public function it_includes_skill_advantages_from_feats(): void
    {
        // Get skills from seeded data
        $deception = Skill::where('name', 'Deception')->first();
        $this->assertNotNull($deception, 'Deception skill should be seeded');

        // Create race
        $race = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        // Create feat with skill advantage (like Actor)
        $feat = Feat::factory()->create(['name' => 'Actor', 'slug' => 'actor']);
        $feat->modifiers()->create([
            'modifier_category' => 'skill_advantage',
            'skill_id' => $deception->id,
            'value' => 'advantage',
            'condition' => 'when trying to pass yourself off as a different person',
        ]);

        // Create character with the feat
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Add feat to character via features
        $character->features()->create([
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
        ]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.skill_advantages.0.skill', 'Deception')
            ->assertJsonPath('data.skill_advantages.0.skill_slug', 'deception')
            ->assertJsonPath('data.skill_advantages.0.condition', 'when trying to pass yourself off as a different person')
            ->assertJsonPath('data.skill_advantages.0.source', 'Actor');
    }

    #[Test]
    public function it_aggregates_skill_advantages_from_multiple_sources(): void
    {
        // Get skills from seeded data
        $history = Skill::where('name', 'History')->first();
        $perception = Skill::where('name', 'Perception')->first();
        $this->assertNotNull($history, 'History skill should be seeded');
        $this->assertNotNull($perception, 'Perception skill should be seeded');

        // Create race with skill advantage
        $race = Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);
        $race->modifiers()->create([
            'modifier_category' => 'skill_advantage',
            'skill_id' => $history->id,
            'value' => 'advantage',
            'condition' => 'related to stonework',
        ]);

        // Create feat with different skill advantage
        $feat = Feat::factory()->create(['name' => 'Observant', 'slug' => 'observant']);
        $feat->modifiers()->create([
            'modifier_category' => 'skill_advantage',
            'skill_id' => $perception->id,
            'value' => 'advantage',
            'condition' => 'to detect hidden enemies',
        ]);

        // Create character with race and feat
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        $character->features()->create([
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
        ]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonCount(2, 'data.skill_advantages');

        // Verify both advantages are present (order may vary)
        $advantages = $response->json('data.skill_advantages');
        $skills = array_column($advantages, 'skill');
        $this->assertContains('History', $skills);
        $this->assertContains('Perception', $skills);
    }

    #[Test]
    public function it_returns_empty_array_when_no_skill_advantages(): void
    {
        // Create race with no skill advantages
        $race = Race::factory()->create(['name' => 'Human', 'slug' => 'human']);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.skill_advantages', []);
    }

    #[Test]
    public function it_includes_skill_advantages_without_condition(): void
    {
        // Get skill from seeded data
        $athletics = Skill::where('name', 'Athletics')->first();
        $this->assertNotNull($athletics, 'Athletics skill should be seeded');

        // Create race with unconditional skill advantage
        $race = Race::factory()->create(['name' => 'Test Race', 'slug' => 'test-race']);
        $race->modifiers()->create([
            'modifier_category' => 'skill_advantage',
            'skill_id' => $athletics->id,
            'value' => 'advantage',
            'condition' => null,
        ]);

        // Create character
        $character = Character::factory()
            ->withAbilityScores([])
            ->create(['race_slug' => $race->slug]);

        // Get stats
        $response = $this->getJson("/api/v1/characters/{$character->id}/stats");

        $response->assertOk()
            ->assertJsonPath('data.skill_advantages.0.skill', 'Athletics')
            ->assertJsonPath('data.skill_advantages.0.skill_slug', 'athletics')
            ->assertJsonPath('data.skill_advantages.0.condition', null)
            ->assertJsonPath('data.skill_advantages.0.source', 'Test Race');
    }
}

<?php

namespace Tests\Feature\Requests;

use App\Models\AbilityScore;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SkillIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_paginates_skills()
    {
        $abilityScore = AbilityScore::factory()->create();
        Skill::factory()->count(10)->create(['ability_score_id' => $abilityScore->id]);

        // Request with per_page
        $response = $this->getJson('/api/v1/skills?per_page=5');
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name'],
                ],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function it_searches_skills_by_name()
    {
        $abilityScore = AbilityScore::factory()->create();
        Skill::factory()->create(['name' => 'Acrobatics', 'ability_score_id' => $abilityScore->id]);
        Skill::factory()->create(['name' => 'Athletics', 'ability_score_id' => $abilityScore->id]);
        Skill::factory()->create(['name' => 'Perception', 'ability_score_id' => $abilityScore->id]);

        // Search for 'Acrobatics'
        $response = $this->getJson('/api/v1/skills?search=Acrobatics');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Acrobatics']);
    }

    #[Test]
    public function it_filters_skills_by_ability_score()
    {
        $strength = AbilityScore::factory()->create(['code' => 'STR', 'name' => 'Strength']);
        $dexterity = AbilityScore::factory()->create(['code' => 'DEX', 'name' => 'Dexterity']);

        Skill::factory()->create(['name' => 'Athletics', 'ability_score_id' => $strength->id]);
        Skill::factory()->create(['name' => 'Acrobatics', 'ability_score_id' => $dexterity->id]);
        Skill::factory()->create(['name' => 'Stealth', 'ability_score_id' => $dexterity->id]);

        // Filter by STR
        $response = $this->getJson('/api/v1/skills?ability=STR');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Athletics']);

        // Filter by DEX
        $response = $this->getJson('/api/v1/skills?ability=DEX');
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_validates_per_page_maximum()
    {
        // Valid: 50
        $response = $this->getJson('/api/v1/skills?per_page=50');
        $response->assertStatus(200);

        // Invalid: 101 (exceeds max of 100)
        $response = $this->getJson('/api/v1/skills?per_page=101');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function it_validates_ability_exists()
    {
        $abilityScore = AbilityScore::factory()->create(['code' => 'STR']);

        // Valid ability code
        $response = $this->getJson('/api/v1/skills?ability=STR');
        $response->assertStatus(200);

        // Invalid ability code
        $response = $this->getJson('/api/v1/skills?ability=INVALID');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ability']);
    }

    #[Test]
    public function it_validates_search_max_length()
    {
        // Valid: 255 characters
        $search = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/skills?search={$search}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $search = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/skills?search={$search}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }
}

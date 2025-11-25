<?php

namespace Tests\Feature\Api;

use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SkillApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_all_skills(): void
    {
        $response = $this->getJson('/api/v1/lookups/skills');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'ability_score'],
                ],
            ]);
    }

    #[Test]
    public function it_can_get_a_single_skill_by_id(): void
    {
        $skill = Skill::first();

        $response = $this->getJson("/api/v1/lookups/skills/{$skill->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $skill->id)
            ->assertJsonPath('data.name', $skill->name)
            ->assertJsonPath('data.slug', $skill->slug);
    }

    #[Test]
    public function it_can_get_a_single_skill_by_slug(): void
    {
        $skill = Skill::where('name', 'Animal Handling')->first();

        $response = $this->getJson('/api/v1/lookups/skills/animal-handling');

        $response->assertOk()
            ->assertJsonPath('data.id', $skill->id)
            ->assertJsonPath('data.name', 'Animal Handling')
            ->assertJsonPath('data.slug', 'animal-handling');
    }

    #[Test]
    public function it_can_get_sleight_of_hand_by_slug(): void
    {
        $skill = Skill::where('name', 'Sleight of Hand')->first();

        $response = $this->getJson('/api/v1/lookups/skills/sleight-of-hand');

        $response->assertOk()
            ->assertJsonPath('data.id', $skill->id)
            ->assertJsonPath('data.name', 'Sleight of Hand')
            ->assertJsonPath('data.slug', 'sleight-of-hand');
    }

    #[Test]
    public function it_returns_404_for_nonexistent_skill_slug(): void
    {
        $response = $this->getJson('/api/v1/lookups/skills/nonexistent-skill');

        $response->assertNotFound();
    }

    #[Test]
    public function it_can_search_skills_using_q_parameter(): void
    {
        $response = $this->getJson('/api/v1/lookups/skills?q=perception');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Search should return results for "perception"');

        foreach ($data as $item) {
            $this->assertStringContainsStringIgnoringCase('perception', $item['name']);
        }
    }

    #[Test]
    public function it_returns_empty_results_when_no_skills_match_search(): void
    {
        $response = $this->getJson('/api/v1/lookups/skills?q=nonexistent123');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    #[Test]
    public function it_returns_all_skills_when_no_search_query_provided(): void
    {
        $totalSkills = Skill::count();

        $response = $this->getJson('/api/v1/lookups/skills');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount($totalSkills, $data);
    }

    #[Test]
    public function search_is_case_insensitive(): void
    {
        $response = $this->getJson('/api/v1/lookups/skills?q=STEALTH');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data, 'Case insensitive search should work');
    }

    #[Test]
    public function it_can_filter_by_ability_score(): void
    {
        $response = $this->getJson('/api/v1/lookups/skills?ability=DEX');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // Verify all returned skills use DEX
        foreach ($data as $skill) {
            $this->assertEquals('DEX', $skill['ability_score']['code']);
        }
    }

    #[Test]
    public function it_supports_pagination(): void
    {
        $response = $this->getJson('/api/v1/lookups/skills?per_page=5');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.per_page', 5);
    }

    #[Test]
    public function all_skills_have_unique_slugs(): void
    {
        $skills = Skill::all();
        $slugs = $skills->pluck('slug')->toArray();

        $this->assertCount(18, $skills, 'Should have 18 D&D skills');
        $this->assertCount(18, array_unique($slugs), 'All slugs should be unique');
    }
}

<?php

namespace Tests\Feature\Requests;

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
        // Skills are seeded by default
        // Request with per_page
        $response = $this->getJson('/api/v1/skills?per_page=5');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name'],
                ],
                'links',
                'meta',
            ]);

        // Verify pagination is working
        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    #[Test]
    public function it_searches_skills_by_name()
    {
        // Use seeded data - skills include Acrobatics, Athletics, Perception, etc.
        // Search for 'Acrobatics'
        $response = $this->getJson('/api/v1/skills?search=Acrobatics');
        $response->assertStatus(200);

        // Should find Acrobatics skill
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        $this->assertEquals('Acrobatics', $data[0]['name']);
    }

    #[Test]
    public function it_filters_skills_by_ability_score()
    {
        // Use seeded data - Athletics is STR, Acrobatics and Stealth are DEX
        // Filter by STR
        $response = $this->getJson('/api/v1/skills?ability=STR');
        $response->assertStatus(200);
        $strSkills = $response->json('data');
        $this->assertGreaterThan(0, count($strSkills));

        // Filter by DEX
        $response = $this->getJson('/api/v1/skills?ability=DEX');
        $response->assertStatus(200);
        $dexSkills = $response->json('data');
        $this->assertGreaterThan(0, count($dexSkills));
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
        // STR is seeded by default
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

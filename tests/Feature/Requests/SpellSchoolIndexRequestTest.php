<?php

namespace Tests\Feature\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class SpellSchoolIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_paginates_spell_schools()
    {
        // Spell schools are seeded by default (8 schools)
        // Request with per_page
        $response = $this->getJson('/api/v1/lookups/spell-schools?per_page=5');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'description'],
                ],
                'links',
                'meta',
            ]);

        // Verify pagination is working
        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    #[Test]
    public function it_searches_spell_schools_by_name()
    {
        // Use seeded data - spell schools include Evocation, Abjuration, Conjuration, etc.
        // Search for 'Evocation'
        $response = $this->getJson('/api/v1/lookups/spell-schools?q=Evocation');
        $response->assertStatus(200);

        // Should find Evocation school
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        $this->assertEquals('Evocation', $data[0]['name']);
    }

    #[Test]
    public function it_validates_per_page_maximum()
    {
        // Valid: 50
        $response = $this->getJson('/api/v1/lookups/spell-schools?per_page=50');
        $response->assertStatus(200);

        // Invalid: 201 (exceeds max of 200)
        $response = $this->getJson('/api/v1/lookups/spell-schools?per_page=201');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function it_validates_q_max_length()
    {
        // Valid: 255 characters
        $search = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/lookups/spell-schools?q={$search}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $search = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/lookups/spell-schools?q={$search}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_validates_page_is_positive_integer()
    {
        // Valid: 1
        $response = $this->getJson('/api/v1/lookups/spell-schools?page=1');
        $response->assertStatus(200);

        // Invalid: 0
        $response = $this->getJson('/api/v1/lookups/spell-schools?page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // Invalid: -1
        $response = $this->getJson('/api/v1/lookups/spell-schools?page=-1');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }
}

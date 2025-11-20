<?php

namespace Tests\Feature\Requests;

use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SizeIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_paginates_sizes()
    {
        // Sizes are seeded by default, so we just need to test pagination
        // Request with per_page
        $response = $this->getJson('/api/v1/sizes?per_page=3');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name'],
                ],
                'links',
                'meta',
            ]);

        // Verify pagination is working
        $this->assertLessThanOrEqual(3, count($response->json('data')));
    }

    #[Test]
    public function it_searches_sizes_by_name()
    {
        // Use seeded data - sizes include Tiny, Small, Medium, etc.
        // Search for 'Medium'
        $response = $this->getJson('/api/v1/sizes?search=Medium');
        $response->assertStatus(200);

        // Should find at least one size with 'Medium' in the name
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        $this->assertStringContainsStringIgnoringCase('Medium', $data[0]['name']);
    }

    #[Test]
    public function it_validates_per_page_maximum()
    {
        // Valid: 50
        $response = $this->getJson('/api/v1/sizes?per_page=50');
        $response->assertStatus(200);

        // Invalid: 101 (exceeds max of 100)
        $response = $this->getJson('/api/v1/sizes?per_page=101');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function it_validates_search_max_length()
    {
        // Valid: 255 characters
        $search = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/sizes?search={$search}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $search = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/sizes?search={$search}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    #[Test]
    public function it_validates_page_is_positive_integer()
    {
        // Valid: 1
        $response = $this->getJson('/api/v1/sizes?page=1');
        $response->assertStatus(200);

        // Invalid: 0
        $response = $this->getJson('/api/v1/sizes?page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // Invalid: -1
        $response = $this->getJson('/api/v1/sizes?page=-1');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }
}

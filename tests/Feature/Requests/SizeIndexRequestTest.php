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
        Size::factory()->count(10)->create();

        // Request with per_page
        $response = $this->getJson('/api/v1/sizes?per_page=5');
        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name'],
                ],
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function it_searches_sizes_by_name()
    {
        Size::factory()->create(['name' => 'Tiny', 'code' => 'T']);
        Size::factory()->create(['name' => 'Small', 'code' => 'S']);
        Size::factory()->create(['name' => 'Medium', 'code' => 'M']);

        // Search for 'Small'
        $response = $this->getJson('/api/v1/sizes?search=Small');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Small']);
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

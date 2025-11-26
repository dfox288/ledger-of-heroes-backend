<?php

namespace Tests\Feature\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class FeatIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_whitelists_sortable_columns()
    {
        // Valid sortable columns
        $response = $this->getJson('/api/v1/feats?sort_by=name');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/feats?sort_by=created_at');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/feats?sort_by=updated_at');
        $response->assertStatus(200);

        // Invalid column
        $response = $this->getJson('/api/v1/feats?sort_by=invalid_column');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_base_index_parameters()
    {
        // Valid pagination
        $response = $this->getJson('/api/v1/feats?per_page=25&page=2');
        $response->assertStatus(200);

        // Invalid per_page (too high)
        $response = $this->getJson('/api/v1/feats?per_page=200');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);

        // Invalid page (negative)
        $response = $this->getJson('/api/v1/feats?page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // Valid search
        $response = $this->getJson('/api/v1/feats?search=alert');
        $response->assertStatus(200);

        // Valid sort direction
        $response = $this->getJson('/api/v1/feats?sort_direction=desc');
        $response->assertStatus(200);

        // Invalid sort direction
        $response = $this->getJson('/api/v1/feats?sort_direction=invalid');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort_direction']);
    }
}

<?php

namespace Tests\Feature\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class RaceIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_whitelists_sortable_columns()
    {
        // Valid sortable columns
        $validColumns = ['name', 'size', 'speed', 'created_at', 'updated_at'];

        foreach ($validColumns as $column) {
            $response = $this->getJson("/api/v1/races?sort_by={$column}");
            $response->assertOk();
        }

        // Invalid sortable column
        $response = $this->getJson('/api/v1/races?sort_by=invalid_column');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sort_by');
    }

    #[Test]
    public function it_validates_sort_direction()
    {
        // Valid directions
        $response = $this->getJson('/api/v1/races?sort_direction=asc');
        $response->assertOk();

        $response = $this->getJson('/api/v1/races?sort_direction=desc');
        $response->assertOk();

        // Invalid direction
        $response = $this->getJson('/api/v1/races?sort_direction=invalid');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sort_direction');
    }

    #[Test]
    public function it_validates_pagination_parameters()
    {
        // Valid pagination
        $response = $this->getJson('/api/v1/races?per_page=25&page=1');
        $response->assertOk();

        // Invalid per_page (too high)
        $response = $this->getJson('/api/v1/races?per_page=150');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');

        // Invalid per_page (too low)
        $response = $this->getJson('/api/v1/races?per_page=0');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('per_page');

        // Invalid page (too low)
        $response = $this->getJson('/api/v1/races?page=0');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('page');
    }
}

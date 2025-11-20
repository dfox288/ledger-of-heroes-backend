<?php

namespace Tests\Feature\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RaceIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_validates_grants_proficiency_as_string()
    {
        // Valid string
        $response = $this->getJson('/api/v1/races?grants_proficiency=longsword');
        $response->assertOk();

        // String too long (max 255)
        $response = $this->getJson('/api/v1/races?grants_proficiency='.str_repeat('a', 256));
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('grants_proficiency');
    }

    #[Test]
    public function it_validates_grants_skill_as_string()
    {
        // Valid string
        $response = $this->getJson('/api/v1/races?grants_skill=stealth');
        $response->assertOk();

        // String too long (max 255)
        $response = $this->getJson('/api/v1/races?grants_skill='.str_repeat('a', 256));
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('grants_skill');
    }

    #[Test]
    public function it_validates_speaks_language_as_string()
    {
        // Valid string
        $response = $this->getJson('/api/v1/races?speaks_language=elvish');
        $response->assertOk();

        // String too long (max 255)
        $response = $this->getJson('/api/v1/races?speaks_language='.str_repeat('a', 256));
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('speaks_language');
    }

    #[Test]
    public function it_validates_language_choice_count_range()
    {
        // Valid range (0-10)
        $response = $this->getJson('/api/v1/races?language_choice_count=2');
        $response->assertOk();

        $response = $this->getJson('/api/v1/races?language_choice_count=0');
        $response->assertOk();

        $response = $this->getJson('/api/v1/races?language_choice_count=10');
        $response->assertOk();

        // Invalid - too high
        $response = $this->getJson('/api/v1/races?language_choice_count=20');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('language_choice_count');

        // Invalid - negative
        $response = $this->getJson('/api/v1/races?language_choice_count=-1');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('language_choice_count');
    }

    #[Test]
    public function it_validates_grants_languages_boolean()
    {
        // Valid boolean values
        $response = $this->getJson('/api/v1/races?grants_languages=true');
        $response->assertOk();

        $response = $this->getJson('/api/v1/races?grants_languages=false');
        $response->assertOk();

        $response = $this->getJson('/api/v1/races?grants_languages=1');
        $response->assertOk();

        $response = $this->getJson('/api/v1/races?grants_languages=0');
        $response->assertOk();

        // Invalid boolean value
        $response = $this->getJson('/api/v1/races?grants_languages=invalid');
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('grants_languages');
    }

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

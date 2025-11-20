<?php

namespace Tests\Feature\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_prerequisite_race_as_string()
    {
        // Valid string
        $response = $this->getJson('/api/v1/feats?prerequisite_race=dwarf');
        $response->assertStatus(200);

        // String too long (max 255)
        $response = $this->getJson('/api/v1/feats?prerequisite_race='.str_repeat('a', 256));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prerequisite_race']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_prerequisite_ability_as_string()
    {
        // Valid string
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=str');
        $response->assertStatus(200);

        // String too long (max 255)
        $response = $this->getJson('/api/v1/feats?prerequisite_ability='.str_repeat('a', 256));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prerequisite_ability']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_min_value_range()
    {
        // Valid min_value (use lowercase for ability code)
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=str&min_value=13');
        $response->assertStatus(200);

        // min_value too low
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=str&min_value=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_value']);

        // min_value too high
        $response = $this->getJson('/api/v1/feats?prerequisite_ability=str&min_value=50');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_value']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_has_prerequisites_boolean()
    {
        // Valid boolean values
        $response = $this->getJson('/api/v1/feats?has_prerequisites=true');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/feats?has_prerequisites=false');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/feats?has_prerequisites=1');
        $response->assertStatus(200);

        $response = $this->getJson('/api/v1/feats?has_prerequisites=0');
        $response->assertStatus(200);

        // Invalid boolean value
        $response = $this->getJson('/api/v1/feats?has_prerequisites=maybe');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['has_prerequisites']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_grants_proficiency_as_string()
    {
        // Valid string
        $response = $this->getJson('/api/v1/feats?grants_proficiency=longsword');
        $response->assertStatus(200);

        // String too long (max 255)
        $response = $this->getJson('/api/v1/feats?grants_proficiency='.str_repeat('a', 256));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['grants_proficiency']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_grants_skill_as_string()
    {
        // Valid string
        $response = $this->getJson('/api/v1/feats?grants_skill=acrobatics');
        $response->assertStatus(200);

        // String too long (max 255)
        $response = $this->getJson('/api/v1/feats?grants_skill='.str_repeat('a', 256));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['grants_skill']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_prerequisite_proficiency_as_string()
    {
        // Valid string
        $response = $this->getJson('/api/v1/feats?prerequisite_proficiency=medium armor');
        $response->assertStatus(200);

        // String too long (max 255)
        $response = $this->getJson('/api/v1/feats?prerequisite_proficiency='.str_repeat('a', 256));
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prerequisite_proficiency']);
    }

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

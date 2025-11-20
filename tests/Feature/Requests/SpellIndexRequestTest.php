<?php

namespace Tests\Feature\Requests;

use App\Models\Spell;
use App\Models\SpellSchool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_validates_level_filter()
    {
        // Valid level (0-9)
        $response = $this->getJson('/api/v1/spells?level=3');
        $response->assertStatus(200);

        // Invalid level (> 9)
        $response = $this->getJson('/api/v1/spells?level=10');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['level']);
    }

    #[Test]
    public function it_validates_school_exists()
    {
        $school = SpellSchool::first(); // Use seeded data instead of factory

        // Valid school ID
        $response = $this->getJson("/api/v1/spells?school={$school->id}");
        $response->assertStatus(200);

        // Invalid school ID
        $response = $this->getJson('/api/v1/spells?school=999');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['school']);
    }

    #[Test]
    public function it_validates_concentration_boolean()
    {
        // Valid: true
        $response = $this->getJson('/api/v1/spells?concentration=true');
        $response->assertStatus(200);

        // Valid: false
        $response = $this->getJson('/api/v1/spells?concentration=false');
        $response->assertStatus(200);

        // Valid: 1
        $response = $this->getJson('/api/v1/spells?concentration=1');
        $response->assertStatus(200);

        // Valid: 0
        $response = $this->getJson('/api/v1/spells?concentration=0');
        $response->assertStatus(200);

        // Invalid: 'yes'
        $response = $this->getJson('/api/v1/spells?concentration=yes');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['concentration']);
    }

    #[Test]
    public function it_validates_ritual_boolean()
    {
        // Valid: true
        $response = $this->getJson('/api/v1/spells?ritual=true');
        $response->assertStatus(200);

        // Valid: false
        $response = $this->getJson('/api/v1/spells?ritual=false');
        $response->assertStatus(200);

        // Valid: 1
        $response = $this->getJson('/api/v1/spells?ritual=1');
        $response->assertStatus(200);

        // Valid: 0
        $response = $this->getJson('/api/v1/spells?ritual=0');
        $response->assertStatus(200);

        // Invalid: 'yes'
        $response = $this->getJson('/api/v1/spells?ritual=yes');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ritual']);
    }

    #[Test]
    public function it_whitelists_sortable_columns()
    {
        Spell::factory()->create(['name' => 'Test Spell']);

        // Valid: name
        $response = $this->getJson('/api/v1/spells?sort_by=name');
        $response->assertStatus(200);

        // Valid: level
        $response = $this->getJson('/api/v1/spells?sort_by=level');
        $response->assertStatus(200);

        // Valid: created_at
        $response = $this->getJson('/api/v1/spells?sort_by=created_at');
        $response->assertStatus(200);

        // Valid: updated_at
        $response = $this->getJson('/api/v1/spells?sort_by=updated_at');
        $response->assertStatus(200);

        // Invalid: password
        $response = $this->getJson('/api/v1/spells?sort_by=password');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort_by']);
    }

    #[Test]
    public function it_validates_per_page_limit()
    {
        // Valid: 50
        $response = $this->getJson('/api/v1/spells?per_page=50');
        $response->assertStatus(200);

        // Invalid: 101 (exceeds max of 100)
        $response = $this->getJson('/api/v1/spells?per_page=101');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function it_validates_sort_direction()
    {
        Spell::factory()->create(['name' => 'Test Spell']);

        // Valid: asc
        $response = $this->getJson('/api/v1/spells?sort_direction=asc');
        $response->assertStatus(200);

        // Valid: desc
        $response = $this->getJson('/api/v1/spells?sort_direction=desc');
        $response->assertStatus(200);

        // Invalid: sideways
        $response = $this->getJson('/api/v1/spells?sort_direction=sideways');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sort_direction']);
    }

    #[Test]
    public function it_validates_search_max_length()
    {
        // Valid: 255 characters
        $search = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/spells?search={$search}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $search = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/spells?search={$search}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    #[Test]
    public function it_validates_page_is_positive_integer()
    {
        // Valid: 1
        $response = $this->getJson('/api/v1/spells?page=1');
        $response->assertStatus(200);

        // Invalid: 0
        $response = $this->getJson('/api/v1/spells?page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // Invalid: -1
        $response = $this->getJson('/api/v1/spells?page=-1');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }
}

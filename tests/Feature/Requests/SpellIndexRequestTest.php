<?php

namespace Tests\Feature\Requests;

use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class SpellIndexRequestTest extends TestCase
{
    use RefreshDatabase;

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

        // Valid: slug
        $response = $this->getJson('/api/v1/spells?sort_by=slug');
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
    public function it_validates_search_query_max_length()
    {
        // Valid: 255 characters
        $search = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/spells?q={$search}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $search = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/spells?q={$search}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_validates_search_query_min_length()
    {
        // Valid: 2 characters
        $response = $this->getJson('/api/v1/spells?q=ab');
        $response->assertStatus(200);

        // Invalid: 1 character
        $response = $this->getJson('/api/v1/spells?q=a');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
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

    #[Test]
    public function it_validates_filter_max_length()
    {
        // Invalid: 1001 characters (exceeds max of 1000)
        $filter = str_repeat('a', 1001);
        $response = $this->getJson("/api/v1/spells?filter={$filter}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filter']);
    }
}

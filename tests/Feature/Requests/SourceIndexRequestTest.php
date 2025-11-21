<?php

namespace Tests\Feature\Requests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SourceIndexRequestTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_paginates_sources()
    {
        $response = $this->getJson('/api/v1/sources?per_page=5');
        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);
    }

    #[Test]
    public function it_searches_sources_by_name()
    {
        $response = $this->getJson('/api/v1/sources?q=Player');
        $response->assertOk();
        $response->assertJsonFragment(['name' => "Player's Handbook"]);
    }

    #[Test]
    public function it_searches_sources_by_code()
    {
        $response = $this->getJson('/api/v1/sources?q=PHB');
        $response->assertOk();
        $response->assertJsonFragment(['code' => 'PHB']);
    }

    #[Test]
    public function it_validates_per_page_limit()
    {
        $response = $this->getJson('/api/v1/sources?per_page=101');
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['per_page']);
    }

    #[Test]
    public function it_validates_page_is_positive_integer()
    {
        // Valid: 1
        $response = $this->getJson('/api/v1/sources?page=1');
        $response->assertStatus(200);

        // Invalid: 0
        $response = $this->getJson('/api/v1/sources?page=0');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);

        // Invalid: -1
        $response = $this->getJson('/api/v1/sources?page=-1');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['page']);
    }

    #[Test]
    public function it_validates_q_max_length()
    {
        // Valid: 255 characters
        $search = str_repeat('a', 255);
        $response = $this->getJson("/api/v1/sources?q={$search}");
        $response->assertStatus(200);

        // Invalid: 256 characters
        $search = str_repeat('a', 256);
        $response = $this->getJson("/api/v1/sources?q={$search}");
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }
}

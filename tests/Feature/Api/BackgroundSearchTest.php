<?php

namespace Tests\Feature\Api;

use App\Models\Background;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackgroundSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_backgrounds_using_scout_when_available(): void
    {
        Background::factory()->create(['name' => 'Acolyte']);
        Background::factory()->create(['name' => 'Soldier']);

        $this->artisan('scout:import', ['model' => Background::class]);
        sleep(1);

        $response = $this->getJson('/api/v1/backgrounds?q=acolyte');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['*' => ['id', 'name', 'slug']],
                'meta',
            ])
            ->assertJsonPath('data.0.name', 'Acolyte');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/backgrounds?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_search_query_gracefully(): void
    {
        Background::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/backgrounds');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(3, 'data');
    }
}

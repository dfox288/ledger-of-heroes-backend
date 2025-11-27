<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\WaitsForMeilisearch;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;
    use WaitsForMeilisearch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_across_all_entity_types(): void
    {
        Spell::factory()->create(['name' => 'Fireball']);
        Item::factory()->create(['name' => 'Flame Tongue']);

        $this->artisan('scout:import', ['model' => Spell::class]);
        $this->artisan('scout:import', ['model' => Item::class]);
        $this->waitForMeilisearchIndex('test_spells');
        $this->waitForMeilisearchIndex('test_items');

        $response = $this->getJson('/api/v1/search?q=fire');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'spells' => ['*' => ['id', 'name']],
                    'items' => ['*' => ['id', 'name']],
                ],
                'meta' => ['query', 'types_searched', 'total_results'],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_by_entity_types(): void
    {
        Spell::factory()->create(['name' => 'Fireball']);
        Item::factory()->create(['name' => 'Flame Tongue']);

        $this->artisan('scout:import', ['model' => Spell::class]);
        $this->artisan('scout:import', ['model' => Item::class]);
        $this->waitForMeilisearchIndex('test_spells');
        $this->waitForMeilisearchIndex('test_items');

        $response = $this->getJson('/api/v1/search?q=fire&types[]=spell');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['spells']])
            ->assertJsonPath('data.items', []);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_query_required(): void
    {
        $response = $this->getJson('/api/v1/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_supports_debug_mode(): void
    {
        $spell = Spell::factory()->create(['name' => 'Fireball']);
        $this->artisan('scout:import', ['model' => Spell::class]);
        $this->waitForMeilisearch($spell);

        $response = $this->getJson('/api/v1/search?q=fire&debug=1');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta',
                'debug' => ['query', 'execution_time_ms'],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/search?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }
}

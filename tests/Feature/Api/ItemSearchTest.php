<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\ItemType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('search:configure-indexes');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_items_using_scout(): void
    {
        $type = ItemType::factory()->create(['name' => 'Weapon']);

        Item::factory()->create(['name' => 'Longsword', 'item_type_id' => $type->id]);
        Item::factory()->create(['name' => 'Shortsword', 'item_type_id' => $type->id]);

        $this->artisan('scout:import', ['model' => Item::class]);
        sleep(1);

        $response = $this->getJson('/api/v1/items?q=long');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['id', 'name']], 'meta'])
            ->assertJsonPath('data.0.name', 'Longsword');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_search_query_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/items?q=a');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_empty_search_gracefully(): void
    {
        Item::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/items');

        $response->assertOk()->assertJsonStructure(['data', 'meta']);
    }
}

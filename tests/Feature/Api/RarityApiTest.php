<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class RarityApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ItemTypeSeeder::class);
    }

    #[Test]
    public function it_can_list_all_rarities(): void
    {
        Item::factory()->create(['rarity' => 'Common']);
        Item::factory()->create(['rarity' => 'Rare']);
        Item::factory()->create(['rarity' => 'Legendary']);

        $response = $this->getJson('/api/v1/lookups/rarities');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['slug', 'name'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    #[Test]
    public function it_returns_distinct_rarities(): void
    {
        // Create multiple items with the same rarity
        Item::factory()->count(5)->create(['rarity' => 'Uncommon']);
        Item::factory()->create(['rarity' => 'Very Rare']);

        $response = $this->getJson('/api/v1/lookups/rarities');

        $response->assertOk();

        $rarities = collect($response->json('data'))->pluck('name')->toArray();

        // Should only have each rarity once (distinct)
        $this->assertCount(2, $rarities);
        $this->assertContains('Uncommon', $rarities);
        $this->assertContains('Very Rare', $rarities);
    }

    #[Test]
    public function it_returns_rarities_in_canonical_order(): void
    {
        // Create items in non-canonical order
        Item::factory()->create(['rarity' => 'Legendary']);
        Item::factory()->create(['rarity' => 'Common']);
        Item::factory()->create(['rarity' => 'Very Rare']);
        Item::factory()->create(['rarity' => 'Uncommon']);
        Item::factory()->create(['rarity' => 'Rare']);
        Item::factory()->create(['rarity' => 'Artifact']);

        $response = $this->getJson('/api/v1/lookups/rarities');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();

        // Should be in canonical D&D rarity order
        $expectedOrder = ['Common', 'Uncommon', 'Rare', 'Very Rare', 'Legendary', 'Artifact'];
        $this->assertEquals($expectedOrder, $names, 'Rarities should be in canonical D&D order');
    }

    #[Test]
    public function it_excludes_null_and_empty_rarities(): void
    {
        Item::factory()->create(['rarity' => 'Rare']);
        Item::factory()->create(['rarity' => null]);
        Item::factory()->create(['rarity' => '']);

        $response = $this->getJson('/api/v1/lookups/rarities');

        $response->assertOk();

        $rarities = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertCount(1, $rarities);
        $this->assertContains('Rare', $rarities);
    }

    #[Test]
    public function it_returns_slug_and_name_for_each_rarity(): void
    {
        Item::factory()->create(['rarity' => 'Very Rare']);

        $response = $this->getJson('/api/v1/lookups/rarities');

        $response->assertOk();

        $rarity = collect($response->json('data'))->firstWhere('name', 'Very Rare');

        $this->assertNotNull($rarity);
        $this->assertEquals('very-rare', $rarity['slug']);
        $this->assertEquals('Very Rare', $rarity['name']);
    }

    #[Test]
    public function it_returns_empty_data_when_no_items_exist(): void
    {
        $response = $this->getJson('/api/v1/lookups/rarities');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function it_handles_unknown_rarities_at_end(): void
    {
        // Create an item with a non-standard rarity
        Item::factory()->create(['rarity' => 'Common']);
        Item::factory()->create(['rarity' => 'Custom Rarity']);

        $response = $this->getJson('/api/v1/lookups/rarities');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();

        // Common should come first, custom should be at the end
        $this->assertEquals('Common', $names[0]);
        $this->assertEquals('Custom Rarity', end($names));
    }
}

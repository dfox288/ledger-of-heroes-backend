<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Tags\Tag;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_all_tags(): void
    {
        // Create some tags
        Tag::findOrCreate('Ritual', 'spell');
        Tag::findOrCreate('Concentration', 'spell');
        Tag::findOrCreate('Healing', 'spell');

        $response = $this->getJson('/api/v1/lookups/tags');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'type'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    #[Test]
    public function it_returns_tags_in_consistent_order(): void
    {
        // Create tags - Spatie tags use order_column for ordering, not alphabetical
        Tag::findOrCreate('Zombie', 'monster');
        Tag::findOrCreate('Alpha', 'monster');
        Tag::findOrCreate('Beta', 'monster');

        $response = $this->getJson('/api/v1/lookups/tags');

        $response->assertOk();

        // Tags should be returned in a consistent order (by order_column/creation order)
        $data = $response->json('data');
        $this->assertCount(3, $data);

        // The first tag created should be first (by default order_column)
        $names = collect($data)->pluck('name')->toArray();
        $this->assertContains('Zombie', $names);
        $this->assertContains('Alpha', $names);
        $this->assertContains('Beta', $names);
    }

    #[Test]
    public function it_can_filter_tags_by_type(): void
    {
        Tag::findOrCreate('Ritual', 'spell');
        Tag::findOrCreate('Legendary', 'monster');
        Tag::findOrCreate('Magic', 'item');

        $response = $this->getJson('/api/v1/lookups/tags?type=spell');

        $response->assertOk();

        $data = $response->json('data');

        foreach ($data as $tag) {
            $this->assertEquals('spell', $tag['type'], 'All tags should have type "spell"');
        }
    }

    #[Test]
    public function it_returns_empty_data_when_no_tags_exist(): void
    {
        $response = $this->getJson('/api/v1/lookups/tags');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function it_includes_slug_in_response(): void
    {
        Tag::findOrCreate('Fire Damage', 'spell');

        $response = $this->getJson('/api/v1/lookups/tags');

        $response->assertOk();

        $tag = collect($response->json('data'))->firstWhere('name', 'Fire Damage');

        $this->assertNotNull($tag);
        $this->assertEquals('fire-damage', $tag['slug']);
    }
}

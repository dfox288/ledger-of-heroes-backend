<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ArmorTypeApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_list_all_armor_types(): void
    {
        Monster::factory()->create(['armor_type' => 'Natural armor']);
        Monster::factory()->create(['armor_type' => 'Plate armor']);
        Monster::factory()->create(['armor_type' => 'Chain mail']);

        $response = $this->getJson('/api/v1/lookups/armor-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['slug', 'name'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    #[Test]
    public function it_returns_distinct_armor_types(): void
    {
        // Create multiple monsters with the same armor type
        Monster::factory()->count(10)->create(['armor_type' => 'Natural armor']);
        Monster::factory()->create(['armor_type' => 'Leather armor']);

        $response = $this->getJson('/api/v1/lookups/armor-types');

        $response->assertOk();

        $types = collect($response->json('data'))->pluck('name')->toArray();

        // Should only have each type once (distinct)
        $this->assertCount(2, $types);
        $this->assertContains('Natural armor', $types);
        $this->assertContains('Leather armor', $types);
    }

    #[Test]
    public function it_returns_armor_types_ordered_alphabetically(): void
    {
        Monster::factory()->create(['armor_type' => 'Studded leather']);
        Monster::factory()->create(['armor_type' => 'Chain mail']);
        Monster::factory()->create(['armor_type' => 'Natural armor']);

        $response = $this->getJson('/api/v1/lookups/armor-types');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();

        $sorted = $names;
        sort($sorted);
        $this->assertEquals($sorted, $names, 'Armor types should be ordered alphabetically');
    }

    #[Test]
    public function it_excludes_null_and_empty_armor_types(): void
    {
        Monster::factory()->create(['armor_type' => 'Shield']);
        Monster::factory()->create(['armor_type' => null]);
        Monster::factory()->create(['armor_type' => '']);

        $response = $this->getJson('/api/v1/lookups/armor-types');

        $response->assertOk();

        $types = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertCount(1, $types);
        $this->assertContains('Shield', $types);
    }

    #[Test]
    public function it_returns_slug_and_name_for_each_armor_type(): void
    {
        Monster::factory()->create(['armor_type' => 'Scale mail']);

        $response = $this->getJson('/api/v1/lookups/armor-types');

        $response->assertOk();

        $type = collect($response->json('data'))->firstWhere('name', 'Scale mail');

        $this->assertNotNull($type);
        $this->assertEquals('scale-mail', $type['slug']);
        $this->assertEquals('Scale mail', $type['name']);
    }

    #[Test]
    public function it_returns_empty_data_when_no_monsters_exist(): void
    {
        $response = $this->getJson('/api/v1/lookups/armor-types');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }
}

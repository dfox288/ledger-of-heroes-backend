<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use App\Models\Race;
use App\Models\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class SizeReverseRelationshipsApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_races_for_size(): void
    {
        // Get Small size (ID: 2)
        $small = Size::where('code', 'S')->firstOrFail();

        // Create test races
        Race::factory()->count(3)->create([
            'size_id' => $small->id,
        ]);

        // Small races should exist from import
        $response = $this->getJson("/api/v1/lookups/sizes/{$small->id}/races");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        'size',
                        'sources',
                        'tags',
                    ],
                ],
                'meta',
            ]);

        // Verify we got Small races
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_when_size_has_no_races(): void
    {
        // Get Gargantuan size (ID: 6) - no playable races
        $gargantuan = Size::where('code', 'G')->firstOrFail();

        $response = $this->getJson("/api/v1/lookups/sizes/{$gargantuan->id}/races");

        $response->assertOk()
            ->assertJson([
                'data' => [],
            ]);

        $this->assertEquals(0, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_numeric_id_for_races_endpoint(): void
    {
        $small = Size::where('code', 'S')->firstOrFail();

        // Create test races
        Race::factory()->count(2)->create([
            'size_id' => $small->id,
        ]);

        // Test numeric ID routing (sizes only use numeric IDs)
        $response = $this->getJson('/api/v1/lookups/sizes/2/races');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_paginates_race_results(): void
    {
        // Get Medium size (ID: 3) - has many races
        $medium = Size::where('code', 'M')->firstOrFail();

        // Create test races
        Race::factory()->count(15)->create([
            'size_id' => $medium->id,
        ]);

        $response = $this->getJson("/api/v1/lookups/sizes/{$medium->id}/races?per_page=10");

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 10);

        // Should have races
        $this->assertGreaterThan(0, $response->json('meta.total'));

        // Verify alphabetical ordering
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sortedNames = collect($names)->sort()->values()->toArray();
        $this->assertEquals($sortedNames, $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_monsters_for_size(): void
    {
        // Get Large size (ID: 4)
        $large = Size::where('code', 'L')->firstOrFail();

        // Create test monsters
        Monster::factory()->count(5)->create([
            'size_id' => $large->id,
        ]);

        // Large monsters should exist from import
        $response = $this->getJson("/api/v1/lookups/sizes/{$large->id}/monsters");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'name',
                        'size',
                        'sources',
                    ],
                ],
                'meta',
            ]);

        // Verify we got Large monsters
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_when_size_has_no_monsters(): void
    {
        // Create a test size with no monsters (code max 2 chars)
        $testSize = Size::factory()->create(['code' => 'X', 'name' => 'Test Size']);

        $response = $this->getJson("/api/v1/lookups/sizes/{$testSize->id}/monsters");

        $response->assertOk()
            ->assertJson([
                'data' => [],
            ]);

        $this->assertEquals(0, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_accepts_numeric_id_for_monsters_endpoint(): void
    {
        $medium = Size::where('code', 'M')->firstOrFail();

        // Create test monsters
        Monster::factory()->count(3)->create([
            'size_id' => $medium->id,
        ]);

        // Test numeric ID routing for monsters (sizes only use numeric IDs)
        $response = $this->getJson('/api/v1/lookups/sizes/3/monsters');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_paginates_monster_results(): void
    {
        // Get Medium size (ID: 3) - has many monsters
        $medium = Size::where('code', 'M')->firstOrFail();

        // Create test monsters
        Monster::factory()->count(30)->create([
            'size_id' => $medium->id,
        ]);

        $response = $this->getJson("/api/v1/lookups/sizes/{$medium->id}/monsters?per_page=25");

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 25);

        // Should have monsters
        $this->assertGreaterThan(0, $response->json('meta.total'));

        // Verify alphabetical ordering
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sortedNames = collect($names)->sort()->values()->toArray();
        $this->assertEquals($sortedNames, $names);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use App\Models\Race;
use App\Models\Size;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Api\Concerns\ReverseRelationshipTestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class SizeReverseRelationshipsApiTest extends ReverseRelationshipTestCase
{
    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function it_returns_races_for_size(): void
    {
        $small = Size::where('code', 'S')->firstOrFail();

        Race::factory()->count(3)->create(['size_id' => $small->id]);

        $response = $this->assertAcceptsAlternativeIdentifier("/api/v1/lookups/sizes/{$small->id}/races", 3);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    #[Test]
    public function it_returns_empty_when_size_has_no_races(): void
    {
        $gargantuan = Size::where('code', 'G')->firstOrFail();

        $this->assertReturnsEmpty("/api/v1/lookups/sizes/{$gargantuan->id}/races");
    }

    #[Test]
    public function it_accepts_numeric_id_for_races_endpoint(): void
    {
        $small = Size::where('code', 'S')->firstOrFail();

        Race::factory()->count(2)->create(['size_id' => $small->id]);

        $this->assertAcceptsAlternativeIdentifier('/api/v1/lookups/sizes/2/races', 2);
    }

    #[Test]
    public function it_paginates_race_results(): void
    {
        $medium = Size::where('code', 'M')->firstOrFail();

        $this->createMultipleEntities(15, fn () => Race::factory()->create(['size_id' => $medium->id]));

        $response = $this->getJson("/api/v1/lookups/sizes/{$medium->id}/races?per_page=10");

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 10);

        $this->assertGreaterThan(0, $response->json('meta.total'));

        // Verify alphabetical ordering
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sortedNames = collect($names)->sort()->values()->toArray();
        $this->assertEquals($sortedNames, $names);
    }

    #[Test]
    public function it_returns_monsters_for_size(): void
    {
        $large = Size::where('code', 'L')->firstOrFail();

        Monster::factory()->count(5)->create(['size_id' => $large->id]);

        $response = $this->assertAcceptsAlternativeIdentifier("/api/v1/lookups/sizes/{$large->id}/monsters", 5);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    #[Test]
    public function it_returns_empty_when_size_has_no_monsters(): void
    {
        $testSize = Size::factory()->create(['code' => 'X', 'name' => 'Test Size']);

        $this->assertReturnsEmpty("/api/v1/lookups/sizes/{$testSize->id}/monsters");
    }

    #[Test]
    public function it_accepts_numeric_id_for_monsters_endpoint(): void
    {
        $medium = Size::where('code', 'M')->firstOrFail();

        Monster::factory()->count(3)->create(['size_id' => $medium->id]);

        $this->assertAcceptsAlternativeIdentifier('/api/v1/lookups/sizes/3/monsters', 3);
    }

    #[Test]
    public function it_paginates_monster_results(): void
    {
        $medium = Size::where('code', 'M')->firstOrFail();

        $this->createMultipleEntities(30, fn () => Monster::factory()->create(['size_id' => $medium->id]));

        $response = $this->getJson("/api/v1/lookups/sizes/{$medium->id}/monsters?per_page=25");

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 25);

        $this->assertGreaterThan(0, $response->json('meta.total'));

        // Verify alphabetical ordering
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sortedNames = collect($names)->sort()->values()->toArray();
        $this->assertEquals($sortedNames, $names);
    }
}

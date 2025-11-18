<?php

namespace Tests\Feature\Api;

use App\Models\Race;
use App\Models\Size;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaceApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_get_all_races()
    {
        // Create test races
        $size = Size::where('code', 'M')->first();
        $source = Source::where('code', 'PHB')->first();

        Race::create([
            'name' => 'Dragonborn',
            'size_id' => $size->id,
            'speed' => 30,
            'description' => 'Born of dragons',
            'source_id' => $source->id,
            'source_pages' => '32',
        ]);

        Race::create([
            'name' => 'Dwarf, Hill',
            'size_id' => $size->id,
            'speed' => 25,
            'description' => 'Bold and hardy',
            'source_id' => $source->id,
            'source_pages' => '19',
        ]);

        $response = $this->getJson('/api/v1/races');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'size' => ['id', 'code', 'name'],
                        'speed',
                        'description',
                        'source' => ['id', 'code', 'name'],
                        'source_pages',
                    ],
                ],
                'links',
                'meta',
            ]);
    }

    /** @test */
    public function can_search_races()
    {
        $size = Size::where('code', 'M')->first();
        $source = Source::where('code', 'PHB')->first();

        Race::create([
            'name' => 'Dragonborn',
            'size_id' => $size->id,
            'speed' => 30,
            'description' => 'Born of dragons',
            'source_id' => $source->id,
            'source_pages' => '32',
        ]);

        Race::create([
            'name' => 'Dwarf, Hill',
            'size_id' => $size->id,
            'speed' => 25,
            'description' => 'Bold and hardy',
            'source_id' => $source->id,
            'source_pages' => '19',
        ]);

        $response = $this->getJson('/api/v1/races?search=Dragon');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Dragonborn');
    }

    /** @test */
    public function can_get_single_race()
    {
        $size = Size::where('code', 'M')->first();
        $source = Source::where('code', 'PHB')->first();

        $race = Race::create([
            'name' => 'Dragonborn',
            'size_id' => $size->id,
            'speed' => 30,
            'description' => 'Born of dragons',
            'source_id' => $source->id,
            'source_pages' => '32',
        ]);

        $response = $this->getJson("/api/v1/races/{$race->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $race->id,
                    'name' => 'Dragonborn',
                    'speed' => 30,
                ],
            ]);
    }

    /** @test */
    public function it_includes_parent_race_in_response()
    {
        // Create base race and subrace
        $baseRace = Race::factory()->create([
            'name' => 'Dwarf',
            'parent_race_id' => null,
        ]);

        $subrace = Race::factory()->create([
            'name' => 'Hill',
            'parent_race_id' => $baseRace->id,
        ]);

        $response = $this->getJson("/api/v1/races/{$subrace->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'parent_race',
                'subraces',
            ]
        ]);

        $this->assertEquals('Dwarf', $response->json('data.parent_race.name'));
    }

    /** @test */
    public function it_includes_subraces_in_response()
    {
        $baseRace = Race::factory()->create([
            'name' => 'Elf',
            'parent_race_id' => null,
        ]);

        Race::factory()->create([
            'name' => 'High',
            'parent_race_id' => $baseRace->id,
        ]);

        Race::factory()->create([
            'name' => 'Wood',
            'parent_race_id' => $baseRace->id,
        ]);

        $response = $this->getJson("/api/v1/races/{$baseRace->id}");

        $response->assertStatus(200);
        $subraces = $response->json('data.subraces');
        $this->assertCount(2, $subraces);
    }
}

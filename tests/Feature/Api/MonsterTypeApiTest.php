<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class MonsterTypeApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    #[Test]
    public function it_can_list_all_monster_types(): void
    {
        Monster::factory()->create(['type' => 'Aberration']);
        Monster::factory()->create(['type' => 'Beast']);
        Monster::factory()->create(['type' => 'Dragon']);

        $response = $this->getJson('/api/v1/lookups/monster-types');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['slug', 'name'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    #[Test]
    public function it_returns_distinct_monster_types(): void
    {
        // Create multiple monsters with the same type
        Monster::factory()->count(3)->create(['type' => 'Undead']);
        Monster::factory()->create(['type' => 'Fiend']);

        $response = $this->getJson('/api/v1/lookups/monster-types');

        $response->assertOk();

        $types = collect($response->json('data'))->pluck('name')->toArray();

        // Should only have each type once (distinct)
        $this->assertCount(2, $types);
        $this->assertContains('Undead', $types);
        $this->assertContains('Fiend', $types);
    }

    #[Test]
    public function it_returns_monster_types_ordered_alphabetically(): void
    {
        Monster::factory()->create(['type' => 'Undead']);
        Monster::factory()->create(['type' => 'Aberration']);
        Monster::factory()->create(['type' => 'Giant']);

        $response = $this->getJson('/api/v1/lookups/monster-types');

        $response->assertOk();

        $names = collect($response->json('data'))->pluck('name')->toArray();

        $sorted = $names;
        sort($sorted);
        $this->assertEquals($sorted, $names, 'Monster types should be ordered alphabetically');
    }

    #[Test]
    public function it_excludes_empty_monster_types(): void
    {
        // Monster factory requires type (NOT NULL), so we test with valid type
        // and verify empty string filtering via DB::table insert
        Monster::factory()->create(['type' => 'Beast']);

        // Insert a monster with empty string type via raw query to bypass factory
        \DB::table('monsters')->insert([
            'name' => 'Empty Type Monster',
            'slug' => 'empty-type-monster',
            'sort_name' => 'empty-type-monster',
            'type' => '',
            'size_id' => 1,
            'armor_class' => 10,
            'hit_points_average' => 10,
            'hit_dice' => '2d8',
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
            'challenge_rating' => '0',
            'experience_points' => 10,
            'passive_perception' => 10,
        ]);

        $response = $this->getJson('/api/v1/lookups/monster-types');

        $response->assertOk();

        $types = collect($response->json('data'))->pluck('name')->toArray();

        $this->assertCount(1, $types);
        $this->assertContains('Beast', $types);
    }

    #[Test]
    public function it_returns_slug_and_name_for_each_type(): void
    {
        Monster::factory()->create(['type' => 'Humanoid']);

        $response = $this->getJson('/api/v1/lookups/monster-types');

        $response->assertOk();

        $type = collect($response->json('data'))->firstWhere('name', 'Humanoid');

        $this->assertNotNull($type);
        $this->assertEquals('humanoid', $type['slug']);
        $this->assertEquals('Humanoid', $type['name']);
    }

    #[Test]
    public function it_returns_empty_data_when_no_monsters_exist(): void
    {
        $response = $this->getJson('/api/v1/lookups/monster-types');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }
}

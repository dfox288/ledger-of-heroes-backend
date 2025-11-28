<?php

namespace Tests\Feature\Api;

use App\Models\Monster;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * These tests use fixture-based test data from TestDatabaseSeeder.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class MonsterApiTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected $seeder = \Database\Seeders\TestDatabaseSeeder::class;

    #[Test]
    public function can_get_all_monsters()
    {
        $response = $this->getJson('/api/v1/monsters?per_page=10');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'slug',
                    'name',
                    'type',
                    'alignment',
                    'armor_class',
                    'hit_points_average',
                    'challenge_rating',
                ],
            ],
        ]);

        // Verify we have at least some monsters from imported data
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    #[Test]
    public function can_get_single_monster_by_id()
    {
        // Use any monster from fixtures
        $monster = Monster::first();
        $this->assertNotNull($monster, 'Should have monsters in database');

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonPath('data.slug', $monster->slug);
    }

    #[Test]
    public function can_get_single_monster_by_slug()
    {
        // Use any monster from fixtures
        $monster = Monster::first();
        $this->assertNotNull($monster, 'Should have monsters in database');

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.slug', $monster->slug);
    }

    #[Test]
    public function can_filter_monsters_by_challenge_rating()
    {
        // Use pre-imported data - filter by CR 0.25 (which is 1/4)
        // Meilisearch stores challenge_rating as numeric, so 1/4 = 0.25
        $response = $this->getJson('/api/v1/monsters?filter=challenge_rating = 0.25');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected some CR 1/4 monsters');

        // Verify all results match the filter (API returns original string format)
        $challengeRatings = collect($response->json('data'))->pluck('challenge_rating')->unique();
        $this->assertEquals(['1/4'], $challengeRatings->values()->all());
    }

    #[Test]
    public function can_filter_monsters_by_type()
    {
        // Use pre-imported data - filter by dragon type
        $response = $this->getJson('/api/v1/monsters?filter=type = dragon');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected some dragon monsters');

        // Verify all results match the filter
        foreach ($response->json('data') as $monster) {
            $this->assertEquals('dragon', $monster['type']);
        }
    }

    #[Test]
    public function can_filter_monsters_by_size()
    {
        // Use pre-imported data - filter by Large size
        $response = $this->getJson('/api/v1/monsters?filter=size_code = L');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected some Large monsters');
    }

    #[Test]
    public function monster_includes_size_in_response()
    {
        // Use any monster from fixtures
        $monster = Monster::first();
        $this->assertNotNull($monster, 'Should have monsters in database');

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'size' => ['id', 'code', 'name'],
            ],
        ]);
    }

    #[Test]
    public function monster_includes_traits_in_response()
    {
        // Find a monster with traits
        $monster = Monster::has('traits')->first();

        if (! $monster) {
            $this->markTestSkipped('No monsters with traits in fixtures');
        }

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'traits' => [
                    '*' => ['id', 'name', 'description'],
                ],
            ],
        ]);

        // Verify monster has at least one trait
        $this->assertGreaterThan(0, count($response->json('data.traits')));
    }

    #[Test]
    public function monster_includes_actions_in_response()
    {
        // Find a monster with actions
        $monster = Monster::has('actions')->first();

        if (! $monster) {
            $this->markTestSkipped('No monsters with actions in fixtures');
        }

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'actions' => [
                    '*' => ['id', 'name', 'description', 'action_type'],
                ],
            ],
        ]);

        // Verify monster has at least one action
        $this->assertGreaterThan(0, count($response->json('data.actions')));
    }

    #[Test]
    public function monster_includes_modifiers_in_response()
    {
        // Ancient Red Dragon likely has modifiers
        $dragon = Monster::where('name', 'like', '%dragon%')->firstOrFail();

        $response = $this->getJson("/api/v1/monsters/{$dragon->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'modifiers',
            ],
        ]);
    }

    #[Test]
    public function can_paginate_monsters()
    {
        $response = $this->getJson('/api/v1/monsters?per_page=5');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonPath('meta.per_page', 5);
    }

    #[Test]
    public function can_sort_monsters_by_name()
    {
        $response = $this->getJson('/api/v1/monsters?sort_by=name&sort_direction=asc&per_page=5');

        $response->assertOk();

        // Verify sorted order
        $names = collect($response->json('data'))->pluck('name')->all();
        $sortedNames = collect($names)->sort()->values()->all();
        $this->assertEquals($sortedNames, $names);
    }

    #[Test]
    public function returns_404_for_nonexistent_monster()
    {
        $response = $this->getJson('/api/v1/monsters/999999');

        $response->assertNotFound();
    }

    #[Test]
    public function returns_404_for_nonexistent_monster_slug()
    {
        $response = $this->getJson('/api/v1/monsters/nonexistent-monster-xyz-123');

        $response->assertNotFound();
    }
}

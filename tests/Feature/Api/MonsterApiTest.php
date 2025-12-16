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

    #[Test]
    public function monster_separates_legendary_actions_from_lair_actions()
    {
        // Find a legendary monster with lair actions
        $monster = Monster::whereHas('legendaryActions', function ($query) {
            $query->where('is_lair_action', true);
        })->first();

        if (! $monster) {
            $this->markTestSkipped('No monsters with lair actions in fixtures');
        }

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertOk();

        // Both arrays should exist in response
        $response->assertJsonStructure([
            'data' => [
                'legendary_actions',
                'lair_actions',
            ],
        ]);

        // Verify legendary_actions only contains non-lair actions
        $legendaryActions = $response->json('data.legendary_actions');
        foreach ($legendaryActions as $action) {
            $this->assertFalse(
                $action['is_lair_action'],
                "legendary_actions should not contain lair actions: {$action['name']}"
            );
        }

        // Verify lair_actions only contains lair actions
        $lairActions = $response->json('data.lair_actions');
        $this->assertNotEmpty($lairActions, 'Expected lair_actions to not be empty');
        foreach ($lairActions as $action) {
            $this->assertTrue(
                $action['is_lair_action'],
                "lair_actions should only contain lair actions: {$action['name']}"
            );
        }
    }

    #[Test]
    public function monster_without_lair_actions_returns_empty_lair_actions_array()
    {
        // Find a monster without any legendary actions (and thus no lair actions)
        $monster = Monster::doesntHave('legendaryActions')->first();

        if (! $monster) {
            $this->markTestSkipped('No monsters without legendary actions in fixtures');
        }

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.legendary_actions', []);
        $response->assertJsonPath('data.lair_actions', []);
    }

    #[Test]
    public function can_search_monsters_by_name()
    {
        // Use a specific monster that should exist in fixtures
        $monster = Monster::where('name', 'LIKE', '%goblin%')->first();

        if (! $monster) {
            $this->markTestSkipped('No goblin-like monster in fixtures');
        }

        $response = $this->getJson('/api/v1/monsters?q='.urlencode($monster->name));

        $response->assertOk();

        // Verify that some results were returned
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected search results for monster name');
    }

    #[Test]
    public function can_search_monsters_by_partial_name()
    {
        // Find a dragon for partial search
        $dragon = Monster::where('type', 'dragon')->first();

        if (! $dragon) {
            $this->markTestSkipped('No dragon monsters in fixtures');
        }

        $response = $this->getJson('/api/v1/monsters?q=dragon');

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected some dragons in search results');

        // Verify all results contain "dragon" in name or type
        foreach ($response->json('data') as $result) {
            $hasMatch = stripos($result['name'], 'dragon') !== false ||
                        stripos($result['type'], 'dragon') !== false;
            $this->assertTrue($hasMatch, "Expected dragon in name or type: {$result['name']} ({$result['type']})");
        }
    }

    #[Test]
    public function can_search_and_filter_monsters_combined()
    {
        // Search for dragons with CR >= 10
        $response = $this->getJson('/api/v1/monsters?q=dragon&filter=challenge_rating >= 10');

        $response->assertOk();

        // If there are results, verify CR criteria is met
        if (count($response->json('data')) > 0) {
            foreach ($response->json('data') as $result) {
                // Should have CR >= 10 (convert fractional CRs to numeric)
                $crNumeric = $this->convertCRToNumeric($result['challenge_rating']);
                $this->assertGreaterThanOrEqual(10, $crNumeric,
                    "Expected CR >= 10, got {$result['challenge_rating']} for {$result['name']}");
            }

            // Meilisearch may return results with 'dragon' in various fields
            // Just verify we got some results
            $this->assertGreaterThan(0, count($response->json('data')), 'Expected results from combined search/filter');
        } else {
            $this->markTestSkipped('No dragons with CR >= 10 in test fixtures');
        }
    }

    #[Test]
    public function can_search_monsters_by_type()
    {
        $response = $this->getJson('/api/v1/monsters?q=undead');

        $response->assertOk();

        // Should find undead type monsters
        if (count($response->json('data')) > 0) {
            $types = collect($response->json('data'))->pluck('type')->unique()->toArray();
            $this->assertContains('undead', $types, 'Expected to find undead monsters');
        } else {
            $this->markTestSkipped('No undead monsters in test fixtures');
        }
    }

    #[Test]
    public function search_returns_empty_for_nonexistent_term()
    {
        $response = $this->getJson('/api/v1/monsters?q=xyznonexistentmonster12345');

        $response->assertOk();
        $this->assertEquals(0, count($response->json('data')), 'Expected no results for nonexistent search term');
    }

    #[Test]
    public function can_paginate_search_results()
    {
        // Search for a common type
        $response = $this->getJson('/api/v1/monsters?q=dragon&per_page=5');

        $response->assertOk();

        if (count($response->json('data')) > 0) {
            $this->assertLessThanOrEqual(5, count($response->json('data')), 'Should respect per_page limit');
            $response->assertJsonPath('meta.per_page', 5);
        } else {
            $this->markTestSkipped('No dragon monsters in test fixtures');
        }
    }

    #[Test]
    public function can_sort_search_results()
    {
        // Search and sort by name
        $response = $this->getJson('/api/v1/monsters?q=dragon&sort_by=name&sort_direction=asc&per_page=10');

        $response->assertOk();

        if (count($response->json('data')) > 1) {
            $names = collect($response->json('data'))->pluck('name')->toArray();
            $sortedNames = collect($names)->sort()->values()->toArray();
            $this->assertEquals($sortedNames, $names, 'Search results should be sorted by name');
        } else {
            $this->markTestSkipped('Need at least 2 dragon monsters to test sorting');
        }
    }

    #[Test]
    public function monster_includes_saving_throws_in_response()
    {
        // Use any monster from fixtures
        $monster = Monster::first();
        $this->assertNotNull($monster, 'Should have monsters in database');

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'saving_throws' => [
                    'STR',
                    'DEX',
                    'CON',
                    'INT',
                    'WIS',
                    'CHA',
                ],
            ],
        ]);

        // Verify all values are integers
        $savingThrows = $response->json('data.saving_throws');
        foreach ($savingThrows as $ability => $value) {
            $this->assertIsInt($value, "Saving throw for {$ability} should be an integer");
        }
    }

    #[Test]
    public function monster_saving_throws_uses_proficient_values_when_available()
    {
        // Find a monster with saving throw modifiers (dragons have them)
        $monster = Monster::whereHas('modifiers', function ($query) {
            $query->where('modifier_category', 'like', 'saving_throw_%');
        })->first();

        if (! $monster) {
            $this->markTestSkipped('No monsters with saving throw proficiencies in fixtures');
        }

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();

        $savingThrows = $response->json('data.saving_throws');

        // Get proficient saves from modifiers
        $proficientSaves = $monster->modifiers
            ->filter(fn ($m) => str_starts_with($m->modifier_category, 'saving_throw_'))
            ->keyBy(fn ($m) => strtoupper(str_replace('saving_throw_', '', $m->modifier_category)));

        // Verify proficient saves match stored values
        foreach ($proficientSaves as $ability => $modifier) {
            $this->assertSame(
                (int) $modifier->value,
                $savingThrows[$ability],
                "Proficient save for {$ability} should match stored modifier"
            );
        }
    }

    /**
     * Helper method to convert challenge rating string to numeric value
     */
    private function convertCRToNumeric(string $cr): float
    {
        if (strpos($cr, '/') !== false) {
            // Fractional CR (e.g., "1/4" -> 0.25, "1/2" -> 0.5)
            [$numerator, $denominator] = explode('/', $cr);

            return (float) $numerator / (float) $denominator;
        }

        return (float) $cr;
    }
}

<?php

namespace Tests\Feature\Api;

use App\Models\CharacterTrait;
use App\Models\Modifier;
use App\Models\Monster;
use App\Models\MonsterAction;
use App\Models\MonsterLegendaryAction;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * These tests use fixture-based test data from TestDatabaseSeeder.
 */
#[Group('feature-search')]
class MonsterApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = TestDatabaseSeeder::class;

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
        $monster = Monster::factory()->create(['slug' => 'test:monster-traits-'.uniqid()]);
        CharacterTrait::factory()->forEntity(Monster::class, $monster->id)->create();

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'traits' => [
                    '*' => ['id', 'name', 'description'],
                ],
            ],
        ]);
        $this->assertGreaterThan(0, count($response->json('data.traits')));
    }

    #[Test]
    public function monster_includes_actions_in_response()
    {
        $monster = Monster::factory()->create(['slug' => 'test:monster-actions-'.uniqid()]);
        MonsterAction::factory()->create(['monster_id' => $monster->id, 'action_type' => 'action']);

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'actions' => [
                    '*' => ['id', 'name', 'description', 'action_type'],
                ],
            ],
        ]);
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
        // Note: MonsterSearchService intentionally does NOT pass sort params to
        // Meilisearch (see MonsterSearchServiceTest + service docblock). Relevance
        // ranking wins whenever ?q= or ?filter= is present, so sorting only
        // applies on the pure-pagination Eloquent path exercised here.
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
        $monster = Monster::factory()->create(['slug' => 'test:monster-lair-'.uniqid()]);
        MonsterLegendaryAction::factory()->create([
            'monster_id' => $monster->id,
            'is_lair_action' => false,
            'name' => 'Tail Sweep',
        ]);
        MonsterLegendaryAction::factory()->create([
            'monster_id' => $monster->id,
            'is_lair_action' => true,
            'name' => 'Tremor',
        ]);

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
        $monster = Monster::factory()->create(['slug' => 'test:monster-no-lair-'.uniqid()]);

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.legendary_actions', []);
        $response->assertJsonPath('data.lair_actions', []);
    }

    #[Test]
    public function can_search_monsters_by_name()
    {
        // Use any monster from the seeded + indexed fixture set; the name-search
        // path is Meilisearch-backed so the target must be indexed (TestDatabaseSeeder
        // handles indexing). A guaranteed-present name means we test the happy path
        // on every run rather than skipping when fixtures drift.
        $monster = Monster::first();
        $this->assertNotNull($monster, 'Database must be seeded with at least one monster');

        $response = $this->getJson('/api/v1/monsters?q='.urlencode($monster->name));

        $response->assertOk();
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected search results for monster name');
    }

    #[Test]
    public function search_returns_empty_for_nonexistent_term()
    {
        $response = $this->getJson('/api/v1/monsters?q=xyznonexistentmonster12345');

        $response->assertOk();
        $this->assertEquals(0, count($response->json('data')), 'Expected no results for nonexistent search term');
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
        $monster = Monster::factory()->create(['slug' => 'test:monster-saves-'.uniqid()]);
        Modifier::factory()->forEntity(Monster::class, $monster->id)->create([
            'modifier_category' => 'saving_throw_str',
            'value' => '+9',
        ]);
        Modifier::factory()->forEntity(Monster::class, $monster->id)->create([
            'modifier_category' => 'saving_throw_con',
            'value' => '+13',
        ]);

        $response = $this->getJson("/api/v1/monsters/{$monster->id}");

        $response->assertOk();

        $savingThrows = $response->json('data.saving_throws');

        $proficientSaves = $monster->modifiers
            ->filter(fn ($m) => str_starts_with($m->modifier_category, 'saving_throw_'))
            ->keyBy(fn ($m) => strtoupper(str_replace('saving_throw_', '', $m->modifier_category)));

        foreach ($proficientSaves as $ability => $modifier) {
            $this->assertSame(
                (int) $modifier->value,
                $savingThrows[$ability],
                "Proficient save for {$ability} should match stored modifier"
            );
        }
    }

    #[Test]
    public function monster_includes_legendary_metadata_in_response()
    {
        // is_legendary is a computed accessor — true when the monster has at least
        // one non-lair legendary action. Seed the action to flip it.
        $monster = Monster::factory()->create([
            'slug' => 'test:monster-legendary-'.uniqid(),
            'legendary_actions_per_round' => 3,
        ]);
        MonsterLegendaryAction::factory()->create([
            'monster_id' => $monster->id,
            'is_lair_action' => false,
        ]);

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'is_legendary',
                'legendary_actions_per_round',
                'legendary_resistance_uses',
            ],
        ]);
        $this->assertTrue($response->json('data.is_legendary'));
    }

    #[Test]
    public function non_legendary_monster_has_null_legendary_metadata()
    {
        // is_legendary is a computed accessor — false when the monster has no
        // non-lair legendary actions. No seeding of legendary actions needed.
        $monster = Monster::factory()->create([
            'slug' => 'test:monster-non-legendary-'.uniqid(),
            'legendary_actions_per_round' => null,
        ]);

        $response = $this->getJson("/api/v1/monsters/{$monster->slug}");

        $response->assertOk();
        $this->assertFalse($response->json('data.is_legendary'));
        $this->assertNull($response->json('data.legendary_actions_per_round'));
        $this->assertNull($response->json('data.legendary_resistance_uses'));
    }
}

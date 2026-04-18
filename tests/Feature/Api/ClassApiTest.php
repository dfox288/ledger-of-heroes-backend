<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\ClassLevelProgression;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * These tests use fixture-based test data from TestDatabaseSeeder.
 */
#[Group('feature-search')]
class ClassApiTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = TestDatabaseSeeder::class;

    #[Test]
    public function it_returns_paginated_list_of_classes()
    {
        $response = $this->getJson('/api/v1/classes?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'hit_die',
                        'description',
                        'primary_ability',
                        'parent_class_id',
                        'is_base_class',
                        'sources' => [
                            '*' => ['code', 'name', 'pages'],
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);

        // Verify we have at least some classes from imported data
        $this->assertGreaterThan(0, count($response->json('data')));
    }

    #[Test]
    public function it_returns_a_single_class_by_id()
    {
        $fighter = CharacterClass::where('slug', 'fighter')->firstOrFail();

        $response = $this->getJson("/api/v1/classes/{$fighter->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $fighter->id,
                    'slug' => 'fighter',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'proficiencies',
                    'features',
                ],
            ]);
    }

    #[Test]
    public function it_returns_a_single_class_by_slug()
    {
        $response = $this->getJson('/api/v1/classes/fighter');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'slug' => 'fighter',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_non_existent_class()
    {
        $response = $this->getJson('/api/v1/classes/999999');

        $response->assertStatus(404);
    }

    #[Test]
    public function it_filters_base_classes_only()
    {
        $response = $this->getJson('/api/v1/classes?filter=is_subclass = false');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected some base classes');

        // Verify all results are base classes (parent_class_id is null)
        foreach ($response->json('data') as $class) {
            $this->assertNull($class['parent_class_id'], "Expected {$class['name']} to be a base class");
        }
    }

    #[Test]
    public function it_searches_classes_by_name()
    {
        $response = $this->getJson('/api/v1/classes?q=Fighter');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, count($response->json('data')), 'Expected to find Fighter');

        // Verify Fighter is in results
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Fighter', $names);

        // Test case-insensitive search
        $response = $this->getJson('/api/v1/classes?q=fighter');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertContains('Fighter', $names);
    }

    #[Test]
    public function it_includes_subclasses_in_class_response()
    {
        $fighter = CharacterClass::where('slug', 'fighter')->firstOrFail();

        $response = $this->getJson("/api/v1/classes/{$fighter->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'subclasses' => [
                        '*' => ['id', 'name', 'slug'],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_paginates_classes()
    {
        $response = $this->getJson('/api/v1/classes?per_page=5');

        $response->assertStatus(200)
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.per_page', 5);
    }

    #[Test]
    public function it_includes_class_features_in_response()
    {
        $class = CharacterClass::factory()->create();
        ClassFeature::factory()->forClass($class)->create();

        $response = $this->getJson("/api/v1/classes/{$class->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'features' => [
                        '*' => ['id', 'feature_name', 'description', 'level'],
                    ],
                ],
            ]);
        $this->assertGreaterThan(0, count($response->json('data.features')));
    }

    #[Test]
    public function it_includes_level_progression_in_response()
    {
        $class = CharacterClass::factory()->create();
        ClassLevelProgression::factory()->forClass($class)->atLevel(1)->create();

        $response = $this->getJson("/api/v1/classes/{$class->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'level_progression' => [
                        '*' => ['id', 'level'],
                    ],
                ],
            ]);
        $this->assertGreaterThan(0, count($response->json('data.level_progression')));
    }

    #[Test]
    public function class_resource_includes_all_fields()
    {
        $wizard = CharacterClass::where('slug', 'wizard')->firstOrFail();

        $response = $this->getJson("/api/v1/classes/{$wizard->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'hit_die',
                    'description',
                    'primary_ability',
                    'spellcasting_ability',
                    'sources',
                ],
            ]);

        // Wizard should have Intelligence as spellcasting ability
        $this->assertNotNull($response->json('data.spellcasting_ability'));
    }

    #[Test]
    public function it_exposes_fixed_proficiencies_in_api()
    {
        // Choice semantics (is_choice, quantity, choice_group) were moved out of
        // entity_proficiencies into entity_choices (migration
        // 2025_01_01_000021_drop_choice_columns_from_entity_tables). The
        // proficiencies array now represents only fixed grants.
        $fighter = CharacterClass::where('slug', 'fighter')->firstOrFail();

        $response = $this->getJson("/api/v1/classes/{$fighter->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'proficiencies' => [
                        '*' => ['id', 'proficiency_type', 'proficiency_name', 'grants'],
                    ],
                ],
            ]);

        // Verify Fighter has proficiencies
        $this->assertGreaterThan(0, count($response->json('data.proficiencies')));
    }

    #[Test]
    public function returns_404_for_nonexistent_class_slug()
    {
        $response = $this->getJson('/api/v1/classes/nonexistent-class-xyz-123');

        $response->assertNotFound();
    }

    #[Test]
    public function base_class_includes_subclass_level()
    {
        // subclass_level is computed from the lowest level at which a subclass
        // has a feature. Seed: base class + subclass + subclass feature at level 3.
        $baseClass = CharacterClass::factory()->create();
        $subclass = CharacterClass::factory()->subclass($baseClass)->create();
        ClassFeature::factory()->forClass($subclass)->atLevel(3)->create();

        $response = $this->getJson("/api/v1/classes/{$baseClass->slug}");

        $response->assertStatus(200);
        $this->assertSame(3, $response->json('data.subclass_level'));
    }

    #[Test]
    public function subclass_has_null_subclass_level()
    {
        // Subclasses should return null for subclass_level
        $response = $this->getJson('/api/v1/classes/fighter-champion');

        $response->assertStatus(200)
            ->assertJsonPath('data.subclass_level', null);
    }

    #[Test]
    public function base_class_includes_starting_wealth()
    {
        // Fighter should have starting wealth data (5d4 × 10 gp)
        $response = $this->getJson('/api/v1/classes/fighter');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'starting_wealth' => [
                        'dice',
                        'multiplier',
                        'average',
                        'formula',
                        'description',
                    ],
                ],
            ]);

        // Verify the values
        $startingWealth = $response->json('data.starting_wealth');
        $this->assertEquals('5d4', $startingWealth['dice']);
        $this->assertEquals(10, $startingWealth['multiplier']);
        $this->assertEquals(125, $startingWealth['average']);
        $this->assertStringContainsString('5d4', $startingWealth['formula']);
    }

    #[Test]
    public function subclass_has_null_starting_wealth()
    {
        // Subclasses should not have their own starting wealth
        $response = $this->getJson('/api/v1/classes/fighter-champion');

        $response->assertStatus(200)
            ->assertJsonPath('data.starting_wealth', null);
    }

    #[Test]
    public function monk_has_starting_wealth_without_multiplier()
    {
        // Monk has "5d4" without a multiplier (effectively ×1)
        $response = $this->getJson('/api/v1/classes/monk');

        $response->assertStatus(200);

        $startingWealth = $response->json('data.starting_wealth');
        $this->assertNotNull($startingWealth);
        $this->assertEquals('5d4', $startingWealth['dice']);
        $this->assertEquals(1, $startingWealth['multiplier']);
        $this->assertEquals(12, $startingWealth['average']); // 5 * 2.5 * 1 = 12.5, truncated
        $this->assertEquals('5d4 gp', $startingWealth['formula']);
    }
}

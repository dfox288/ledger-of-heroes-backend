<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * These tests use fixture-based test data from TestDatabaseSeeder.
 */
#[\PHPUnit\Framework\Attributes\Group('feature-search')]
class ClassApiTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected $seed = true;

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
        // Fighter should have features
        $fighter = CharacterClass::where('slug', 'fighter')->firstOrFail();

        $response = $this->getJson("/api/v1/classes/{$fighter->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'features' => [
                        '*' => ['id', 'feature_name', 'description', 'level'],
                    ],
                ],
            ]);

        // Verify Fighter has at least one feature
        $this->assertGreaterThan(0, count($response->json('data.features')));
    }

    #[Test]
    public function it_includes_level_progression_in_response()
    {
        // Wizard should have level progression with spell slots
        $wizard = CharacterClass::where('slug', 'wizard')->firstOrFail();

        $response = $this->getJson("/api/v1/classes/{$wizard->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'level_progression' => [
                        '*' => ['id', 'level'],
                    ],
                ],
            ]);

        // Verify Wizard has level progression data
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
    public function it_exposes_proficiency_choice_metadata_in_api()
    {
        $fighter = CharacterClass::where('slug', 'fighter')->firstOrFail();

        $response = $this->getJson("/api/v1/classes/{$fighter->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'proficiencies' => [
                        '*' => ['id', 'proficiency_type', 'proficiency_name', 'is_choice', 'quantity', 'grants'],
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
        // Fighter gets subclass at level 3 (Martial Archetype)
        $response = $this->getJson('/api/v1/classes/fighter');

        $response->assertStatus(200)
            ->assertJsonPath('data.subclass_level', 3);
    }

    #[Test]
    public function cleric_has_subclass_level_1()
    {
        // Cleric gets subclass at level 1 (Divine Domain)
        $response = $this->getJson('/api/v1/classes/cleric');

        $response->assertStatus(200)
            ->assertJsonPath('data.subclass_level', 1);
    }

    #[Test]
    public function wizard_has_subclass_level_2()
    {
        // Wizard gets subclass at level 2 (Arcane Tradition)
        $response = $this->getJson('/api/v1/classes/wizard');

        $response->assertStatus(200)
            ->assertJsonPath('data.subclass_level', 2);
    }

    #[Test]
    public function subclass_has_null_subclass_level()
    {
        // Subclasses should return null for subclass_level
        $response = $this->getJson('/api/v1/classes/fighter-champion');

        $response->assertStatus(200)
            ->assertJsonPath('data.subclass_level', null);
    }
}

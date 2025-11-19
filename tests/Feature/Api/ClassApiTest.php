<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use App\Models\ClassLevelProgression;
use App\Models\Proficiency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_class_resource_includes_all_fields()
    {
        $intAbility = $this->getAbilityScore('INT');
        $source = $this->getSource('PHB');

        $class = CharacterClass::factory()->spellcaster('INT')->create([
            'name' => 'Wizard',
            'hit_die' => 6,
            'description' => 'A scholarly magic-user',
            'primary_ability' => 'Intelligence',
        ]);

        $class->sources()->create([
            'reference_type' => CharacterClass::class,
            'reference_id' => $class->id,
            'source_id' => $source->id,
            'pages' => '110',
        ]);

        $resource = new \App\Http\Resources\ClassResource($class->load('spellcastingAbility', 'sources.source'));
        $data = $resource->toArray(request());

        $this->assertEquals('Wizard', $data['name']);
        $this->assertEquals(6, $data['hit_die']);
        $this->assertEquals('Intelligence', $data['primary_ability']);
        $this->assertArrayHasKey('spellcasting_ability', $data);
        $this->assertEquals('INT', $data['spellcasting_ability']['code']);
        $this->assertArrayHasKey('sources', $data);
    }

    #[Test]
    public function it_returns_paginated_list_of_classes()
    {
        $source = $this->getSource('PHB');

        // Create test classes
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        $fighter->sources()->create([
            'source_id' => $source->id,
            'pages' => '70',
        ]);

        $wizard = CharacterClass::factory()->spellcaster('INT')->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'hit_die' => 6,
        ]);

        $wizard->sources()->create([
            'source_id' => $source->id,
            'pages' => '112',
        ]);

        $response = $this->getJson('/api/v1/classes');

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
            ])
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_returns_a_single_class_by_id()
    {
        $source = $this->getSource('PHB');

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
            'description' => 'A master of martial combat',
        ]);

        $fighter->sources()->create([
            'source_id' => $source->id,
            'pages' => '70',
        ]);

        // Add proficiencies
        Proficiency::factory()->forEntity(CharacterClass::class, $fighter->id)->create([
            'proficiency_type' => 'armor',
            'proficiency_name' => 'Heavy Armor',
        ]);

        // Add features
        ClassFeature::factory()->create([
            'class_id' => $fighter->id,
            'feature_name' => 'Fighting Style',
            'description' => 'You adopt a particular style of fighting',
            'level' => 1,
        ]);

        $response = $this->getJson("/api/v1/classes/{$fighter->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $fighter->id,
                    'name' => 'Fighter',
                    'slug' => 'fighter',
                    'hit_die' => 10,
                    'description' => 'A master of martial combat',
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'proficiencies' => [
                        '*' => ['id', 'proficiency_type', 'proficiency_name'],
                    ],
                    'features' => [
                        '*' => ['id', 'feature_name', 'description', 'level'],
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_returns_a_single_class_by_slug()
    {
        $source = $this->getSource('PHB');

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
            'description' => 'A master of martial combat',
        ]);

        $fighter->sources()->create([
            'source_id' => $source->id,
            'pages' => '70',
        ]);

        $response = $this->getJson('/api/v1/classes/fighter');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'Fighter',
                    'slug' => 'fighter',
                    'hit_die' => 10,
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
        $source = $this->getSource('PHB');

        // Create base class
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'parent_class_id' => null,
        ]);

        $fighter->sources()->create([
            'source_id' => $source->id,
            'pages' => '70',
        ]);

        // Create subclass
        $battleMaster = CharacterClass::factory()->create([
            'name' => 'Battle Master',
            'slug' => 'fighter-battle-master',
            'parent_class_id' => $fighter->id,
        ]);

        $battleMaster->sources()->create([
            'source_id' => $source->id,
            'pages' => '73',
        ]);

        $response = $this->getJson('/api/v1/classes?base_only=true');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fighter');

        // Verify Battle Master is not in results
        $classNames = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertNotContains('Battle Master', $classNames);
    }

    #[Test]
    public function it_searches_classes_by_name()
    {
        $source = $this->getSource('PHB');

        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        $fighter->sources()->create([
            'source_id' => $source->id,
            'pages' => '70',
        ]);

        $wizard = CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
        ]);

        $wizard->sources()->create([
            'source_id' => $source->id,
            'pages' => '112',
        ]);

        $response = $this->getJson('/api/v1/classes?search=Fighter');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fighter');

        // Test case-insensitive search
        $response = $this->getJson('/api/v1/classes?search=fighter');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fighter');
    }

    #[Test]
    public function it_includes_subclasses_in_class_response()
    {
        $source = $this->getSource('PHB');

        // Create base class
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'parent_class_id' => null,
        ]);

        $fighter->sources()->create([
            'source_id' => $source->id,
            'pages' => '70',
        ]);

        // Create subclasses
        $battleMaster = CharacterClass::factory()->create([
            'name' => 'Battle Master',
            'slug' => 'fighter-battle-master',
            'parent_class_id' => $fighter->id,
        ]);

        $battleMaster->sources()->create([
            'source_id' => $source->id,
            'pages' => '73',
        ]);

        $champion = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'fighter-champion',
            'parent_class_id' => $fighter->id,
        ]);

        $champion->sources()->create([
            'source_id' => $source->id,
            'pages' => '72',
        ]);

        $response = $this->getJson('/api/v1/classes/fighter');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'subclasses' => [
                        '*' => ['id', 'name', 'slug'],
                    ],
                ],
            ]);

        $subclasses = $response->json('data.subclasses');
        $this->assertCount(2, $subclasses);

        $subclassNames = collect($subclasses)->pluck('name')->toArray();
        $this->assertContains('Battle Master', $subclassNames);
        $this->assertContains('Champion', $subclassNames);

        // Verify slugs
        $subclassSlugs = collect($subclasses)->pluck('slug')->toArray();
        $this->assertContains('fighter-battle-master', $subclassSlugs);
        $this->assertContains('fighter-champion', $subclassSlugs);
    }

    #[Test]
    public function it_paginates_classes()
    {
        $source = $this->getSource('PHB');

        // Create 5 classes
        for ($i = 1; $i <= 5; $i++) {
            $class = CharacterClass::factory()->create([
                'name' => "Class {$i}",
                'slug' => "class-{$i}",
            ]);

            $class->sources()->create([
                'source_id' => $source->id,
                'pages' => (string) $i,
            ]);
        }

        $response = $this->getJson('/api/v1/classes?per_page=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5);
    }

    #[Test]
    public function it_includes_class_features_in_response()
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'feature_name' => 'Second Wind',
            'description' => 'You have a limited well of stamina',
            'level' => 1,
        ]);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'feature_name' => 'Action Surge',
            'description' => 'You can push yourself beyond your limits',
            'level' => 2,
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'features' => [
                        '*' => ['id', 'feature_name', 'description', 'level'],
                    ],
                ],
            ]);

        $features = $response->json('data.features');
        $this->assertCount(2, $features);
    }

    #[Test]
    public function it_includes_class_counters_in_response()
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Monk',
            'slug' => 'monk',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Ki Points',
            'level' => 2,
            'counter_value' => 2,
            'reset_timing' => 'S',
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'counters' => [
                        '*' => ['id', 'counter_name', 'level', 'counter_value', 'reset_timing'],
                    ],
                ],
            ]);

        $counters = $response->json('data.counters');
        $this->assertCount(1, $counters);
        $this->assertEquals('Ki Points', $counters[0]['counter_name']);
    }

    #[Test]
    public function it_includes_level_progression_in_response()
    {
        $class = CharacterClass::factory()->spellcaster('INT')->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
        ]);

        ClassLevelProgression::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'cantrips_known' => 3,
            'spell_slots_1st' => 2,
        ]);

        ClassLevelProgression::factory()->create([
            'class_id' => $class->id,
            'level' => 2,
            'cantrips_known' => 3,
            'spell_slots_1st' => 3,
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'level_progression' => [
                        '*' => ['id', 'level', 'cantrips_known', 'spell_slots_1st'],
                    ],
                ],
            ]);

        $progression = $response->json('data.level_progression');
        $this->assertCount(2, $progression);
    }
}

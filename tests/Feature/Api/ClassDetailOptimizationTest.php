<?php

namespace Tests\Feature\Api;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('feature-db')]
class ClassDetailOptimizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function show_endpoint_returns_computed_hit_points_for_base_class(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}");

        $response->assertOk()
            ->assertJsonPath('data.computed.hit_points.hit_die', 'd10')
            ->assertJsonPath('data.computed.hit_points.hit_die_numeric', 10)
            ->assertJsonPath('data.computed.hit_points.first_level.value', 10)
            ->assertJsonPath('data.computed.hit_points.higher_levels.average', 6);
    }

    #[Test]
    public function show_endpoint_returns_computed_hit_points_for_subclass_with_inherited_hit_die(): void
    {
        $parent = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);
        $subclass = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'champion',
            'parent_class_id' => $parent->id,
            'hit_die' => 10, // Subclasses inherit parent's hit die
        ]);

        $response = $this->getJson("/api/v1/classes/{$subclass->slug}");

        $response->assertOk()
            ->assertJsonPath('data.computed.hit_points.hit_die', 'd10')
            ->assertJsonPath('data.computed.hit_points.hit_die_numeric', 10)
            ->assertJsonPath('data.computed.hit_points.first_level.value', 10)
            ->assertJsonPath('data.computed.hit_points.higher_levels.average', 6);
    }

    #[Test]
    public function show_endpoint_returns_inherited_data_for_subclass(): void
    {
        $parent = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        ClassCounter::factory()->create([
            'class_id' => $parent->id,
            'counter_name' => 'Action Surge',
            'level' => 2,
            'counter_value' => 1,
        ]);

        $subclass = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'champion',
            'parent_class_id' => $parent->id,
            'hit_die' => 10, // Subclasses inherit parent's hit die
        ]);

        $response = $this->getJson("/api/v1/classes/{$subclass->slug}");

        $response->assertOk()
            ->assertJsonPath('data.inherited_data.hit_die', 10)
            ->assertJsonStructure([
                'data' => [
                    'inherited_data' => [
                        'hit_die',
                        'hit_points',
                    ],
                ],
            ]);
    }

    #[Test]
    public function show_endpoint_does_not_return_inherited_data_for_base_class(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}");

        $response->assertOk();

        $data = $response->json('data');
        $this->assertArrayNotHasKey('inherited_data', $data);
    }

    #[Test]
    public function show_endpoint_returns_computed_section_counts(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        ClassFeature::factory()->count(5)->create(['class_id' => $class->id]);
        ClassCounter::factory()->count(2)->create(['class_id' => $class->id]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}");

        $response->assertOk()
            ->assertJsonPath('data.computed.section_counts.features', 5)
            ->assertJsonPath('data.computed.section_counts.counters', 2);
    }

    #[Test]
    public function show_endpoint_returns_computed_progression_table(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Sneak Attack',
        ]);

        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Sneak Attack',
            'level' => 1,
            'counter_value' => 1,
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'computed' => [
                        'progression_table' => [
                            'columns',
                            'rows',
                        ],
                    ],
                ],
            ]);

        $table = $response->json('data.computed.progression_table');
        $this->assertCount(20, $table['rows']);
        $this->assertEquals(1, $table['rows'][0]['level']);
        $this->assertEquals('+2', $table['rows'][0]['proficiency_bonus']);
    }

    #[Test]
    public function progression_endpoint_returns_table(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}/progression");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'columns',
                    'rows',
                ],
            ])
            ->assertJsonCount(20, 'data.rows');
    }

    #[Test]
    public function progression_endpoint_uses_parent_for_subclass(): void
    {
        $parent = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $parent->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
        ]);

        $subclass = CharacterClass::factory()->create([
            'name' => 'Champion',
            'slug' => 'champion',
            'parent_class_id' => $parent->id,
        ]);

        $response = $this->getJson("/api/v1/classes/{$subclass->slug}/progression");

        $response->assertOk();

        $features = $response->json('data.rows.0.features');
        $this->assertStringContainsString('Fighting Style', $features);
    }

    #[Test]
    public function progression_table_includes_counter_columns(): void
    {
        $class = CharacterClass::factory()->create([
            'name' => 'Rogue',
            'slug' => 'rogue',
        ]);

        // Create sparse counter data (Sneak Attack at levels 1, 3, 5)
        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Sneak Attack',
            'level' => 1,
            'counter_value' => 1,
        ]);
        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Sneak Attack',
            'level' => 3,
            'counter_value' => 2,
        ]);

        $response = $this->getJson("/api/v1/classes/{$class->slug}/progression");

        $response->assertOk();

        $columns = $response->json('data.columns');
        $columnKeys = array_column($columns, 'key');
        $this->assertContains('sneak_attack', $columnKeys);

        // Check interpolation: level 2 should have 1d6 (from level 1)
        $rows = $response->json('data.rows');
        $this->assertEquals('1d6', $rows[1]['sneak_attack']); // Level 2
        $this->assertEquals('2d6', $rows[2]['sneak_attack']); // Level 3
    }

    #[Test]
    public function proficiency_bonus_calculation_is_correct(): void
    {
        $class = CharacterClass::factory()->create(['slug' => 'fighter']);

        $response = $this->getJson("/api/v1/classes/{$class->slug}/progression");

        $response->assertOk();

        $rows = $response->json('data.rows');

        // D&D 5e proficiency bonus: +2 at levels 1-4, +3 at 5-8, +4 at 9-12, +5 at 13-16, +6 at 17-20
        $this->assertEquals('+2', $rows[0]['proficiency_bonus']);  // Level 1
        $this->assertEquals('+2', $rows[3]['proficiency_bonus']);  // Level 4
        $this->assertEquals('+3', $rows[4]['proficiency_bonus']);  // Level 5
        $this->assertEquals('+3', $rows[7]['proficiency_bonus']);  // Level 8
        $this->assertEquals('+4', $rows[8]['proficiency_bonus']);  // Level 9
        $this->assertEquals('+6', $rows[19]['proficiency_bonus']); // Level 20
    }

    #[Test]
    public function index_endpoint_does_not_include_computed_object(): void
    {
        CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'hit_die' => 10,
        ]);

        $response = $this->getJson('/api/v1/classes');

        $response->assertOk();

        $firstClass = $response->json('data.0');
        $this->assertArrayNotHasKey('computed', $firstClass);
    }
}

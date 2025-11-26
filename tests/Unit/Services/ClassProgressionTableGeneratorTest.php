<?php

namespace Tests\Unit\Services;

use App\Models\CharacterClass;
use App\Models\ClassCounter;
use App\Models\ClassFeature;
use App\Models\ClassLevelProgression;
use App\Services\ClassProgressionTableGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ClassProgressionTableGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private ClassProgressionTableGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new ClassProgressionTableGenerator;
    }

    #[Test]
    public function it_generates_basic_progression_table(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
        ]);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
        ]);

        $result = $this->generator->generate($class);

        $this->assertArrayHasKey('columns', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertCount(20, $result['rows']);

        // Check level 1 row
        $row1 = $result['rows'][0];
        $this->assertEquals(1, $row1['level']);
        $this->assertEquals('+2', $row1['proficiency_bonus']);
        $this->assertStringContainsString('Fighting Style', $row1['features']);
        $this->assertStringContainsString('Second Wind', $row1['features']);
    }

    #[Test]
    public function it_includes_counter_columns_with_interpolation(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Rogue']);

        // Sneak Attack: 1d6 at 1, 2d6 at 3
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

        $result = $this->generator->generate($class);

        // Check that counter column exists
        $counterColumns = array_filter($result['columns'], fn ($c) => $c['key'] === 'sneak_attack');
        $this->assertNotEmpty($counterColumns);

        // Check interpolation: level 2 should have 1d6 (from level 1)
        $row2 = $result['rows'][1];
        $this->assertEquals('1d6', $row2['sneak_attack']);

        // Level 3 should have 2d6
        $row3 = $result['rows'][2];
        $this->assertEquals('2d6', $row3['sneak_attack']);

        // Level 4 should still have 2d6 (interpolated from level 3)
        $row4 = $result['rows'][3];
        $this->assertEquals('2d6', $row4['sneak_attack']);
    }

    #[Test]
    public function it_uses_parent_class_progression_for_subclasses(): void
    {
        $parent = CharacterClass::factory()->create(['name' => 'Fighter', 'hit_die' => 10]);
        $subclass = CharacterClass::factory()->create([
            'name' => 'Champion',
            'parent_class_id' => $parent->id,
            'hit_die' => 10, // Subclasses inherit hit die from parent
        ]);

        ClassFeature::factory()->create([
            'class_id' => $parent->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
        ]);

        $result = $this->generator->generate($subclass);

        // Should use parent's features
        $row1 = $result['rows'][0];
        $this->assertStringContainsString('Fighting Style', $row1['features']);
    }

    #[Test]
    public function it_handles_subclass_with_parent_having_no_data(): void
    {
        // Create parent without any progression data
        $parent = CharacterClass::factory()->create([
            'name' => 'Empty Parent',
            'hit_die' => 10,
        ]);

        $subclass = CharacterClass::factory()->create([
            'name' => 'Subclass',
            'parent_class_id' => $parent->id,
            'hit_die' => 10,
        ]);

        $result = $this->generator->generate($subclass);

        // Should use parent (which has no features/counters/progression)
        // So we get basic columns with em dashes
        $this->assertArrayHasKey('columns', $result);
        $this->assertArrayHasKey('rows', $result);
        $this->assertCount(20, $result['rows']);

        // All features should be em dash since parent has no features
        $row1 = $result['rows'][0];
        $this->assertEquals('—', $row1['features']);
    }

    #[Test]
    public function it_shows_em_dash_for_levels_without_features(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Simple']);

        // Only add feature at level 5
        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 5,
            'feature_name' => 'Extra Attack',
        ]);

        $result = $this->generator->generate($class);

        // Levels 1-4 should show em dash
        $this->assertEquals('—', $result['rows'][0]['features']);
        $this->assertEquals('—', $result['rows'][3]['features']);

        // Level 5 should show the feature
        $this->assertEquals('Extra Attack', $result['rows'][4]['features']);
    }

    #[Test]
    public function it_shows_em_dash_for_counter_before_first_value(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Monk']);

        // Ki points start at level 2
        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Ki Points',
            'level' => 2,
            'counter_value' => 2,
        ]);

        $result = $this->generator->generate($class);

        // Level 1 should show em dash (no Ki yet)
        $row1 = $result['rows'][0];
        $this->assertEquals('—', $row1['ki_points']);

        // Level 2 should show 2
        $row2 = $result['rows'][1];
        $this->assertEquals('2', $row2['ki_points']);
    }

    #[Test]
    public function it_calculates_proficiency_bonus_correctly(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Test']);

        $result = $this->generator->generate($class);

        // D&D 5e proficiency bonus formula
        $this->assertEquals('+2', $result['rows'][0]['proficiency_bonus']);  // Level 1
        $this->assertEquals('+2', $result['rows'][3]['proficiency_bonus']);  // Level 4
        $this->assertEquals('+3', $result['rows'][4]['proficiency_bonus']);  // Level 5
        $this->assertEquals('+3', $result['rows'][7]['proficiency_bonus']);  // Level 8
        $this->assertEquals('+4', $result['rows'][8]['proficiency_bonus']);  // Level 9
        $this->assertEquals('+4', $result['rows'][11]['proficiency_bonus']); // Level 12
        $this->assertEquals('+5', $result['rows'][12]['proficiency_bonus']); // Level 13
        $this->assertEquals('+5', $result['rows'][15]['proficiency_bonus']); // Level 16
        $this->assertEquals('+6', $result['rows'][16]['proficiency_bonus']); // Level 17
        $this->assertEquals('+6', $result['rows'][19]['proficiency_bonus']); // Level 20
    }

    #[Test]
    public function it_always_includes_base_columns(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Empty']);

        $result = $this->generator->generate($class);

        $columnKeys = array_column($result['columns'], 'key');

        $this->assertContains('level', $columnKeys);
        $this->assertContains('proficiency_bonus', $columnKeys);
        $this->assertContains('features', $columnKeys);
    }

    #[Test]
    public function it_formats_martial_arts_die_correctly(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Monk']);

        // Martial Arts: 1d4 at level 1, 1d6 at level 5
        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Martial Arts',
            'level' => 1,
            'counter_value' => 4,
        ]);
        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Martial Arts',
            'level' => 5,
            'counter_value' => 6,
        ]);

        $result = $this->generator->generate($class);

        // Check formatting: should be "1d4", "1d6", etc.
        $row1 = $result['rows'][0];
        $this->assertEquals('1d4', $row1['martial_arts']);

        $row5 = $result['rows'][4];
        $this->assertEquals('1d6', $row5['martial_arts']);
    }

    #[Test]
    public function it_includes_cantrips_known_column_when_applicable(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);

        // Add spell progression with cantrips
        ClassLevelProgression::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'cantrips_known' => 3,
        ]);

        $result = $this->generator->generate($class);

        $columnKeys = array_column($result['columns'], 'key');
        $this->assertContains('cantrips_known', $columnKeys);

        // Check the value in the row
        $row1 = $result['rows'][0];
        $this->assertEquals(3, $row1['cantrips_known']);
    }

    #[Test]
    public function it_includes_spell_slot_columns_when_applicable(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);

        // Add spell progression with 1st and 2nd level slots
        ClassLevelProgression::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'spell_slots_1st' => 2,
            'spell_slots_2nd' => 0,
        ]);
        ClassLevelProgression::factory()->create([
            'class_id' => $class->id,
            'level' => 3,
            'spell_slots_1st' => 4,
            'spell_slots_2nd' => 2,
        ]);

        $result = $this->generator->generate($class);

        $columnKeys = array_column($result['columns'], 'key');
        $this->assertContains('spell_slots_1st', $columnKeys);
        $this->assertContains('spell_slots_2nd', $columnKeys);

        // Check the values
        $row1 = $result['rows'][0];
        $this->assertEquals(2, $row1['spell_slots_1st']);
        $this->assertEquals(0, $row1['spell_slots_2nd']);

        $row3 = $result['rows'][2];
        $this->assertEquals(4, $row3['spell_slots_1st']);
        $this->assertEquals(2, $row3['spell_slots_2nd']);
    }

    #[Test]
    public function it_handles_multiple_features_at_same_level(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);

        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
        ]);
        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
        ]);
        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Another Feature',
        ]);

        $result = $this->generator->generate($class);

        $row1 = $result['rows'][0];
        // All features should be comma-separated
        $this->assertStringContainsString('Fighting Style', $row1['features']);
        $this->assertStringContainsString('Second Wind', $row1['features']);
        $this->assertStringContainsString('Another Feature', $row1['features']);
        $this->assertStringContainsString(',', $row1['features']);
    }

    #[Test]
    public function it_handles_counter_type_detection_correctly(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Test']);

        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Rage Uses',
            'level' => 1,
            'counter_value' => 2,
        ]);

        $result = $this->generator->generate($class);

        // Check that counter column has correct type
        $counterColumn = collect($result['columns'])->firstWhere('key', 'rage_uses');
        $this->assertNotNull($counterColumn);
        $this->assertEquals('integer', $counterColumn['type']);

        // Check formatting (should be plain number, not dice)
        $row1 = $result['rows'][0];
        $this->assertEquals('2', $row1['rage_uses']);
    }

    #[Test]
    public function it_slugifies_counter_names_correctly(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Test']);

        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Bardic Inspiration',
            'level' => 1,
            'counter_value' => 3,
        ]);

        $result = $this->generator->generate($class);

        $columnKeys = array_column($result['columns'], 'key');
        $this->assertContains('bardic_inspiration', $columnKeys);

        $row1 = $result['rows'][0];
        $this->assertEquals('3', $row1['bardic_inspiration']);
    }
}

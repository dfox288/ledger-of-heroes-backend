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

    #[Test]
    public function it_excludes_multiclass_only_features_from_progression(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Wizard']);

        // Regular features - should be included
        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Spellcasting',
            'is_multiclass_only' => false,
        ]);

        // Multiclass features - should be excluded
        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Multiclass Wizard',
            'is_multiclass_only' => true,
        ]);
        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Multiclass Features',
            'is_multiclass_only' => true,
        ]);

        $result = $this->generator->generate($class);

        $row1 = $result['rows'][0];

        // Spellcasting should be included
        $this->assertStringContainsString('Spellcasting', $row1['features']);

        // Multiclass features should NOT be included
        $this->assertStringNotContainsString('Multiclass Wizard', $row1['features']);
        $this->assertStringNotContainsString('Multiclass Features', $row1['features']);
    }

    #[Test]
    public function it_excludes_choice_options_from_progression(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'Fighter']);

        // Parent feature - should be included
        $fightingStyle = ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Fighting Style',
            'is_optional' => false,
        ]);

        // Choice options - should be excluded
        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Fighting Style: Archery',
            'is_optional' => true,
            'parent_feature_id' => $fightingStyle->id,
        ]);
        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Fighting Style: Defense',
            'is_optional' => true,
            'parent_feature_id' => $fightingStyle->id,
        ]);

        // Regular feature - should be included
        ClassFeature::factory()->create([
            'class_id' => $class->id,
            'level' => 1,
            'feature_name' => 'Second Wind',
            'is_optional' => false,
        ]);

        $result = $this->generator->generate($class);

        $row1 = $result['rows'][0];

        // Parent feature should be included
        $this->assertStringContainsString('Fighting Style', $row1['features']);
        $this->assertStringContainsString('Second Wind', $row1['features']);

        // Choice options should NOT be included
        $this->assertStringNotContainsString('Archery', $row1['features']);
        $this->assertStringNotContainsString('Defense', $row1['features']);
    }

    #[Test]
    public function it_excludes_redundant_counters_from_columns(): void
    {
        $class = CharacterClass::factory()->create(['name' => 'TestClass']);

        // Counters that should be EXCLUDED (formula-based or in Features column)
        $excludedCounters = [
            'Arcane Recovery',      // Wizard - formula-based
            'Action Surge',         // Fighter - in Features column
            'Indomitable',          // Fighter - in Features column
            'Second Wind',          // Fighter - in Features column
            'Lay on Hands',         // Paladin - formula-based
            'Channel Divinity',     // Paladin/Cleric - in Features column
        ];

        foreach ($excludedCounters as $counterName) {
            ClassCounter::factory()->create([
                'class_id' => $class->id,
                'counter_name' => $counterName,
                'level' => 1,
                'counter_value' => 1,
            ]);
        }

        // Counter that should be INCLUDED
        ClassCounter::factory()->create([
            'class_id' => $class->id,
            'counter_name' => 'Ki Points',
            'level' => 2,
            'counter_value' => 2,
        ]);

        $result = $this->generator->generate($class);

        $columnKeys = array_column($result['columns'], 'key');

        // All excluded counters should NOT be in columns
        $this->assertNotContains('arcane_recovery', $columnKeys);
        $this->assertNotContains('action_surge', $columnKeys);
        $this->assertNotContains('indomitable', $columnKeys);
        $this->assertNotContains('second_wind', $columnKeys);
        $this->assertNotContains('lay_on_hands', $columnKeys);
        $this->assertNotContains('channel_divinity', $columnKeys);

        // Ki Points should still be included
        $this->assertContains('ki_points', $columnKeys);
    }

    #[Test]
    public function it_excludes_wholeness_of_body_from_columns(): void
    {
        $monk = CharacterClass::factory()->create(['name' => 'Monk']);

        // Wholeness of Body counter - should be EXCLUDED (one-time feature, not progression)
        ClassCounter::factory()->create([
            'class_id' => $monk->id,
            'counter_name' => 'Wholeness of Body',
            'level' => 6,
            'counter_value' => 1,
        ]);

        // Ki Points - should be INCLUDED
        ClassCounter::factory()->create([
            'class_id' => $monk->id,
            'counter_name' => 'Ki',
            'level' => 2,
            'counter_value' => 2,
        ]);

        $result = $this->generator->generate($monk);

        $columnKeys = array_column($result['columns'], 'key');

        $this->assertContains('ki', $columnKeys);
        $this->assertNotContains('wholeness_of_body', $columnKeys);
    }

    #[Test]
    public function it_excludes_stroke_of_luck_from_columns(): void
    {
        $rogue = CharacterClass::factory()->create(['name' => 'Rogue']);

        // Stroke of Luck counter - should be EXCLUDED (capstone feature, not progression)
        ClassCounter::factory()->create([
            'class_id' => $rogue->id,
            'counter_name' => 'Stroke of Luck',
            'level' => 20,
            'counter_value' => 1,
        ]);

        // Sneak Attack - should be INCLUDED
        ClassCounter::factory()->create([
            'class_id' => $rogue->id,
            'counter_name' => 'Sneak Attack',
            'level' => 1,
            'counter_value' => 1,
        ]);

        $result = $this->generator->generate($rogue);

        $columnKeys = array_column($result['columns'], 'key');

        $this->assertContains('sneak_attack', $columnKeys);
        $this->assertNotContains('stroke_of_luck', $columnKeys);
    }

    #[Test]
    public function it_includes_columns_from_feature_data_tables(): void
    {
        $rogue = CharacterClass::factory()->create(['name' => 'Rogue']);

        $feature = ClassFeature::factory()->create([
            'class_id' => $rogue->id,
            'feature_name' => 'Sneak Attack',
            'level' => 1,
        ]);

        // Create an EntityDataTable with progression type
        $table = \App\Models\EntityDataTable::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'table_name' => 'Extra Damage',
            'dice_type' => 'd6',
            'table_type' => \App\Enums\DataTableType::PROGRESSION,
        ]);

        // Create entries with level values
        \App\Models\EntityDataTableEntry::create([
            'entity_data_table_id' => $table->id,
            'roll_min' => 1, 'roll_max' => 1,
            'result_text' => '1d6',
            'level' => 1,
            'sort_order' => 0,
        ]);
        \App\Models\EntityDataTableEntry::create([
            'entity_data_table_id' => $table->id,
            'roll_min' => 3, 'roll_max' => 3,
            'result_text' => '2d6',
            'level' => 3,
            'sort_order' => 1,
        ]);
        \App\Models\EntityDataTableEntry::create([
            'entity_data_table_id' => $table->id,
            'roll_min' => 5, 'roll_max' => 5,
            'result_text' => '3d6',
            'level' => 5,
            'sort_order' => 2,
        ]);

        $result = $this->generator->generate($rogue);

        // Check that a sneak_attack column exists
        $columnKeys = array_column($result['columns'], 'key');
        $this->assertContains('sneak_attack', $columnKeys);

        // Check row values - level 1 should have 1d6
        $row1 = $result['rows'][0];
        $this->assertEquals('1d6', $row1['sneak_attack']);

        // Level 2 should interpolate from level 1 (1d6)
        $row2 = $result['rows'][1];
        $this->assertEquals('1d6', $row2['sneak_attack']);

        // Level 3 should have 2d6
        $row3 = $result['rows'][2];
        $this->assertEquals('2d6', $row3['sneak_attack']);

        // Level 4 should interpolate from level 3 (2d6)
        $row4 = $result['rows'][3];
        $this->assertEquals('2d6', $row4['sneak_attack']);

        // Level 5 should have 3d6
        $row5 = $result['rows'][4];
        $this->assertEquals('3d6', $row5['sneak_attack']);
    }

    #[Test]
    public function it_uses_feature_name_for_data_table_column_key(): void
    {
        $monk = CharacterClass::factory()->create(['name' => 'Monk']);

        $feature = ClassFeature::factory()->create([
            'class_id' => $monk->id,
            'feature_name' => 'Martial Arts',
            'level' => 1,
        ]);

        // Create progression table
        $table = \App\Models\EntityDataTable::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'table_name' => 'Martial Arts',
            'dice_type' => 'd4',
            'table_type' => \App\Enums\DataTableType::PROGRESSION,
        ]);

        \App\Models\EntityDataTableEntry::create([
            'entity_data_table_id' => $table->id,
            'roll_min' => 1, 'roll_max' => 1,
            'result_text' => '1d4',
            'level' => 1,
            'sort_order' => 0,
        ]);

        $result = $this->generator->generate($monk);

        // Column key should be based on feature name (martial_arts)
        $columnKeys = array_column($result['columns'], 'key');
        $this->assertContains('martial_arts', $columnKeys);

        // Check the column has correct label
        $column = collect($result['columns'])->firstWhere('key', 'martial_arts');
        $this->assertEquals('Martial Arts', $column['label']);
    }

    #[Test]
    public function it_includes_synthetic_rage_damage_for_barbarian(): void
    {
        // Create barbarian class
        $barbarian = CharacterClass::factory()->create(['name' => 'Barbarian', 'slug' => 'barbarian']);

        $result = $this->generator->generate($barbarian);

        // Should have rage_damage column
        $columnKeys = array_column($result['columns'], 'key');
        $this->assertContains('rage_damage', $columnKeys);

        // Check the column has correct label
        $column = collect($result['columns'])->firstWhere('key', 'rage_damage');
        $this->assertEquals('Rage Damage', $column['label']);
        $this->assertEquals('bonus', $column['type']);

        // Check row values follow PHB progression: +2 (L1), +3 (L9), +4 (L16)
        $this->assertEquals('+2', $result['rows'][0]['rage_damage']);   // L1
        $this->assertEquals('+2', $result['rows'][7]['rage_damage']);   // L8 (still +2)
        $this->assertEquals('+3', $result['rows'][8]['rage_damage']);   // L9
        $this->assertEquals('+3', $result['rows'][14]['rage_damage']);  // L15 (still +3)
        $this->assertEquals('+4', $result['rows'][15]['rage_damage']);  // L16
        $this->assertEquals('+4', $result['rows'][19]['rage_damage']);  // L20
    }

    #[Test]
    public function it_does_not_add_synthetic_columns_to_non_barbarian(): void
    {
        // Rogue should not have rage_damage
        $rogue = CharacterClass::factory()->create(['name' => 'Rogue', 'slug' => 'rogue']);

        $result = $this->generator->generate($rogue);

        $columnKeys = array_column($result['columns'], 'key');
        $this->assertNotContains('rage_damage', $columnKeys);
    }

    #[Test]
    public function it_includes_synthetic_sneak_attack_for_rogue(): void
    {
        // Create rogue class
        $rogue = CharacterClass::factory()->create(['name' => 'Rogue', 'slug' => 'rogue']);

        $result = $this->generator->generate($rogue);

        // Should have sneak_attack column
        $columnKeys = array_column($result['columns'], 'key');
        $this->assertContains('sneak_attack', $columnKeys);

        // Check the column has correct label
        $column = collect($result['columns'])->firstWhere('key', 'sneak_attack');
        $this->assertEquals('Sneak Attack', $column['label']);
        $this->assertEquals('dice', $column['type']);

        // Check row values follow PHB p.96 progression: ceil(level / 2) d6
        // L1: 1d6, L3: 2d6, L5: 3d6, L7: 4d6, L9: 5d6, L11: 6d6, L13: 7d6, L15: 8d6, L17: 9d6, L19: 10d6
        $this->assertEquals('1d6', $result['rows'][0]['sneak_attack']);   // L1
        $this->assertEquals('1d6', $result['rows'][1]['sneak_attack']);   // L2 (still 1d6)
        $this->assertEquals('2d6', $result['rows'][2]['sneak_attack']);   // L3
        $this->assertEquals('2d6', $result['rows'][3]['sneak_attack']);   // L4 (still 2d6)
        $this->assertEquals('3d6', $result['rows'][4]['sneak_attack']);   // L5
        $this->assertEquals('4d6', $result['rows'][6]['sneak_attack']);   // L7
        $this->assertEquals('5d6', $result['rows'][8]['sneak_attack']);   // L9
        $this->assertEquals('5d6', $result['rows'][9]['sneak_attack']);   // L10 (still 5d6)
        $this->assertEquals('6d6', $result['rows'][10]['sneak_attack']);  // L11
        $this->assertEquals('7d6', $result['rows'][12]['sneak_attack']);  // L13
        $this->assertEquals('8d6', $result['rows'][14]['sneak_attack']);  // L15
        $this->assertEquals('9d6', $result['rows'][16]['sneak_attack']);  // L17
        $this->assertEquals('10d6', $result['rows'][18]['sneak_attack']); // L19
        $this->assertEquals('10d6', $result['rows'][19]['sneak_attack']); // L20 (still 10d6)
    }

    #[Test]
    public function it_synthetic_sneak_attack_overrides_bad_data_table(): void
    {
        // Create rogue with incorrect data table (like the current broken state)
        $rogue = CharacterClass::factory()->create(['name' => 'Rogue', 'slug' => 'rogue']);

        $feature = ClassFeature::factory()->create([
            'class_id' => $rogue->id,
            'feature_name' => 'Sneak Attack',
            'level' => 1,
        ]);

        // Create an EntityDataTable with WRONG level values (the bug we're fixing)
        $table = \App\Models\EntityDataTable::create([
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'table_name' => 'Extra Damage',
            'dice_type' => 'd6',
            'table_type' => \App\Enums\DataTableType::DAMAGE,
        ]);

        // Wrong data: levels 1-9 instead of 1,3,5,7,9,11,13,15,17,19
        for ($i = 1; $i <= 9; $i++) {
            \App\Models\EntityDataTableEntry::create([
                'entity_data_table_id' => $table->id,
                'roll_min' => $i,
                'roll_max' => $i,
                'result_text' => "{$i}d6",
                'level' => $i,
                'sort_order' => $i - 1,
            ]);
        }

        $result = $this->generator->generate($rogue);

        // Synthetic progression should override the bad data table
        // L10 should be 5d6 (not 9d6 from bad data), L11 should be 6d6
        $this->assertEquals('5d6', $result['rows'][9]['sneak_attack']);   // L10
        $this->assertEquals('6d6', $result['rows'][10]['sneak_attack']);  // L11
        $this->assertEquals('10d6', $result['rows'][19]['sneak_attack']); // L20
    }
}

<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassRelatedTablesTest extends TestCase
{
    use RefreshDatabase;

    // Class Level Progression Tests
    public function test_class_level_progression_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('class_level_progression'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'id'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'class_id'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'level'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'cantrips_known'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'spell_slots_1st'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'spell_slots_2nd'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'spell_slots_3rd'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'spell_slots_4th'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'spell_slots_5th'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'spell_slots_6th'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'spell_slots_7th'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'spell_slots_8th'));
        $this->assertTrue(Schema::hasColumn('class_level_progression', 'spell_slots_9th'));
    }

    public function test_class_level_progression_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('class_level_progression', 'created_at'));
        $this->assertFalse(Schema::hasColumn('class_level_progression', 'updated_at'));
    }

    public function test_class_level_progression_can_track_wizard_spell_slots(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $intelligence = DB::table('ability_scores')->where('code', 'INT')->first();

        // Create Wizard class
        DB::table('classes')->insert([
            'name' => 'Wizard',
            'parent_class_id' => null,
            'hit_die' => 6,
            'description' => 'A scholarly magic-user...',
            'primary_ability' => 'Intelligence',
            'spellcasting_ability_id' => $intelligence->id,
            'source_id' => $phb->id,
            'source_pages' => '112-116',
        ]);

        $wizard = DB::table('classes')->where('name', 'Wizard')->first();

        // Add level 1 progression
        DB::table('class_level_progression')->insert([
            'class_id' => $wizard->id,
            'level' => 1,
            'cantrips_known' => 3,
            'spell_slots_1st' => 2,
        ]);

        // Add level 5 progression
        DB::table('class_level_progression')->insert([
            'class_id' => $wizard->id,
            'level' => 5,
            'cantrips_known' => 4,
            'spell_slots_1st' => 4,
            'spell_slots_2nd' => 3,
            'spell_slots_3rd' => 2,
        ]);

        $level1 = DB::table('class_level_progression')
            ->where('class_id', $wizard->id)
            ->where('level', 1)
            ->first();

        $this->assertEquals(3, $level1->cantrips_known);
        $this->assertEquals(2, $level1->spell_slots_1st);

        $level5 = DB::table('class_level_progression')
            ->where('class_id', $wizard->id)
            ->where('level', 5)
            ->first();

        $this->assertEquals(4, $level5->cantrips_known);
        $this->assertEquals(2, $level5->spell_slots_3rd);
    }

    // Class Features Tests
    public function test_class_features_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('class_features'));
        $this->assertTrue(Schema::hasColumn('class_features', 'id'));
        $this->assertTrue(Schema::hasColumn('class_features', 'class_id'));
        $this->assertTrue(Schema::hasColumn('class_features', 'level'));
        $this->assertTrue(Schema::hasColumn('class_features', 'feature_name'));
        $this->assertTrue(Schema::hasColumn('class_features', 'is_optional'));
        $this->assertTrue(Schema::hasColumn('class_features', 'description'));
        $this->assertTrue(Schema::hasColumn('class_features', 'sort_order'));
    }

    public function test_class_features_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('class_features', 'created_at'));
        $this->assertFalse(Schema::hasColumn('class_features', 'updated_at'));
    }

    public function test_class_features_can_track_rogue_features(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $dex = DB::table('ability_scores')->where('code', 'DEX')->first();

        // Create Rogue class
        DB::table('classes')->insert([
            'name' => 'Rogue',
            'parent_class_id' => null,
            'hit_die' => 8,
            'description' => 'Rogues rely on skill, stealth...',
            'primary_ability' => 'Dexterity',
            'spellcasting_ability_id' => null,
            'source_id' => $phb->id,
            'source_pages' => '94-98',
        ]);

        $rogue = DB::table('classes')->where('name', 'Rogue')->first();

        // Add features at level 1
        DB::table('class_features')->insert([
            [
                'class_id' => $rogue->id,
                'level' => 1,
                'feature_name' => 'Expertise',
                'is_optional' => false,
                'description' => 'You choose two of your skill proficiencies...',
                'sort_order' => 1,
            ],
            [
                'class_id' => $rogue->id,
                'level' => 1,
                'feature_name' => 'Sneak Attack',
                'is_optional' => false,
                'description' => 'You know how to strike subtly...',
                'sort_order' => 2,
            ],
            [
                'class_id' => $rogue->id,
                'level' => 1,
                'feature_name' => 'Thieves\' Cant',
                'is_optional' => false,
                'description' => 'During your rogue training...',
                'sort_order' => 3,
            ],
        ]);

        $level1Features = DB::table('class_features')
            ->where('class_id', $rogue->id)
            ->where('level', 1)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(3, $level1Features);
        $this->assertEquals('Expertise', $level1Features[0]->feature_name);
        $this->assertEquals('Sneak Attack', $level1Features[1]->feature_name);
    }

    public function test_class_features_can_track_optional_features(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        // Create Fighter class
        DB::table('classes')->insert([
            'name' => 'Fighter',
            'parent_class_id' => null,
            'hit_die' => 10,
            'description' => 'A master of martial combat...',
            'primary_ability' => 'Strength or Dexterity',
            'spellcasting_ability_id' => null,
            'source_id' => $phb->id,
            'source_pages' => '70-75',
        ]);

        $fighter = DB::table('classes')->where('name', 'Fighter')->first();

        // Add required and optional features
        DB::table('class_features')->insert([
            [
                'class_id' => $fighter->id,
                'level' => 1,
                'feature_name' => 'Fighting Style',
                'is_optional' => false,
                'description' => 'You adopt a particular style...',
                'sort_order' => 1,
            ],
            [
                'class_id' => $fighter->id,
                'level' => 1,
                'feature_name' => 'Multiclass Fighter',
                'is_optional' => true, // Multiclass rules are optional
                'description' => 'When multiclassing into Fighter...',
                'sort_order' => 99,
            ],
        ]);

        $optionalFeature = DB::table('class_features')
            ->where('class_id', $fighter->id)
            ->where('is_optional', true)
            ->first();

        $this->assertEquals('Multiclass Fighter', $optionalFeature->feature_name);
        $this->assertTrue((bool) $optionalFeature->is_optional);
    }

    // Class Counters Tests
    public function test_class_counters_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('class_counters'));
        $this->assertTrue(Schema::hasColumn('class_counters', 'id'));
        $this->assertTrue(Schema::hasColumn('class_counters', 'class_id'));
        $this->assertTrue(Schema::hasColumn('class_counters', 'level'));
        $this->assertTrue(Schema::hasColumn('class_counters', 'counter_name'));
        $this->assertTrue(Schema::hasColumn('class_counters', 'counter_value'));
        $this->assertTrue(Schema::hasColumn('class_counters', 'reset_timing'));
    }

    public function test_class_counters_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('class_counters', 'created_at'));
        $this->assertFalse(Schema::hasColumn('class_counters', 'updated_at'));
    }

    public function test_class_counters_can_track_monk_ki_points(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $wisdom = DB::table('ability_scores')->where('code', 'WIS')->first();

        // Create Monk class
        DB::table('classes')->insert([
            'name' => 'Monk',
            'parent_class_id' => null,
            'hit_die' => 8,
            'description' => 'A master of martial arts...',
            'primary_ability' => 'Dexterity and Wisdom',
            'spellcasting_ability_id' => null,
            'source_id' => $phb->id,
            'source_pages' => '76-80',
        ]);

        $monk = DB::table('classes')->where('name', 'Monk')->first();

        // Add Ki point progression
        DB::table('class_counters')->insert([
            [
                'class_id' => $monk->id,
                'level' => 2,
                'counter_name' => 'Ki Points',
                'counter_value' => 2,
                'reset_timing' => 'S', // Short rest
            ],
            [
                'class_id' => $monk->id,
                'level' => 5,
                'counter_name' => 'Ki Points',
                'counter_value' => 5,
                'reset_timing' => 'S',
            ],
        ]);

        $level2Ki = DB::table('class_counters')
            ->where('class_id', $monk->id)
            ->where('level', 2)
            ->where('counter_name', 'Ki Points')
            ->first();

        $this->assertEquals(2, $level2Ki->counter_value);
        $this->assertEquals('S', $level2Ki->reset_timing);
    }

    public function test_class_counters_can_track_barbarian_rage(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        // Create Barbarian class
        DB::table('classes')->insert([
            'name' => 'Barbarian',
            'parent_class_id' => null,
            'hit_die' => 12,
            'description' => 'A fierce warrior of primitive background...',
            'primary_ability' => 'Strength',
            'spellcasting_ability_id' => null,
            'source_id' => $phb->id,
            'source_pages' => '46-50',
        ]);

        $barbarian = DB::table('classes')->where('name', 'Barbarian')->first();

        // Add Rage progression
        DB::table('class_counters')->insert([
            [
                'class_id' => $barbarian->id,
                'level' => 1,
                'counter_name' => 'Rage Uses',
                'counter_value' => 2,
                'reset_timing' => 'L', // Long rest
            ],
            [
                'class_id' => $barbarian->id,
                'level' => 3,
                'counter_name' => 'Rage Uses',
                'counter_value' => 3,
                'reset_timing' => 'L',
            ],
        ]);

        $rages = DB::table('class_counters')
            ->where('class_id', $barbarian->id)
            ->where('counter_name', 'Rage Uses')
            ->orderBy('level')
            ->get();

        $this->assertCount(2, $rages);
        $this->assertEquals(2, $rages[0]->counter_value);
        $this->assertEquals(3, $rages[1]->counter_value);
        $this->assertEquals('L', $rages[0]->reset_timing);
    }
}

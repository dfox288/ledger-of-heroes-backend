<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PolymorphicTablesTest extends TestCase
{
    use RefreshDatabase;

    // Ability Score Bonuses Tests
    public function test_ability_score_bonuses_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('ability_score_bonuses'));
        $this->assertTrue(Schema::hasColumn('ability_score_bonuses', 'ability_score_id'));
        $this->assertTrue(Schema::hasColumn('ability_score_bonuses', 'bonus'));
        $this->assertTrue(Schema::hasColumn('ability_score_bonuses', 'race_id'));
        $this->assertTrue(Schema::hasColumn('ability_score_bonuses', 'class_id'));
        $this->assertTrue(Schema::hasColumn('ability_score_bonuses', 'background_id'));
        $this->assertTrue(Schema::hasColumn('ability_score_bonuses', 'feat_id'));
    }

    public function test_ability_score_bonuses_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('ability_score_bonuses', 'created_at'));
        $this->assertFalse(Schema::hasColumn('ability_score_bonuses', 'updated_at'));
    }

    public function test_ability_score_bonuses_table_does_not_have_id_column(): void
    {
        // Composite PK - no surrogate ID
        $columns = Schema::getColumnListing('ability_score_bonuses');
        $this->assertNotContains('id', $columns);
    }

    public function test_ability_score_bonuses_can_be_associated_with_race(): void
    {
        $strength = DB::table('ability_scores')->where('code', 'STR')->first();
        $medium = DB::table('sizes')->where('code', 'M')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        // Create a race
        DB::table('races')->insert([
            'name' => 'Half-Orc',
            'size_id' => $medium->id,
            'speed' => 30,
            'description' => 'Half-orcs inherit a tendency toward chaos...',
            'source_id' => $phb->id,
            'source_pages' => '40-41',
        ]);

        $race = DB::table('races')->where('name', 'Half-Orc')->first();

        // Add STR +2 bonus for Half-Orc
        DB::table('ability_score_bonuses')->insert([
            'ability_score_id' => $strength->id,
            'bonus' => 2,
            'race_id' => $race->id,
            'class_id' => 0,
            'background_id' => 0,
            'feat_id' => 0,
        ]);

        $bonus = DB::table('ability_score_bonuses')
            ->where('race_id', $race->id)
            ->where('ability_score_id', $strength->id)
            ->first();

        $this->assertEquals(2, $bonus->bonus);
        $this->assertEquals($race->id, $bonus->race_id);
        $this->assertEquals(0, $bonus->class_id);
    }

    public function test_ability_score_bonuses_can_be_associated_with_feat(): void
    {
        $strength = DB::table('ability_scores')->where('code', 'STR')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        // Create a feat
        DB::table('feats')->insert([
            'name' => 'Athlete',
            'prerequisites' => null,
            'description' => 'You have undergone extensive physical training...',
            'source_id' => $phb->id,
            'source_pages' => '165',
        ]);

        $feat = DB::table('feats')->where('name', 'Athlete')->first();

        // Add STR +1 bonus for Athlete feat
        DB::table('ability_score_bonuses')->insert([
            'ability_score_id' => $strength->id,
            'bonus' => 1,
            'race_id' => 0,
            'class_id' => 0,
            'background_id' => 0,
            'feat_id' => $feat->id,
        ]);

        $bonus = DB::table('ability_score_bonuses')
            ->where('feat_id', $feat->id)
            ->first();

        $this->assertEquals(1, $bonus->bonus);
        $this->assertEquals($feat->id, $bonus->feat_id);
        $this->assertEquals(0, $bonus->race_id);
    }

    // Skill Proficiencies Tests
    public function test_skill_proficiencies_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('skill_proficiencies'));
        $this->assertTrue(Schema::hasColumn('skill_proficiencies', 'skill_id'));
        $this->assertTrue(Schema::hasColumn('skill_proficiencies', 'race_id'));
        $this->assertTrue(Schema::hasColumn('skill_proficiencies', 'class_id'));
        $this->assertTrue(Schema::hasColumn('skill_proficiencies', 'background_id'));
        $this->assertTrue(Schema::hasColumn('skill_proficiencies', 'feat_id'));
    }

    public function test_skill_proficiencies_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('skill_proficiencies', 'created_at'));
        $this->assertFalse(Schema::hasColumn('skill_proficiencies', 'updated_at'));
    }

    public function test_skill_proficiencies_table_does_not_have_id_column(): void
    {
        $columns = Schema::getColumnListing('skill_proficiencies');
        $this->assertNotContains('id', $columns);
    }

    public function test_skill_proficiencies_can_be_associated_with_background(): void
    {
        $insight = DB::table('skills')->where('name', 'Insight')->first();
        $religion = DB::table('skills')->where('name', 'Religion')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        // Create background
        DB::table('backgrounds')->insert([
            'name' => 'Acolyte',
            'description' => 'You have spent your life in service...',
            'skill_proficiencies' => json_encode(['Insight', 'Religion']),
            'source_id' => $phb->id,
            'source_pages' => '127',
        ]);

        $background = DB::table('backgrounds')->where('name', 'Acolyte')->first();

        // Add skill proficiencies
        DB::table('skill_proficiencies')->insert([
            'skill_id' => $insight->id,
            'race_id' => 0,
            'class_id' => 0,
            'background_id' => $background->id,
            'feat_id' => 0,
        ]);

        DB::table('skill_proficiencies')->insert([
            'skill_id' => $religion->id,
            'race_id' => 0,
            'class_id' => 0,
            'background_id' => $background->id,
            'feat_id' => 0,
        ]);

        $proficiencies = DB::table('skill_proficiencies')
            ->where('background_id', $background->id)
            ->get();

        $this->assertCount(2, $proficiencies);
    }

    public function test_skill_proficiencies_can_be_associated_with_class(): void
    {
        $stealth = DB::table('skills')->where('name', 'Stealth')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $dex = DB::table('ability_scores')->where('code', 'DEX')->first();

        // Create class
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

        $class = DB::table('classes')->where('name', 'Rogue')->first();

        // Add skill proficiency
        DB::table('skill_proficiencies')->insert([
            'skill_id' => $stealth->id,
            'race_id' => 0,
            'class_id' => $class->id,
            'background_id' => 0,
            'feat_id' => 0,
        ]);

        $proficiency = DB::table('skill_proficiencies')
            ->where('class_id', $class->id)
            ->where('skill_id', $stealth->id)
            ->first();

        $this->assertEquals($class->id, $proficiency->class_id);
        $this->assertEquals(0, $proficiency->background_id);
    }
}

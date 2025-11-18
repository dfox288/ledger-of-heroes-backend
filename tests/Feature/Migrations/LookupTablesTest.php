<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LookupTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_schools_table_exists_with_correct_structure(): void
    {
        $this->assertTrue(Schema::hasTable('spell_schools'));
        $this->assertTrue(Schema::hasColumn('spell_schools', 'id'));
        $this->assertTrue(Schema::hasColumn('spell_schools', 'code'));
        $this->assertTrue(Schema::hasColumn('spell_schools', 'name'));
        $this->assertFalse(Schema::hasColumn('spell_schools', 'created_at'));

        // Verify 8 schools exist
        $this->assertEquals(8, DB::table('spell_schools')->count());
    }

    public function test_damage_types_table_exists_with_correct_structure(): void
    {
        $this->assertTrue(Schema::hasTable('damage_types'));
        $this->assertTrue(Schema::hasColumn('damage_types', 'id'));
        $this->assertTrue(Schema::hasColumn('damage_types', 'name'));
        $this->assertFalse(Schema::hasColumn('damage_types', 'created_at'));

        // Verify 13 damage types exist
        $this->assertEquals(13, DB::table('damage_types')->count());
    }

    public function test_sizes_table_exists_with_correct_structure(): void
    {
        $this->assertTrue(Schema::hasTable('sizes'));
        $this->assertTrue(Schema::hasColumn('sizes', 'id'));
        $this->assertTrue(Schema::hasColumn('sizes', 'code'));
        $this->assertTrue(Schema::hasColumn('sizes', 'name'));
        $this->assertFalse(Schema::hasColumn('sizes', 'created_at'));

        // Verify 6 sizes exist
        $this->assertEquals(6, DB::table('sizes')->count());
    }

    public function test_ability_scores_table_exists_with_correct_structure(): void
    {
        $this->assertTrue(Schema::hasTable('ability_scores'));
        $this->assertTrue(Schema::hasColumn('ability_scores', 'id'));
        $this->assertTrue(Schema::hasColumn('ability_scores', 'code'));
        $this->assertTrue(Schema::hasColumn('ability_scores', 'name'));
        $this->assertFalse(Schema::hasColumn('ability_scores', 'created_at'));

        // Verify 6 abilities exist
        $this->assertEquals(6, DB::table('ability_scores')->count());
    }

    public function test_skills_table_exists_with_correct_structure(): void
    {
        $this->assertTrue(Schema::hasTable('skills'));
        $this->assertTrue(Schema::hasColumn('skills', 'id'));
        $this->assertTrue(Schema::hasColumn('skills', 'name'));
        $this->assertTrue(Schema::hasColumn('skills', 'ability_score_id'));
        $this->assertFalse(Schema::hasColumn('skills', 'created_at'));

        // Verify 18 skills exist
        $this->assertEquals(18, DB::table('skills')->count());

        // Verify FK relationship exists
        $acrobatics = DB::table('skills')->where('name', 'Acrobatics')->first();
        $dex = DB::table('ability_scores')->where('code', 'DEX')->first();
        $this->assertEquals($dex->id, $acrobatics->ability_score_id);
    }

    public function test_item_types_table_exists_with_correct_structure(): void
    {
        $this->assertTrue(Schema::hasTable('item_types'));
        $this->assertTrue(Schema::hasColumn('item_types', 'id'));
        $this->assertTrue(Schema::hasColumn('item_types', 'name'));
        $this->assertTrue(Schema::hasColumn('item_types', 'code'));
        $this->assertTrue(Schema::hasColumn('item_types', 'description'));
        $this->assertFalse(Schema::hasColumn('item_types', 'created_at'));

        // Verify 16 item types exist
        $this->assertEquals(16, DB::table('item_types')->count());

        // Verify code uniqueness
        $codes = DB::table('item_types')->pluck('code')->toArray();
        $this->assertEquals(count($codes), count(array_unique($codes)), 'All item type codes should be unique');
    }
}

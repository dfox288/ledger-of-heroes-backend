<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RacesBackgroundsFeatsTablesTest extends TestCase
{
    use RefreshDatabase;

    // Races Table Tests
    public function test_races_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('races'));
        $this->assertTrue(Schema::hasColumn('races', 'id'));
        $this->assertTrue(Schema::hasColumn('races', 'name'));
        $this->assertTrue(Schema::hasColumn('races', 'size_id'));
        $this->assertTrue(Schema::hasColumn('races', 'speed'));
        $this->assertFalse(Schema::hasColumn('races', 'source_id'));
        $this->assertFalse(Schema::hasColumn('races', 'source_pages'));
    }

    public function test_races_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('races', 'created_at'));
        $this->assertFalse(Schema::hasColumn('races', 'updated_at'));
    }

    public function test_races_table_has_foreign_keys(): void
    {
        $medium = DB::table('sizes')->where('code', 'M')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        DB::table('races')->insert([
            'name' => 'Human',
            'size_id' => $medium->id,
            'speed' => 30,
        ]);

        $race = DB::table('races')->where('name', 'Human')->first();
        $this->assertEquals($medium->id, $race->size_id);
    }

    public function test_races_table_uses_correct_naming_conventions(): void
    {
        $this->assertFalse(Schema::hasColumn('races', 'source_id'));
        $this->assertFalse(Schema::hasColumn('races', 'source_book_id'));
        $this->assertFalse(Schema::hasColumn('races', 'source_pages'));
        $this->assertFalse(Schema::hasColumn('races', 'source_page'));
    }

    // Backgrounds Table Tests
    public function test_backgrounds_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('backgrounds'));
        $this->assertTrue(Schema::hasColumn('backgrounds', 'id'));
        $this->assertTrue(Schema::hasColumn('backgrounds', 'name'));
        $this->assertTrue(Schema::hasColumn('backgrounds', 'description'));
        $this->assertTrue(Schema::hasColumn('backgrounds', 'skill_proficiencies'));
        $this->assertTrue(Schema::hasColumn('backgrounds', 'tool_proficiencies'));
        $this->assertTrue(Schema::hasColumn('backgrounds', 'languages'));
        $this->assertTrue(Schema::hasColumn('backgrounds', 'equipment'));
        $this->assertTrue(Schema::hasColumn('backgrounds', 'feature_name'));
        $this->assertTrue(Schema::hasColumn('backgrounds', 'feature_description'));
        $this->assertFalse(Schema::hasColumn('backgrounds', 'source_id'));
        $this->assertFalse(Schema::hasColumn('backgrounds', 'source_pages'));
    }

    public function test_backgrounds_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('backgrounds', 'created_at'));
        $this->assertFalse(Schema::hasColumn('backgrounds', 'updated_at'));
    }

    public function test_backgrounds_table_can_store_data(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        DB::table('backgrounds')->insert([
            'name' => 'Acolyte',
            'description' => 'You have spent your life in service to a temple...',
            'skill_proficiencies' => json_encode(['Insight', 'Religion']),
            'tool_proficiencies' => null,
            'languages' => 'Two of your choice',
            'equipment' => 'A holy symbol, a prayer book...',
            'feature_name' => 'Shelter of the Faithful',
            'feature_description' => 'As an acolyte, you command respect...',
        ]);

        $bg = DB::table('backgrounds')->where('name', 'Acolyte')->first();
        $this->assertEquals('Acolyte', $bg->name);
    }

    public function test_backgrounds_table_uses_correct_naming_conventions(): void
    {
        $this->assertFalse(Schema::hasColumn('backgrounds', 'source_id'));
        $this->assertFalse(Schema::hasColumn('backgrounds', 'source_book_id'));
        $this->assertFalse(Schema::hasColumn('backgrounds', 'source_pages'));
        $this->assertFalse(Schema::hasColumn('backgrounds', 'source_page'));
    }

    // Feats Table Tests
    public function test_feats_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('feats'));
        $this->assertTrue(Schema::hasColumn('feats', 'id'));
        $this->assertTrue(Schema::hasColumn('feats', 'name'));
        $this->assertTrue(Schema::hasColumn('feats', 'prerequisites'));
        $this->assertTrue(Schema::hasColumn('feats', 'description'));
        $this->assertFalse(Schema::hasColumn('feats', 'source_id'));
        $this->assertFalse(Schema::hasColumn('feats', 'source_pages'));
    }

    public function test_feats_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('feats', 'created_at'));
        $this->assertFalse(Schema::hasColumn('feats', 'updated_at'));
    }

    public function test_feats_table_can_store_data(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        DB::table('feats')->insert([
            'name' => 'Alert',
            'prerequisites' => null,
            'description' => 'Always on the lookout for danger...',
        ]);

        DB::table('feats')->insert([
            'name' => 'Heavy Armor Master',
            'prerequisites' => 'Proficiency with heavy armor',
            'description' => 'You can use your armor to deflect strikes...',
        ]);

        $feat1 = DB::table('feats')->where('name', 'Alert')->first();
        $feat2 = DB::table('feats')->where('name', 'Heavy Armor Master')->first();

        $this->assertNull($feat1->prerequisites);
        $this->assertEquals('Proficiency with heavy armor', $feat2->prerequisites);
    }

    public function test_feats_table_uses_correct_naming_conventions(): void
    {
        $this->assertFalse(Schema::hasColumn('feats', 'source_id'));
        $this->assertFalse(Schema::hasColumn('feats', 'source_book_id'));
        $this->assertFalse(Schema::hasColumn('feats', 'source_pages'));
        $this->assertFalse(Schema::hasColumn('feats', 'source_page'));
    }
}

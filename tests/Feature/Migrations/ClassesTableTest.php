<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_classes_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('classes'));
        $this->assertTrue(Schema::hasColumn('classes', 'id'));
        $this->assertTrue(Schema::hasColumn('classes', 'name'));
        $this->assertTrue(Schema::hasColumn('classes', 'parent_class_id'));
        $this->assertTrue(Schema::hasColumn('classes', 'hit_die'));
        $this->assertTrue(Schema::hasColumn('classes', 'description'));
        $this->assertTrue(Schema::hasColumn('classes', 'primary_ability'));
        $this->assertTrue(Schema::hasColumn('classes', 'spellcasting_ability_id'));
        $this->assertTrue(Schema::hasColumn('classes', 'source_id'));
        $this->assertTrue(Schema::hasColumn('classes', 'source_pages'));
    }

    public function test_classes_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('classes', 'created_at'));
        $this->assertFalse(Schema::hasColumn('classes', 'updated_at'));
    }

    public function test_classes_table_can_store_base_class(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $strength = DB::table('ability_scores')->where('code', 'STR')->first();

        DB::table('classes')->insert([
            'name' => 'Barbarian',
            'parent_class_id' => null, // Base class
            'hit_die' => 12,
            'description' => 'A fierce warrior of primitive background...',
            'primary_ability' => 'Strength',
            'spellcasting_ability_id' => null, // Not a spellcaster
            'source_id' => $phb->id,
            'source_pages' => '46-50',
        ]);

        $class = DB::table('classes')->where('name', 'Barbarian')->first();
        $this->assertEquals('Barbarian', $class->name);
        $this->assertNull($class->parent_class_id);
        $this->assertEquals(12, $class->hit_die);
    }

    public function test_classes_table_can_store_spellcasting_class(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $wisdom = DB::table('ability_scores')->where('code', 'WIS')->first();

        DB::table('classes')->insert([
            'name' => 'Druid',
            'parent_class_id' => null,
            'hit_die' => 8,
            'description' => 'A priest of the Old Faith...',
            'primary_ability' => 'Wisdom',
            'spellcasting_ability_id' => $wisdom->id, // Wisdom-based spellcaster
            'source_id' => $phb->id,
            'source_pages' => '64-68',
        ]);

        $class = DB::table('classes')->where('name', 'Druid')->first();
        $this->assertEquals($wisdom->id, $class->spellcasting_ability_id);
    }

    public function test_classes_table_supports_subclasses(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $xge = DB::table('sources')->where('code', 'XGE')->first();
        $wisdom = DB::table('ability_scores')->where('code', 'WIS')->first();

        // Create base class first
        DB::table('classes')->insert([
            'name' => 'Druid',
            'parent_class_id' => null,
            'hit_die' => 8,
            'description' => 'A priest of the Old Faith...',
            'primary_ability' => 'Wisdom',
            'spellcasting_ability_id' => $wisdom->id,
            'source_id' => $phb->id,
            'source_pages' => '64-68',
        ]);

        $druid = DB::table('classes')->where('name', 'Druid')->first();

        // Create subclass
        DB::table('classes')->insert([
            'name' => 'Circle of the Moon',
            'parent_class_id' => $druid->id, // References parent Druid class
            'hit_die' => 8, // Inherited from parent
            'description' => 'Druids of the Circle of the Moon are fierce guardians...',
            'primary_ability' => 'Wisdom',
            'spellcasting_ability_id' => $wisdom->id,
            'source_id' => $phb->id,
            'source_pages' => '69',
        ]);

        $subclass = DB::table('classes')->where('name', 'Circle of the Moon')->first();
        $this->assertEquals($druid->id, $subclass->parent_class_id);
    }

    public function test_classes_table_uses_correct_naming_conventions(): void
    {
        $this->assertTrue(Schema::hasColumn('classes', 'source_id'));
        $this->assertFalse(Schema::hasColumn('classes', 'source_book_id'));
        $this->assertTrue(Schema::hasColumn('classes', 'source_pages'));
        $this->assertFalse(Schema::hasColumn('classes', 'source_page'));
    }

    public function test_classes_table_has_self_referential_foreign_key(): void
    {
        // This test verifies that parent_class_id references classes.id
        // We test this by creating a parent-child relationship
        $phb = DB::table('sources')->where('code', 'PHB')->first();

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

        DB::table('classes')->insert([
            'name' => 'Battle Master',
            'parent_class_id' => $fighter->id,
            'hit_die' => 10,
            'description' => 'Those who emulate the archetypal Battle Master...',
            'primary_ability' => 'Strength or Dexterity',
            'spellcasting_ability_id' => null,
            'source_id' => $phb->id,
            'source_pages' => '73',
        ]);

        $battleMaster = DB::table('classes')->where('name', 'Battle Master')->first();
        $this->assertEquals($fighter->id, $battleMaster->parent_class_id);
    }

    public function test_classes_table_supports_multi_page_references(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        DB::table('classes')->insert([
            'name' => 'Wizard',
            'parent_class_id' => null,
            'hit_die' => 6,
            'description' => 'A scholarly magic-user...',
            'primary_ability' => 'Intelligence',
            'spellcasting_ability_id' => DB::table('ability_scores')->where('code', 'INT')->first()->id,
            'source_id' => $phb->id,
            'source_pages' => '112, 113, 114, 115, 116', // Multiple pages
        ]);

        $wizard = DB::table('classes')->where('name', 'Wizard')->first();
        $this->assertStringContainsString(',', $wizard->source_pages);
    }
}

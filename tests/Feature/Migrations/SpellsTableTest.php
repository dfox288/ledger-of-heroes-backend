<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpellsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_spells_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('spells'));

        // Core columns
        $this->assertTrue(Schema::hasColumn('spells', 'id'));
        $this->assertTrue(Schema::hasColumn('spells', 'name'));
        $this->assertTrue(Schema::hasColumn('spells', 'level'));
        $this->assertTrue(Schema::hasColumn('spells', 'spell_school_id'));

        // Casting details
        $this->assertTrue(Schema::hasColumn('spells', 'casting_time'));
        $this->assertTrue(Schema::hasColumn('spells', 'range'));
        $this->assertTrue(Schema::hasColumn('spells', 'components'));
        $this->assertTrue(Schema::hasColumn('spells', 'material_components'));
        $this->assertTrue(Schema::hasColumn('spells', 'duration'));

        // Critical flags
        $this->assertTrue(Schema::hasColumn('spells', 'needs_concentration'));
        $this->assertTrue(Schema::hasColumn('spells', 'is_ritual'));

        // Content
        $this->assertTrue(Schema::hasColumn('spells', 'description'));
        $this->assertTrue(Schema::hasColumn('spells', 'higher_levels'));

        // Source attribution
        $this->assertTrue(Schema::hasColumn('spells', 'source_id')); // NOT source_book_id
        $this->assertTrue(Schema::hasColumn('spells', 'source_pages')); // NOT source_page
    }

    public function test_spells_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('spells', 'created_at'));
        $this->assertFalse(Schema::hasColumn('spells', 'updated_at'));
    }

    public function test_spells_table_has_foreign_keys(): void
    {
        // Verify FK to spell_schools
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        DB::table('spells')->insert([
            'name' => 'Fireball',
            'level' => 3,
            'spell_school_id' => $evocation->id,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'components' => 'V, S, M',
            'material_components' => 'A tiny ball of bat guano and sulfur',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A bright streak flashes from your pointing finger...',
            'higher_levels' => 'When you cast this spell using a spell slot of 4th level or higher...',
            'source_id' => $phb->id,
            'source_pages' => '241',
        ]);

        $spell = DB::table('spells')->where('name', 'Fireball')->first();
        $this->assertEquals($evocation->id, $spell->spell_school_id);
        $this->assertEquals($phb->id, $spell->source_id);
    }

    public function test_spells_table_supports_multi_page_references(): void
    {
        $abjuration = DB::table('spell_schools')->where('code', 'A')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        DB::table('spells')->insert([
            'name' => 'Test Spell',
            'level' => 1,
            'spell_school_id' => $abjuration->id,
            'casting_time' => '1 action',
            'range' => 'Self',
            'components' => 'V',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'Test description',
            'source_id' => $phb->id,
            'source_pages' => '148, 150, 152', // Multiple pages
        ]);

        $spell = DB::table('spells')->where('name', 'Test Spell')->first();
        $this->assertEquals('148, 150, 152', $spell->source_pages);
    }

    public function test_spells_table_has_needs_concentration_field(): void
    {
        // This field was MISSING in old implementation - verify it exists
        $this->assertTrue(Schema::hasColumn('spells', 'needs_concentration'));

        // Verify it can store boolean values
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        DB::table('spells')->insert([
            'name' => 'Concentration Spell',
            'level' => 1,
            'spell_school_id' => $evocation->id,
            'casting_time' => '1 action',
            'range' => 'Self',
            'components' => 'V, S',
            'duration' => 'Concentration, up to 1 minute',
            'needs_concentration' => true, // Test concentration = true
            'is_ritual' => false,
            'description' => 'Test concentration spell',
            'source_id' => $phb->id,
            'source_pages' => '100',
        ]);

        $spell = DB::table('spells')->where('name', 'Concentration Spell')->first();
        $this->assertTrue((bool) $spell->needs_concentration);
    }

    public function test_spells_table_uses_source_id_not_source_book_id(): void
    {
        // Verify we're using the CORRECT naming convention
        $this->assertTrue(Schema::hasColumn('spells', 'source_id'));
        $this->assertFalse(Schema::hasColumn('spells', 'source_book_id')); // OLD naming
    }

    public function test_spells_table_uses_source_pages_not_source_page(): void
    {
        // Verify we're using the CORRECT naming convention for multi-page support
        $this->assertTrue(Schema::hasColumn('spells', 'source_pages'));
        $this->assertFalse(Schema::hasColumn('spells', 'source_page')); // OLD naming
    }
}

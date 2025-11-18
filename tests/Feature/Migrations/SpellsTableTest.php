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

        // Verify source_id and source_pages have been removed (moved to entity_sources table)
        $this->assertFalse(Schema::hasColumn('spells', 'source_id'));
        $this->assertFalse(Schema::hasColumn('spells', 'source_pages'));
    }

    public function test_spells_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('spells', 'created_at'));
        $this->assertFalse(Schema::hasColumn('spells', 'updated_at'));
    }

    public function test_spells_table_has_foreign_key_to_spell_schools(): void
    {
        // Verify FK to spell_schools
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();

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
        ]);

        $spell = DB::table('spells')->where('name', 'Fireball')->first();
        $this->assertEquals($evocation->id, $spell->spell_school_id);
    }

    public function test_entity_sources_table_supports_multi_page_references(): void
    {
        $abjuration = DB::table('spell_schools')->where('code', 'A')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        // Create spell
        $spellId = DB::table('spells')->insertGetId([
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
        ]);

        // Link spell to source via entity_sources
        DB::table('entity_sources')->insert([
            'reference_type' => 'App\Models\Spell',
            'reference_id' => $spellId,
            'source_id' => $phb->id,
            'pages' => '148, 150, 152', // Multiple pages
        ]);

        $entitySource = DB::table('entity_sources')
            ->where('reference_type', 'App\Models\Spell')
            ->where('reference_id', $spellId)
            ->first();

        $this->assertEquals('148, 150, 152', $entitySource->pages);
    }

    public function test_spells_table_has_needs_concentration_field(): void
    {
        // This field was MISSING in old implementation - verify it exists
        $this->assertTrue(Schema::hasColumn('spells', 'needs_concentration'));

        // Verify it can store boolean values
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();

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
        ]);

        $spell = DB::table('spells')->where('name', 'Concentration Spell')->first();
        $this->assertTrue((bool) $spell->needs_concentration);
    }

    public function test_entity_sources_table_exists_for_multi_source_support(): void
    {
        // Verify entity_sources junction table exists
        $this->assertTrue(Schema::hasTable('entity_sources'));
        $this->assertTrue(Schema::hasColumn('entity_sources', 'reference_type'));
        $this->assertTrue(Schema::hasColumn('entity_sources', 'reference_id'));
        $this->assertTrue(Schema::hasColumn('entity_sources', 'source_id'));
        $this->assertTrue(Schema::hasColumn('entity_sources', 'pages'));
    }
}

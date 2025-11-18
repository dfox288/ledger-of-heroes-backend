<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassSpellsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_spells_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('class_spells'));
        $this->assertTrue(Schema::hasColumn('class_spells', 'class_id'));
        $this->assertTrue(Schema::hasColumn('class_spells', 'spell_id'));
        $this->assertTrue(Schema::hasColumn('class_spells', 'level_learned'));
    }

    public function test_class_spells_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('class_spells', 'created_at'));
        $this->assertFalse(Schema::hasColumn('class_spells', 'updated_at'));
    }

    public function test_class_spells_table_does_not_have_id_column(): void
    {
        $columns = Schema::getColumnListing('class_spells');
        $this->assertNotContains('id', $columns);
    }

    public function test_class_spells_can_associate_spell_with_class(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $wisdom = DB::table('ability_scores')->where('code', 'WIS')->first();
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();

        // Create Cleric class
        DB::table('classes')->insert([
            'name' => 'Cleric',
            'parent_class_id' => null,
            'hit_die' => 8,
            'description' => 'A priestly champion...',
            'primary_ability' => 'Wisdom',
            'spellcasting_ability_id' => $wisdom->id,
        ]);

        $cleric = DB::table('classes')->where('name', 'Cleric')->first();

        // Create Cure Wounds spell
        DB::table('spells')->insert([
            'name' => 'Cure Wounds',
            'level' => 1,
            'spell_school_id' => $evocation->id,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'components' => 'V, S',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A creature you touch regains hit points...',
        ]);

        $spell = DB::table('spells')->where('name', 'Cure Wounds')->first();

        // Associate spell with class
        DB::table('class_spells')->insert([
            'class_id' => $cleric->id,
            'spell_id' => $spell->id,
            'level_learned' => 1, // Clerics get this at level 1
        ]);

        $association = DB::table('class_spells')
            ->where('class_id', $cleric->id)
            ->where('spell_id', $spell->id)
            ->first();

        $this->assertEquals($cleric->id, $association->class_id);
        $this->assertEquals($spell->id, $association->spell_id);
        $this->assertEquals(1, $association->level_learned);
    }

    public function test_class_spells_can_have_multiple_spells_for_one_class(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $intelligence = DB::table('ability_scores')->where('code', 'INT')->first();
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();
        $abjuration = DB::table('spell_schools')->where('code', 'A')->first();

        // Create Wizard class
        DB::table('classes')->insert([
            'name' => 'Wizard',
            'parent_class_id' => null,
            'hit_die' => 6,
            'description' => 'A scholarly magic-user...',
            'primary_ability' => 'Intelligence',
            'spellcasting_ability_id' => $intelligence->id,
        ]);

        $wizard = DB::table('classes')->where('name', 'Wizard')->first();

        // Create spells
        DB::table('spells')->insert([
            [
                'name' => 'Magic Missile',
                'level' => 1,
                'spell_school_id' => $evocation->id,
                'casting_time' => '1 action',
                'range' => '120 feet',
                'components' => 'V, S',
                'duration' => 'Instantaneous',
                'needs_concentration' => false,
                'is_ritual' => false,
                'description' => 'You create three glowing darts...',
            ],
            [
                'name' => 'Shield',
                'level' => 1,
                'spell_school_id' => $abjuration->id,
                'casting_time' => '1 reaction',
                'range' => 'Self',
                'components' => 'V, S',
                'duration' => '1 round',
                'needs_concentration' => false,
                'is_ritual' => false,
                'description' => 'An invisible barrier of magical force...',
            ],
        ]);

        $magicMissile = DB::table('spells')->where('name', 'Magic Missile')->first();
        $shield = DB::table('spells')->where('name', 'Shield')->first();

        // Associate both spells with wizard
        DB::table('class_spells')->insert([
            ['class_id' => $wizard->id, 'spell_id' => $magicMissile->id, 'level_learned' => 1],
            ['class_id' => $wizard->id, 'spell_id' => $shield->id, 'level_learned' => 1],
        ]);

        $wizardSpells = DB::table('class_spells')
            ->where('class_id', $wizard->id)
            ->get();

        $this->assertCount(2, $wizardSpells);
    }

    public function test_class_spells_can_have_same_spell_for_multiple_classes(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $wisdom = DB::table('ability_scores')->where('code', 'WIS')->first();
        $charisma = DB::table('ability_scores')->where('code', 'CHA')->first();
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();

        // Create Cleric and Paladin classes
        DB::table('classes')->insert([
            [
                'name' => 'Cleric',
                'parent_class_id' => null,
                'hit_die' => 8,
                'description' => 'A priestly champion...',
                'primary_ability' => 'Wisdom',
                'spellcasting_ability_id' => $wisdom->id,
            ],
            [
                'name' => 'Paladin',
                'parent_class_id' => null,
                'hit_die' => 10,
                'description' => 'A holy warrior...',
                'primary_ability' => 'Strength and Charisma',
                'spellcasting_ability_id' => $charisma->id,
            ],
        ]);

        $cleric = DB::table('classes')->where('name', 'Cleric')->first();
        $paladin = DB::table('classes')->where('name', 'Paladin')->first();

        // Create shared spell
        DB::table('spells')->insert([
            'name' => 'Cure Wounds',
            'level' => 1,
            'spell_school_id' => $evocation->id,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'components' => 'V, S',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A creature you touch regains hit points...',
        ]);

        $spell = DB::table('spells')->where('name', 'Cure Wounds')->first();

        // Associate same spell with both classes
        DB::table('class_spells')->insert([
            ['class_id' => $cleric->id, 'spell_id' => $spell->id, 'level_learned' => 1],
            ['class_id' => $paladin->id, 'spell_id' => $spell->id, 'level_learned' => 2], // Paladins get it at level 2
        ]);

        $associations = DB::table('class_spells')
            ->where('spell_id', $spell->id)
            ->get();

        $this->assertCount(2, $associations);

        // Verify different level_learned
        $clericAssoc = $associations->firstWhere('class_id', $cleric->id);
        $paladinAssoc = $associations->firstWhere('class_id', $paladin->id);

        $this->assertEquals(1, $clericAssoc->level_learned);
        $this->assertEquals(2, $paladinAssoc->level_learned);
    }

    public function test_class_spells_level_learned_can_be_null(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $wisdom = DB::table('ability_scores')->where('code', 'WIS')->first();
        $conjuration = DB::table('spell_schools')->where('code', 'C')->first();

        // Create Druid class
        DB::table('classes')->insert([
            'name' => 'Druid',
            'parent_class_id' => null,
            'hit_die' => 8,
            'description' => 'A priest of the Old Faith...',
            'primary_ability' => 'Wisdom',
            'spellcasting_ability_id' => $wisdom->id,
        ]);

        $druid = DB::table('classes')->where('name', 'Druid')->first();

        // Create spell
        DB::table('spells')->insert([
            'name' => 'Goodberry',
            'level' => 1,
            'spell_school_id' => $conjuration->id,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'components' => 'V, S, M',
            'material_components' => 'a sprig of mistletoe',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'Up to ten berries appear in your hand...',
        ]);

        $spell = DB::table('spells')->where('name', 'Goodberry')->first();

        // Associate without level_learned (druids prepare spells)
        DB::table('class_spells')->insert([
            'class_id' => $druid->id,
            'spell_id' => $spell->id,
            'level_learned' => null, // Can prepare at any level
        ]);

        $association = DB::table('class_spells')
            ->where('class_id', $druid->id)
            ->first();

        $this->assertNull($association->level_learned);
    }
}

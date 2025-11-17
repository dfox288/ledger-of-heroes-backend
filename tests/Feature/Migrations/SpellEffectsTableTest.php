<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpellEffectsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_spell_effects_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('spell_effects'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'id'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'spell_id'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'effect_type'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'description'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'dice_formula'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'base_value'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'scaling_type'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'min_character_level'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'min_spell_slot'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'scaling_increment'));
    }

    public function test_spell_effects_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('spell_effects', 'created_at'));
        $this->assertFalse(Schema::hasColumn('spell_effects', 'updated_at'));
    }

    public function test_spell_effects_can_track_damage_spell(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();

        // Create Fireball spell
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
            'source_id' => $phb->id,
            'source_pages' => '241',
        ]);

        $fireball = DB::table('spells')->where('name', 'Fireball')->first();

        // Add damage effect
        DB::table('spell_effects')->insert([
            'spell_id' => $fireball->id,
            'effect_type' => 'damage',
            'description' => 'Fire damage in 20-foot radius',
            'dice_formula' => '8d6',
            'base_value' => null,
            'scaling_type' => 'spell_slot',
            'min_character_level' => null,
            'min_spell_slot' => 3,
            'scaling_increment' => '1d6',
        ]);

        $effect = DB::table('spell_effects')->where('spell_id', $fireball->id)->first();

        $this->assertEquals('damage', $effect->effect_type);
        $this->assertEquals('Fire damage in 20-foot radius', $effect->description);
        $this->assertEquals('8d6', $effect->dice_formula);
        $this->assertEquals('spell_slot', $effect->scaling_type);
        $this->assertEquals(3, $effect->min_spell_slot);
        $this->assertEquals('1d6', $effect->scaling_increment);
    }

    public function test_spell_effects_can_track_healing_spell(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();

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
            'source_id' => $phb->id,
            'source_pages' => '230',
        ]);

        $cureWounds = DB::table('spells')->where('name', 'Cure Wounds')->first();

        // Add healing effect
        DB::table('spell_effects')->insert([
            'spell_id' => $cureWounds->id,
            'effect_type' => 'healing',
            'description' => 'Restores hit points',
            'dice_formula' => '1d8',
            'base_value' => null,
            'scaling_type' => 'spell_slot',
            'min_character_level' => null,
            'min_spell_slot' => 1,
            'scaling_increment' => '1d8',
        ]);

        $effect = DB::table('spell_effects')->where('spell_id', $cureWounds->id)->first();

        $this->assertEquals('healing', $effect->effect_type);
        $this->assertEquals('Restores hit points', $effect->description);
        $this->assertEquals('1d8', $effect->dice_formula);
        $this->assertEquals('spell_slot', $effect->scaling_type);
        $this->assertEquals('1d8', $effect->scaling_increment);
    }

    public function test_spell_effects_can_track_control_spell(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $enchantment = DB::table('spell_schools')->where('code', 'EN')->first();

        // Create Hold Person spell
        DB::table('spells')->insert([
            'name' => 'Hold Person',
            'level' => 2,
            'spell_school_id' => $enchantment->id,
            'casting_time' => '1 action',
            'range' => '60 feet',
            'components' => 'V, S, M',
            'material_components' => 'A small, straight piece of iron',
            'duration' => 'Concentration, up to 1 minute',
            'needs_concentration' => true,
            'is_ritual' => false,
            'description' => 'Choose a humanoid you can see...',
            'source_id' => $phb->id,
            'source_pages' => '251',
        ]);

        $holdPerson = DB::table('spells')->where('name', 'Hold Person')->first();

        // Add control effect (no damage)
        DB::table('spell_effects')->insert([
            'spell_id' => $holdPerson->id,
            'effect_type' => 'control',
            'description' => 'Target is paralyzed',
            'dice_formula' => null,
            'base_value' => null,
            'scaling_type' => null,
            'min_character_level' => null,
            'min_spell_slot' => null,
            'scaling_increment' => null,
        ]);

        $effect = DB::table('spell_effects')->where('spell_id', $holdPerson->id)->first();

        $this->assertEquals('control', $effect->effect_type);
        $this->assertEquals('Target is paralyzed', $effect->description);
        $this->assertNull($effect->dice_formula);
        $this->assertNull($effect->scaling_type);
    }

    public function test_spell_can_have_multiple_effects(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();

        // Create Flame Strike spell (has both fire and radiant damage)
        DB::table('spells')->insert([
            'name' => 'Flame Strike',
            'level' => 5,
            'spell_school_id' => $evocation->id,
            'casting_time' => '1 action',
            'range' => '60 feet',
            'components' => 'V, S, M',
            'material_components' => 'Pinch of sulfur',
            'duration' => 'Instantaneous',
            'needs_concentration' => false,
            'is_ritual' => false,
            'description' => 'A vertical column of divine fire...',
            'source_id' => $phb->id,
            'source_pages' => '242',
        ]);

        $flameStrike = DB::table('spells')->where('name', 'Flame Strike')->first();

        // Add fire damage effect
        DB::table('spell_effects')->insert([
            'spell_id' => $flameStrike->id,
            'effect_type' => 'damage',
            'description' => 'Fire damage component',
            'dice_formula' => '4d6',
            'base_value' => null,
            'scaling_type' => 'spell_slot',
            'min_character_level' => null,
            'min_spell_slot' => 5,
            'scaling_increment' => '1d6',
        ]);

        // Add radiant damage effect
        DB::table('spell_effects')->insert([
            'spell_id' => $flameStrike->id,
            'effect_type' => 'damage',
            'description' => 'Radiant damage component',
            'dice_formula' => '4d6',
            'base_value' => null,
            'scaling_type' => 'spell_slot',
            'min_character_level' => null,
            'min_spell_slot' => 5,
            'scaling_increment' => '1d6',
        ]);

        $effects = DB::table('spell_effects')
            ->where('spell_id', $flameStrike->id)
            ->get();

        $this->assertCount(2, $effects);

        $fireEffect = $effects->firstWhere('description', 'Fire damage component');
        $radiantEffect = $effects->firstWhere('description', 'Radiant damage component');

        $this->assertEquals('4d6', $fireEffect->dice_formula);
        $this->assertEquals('4d6', $radiantEffect->dice_formula);
        $this->assertEquals('spell_slot', $fireEffect->scaling_type);
        $this->assertEquals('spell_slot', $radiantEffect->scaling_type);
    }
}

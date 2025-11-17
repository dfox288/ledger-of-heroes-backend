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
        $this->assertTrue(Schema::hasColumn('spell_effects', 'damage_dice'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'damage_type_id'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'save_ability_id'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'save_effect'));
        $this->assertTrue(Schema::hasColumn('spell_effects', 'description'));
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
        $fire = DB::table('damage_types')->where('name', 'Fire')->first();
        $dex = DB::table('ability_scores')->where('code', 'DEX')->first();

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
            'damage_dice' => '8d6',
            'damage_type_id' => $fire->id,
            'save_ability_id' => $dex->id,
            'save_effect' => 'half damage',
            'description' => 'Fire damage in 20-foot radius',
        ]);

        $effect = DB::table('spell_effects')->where('spell_id', $fireball->id)->first();

        $this->assertEquals('damage', $effect->effect_type);
        $this->assertEquals('8d6', $effect->damage_dice);
        $this->assertEquals($fire->id, $effect->damage_type_id);
        $this->assertEquals($dex->id, $effect->save_ability_id);
        $this->assertEquals('half damage', $effect->save_effect);
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

        // Add healing effect (no damage type, no save)
        DB::table('spell_effects')->insert([
            'spell_id' => $cureWounds->id,
            'effect_type' => 'healing',
            'damage_dice' => '1d8+modifier',
            'damage_type_id' => null, // Healing doesn't have damage type
            'save_ability_id' => null, // No save
            'save_effect' => null,
            'description' => 'Restores hit points',
        ]);

        $effect = DB::table('spell_effects')->where('spell_id', $cureWounds->id)->first();

        $this->assertEquals('healing', $effect->effect_type);
        $this->assertEquals('1d8+modifier', $effect->damage_dice);
        $this->assertNull($effect->damage_type_id);
        $this->assertNull($effect->save_ability_id);
    }

    public function test_spell_effects_can_track_control_spell(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $enchantment = DB::table('spell_schools')->where('code', 'EN')->first();
        $wisdom = DB::table('ability_scores')->where('code', 'WIS')->first();

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
            'damage_dice' => null, // No damage
            'damage_type_id' => null,
            'save_ability_id' => $wisdom->id,
            'save_effect' => 'negates',
            'description' => 'Target is paralyzed',
        ]);

        $effect = DB::table('spell_effects')->where('spell_id', $holdPerson->id)->first();

        $this->assertEquals('control', $effect->effect_type);
        $this->assertNull($effect->damage_dice);
        $this->assertNull($effect->damage_type_id);
        $this->assertEquals($wisdom->id, $effect->save_ability_id);
        $this->assertEquals('negates', $effect->save_effect);
    }

    public function test_spell_can_have_multiple_effects(): void
    {
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();
        $fire = DB::table('damage_types')->where('name', 'Fire')->first();
        $dex = DB::table('ability_scores')->where('code', 'DEX')->first();

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
        $radiant = DB::table('damage_types')->where('name', 'Radiant')->first();

        // Add fire damage effect
        DB::table('spell_effects')->insert([
            'spell_id' => $flameStrike->id,
            'effect_type' => 'damage',
            'damage_dice' => '4d6',
            'damage_type_id' => $fire->id,
            'save_ability_id' => $dex->id,
            'save_effect' => 'half damage',
            'description' => 'Fire damage component',
        ]);

        // Add radiant damage effect
        DB::table('spell_effects')->insert([
            'spell_id' => $flameStrike->id,
            'effect_type' => 'damage',
            'damage_dice' => '4d6',
            'damage_type_id' => $radiant->id,
            'save_ability_id' => $dex->id,
            'save_effect' => 'half damage',
            'description' => 'Radiant damage component',
        ]);

        $effects = DB::table('spell_effects')
            ->where('spell_id', $flameStrike->id)
            ->get();

        $this->assertCount(2, $effects);

        $fireEffect = $effects->firstWhere('damage_type_id', $fire->id);
        $radiantEffect = $effects->firstWhere('damage_type_id', $radiant->id);

        $this->assertEquals('4d6', $fireEffect->damage_dice);
        $this->assertEquals('4d6', $radiantEffect->damage_dice);
    }
}

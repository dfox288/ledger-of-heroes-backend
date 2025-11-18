<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonsterRelatedTablesTest extends TestCase
{
    use RefreshDatabase;

    // Monster Traits Tests
    public function test_monster_traits_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('monster_traits'));
        $this->assertTrue(Schema::hasColumn('monster_traits', 'id'));
        $this->assertTrue(Schema::hasColumn('monster_traits', 'monster_id'));
        $this->assertTrue(Schema::hasColumn('monster_traits', 'name'));
        $this->assertTrue(Schema::hasColumn('monster_traits', 'description'));
        $this->assertTrue(Schema::hasColumn('monster_traits', 'attack_data'));
        $this->assertTrue(Schema::hasColumn('monster_traits', 'sort_order'));
    }

    public function test_monster_traits_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('monster_traits', 'created_at'));
        $this->assertFalse(Schema::hasColumn('monster_traits', 'updated_at'));
    }

    public function test_monster_traits_can_track_dragon_traits(): void
    {
        $huge = DB::table('sizes')->where('code', 'H')->first();
        $mm = DB::table('sources')->where('code', 'MM')->first();

        // Create Adult Red Dragon
        DB::table('monsters')->insert([
            'name' => 'Adult Red Dragon',
            'size_id' => $huge->id,
            'type' => 'Dragon',
            'alignment' => 'Chaotic Evil',
            'armor_class' => 19,
            'armor_type' => 'natural armor',
            'hit_points_average' => 256,
            'hit_dice' => '19d12 + 133',
            'speed_walk' => 40,
            'speed_fly' => 80,
            'strength' => 27,
            'dexterity' => 10,
            'constitution' => 25,
            'intelligence' => 16,
            'wisdom' => 13,
            'charisma' => 21,
            'challenge_rating' => '17',
            'experience_points' => 18000,
        ]);

        $dragon = DB::table('monsters')->where('name', 'Adult Red Dragon')->first();

        // Add dragon traits
        DB::table('monster_traits')->insert([
            [
                'monster_id' => $dragon->id,
                'name' => 'Legendary Resistance',
                'description' => 'If the dragon fails a saving throw...',
                'attack_data' => null,
                'sort_order' => 1,
            ],
            [
                'monster_id' => $dragon->id,
                'name' => 'Amphibious',
                'description' => 'The dragon can breathe air and water.',
                'attack_data' => null,
                'sort_order' => 2,
            ],
        ]);

        $traits = DB::table('monster_traits')
            ->where('monster_id', $dragon->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $traits);
        $this->assertEquals('Legendary Resistance', $traits[0]->name);
    }

    // Monster Actions Tests
    public function test_monster_actions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('monster_actions'));
        $this->assertTrue(Schema::hasColumn('monster_actions', 'id'));
        $this->assertTrue(Schema::hasColumn('monster_actions', 'monster_id'));
        $this->assertTrue(Schema::hasColumn('monster_actions', 'action_type'));
        $this->assertTrue(Schema::hasColumn('monster_actions', 'name'));
        $this->assertTrue(Schema::hasColumn('monster_actions', 'description'));
        $this->assertTrue(Schema::hasColumn('monster_actions', 'attack_data'));
        $this->assertTrue(Schema::hasColumn('monster_actions', 'recharge'));
        $this->assertTrue(Schema::hasColumn('monster_actions', 'sort_order'));
    }

    public function test_monster_actions_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('monster_actions', 'created_at'));
        $this->assertFalse(Schema::hasColumn('monster_actions', 'updated_at'));
    }

    public function test_monster_actions_can_track_goblin_attacks(): void
    {
        $small = DB::table('sizes')->where('code', 'S')->first();
        $mm = DB::table('sources')->where('code', 'MM')->first();

        // Create Goblin
        DB::table('monsters')->insert([
            'name' => 'Goblin',
            'size_id' => $small->id,
            'type' => 'Humanoid',
            'alignment' => 'Neutral Evil',
            'armor_class' => 15,
            'hit_points_average' => 7,
            'hit_dice' => '2d6',
            'speed_walk' => 30,
            'strength' => 8,
            'dexterity' => 14,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 8,
            'charisma' => 8,
            'challenge_rating' => '1/4',
            'experience_points' => 50,
        ]);

        $goblin = DB::table('monsters')->where('name', 'Goblin')->first();

        // Add goblin actions
        DB::table('monster_actions')->insert([
            [
                'monster_id' => $goblin->id,
                'action_type' => 'action',
                'name' => 'Scimitar',
                'description' => 'Melee Weapon Attack...',
                'attack_data' => 'Slashing Damage|+4|1d6+2',
                'recharge' => null,
                'sort_order' => 1,
            ],
            [
                'monster_id' => $goblin->id,
                'action_type' => 'bonus_action',
                'name' => 'Nimble Escape',
                'description' => 'The goblin can take the Disengage or Hide action...',
                'attack_data' => null,
                'recharge' => null,
                'sort_order' => 1,
            ],
        ]);

        $actions = DB::table('monster_actions')
            ->where('monster_id', $goblin->id)
            ->get();

        $this->assertCount(2, $actions);

        $scimitar = $actions->firstWhere('name', 'Scimitar');
        $this->assertEquals('action', $scimitar->action_type);
        $this->assertEquals('Slashing Damage|+4|1d6+2', $scimitar->attack_data);
    }

    // Monster Legendary Actions Tests
    public function test_monster_legendary_actions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('monster_legendary_actions'));
        $this->assertTrue(Schema::hasColumn('monster_legendary_actions', 'id'));
        $this->assertTrue(Schema::hasColumn('monster_legendary_actions', 'monster_id'));
        $this->assertTrue(Schema::hasColumn('monster_legendary_actions', 'name'));
        $this->assertTrue(Schema::hasColumn('monster_legendary_actions', 'description'));
        $this->assertTrue(Schema::hasColumn('monster_legendary_actions', 'action_cost'));
        $this->assertTrue(Schema::hasColumn('monster_legendary_actions', 'is_lair_action'));
        $this->assertTrue(Schema::hasColumn('monster_legendary_actions', 'attack_data'));
        $this->assertTrue(Schema::hasColumn('monster_legendary_actions', 'recharge'));
        $this->assertTrue(Schema::hasColumn('monster_legendary_actions', 'sort_order'));
    }

    public function test_monster_legendary_actions_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('monster_legendary_actions', 'created_at'));
        $this->assertFalse(Schema::hasColumn('monster_legendary_actions', 'updated_at'));
    }

    public function test_monster_legendary_actions_can_track_dragon_legendary_actions(): void
    {
        $huge = DB::table('sizes')->where('code', 'H')->first();
        $mm = DB::table('sources')->where('code', 'MM')->first();

        // Create dragon
        DB::table('monsters')->insert([
            'name' => 'Ancient Gold Dragon',
            'size_id' => $huge->id,
            'type' => 'Dragon',
            'alignment' => 'Lawful Good',
            'armor_class' => 22,
            'hit_points_average' => 546,
            'hit_dice' => '28d20 + 252',
            'speed_walk' => 40,
            'speed_fly' => 80,
            'strength' => 30,
            'dexterity' => 14,
            'constitution' => 29,
            'intelligence' => 18,
            'wisdom' => 17,
            'charisma' => 28,
            'challenge_rating' => '24',
            'experience_points' => 62000,
        ]);

        $dragon = DB::table('monsters')->where('name', 'Ancient Gold Dragon')->first();

        // Add legendary actions
        DB::table('monster_legendary_actions')->insert([
            [
                'monster_id' => $dragon->id,
                'name' => 'Detect',
                'description' => 'The dragon makes a Wisdom (Perception) check.',
                'action_cost' => 1,
                'is_lair_action' => false,
                'attack_data' => null,
                'recharge' => null,
                'sort_order' => 1,
            ],
            [
                'monster_id' => $dragon->id,
                'name' => 'Tail Attack',
                'description' => 'The dragon makes a tail attack.',
                'action_cost' => 1,
                'is_lair_action' => false,
                'attack_data' => 'Bludgeoning Damage|+17|2d8+10',
                'recharge' => null,
                'sort_order' => 2,
            ],
            [
                'monster_id' => $dragon->id,
                'name' => 'Wing Attack',
                'description' => 'The dragon beats its wings...',
                'action_cost' => 2, // Costs 2 actions
                'is_lair_action' => false,
                'attack_data' => null,
                'recharge' => null,
                'sort_order' => 3,
            ],
        ]);

        $legendaryActions = DB::table('monster_legendary_actions')
            ->where('monster_id', $dragon->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(3, $legendaryActions);
        $this->assertEquals(1, $legendaryActions[0]->action_cost);
        $this->assertEquals(2, $legendaryActions[2]->action_cost);
    }

    // Monster Spellcasting Tests
    public function test_monster_spellcasting_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('monster_spellcasting'));
        $this->assertTrue(Schema::hasColumn('monster_spellcasting', 'id'));
        $this->assertTrue(Schema::hasColumn('monster_spellcasting', 'monster_id'));
        $this->assertTrue(Schema::hasColumn('monster_spellcasting', 'description'));
        $this->assertTrue(Schema::hasColumn('monster_spellcasting', 'spell_slots'));
        $this->assertTrue(Schema::hasColumn('monster_spellcasting', 'spellcasting_ability'));
        $this->assertTrue(Schema::hasColumn('monster_spellcasting', 'spell_save_dc'));
        $this->assertTrue(Schema::hasColumn('monster_spellcasting', 'spell_attack_bonus'));
    }

    public function test_monster_spellcasting_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('monster_spellcasting', 'created_at'));
        $this->assertFalse(Schema::hasColumn('monster_spellcasting', 'updated_at'));
    }

    public function test_monster_spellcasting_can_track_lich_spellcasting(): void
    {
        $medium = DB::table('sizes')->where('code', 'M')->first();
        $mm = DB::table('sources')->where('code', 'MM')->first();

        // Create Lich
        DB::table('monsters')->insert([
            'name' => 'Lich',
            'size_id' => $medium->id,
            'type' => 'Undead',
            'alignment' => 'Any Evil',
            'armor_class' => 17,
            'hit_points_average' => 135,
            'hit_dice' => '18d8 + 54',
            'speed_walk' => 30,
            'strength' => 11,
            'dexterity' => 16,
            'constitution' => 16,
            'intelligence' => 20,
            'wisdom' => 14,
            'charisma' => 16,
            'challenge_rating' => '21',
            'experience_points' => 33000,
        ]);

        $lich = DB::table('monsters')->where('name', 'Lich')->first();

        // Add spellcasting ability
        DB::table('monster_spellcasting')->insert([
            'monster_id' => $lich->id,
            'description' => 'The lich is an 18th-level spellcaster...',
            'spell_slots' => '0,4,3,3,3,2,1,1,1,1', // Cantrips + 1st through 9th level
            'spellcasting_ability' => 'Intelligence',
            'spell_save_dc' => 20,
            'spell_attack_bonus' => 12,
        ]);

        $spellcasting = DB::table('monster_spellcasting')
            ->where('monster_id', $lich->id)
            ->first();

        $this->assertEquals('Intelligence', $spellcasting->spellcasting_ability);
        $this->assertEquals(20, $spellcasting->spell_save_dc);
        $this->assertEquals('0,4,3,3,3,2,1,1,1,1', $spellcasting->spell_slots);
    }

    // Monster Spells Junction Tests
    public function test_monster_spells_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('monster_spells'));
        $this->assertTrue(Schema::hasColumn('monster_spells', 'monster_id'));
        $this->assertTrue(Schema::hasColumn('monster_spells', 'spell_id'));
        $this->assertTrue(Schema::hasColumn('monster_spells', 'usage_type'));
        $this->assertTrue(Schema::hasColumn('monster_spells', 'usage_limit'));
    }

    public function test_monster_spells_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('monster_spells', 'created_at'));
        $this->assertFalse(Schema::hasColumn('monster_spells', 'updated_at'));
    }

    public function test_monster_spells_table_does_not_have_id_column(): void
    {
        $columns = Schema::getColumnListing('monster_spells');
        $this->assertNotContains('id', $columns);
    }

    public function test_monster_spells_can_associate_spells_with_monster(): void
    {
        $medium = DB::table('sizes')->where('code', 'M')->first();
        $mm = DB::table('sources')->where('code', 'MM')->first();
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();
        $illusion = DB::table('spell_schools')->where('code', 'I')->first();

        // Create Drow Mage
        DB::table('monsters')->insert([
            'name' => 'Drow Mage',
            'size_id' => $medium->id,
            'type' => 'Humanoid',
            'alignment' => 'Neutral Evil',
            'armor_class' => 12,
            'hit_points_average' => 45,
            'hit_dice' => '10d8',
            'speed_walk' => 30,
            'strength' => 9,
            'dexterity' => 14,
            'constitution' => 10,
            'intelligence' => 17,
            'wisdom' => 13,
            'charisma' => 12,
            'challenge_rating' => '7',
            'experience_points' => 2900,
        ]);

        $drowMage = DB::table('monsters')->where('name', 'Drow Mage')->first();

        // Create spells
        DB::table('spells')->insert([
            [
                'name' => 'Dancing Lights',
                'level' => 0,
                'spell_school_id' => $evocation->id,
                'casting_time' => '1 action',
                'range' => '120 feet',
                'components' => 'V, S, M',
                'duration' => 'Concentration, up to 1 minute',
                'needs_concentration' => true,
                'is_ritual' => false,
                'description' => 'You create up to four torch-sized lights...',
            ],
            [
                'name' => 'Invisibility',
                'level' => 2,
                'spell_school_id' => $illusion->id,
                'casting_time' => '1 action',
                'range' => 'Touch',
                'components' => 'V, S, M',
                'duration' => 'Concentration, up to 1 hour',
                'needs_concentration' => true,
                'is_ritual' => false,
                'description' => 'A creature you touch becomes invisible...',
            ],
        ]);

        $dancingLights = DB::table('spells')->where('name', 'Dancing Lights')->first();
        $invisibility = DB::table('spells')->where('name', 'Invisibility')->first();

        // Associate spells with different usage types
        DB::table('monster_spells')->insert([
            [
                'monster_id' => $drowMage->id,
                'spell_id' => $dancingLights->id,
                'usage_type' => 'at_will',
                'usage_limit' => null,
            ],
            [
                'monster_id' => $drowMage->id,
                'spell_id' => $invisibility->id,
                'usage_type' => '1/day',
                'usage_limit' => '1/day',
            ],
        ]);

        $monsterSpells = DB::table('monster_spells')
            ->where('monster_id', $drowMage->id)
            ->get();

        $this->assertCount(2, $monsterSpells);

        $atWillSpell = $monsterSpells->firstWhere('usage_type', 'at_will');
        $limitedSpell = $monsterSpells->firstWhere('usage_type', '1/day');

        $this->assertEquals($dancingLights->id, $atWillSpell->spell_id);
        $this->assertEquals($invisibility->id, $limitedSpell->spell_id);
        $this->assertEquals('1/day', $limitedSpell->usage_limit);
    }
}

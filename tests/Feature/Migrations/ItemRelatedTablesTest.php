<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ItemRelatedTablesTest extends TestCase
{
    use RefreshDatabase;

    // Item Properties Tests
    public function test_item_properties_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('item_properties'));
        $this->assertTrue(Schema::hasColumn('item_properties', 'id'));
        $this->assertTrue(Schema::hasColumn('item_properties', 'code'));
        $this->assertTrue(Schema::hasColumn('item_properties', 'name'));
        $this->assertTrue(Schema::hasColumn('item_properties', 'description'));
    }

    public function test_item_properties_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('item_properties', 'created_at'));
        $this->assertFalse(Schema::hasColumn('item_properties', 'updated_at'));
    }

    public function test_item_properties_table_has_seed_data(): void
    {
        // Should have 11 weapon/armor properties
        $count = DB::table('item_properties')->count();
        $this->assertEquals(11, $count);

        // Verify some key properties exist
        $this->assertNotNull(DB::table('item_properties')->where('code', 'V')->first());
        $this->assertNotNull(DB::table('item_properties')->where('code', 'F')->first());
        $this->assertNotNull(DB::table('item_properties')->where('code', '2H')->first());
    }

    // Item-Property Junction Tests
    public function test_item_property_junction_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('item_property'));
        $this->assertTrue(Schema::hasColumn('item_property', 'item_id'));
        $this->assertTrue(Schema::hasColumn('item_property', 'property_id'));
    }

    public function test_item_property_junction_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('item_property', 'created_at'));
        $this->assertFalse(Schema::hasColumn('item_property', 'updated_at'));
    }

    public function test_item_property_junction_table_does_not_have_id_column(): void
    {
        $columns = Schema::getColumnListing('item_property');
        $this->assertNotContains('id', $columns);
    }

    public function test_item_property_junction_can_associate_properties_with_item(): void
    {
        $weaponType = DB::table('item_types')->where('name', 'Weapon')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();
        $slashing = DB::table('damage_types')->where('name', 'Slashing')->first();

        // Create Longsword
        DB::table('items')->insert([
            'name' => 'Longsword',
            'item_type_id' => $weaponType->id,
            'description' => 'A versatile martial weapon',
            'weight' => 3.0,
            'cost_cp' => 1500,
            'rarity' => 'Common',
            'damage_dice' => '1d8',
            'damage_type_id' => $slashing->id,
            'weapon_range' => 'Melee',
            'versatile_damage' => '1d10',
        ]);

        $longsword = DB::table('items')->where('name', 'Longsword')->first();
        $versatile = DB::table('item_properties')->where('code', 'V')->first();
        $martial = DB::table('item_properties')->where('code', 'M')->first();

        // Associate Versatile and Martial properties with Longsword
        DB::table('item_property')->insert([
            ['item_id' => $longsword->id, 'property_id' => $versatile->id],
            ['item_id' => $longsword->id, 'property_id' => $martial->id],
        ]);

        $properties = DB::table('item_property')
            ->where('item_id', $longsword->id)
            ->get();

        $this->assertCount(2, $properties);
    }

    // Item Abilities Tests
    public function test_item_abilities_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('item_abilities'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'id'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'item_id'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'ability_type'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'spell_id'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'name'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'description'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'charges_cost'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'usage_limit'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'save_dc'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'attack_bonus'));
        $this->assertTrue(Schema::hasColumn('item_abilities', 'sort_order'));
    }

    public function test_item_abilities_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('item_abilities', 'created_at'));
        $this->assertFalse(Schema::hasColumn('item_abilities', 'updated_at'));
    }

    public function test_item_abilities_can_track_spell_ability(): void
    {
        $wandType = DB::table('item_types')->where('name', 'Wand')->first();
        $dmg = DB::table('sources')->where('code', 'DMG')->first();
        $evocation = DB::table('spell_schools')->where('code', 'EV')->first();

        // Create Wand of Fireballs
        DB::table('items')->insert([
            'name' => 'Wand of Fireballs',
            'item_type_id' => $wandType->id,
            'description' => 'This wand has 7 charges...',
            'weight' => 1.0,
            'rarity' => 'Rare',
            'requires_attunement' => true,
        ]);

        $wand = DB::table('items')->where('name', 'Wand of Fireballs')->first();

        // Create Fireball spell if it doesn't exist
        $fireball = DB::table('spells')->where('name', 'Fireball')->first();
        if (!$fireball) {
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
                'description' => 'A bright streak flashes...',
            ]);
            $fireball = DB::table('spells')->where('name', 'Fireball')->first();
        }

        // Add spell ability
        DB::table('item_abilities')->insert([
            'item_id' => $wand->id,
            'ability_type' => 'spell',
            'spell_id' => $fireball->id,
            'name' => null,
            'description' => 'Cast Fireball (3rd level)',
            'charges_cost' => 3,
            'usage_limit' => '7 charges per day',
            'save_dc' => 15,
            'sort_order' => 1,
        ]);

        $ability = DB::table('item_abilities')
            ->where('item_id', $wand->id)
            ->first();

        $this->assertEquals('spell', $ability->ability_type);
        $this->assertEquals($fireball->id, $ability->spell_id);
        $this->assertEquals(3, $ability->charges_cost);
        $this->assertEquals(15, $ability->save_dc);
    }

    public function test_item_abilities_can_track_feature_ability(): void
    {
        $ringType = DB::table('item_types')->where('name', 'Ring')->first();
        $dmg = DB::table('sources')->where('code', 'DMG')->first();

        // Create Ring of Regeneration
        DB::table('items')->insert([
            'name' => 'Ring of Regeneration',
            'item_type_id' => $ringType->id,
            'description' => 'While wearing this ring...',
            'rarity' => 'Very Rare',
            'requires_attunement' => true,
        ]);

        $ring = DB::table('items')->where('name', 'Ring of Regeneration')->first();

        // Add feature ability (not a spell)
        DB::table('item_abilities')->insert([
            'item_id' => $ring->id,
            'ability_type' => 'feature',
            'spell_id' => null,
            'name' => 'Regeneration',
            'description' => 'You regain 1d6 hit points every 10 minutes...',
            'charges_cost' => null,
            'usage_limit' => 'at will',
            'save_dc' => null,
            'attack_bonus' => null,
            'sort_order' => 1,
        ]);

        $ability = DB::table('item_abilities')
            ->where('item_id', $ring->id)
            ->first();

        $this->assertEquals('feature', $ability->ability_type);
        $this->assertNull($ability->spell_id);
        $this->assertEquals('Regeneration', $ability->name);
        $this->assertEquals('at will', $ability->usage_limit);
    }
}

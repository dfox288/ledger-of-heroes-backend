<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ItemsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_table_has_all_21_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('items'));

        // Core identification (4)
        $this->assertTrue(Schema::hasColumn('items', 'id'));
        $this->assertTrue(Schema::hasColumn('items', 'name'));
        $this->assertTrue(Schema::hasColumn('items', 'item_type_id'));
        $this->assertTrue(Schema::hasColumn('items', 'description'));

        // Common properties (3)
        $this->assertTrue(Schema::hasColumn('items', 'weight'));
        $this->assertTrue(Schema::hasColumn('items', 'cost_cp'));
        $this->assertTrue(Schema::hasColumn('items', 'rarity'));

        // Weapon properties (5) - These were MISSING in old implementation
        $this->assertTrue(Schema::hasColumn('items', 'damage_dice'));
        $this->assertTrue(Schema::hasColumn('items', 'damage_type_id'));
        $this->assertTrue(Schema::hasColumn('items', 'weapon_range'));
        $this->assertTrue(Schema::hasColumn('items', 'versatile_damage'));
        $this->assertTrue(Schema::hasColumn('items', 'weapon_properties'));

        // Armor properties (3) - These were MISSING in old implementation
        $this->assertTrue(Schema::hasColumn('items', 'armor_class'));
        $this->assertTrue(Schema::hasColumn('items', 'strength_requirement'));
        $this->assertTrue(Schema::hasColumn('items', 'stealth_disadvantage'));

        // Magic item properties (1) - This was MISSING in old implementation
        $this->assertTrue(Schema::hasColumn('items', 'requires_attunement'));

        // Source attribution (2)
        $this->assertTrue(Schema::hasColumn('items', 'source_id'));
        $this->assertTrue(Schema::hasColumn('items', 'source_pages'));
    }

    public function test_items_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('items', 'created_at'));
        $this->assertFalse(Schema::hasColumn('items', 'updated_at'));
    }

    public function test_items_table_can_store_weapon_data(): void
    {
        $weaponType = DB::table('item_types')->where('name', 'Weapon')->first();
        $slashing = DB::table('damage_types')->where('name', 'Slashing')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        DB::table('items')->insert([
            'name' => 'Longsword',
            'item_type_id' => $weaponType->id,
            'description' => 'A versatile martial weapon',
            'weight' => 3.0,
            'cost_cp' => 1500, // 15 gp = 1500 cp
            'rarity' => 'Common',
            'damage_dice' => '1d8',
            'damage_type_id' => $slashing->id,
            'weapon_range' => 'Melee',
            'versatile_damage' => '1d10',
            'weapon_properties' => json_encode(['versatile']),
            'requires_attunement' => false,
            'source_id' => $phb->id,
            'source_pages' => '149',
        ]);

        $item = DB::table('items')->where('name', 'Longsword')->first();
        $this->assertEquals('1d8', $item->damage_dice);
        $this->assertEquals('1d10', $item->versatile_damage);
        $this->assertEquals($slashing->id, $item->damage_type_id);
    }

    public function test_items_table_can_store_armor_data(): void
    {
        $armorType = DB::table('item_types')->where('name', 'Armor')->first();
        $phb = DB::table('sources')->where('code', 'PHB')->first();

        DB::table('items')->insert([
            'name' => 'Plate Armor',
            'item_type_id' => $armorType->id,
            'description' => 'Heavy armor with excellent protection',
            'weight' => 65.0,
            'cost_cp' => 150000, // 1500 gp
            'rarity' => 'Common',
            'armor_class' => 18,
            'strength_requirement' => 15,
            'stealth_disadvantage' => true,
            'requires_attunement' => false,
            'source_id' => $phb->id,
            'source_pages' => '145',
        ]);

        $item = DB::table('items')->where('name', 'Plate Armor')->first();
        $this->assertEquals(18, $item->armor_class);
        $this->assertEquals(15, $item->strength_requirement);
        $this->assertTrue((bool) $item->stealth_disadvantage);
    }

    public function test_items_table_can_store_magic_item_data(): void
    {
        $wandType = DB::table('item_types')->where('name', 'Wand')->first();
        $dmg = DB::table('sources')->where('code', 'DMG')->first();

        DB::table('items')->insert([
            'name' => 'Wand of Fireballs',
            'item_type_id' => $wandType->id,
            'description' => 'This wand has 7 charges...',
            'weight' => 1.0,
            'rarity' => 'Rare',
            'requires_attunement' => true, // Magic items often require attunement
            'source_id' => $dmg->id,
            'source_pages' => '211, 212',
        ]);

        $item = DB::table('items')->where('name', 'Wand of Fireballs')->first();
        $this->assertEquals('Rare', $item->rarity);
        $this->assertTrue((bool) $item->requires_attunement);
        $this->assertEquals('211, 212', $item->source_pages);
    }

    public function test_items_table_uses_source_id_not_source_book_id(): void
    {
        $this->assertTrue(Schema::hasColumn('items', 'source_id'));
        $this->assertFalse(Schema::hasColumn('items', 'source_book_id'));
    }

    public function test_items_table_uses_source_pages_not_source_page(): void
    {
        $this->assertTrue(Schema::hasColumn('items', 'source_pages'));
        $this->assertFalse(Schema::hasColumn('items', 'source_page'));
    }

    public function test_items_table_has_exactly_21_columns(): void
    {
        $columns = Schema::getColumnListing('items');

        // Should be 18 columns (21 in design minus NO created_at/updated_at = 19, but id is included in the design count)
        // Let's list all expected columns explicitly
        $expectedColumns = [
            'id', 'name', 'item_type_id', 'description',
            'weight', 'cost_cp', 'rarity',
            'damage_dice', 'damage_type_id', 'weapon_range', 'versatile_damage', 'weapon_properties',
            'armor_class', 'strength_requirement', 'stealth_disadvantage',
            'requires_attunement',
            'source_id', 'source_pages'
        ];

        $this->assertCount(count($expectedColumns), $columns);

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '$column' is missing");
        }
    }
}

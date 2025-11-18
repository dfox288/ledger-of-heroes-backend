<?php

namespace Tests\Feature\Migrations;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ItemsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_table_has_all_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('items'));

        // Core identification (5)
        $this->assertTrue(Schema::hasColumn('items', 'id'));
        $this->assertTrue(Schema::hasColumn('items', 'name'));
        $this->assertTrue(Schema::hasColumn('items', 'slug'));
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

        // Magic item properties (2) - This was MISSING in old implementation
        $this->assertTrue(Schema::hasColumn('items', 'requires_attunement'));
        $this->assertTrue(Schema::hasColumn('items', 'is_magic'));

        // Source attribution (2)
        $this->assertFalse(Schema::hasColumn('items', 'source_id'));
        $this->assertFalse(Schema::hasColumn('items', 'source_pages'));
    }

    public function test_items_table_has_timestamps(): void
    {
        $this->assertTrue(Schema::hasColumn('items', 'created_at'));
        $this->assertTrue(Schema::hasColumn('items', 'updated_at'));
    }

    public function test_items_table_can_store_weapon_data(): void
    {
        $item = Item::factory()->weapon()->versatile()->create([
            'name' => 'Longsword',
            'description' => 'A versatile martial weapon',
            'weight' => 3.0,
            'cost_cp' => 1500, // 15 gp = 1500 cp
            'rarity' => 'Common',
        ]);

        $this->assertEquals('1d8', $item->damage_dice);
        $this->assertEquals('1d10', $item->versatile_damage);
        $this->assertNotNull($item->damage_type_id);
    }

    public function test_items_table_can_store_armor_data(): void
    {
        $item = Item::factory()->armor()->create([
            'name' => 'Plate Armor',
            'description' => 'Heavy armor with excellent protection',
            'weight' => 65.0,
            'cost_cp' => 150000, // 1500 gp
            'rarity' => 'Common',
            'armor_class' => 18,
            'strength_requirement' => 15,
            'stealth_disadvantage' => true,
        ]);

        $this->assertEquals(18, $item->armor_class);
        $this->assertEquals(15, $item->strength_requirement);
        $this->assertTrue($item->stealth_disadvantage);
    }

    public function test_items_table_can_store_magic_item_data(): void
    {
        $item = Item::factory()->magic()->create([
            'name' => 'Wand of Fireballs',
            'description' => 'This wand has 7 charges...',
            'weight' => 1.0,
            'rarity' => 'Rare',
            'requires_attunement' => true, // Magic items often require attunement
        ]);

        $this->assertEquals('Rare', $item->rarity);
        $this->assertTrue($item->requires_attunement);
        $this->assertTrue($item->is_magic);
    }

    public function test_items_table_uses_source_id_not_source_book_id(): void
    {
        $this->assertFalse(Schema::hasColumn('items', 'source_id'));
        $this->assertFalse(Schema::hasColumn('items', 'source_book_id'));
    }

    public function test_items_table_uses_source_pages_not_source_page(): void
    {
        $this->assertFalse(Schema::hasColumn('items', 'source_pages'));
        $this->assertFalse(Schema::hasColumn('items', 'source_page'));
    }

    public function test_items_table_has_exactly_20_columns(): void
    {
        $columns = Schema::getColumnListing('items');

        // 20 columns: id, name, slug, item_type_id, description, weight, cost_cp, rarity,
        // damage_dice, damage_type_id, weapon_range, versatile_damage, weapon_properties,
        // armor_class, strength_requirement, stealth_disadvantage, requires_attunement, is_magic,
        // created_at, updated_at
        $expectedColumns = [
            'id', 'name', 'slug', 'item_type_id', 'description',
            'weight', 'cost_cp', 'rarity',
            'damage_dice', 'damage_type_id', 'weapon_range', 'versatile_damage', 'weapon_properties',
            'armor_class', 'strength_requirement', 'stealth_disadvantage',
            'requires_attunement', 'is_magic',
            'created_at', 'updated_at'
        ];

        $this->assertCount(count($expectedColumns), $columns);

        foreach ($expectedColumns as $column) {
            $this->assertContains($column, $columns, "Column '$column' is missing");
        }
    }
}

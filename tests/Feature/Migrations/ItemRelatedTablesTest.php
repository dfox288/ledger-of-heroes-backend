<?php

namespace Tests\Feature\Migrations;

use App\Models\Item;
use App\Models\ItemAbility;
use App\Models\ItemProperty;
use App\Models\Spell;
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
        // Create Longsword using factory
        $longsword = Item::factory()->weapon()->versatile()->create([
            'name' => 'Longsword',
            'description' => 'A versatile martial weapon',
        ]);

        $versatile = ItemProperty::where('code', 'V')->first();
        $martial = ItemProperty::where('code', 'M')->first();

        // Associate Versatile and Martial properties with Longsword
        $longsword->properties()->attach([$versatile->id, $martial->id]);

        $this->assertCount(2, $longsword->properties);
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
        // Create Wand of Fireballs using factory
        $wand = Item::factory()->magic()->create([
            'name' => 'Wand of Fireballs',
            'description' => 'This wand has 7 charges...',
            'rarity' => 'Rare',
            'requires_attunement' => true,
        ]);

        // Create Fireball spell using factory
        $fireball = Spell::factory()->create([
            'name' => 'Fireball',
            'level' => 3,
        ]);

        // Add spell ability using factory
        $ability = ItemAbility::factory()->forItem($wand)->create([
            'ability_type' => 'spell',
            'spell_id' => $fireball->id,
            'name' => null,
            'description' => 'Cast Fireball (3rd level)',
            'charges_cost' => 3,
            'usage_limit' => '7 charges per day',
            'save_dc' => 15,
            'sort_order' => 1,
        ]);

        $this->assertEquals('spell', $ability->ability_type);
        $this->assertEquals($fireball->id, $ability->spell_id);
        $this->assertEquals(3, $ability->charges_cost);
        $this->assertEquals(15, $ability->save_dc);
    }

    public function test_item_abilities_can_track_feature_ability(): void
    {
        // Create Ring of Regeneration using factory
        $ring = Item::factory()->magic()->create([
            'name' => 'Ring of Regeneration',
            'description' => 'While wearing this ring...',
            'rarity' => 'Very Rare',
            'requires_attunement' => true,
        ]);

        // Add feature ability (not a spell) using factory
        $ability = ItemAbility::factory()->forItem($ring)->create([
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

        $this->assertEquals('feature', $ability->ability_type);
        $this->assertNull($ability->spell_id);
        $this->assertEquals('Regeneration', $ability->name);
        $this->assertEquals('at will', $ability->usage_limit);
    }
}

<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LookupTablesMigrationTest extends TestCase
{
    use RefreshDatabase;

    // Damage Types Tests
    public function test_damage_types_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('damage_types'));
        $this->assertTrue(Schema::hasColumns('damage_types', [
            'id', 'code', 'name', 'created_at', 'updated_at'
        ]));
    }

    public function test_damage_types_code_is_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('damage_types')->insert([
            'code' => 'acid',
            'name' => 'Duplicate Type'
        ]);
    }

    public function test_damage_types_table_has_seed_data(): void
    {
        $count = DB::table('damage_types')->count();
        $this->assertEquals(13, $count);

        $types = DB::table('damage_types')->pluck('name', 'code')->toArray();
        $this->assertEquals('Acid', $types['acid']);
        $this->assertEquals('Fire', $types['fire']);
        $this->assertEquals('Thunder', $types['thunder']);
    }

    // Item Types Tests
    public function test_item_types_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('item_types'));
        $this->assertTrue(Schema::hasColumns('item_types', [
            'id', 'code', 'name', 'category', 'created_at', 'updated_at'
        ]));
    }

    public function test_item_types_code_is_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('item_types')->insert([
            'code' => 'M',
            'name' => 'Duplicate Type',
            'category' => 'weapon'
        ]);
    }

    public function test_item_types_table_has_seed_data(): void
    {
        $count = DB::table('item_types')->count();
        $this->assertGreaterThanOrEqual(15, $count);

        $types = DB::table('item_types')->pluck('name', 'code')->toArray();
        $this->assertEquals('Melee Weapon', $types['M']);
        $this->assertEquals('Light Armor', $types['LA']);
        $this->assertEquals('Potion', $types['P']);
    }

    // Item Rarities Tests
    public function test_item_rarities_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('item_rarities'));
        $this->assertTrue(Schema::hasColumns('item_rarities', [
            'id', 'code', 'name', 'created_at', 'updated_at'
        ]));
    }

    public function test_item_rarities_code_is_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('item_rarities')->insert([
            'code' => 'common',
            'name' => 'Duplicate Rarity'
        ]);
    }

    public function test_item_rarities_table_has_seed_data(): void
    {
        $count = DB::table('item_rarities')->count();
        $this->assertEquals(6, $count);

        $rarities = DB::table('item_rarities')->pluck('name', 'code')->toArray();
        $this->assertEquals('Common', $rarities['common']);
        $this->assertEquals('Rare', $rarities['rare']);
        $this->assertEquals('Legendary', $rarities['legendary']);
    }

    // Sizes Tests
    public function test_sizes_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('sizes'));
        $this->assertTrue(Schema::hasColumns('sizes', [
            'id', 'code', 'name', 'created_at', 'updated_at'
        ]));
    }

    public function test_sizes_code_is_unique(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('sizes')->insert([
            'code' => 'M',
            'name' => 'Duplicate Size'
        ]);
    }

    public function test_sizes_table_has_seed_data(): void
    {
        $count = DB::table('sizes')->count();
        $this->assertEquals(6, $count);

        $sizes = DB::table('sizes')->pluck('name', 'code')->toArray();
        $this->assertEquals('Tiny', $sizes['T']);
        $this->assertEquals('Small', $sizes['S']);
        $this->assertEquals('Medium', $sizes['M']);
        $this->assertEquals('Large', $sizes['L']);
        $this->assertEquals('Huge', $sizes['H']);
        $this->assertEquals('Gargantuan', $sizes['G']);
    }
}

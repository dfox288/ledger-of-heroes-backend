<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonstersTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_monsters_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('monsters'));

        // Core identification
        $this->assertTrue(Schema::hasColumn('monsters', 'id'));
        $this->assertTrue(Schema::hasColumn('monsters', 'name'));
        $this->assertTrue(Schema::hasColumn('monsters', 'size_id'));
        $this->assertTrue(Schema::hasColumn('monsters', 'type'));
        $this->assertTrue(Schema::hasColumn('monsters', 'alignment'));

        // Armor Class
        $this->assertTrue(Schema::hasColumn('monsters', 'armor_class'));
        $this->assertTrue(Schema::hasColumn('monsters', 'armor_type'));

        // Hit Points
        $this->assertTrue(Schema::hasColumn('monsters', 'hit_points_average'));
        $this->assertTrue(Schema::hasColumn('monsters', 'hit_dice'));

        // Speed
        $this->assertTrue(Schema::hasColumn('monsters', 'speed_walk'));
        $this->assertTrue(Schema::hasColumn('monsters', 'speed_fly'));
        $this->assertTrue(Schema::hasColumn('monsters', 'speed_swim'));
        $this->assertTrue(Schema::hasColumn('monsters', 'speed_burrow'));
        $this->assertTrue(Schema::hasColumn('monsters', 'speed_climb'));
        $this->assertTrue(Schema::hasColumn('monsters', 'can_hover'));

        // Ability Scores
        $this->assertTrue(Schema::hasColumn('monsters', 'strength'));
        $this->assertTrue(Schema::hasColumn('monsters', 'dexterity'));
        $this->assertTrue(Schema::hasColumn('monsters', 'constitution'));
        $this->assertTrue(Schema::hasColumn('monsters', 'intelligence'));
        $this->assertTrue(Schema::hasColumn('monsters', 'wisdom'));
        $this->assertTrue(Schema::hasColumn('monsters', 'charisma'));

        // Challenge Rating
        $this->assertTrue(Schema::hasColumn('monsters', 'challenge_rating'));
        $this->assertTrue(Schema::hasColumn('monsters', 'experience_points'));

        // Description
        $this->assertTrue(Schema::hasColumn('monsters', 'description'));

        // Source
        $this->assertFalse(Schema::hasColumn('monsters', 'source_id'));
        $this->assertFalse(Schema::hasColumn('monsters', 'source_pages'));
    }

    public function test_monsters_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('monsters', 'created_at'));
        $this->assertFalse(Schema::hasColumn('monsters', 'updated_at'));
    }

    public function test_monsters_table_can_store_basic_monster(): void
    {
        $medium = DB::table('sizes')->where('code', 'M')->first();
        $mm = DB::table('sources')->where('code', 'MM')->first();

        DB::table('monsters')->insert([
            'name' => 'Goblin',
            'size_id' => $medium->id,
            'type' => 'Humanoid',
            'alignment' => 'Neutral Evil',
            'armor_class' => 15,
            'armor_type' => 'leather armor, shield',
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
            'description' => 'Goblins are small, black-hearted creatures...',
        ]);

        $monster = DB::table('monsters')->where('name', 'Goblin')->first();
        $this->assertEquals('Goblin', $monster->name);
        $this->assertEquals(15, $monster->armor_class);
        $this->assertEquals('1/4', $monster->challenge_rating);
    }

    public function test_monsters_table_can_store_flying_monster(): void
    {
        $huge = DB::table('sizes')->where('code', 'H')->first();
        $mm = DB::table('sources')->where('code', 'MM')->first();

        DB::table('monsters')->insert([
            'name' => 'Ancient Red Dragon',
            'size_id' => $huge->id,
            'type' => 'Dragon',
            'alignment' => 'Chaotic Evil',
            'armor_class' => 22,
            'armor_type' => 'natural armor',
            'hit_points_average' => 546,
            'hit_dice' => '28d20 + 252',
            'speed_walk' => 40,
            'speed_fly' => 80,
            'speed_climb' => null,
            'can_hover' => false,
            'strength' => 30,
            'dexterity' => 10,
            'constitution' => 29,
            'intelligence' => 18,
            'wisdom' => 15,
            'charisma' => 23,
            'challenge_rating' => '24',
            'experience_points' => 62000,
            'description' => 'The most covetous of dragons...',
        ]);

        $monster = DB::table('monsters')->where('name', 'Ancient Red Dragon')->first();
        $this->assertEquals(80, $monster->speed_fly);
        $this->assertEquals('24', $monster->challenge_rating);
        $this->assertEquals(62000, $monster->experience_points);
    }

    public function test_monsters_table_supports_fractional_challenge_ratings(): void
    {
        $tiny = DB::table('sizes')->where('code', 'T')->first();
        $mm = DB::table('sources')->where('code', 'MM')->first();

        DB::table('monsters')->insert([
            'name' => 'Rat',
            'size_id' => $tiny->id,
            'type' => 'Beast',
            'alignment' => 'Unaligned',
            'armor_class' => 10,
            'armor_type' => null,
            'hit_points_average' => 1,
            'hit_dice' => '1d4 - 1',
            'speed_walk' => 20,
            'strength' => 2,
            'dexterity' => 11,
            'constitution' => 9,
            'intelligence' => 2,
            'wisdom' => 10,
            'charisma' => 4,
            'challenge_rating' => '0',
            'experience_points' => 10,
            'description' => null,
        ]);

        $monster = DB::table('monsters')->where('name', 'Rat')->first();
        $this->assertEquals('0', $monster->challenge_rating);
        $this->assertEquals(10, $monster->experience_points);
    }

    public function test_monsters_table_uses_correct_naming_conventions(): void
    {
        $this->assertFalse(Schema::hasColumn('monsters', 'source_id'));
        $this->assertFalse(Schema::hasColumn('monsters', 'source_book_id'));
        $this->assertFalse(Schema::hasColumn('monsters', 'source_pages'));
        $this->assertFalse(Schema::hasColumn('monsters', 'source_page'));
    }

    public function test_monsters_table_has_foreign_keys(): void
    {
        $small = DB::table('sizes')->where('code', 'S')->first();
        $mm = DB::table('sources')->where('code', 'MM')->first();

        DB::table('monsters')->insert([
            'name' => 'Test Monster',
            'size_id' => $small->id,
            'type' => 'Beast',
            'alignment' => 'Unaligned',
            'armor_class' => 12,
            'hit_points_average' => 10,
            'hit_dice' => '2d6',
            'speed_walk' => 30,
            'strength' => 10,
            'dexterity' => 10,
            'constitution' => 10,
            'intelligence' => 10,
            'wisdom' => 10,
            'charisma' => 10,
            'challenge_rating' => '1/8',
            'experience_points' => 25,
        ]);

        $monster = DB::table('monsters')->where('name', 'Test Monster')->first();
        $this->assertEquals($small->id, $monster->size_id);
    }
}

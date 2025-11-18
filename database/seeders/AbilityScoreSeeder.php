<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the ability_scores table with the six core ability scores.
 *
 * This seeder populates the six fundamental ability scores in D&D 5e:
 * Strength, Dexterity, Constitution, Intelligence, Wisdom, and Charisma.
 *
 * Note: This seeder must run before SkillSeeder as skills reference ability scores.
 */
class AbilityScoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('ability_scores')->insert([
            ['code' => 'STR', 'name' => 'Strength'],
            ['code' => 'DEX', 'name' => 'Dexterity'],
            ['code' => 'CON', 'name' => 'Constitution'],
            ['code' => 'INT', 'name' => 'Intelligence'],
            ['code' => 'WIS', 'name' => 'Wisdom'],
            ['code' => 'CHA', 'name' => 'Charisma'],
        ]);
    }
}

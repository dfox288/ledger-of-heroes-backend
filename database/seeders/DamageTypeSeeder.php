<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the damage_types table with all D&D damage types.
 *
 * This seeder populates the 13 standard damage types used in D&D 5e,
 * including physical damage (bludgeoning, piercing, slashing) and
 * elemental/magical damage types.
 */
class DamageTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('damage_types')->insert([
            ['code' => 'A', 'name' => 'Acid'],
            ['code' => 'B', 'name' => 'Bludgeoning'],
            ['code' => 'C', 'name' => 'Cold'],
            ['code' => 'F', 'name' => 'Fire'],
            ['code' => 'Fc', 'name' => 'Force'],
            ['code' => 'L', 'name' => 'Lightning'],
            ['code' => 'N', 'name' => 'Necrotic'],
            ['code' => 'P', 'name' => 'Piercing'],
            ['code' => 'Po', 'name' => 'Poison'],
            ['code' => 'Ps', 'name' => 'Psychic'],
            ['code' => 'R', 'name' => 'Radiant'],
            ['code' => 'S', 'name' => 'Slashing'],
            ['code' => 'T', 'name' => 'Thunder'],
        ]);
    }
}

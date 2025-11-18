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
            ['name' => 'Acid'],
            ['name' => 'Bludgeoning'],
            ['name' => 'Cold'],
            ['name' => 'Fire'],
            ['name' => 'Force'],
            ['name' => 'Lightning'],
            ['name' => 'Necrotic'],
            ['name' => 'Piercing'],
            ['name' => 'Poison'],
            ['name' => 'Psychic'],
            ['name' => 'Radiant'],
            ['name' => 'Slashing'],
            ['name' => 'Thunder'],
        ]);
    }
}

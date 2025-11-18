<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the spell_schools table with the eight schools of magic.
 *
 * This seeder populates the eight traditional D&D schools of magic:
 * Abjuration, Conjuration, Divination, Enchantment, Evocation,
 * Illusion, Necromancy, and Transmutation.
 */
class SpellSchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('spell_schools')->insert([
            ['code' => 'A', 'name' => 'Abjuration'],
            ['code' => 'C', 'name' => 'Conjuration'],
            ['code' => 'D', 'name' => 'Divination'],
            ['code' => 'EN', 'name' => 'Enchantment'],
            ['code' => 'EV', 'name' => 'Evocation'],
            ['code' => 'I', 'name' => 'Illusion'],
            ['code' => 'N', 'name' => 'Necromancy'],
            ['code' => 'T', 'name' => 'Transmutation'],
        ]);
    }
}

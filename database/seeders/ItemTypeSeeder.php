<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the item_types table with standard D&D item categories.
 *
 * This seeder populates the primary item type categories used for
 * equipment classification in D&D 5e, including weapons, armor,
 * magical items, and adventuring gear.
 */
class ItemTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('item_types')->insert([
            ['name' => 'Weapon'],
            ['name' => 'Armor'],
            ['name' => 'Potion'],
            ['name' => 'Scroll'],
            ['name' => 'Wand'],
            ['name' => 'Ring'],
            ['name' => 'Rod'],
            ['name' => 'Staff'],
            ['name' => 'Wondrous Item'],
            ['name' => 'Adventuring Gear'],
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemTypeSeeder extends Seeder
{
    public function run(): void
    {
        $itemTypes = [
            ['code' => 'A', 'name' => 'Ammunition', 'description' => 'Arrows, bolts, sling bullets, and other projectiles'],
            ['code' => 'M', 'name' => 'Melee Weapon', 'description' => 'Weapons used for close combat'],
            ['code' => 'R', 'name' => 'Ranged Weapon', 'description' => 'Weapons used for ranged combat'],
            ['code' => 'LA', 'name' => 'Light Armor', 'description' => 'Armor that allows full dexterity bonus'],
            ['code' => 'MA', 'name' => 'Medium Armor', 'description' => 'Armor that allows partial dexterity bonus'],
            ['code' => 'HA', 'name' => 'Heavy Armor', 'description' => 'Armor that provides no dexterity bonus'],
            ['code' => 'S', 'name' => 'Shield', 'description' => 'Protective shield'],
            ['code' => 'G', 'name' => 'Adventuring Gear', 'description' => 'General equipment and supplies'],
            ['code' => '$', 'name' => 'Trade Goods', 'description' => 'Gems, art objects, and valuable commodities'],
            ['code' => 'P', 'name' => 'Potion', 'description' => 'Potions, oils, and elixirs'],
            ['code' => 'RD', 'name' => 'Rod', 'description' => 'Magic rods'],
            ['code' => 'RG', 'name' => 'Ring', 'description' => 'Magic rings'],
            ['code' => 'WD', 'name' => 'Wand', 'description' => 'Magic wands'],
            ['code' => 'SC', 'name' => 'Scroll', 'description' => 'Spell scrolls'],
            ['code' => 'ST', 'name' => 'Staff', 'description' => 'Quarterstaffs and magic staffs'],
            ['code' => 'W', 'name' => 'Wondrous Item', 'description' => 'Miscellaneous magic items'],
        ];

        foreach ($itemTypes as $itemType) {
            DB::table('item_types')->updateOrInsert(
                ['code' => $itemType['code']],
                $itemType
            );
        }
    }
}

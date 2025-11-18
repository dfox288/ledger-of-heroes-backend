<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemPropertySeeder extends Seeder
{
    public function run(): void
    {
        $properties = [
            ['code' => 'A', 'name' => 'Ammunition', 'description' => 'Weapon requires ammunition to make a ranged attack'],
            ['code' => 'F', 'name' => 'Finesse', 'description' => 'Use DEX modifier instead of STR for attack and damage rolls'],
            ['code' => 'H', 'name' => 'Heavy', 'description' => 'Small creatures have disadvantage on attack rolls'],
            ['code' => 'L', 'name' => 'Light', 'description' => 'Can be used for two-weapon fighting'],
            ['code' => 'LD', 'name' => 'Loading', 'description' => 'Can fire only one piece of ammunition per action'],
            ['code' => 'R', 'name' => 'Reach', 'description' => 'Adds 5 feet to reach for attack'],
            ['code' => 'T', 'name' => 'Thrown', 'description' => 'Can be thrown to make a ranged attack'],
            ['code' => '2H', 'name' => 'Two-Handed', 'description' => 'Requires two hands to use'],
            ['code' => 'V', 'name' => 'Versatile', 'description' => 'Can be used with one or two hands'],
            ['code' => 'M', 'name' => 'Martial', 'description' => 'Requires martial weapon proficiency'],
            ['code' => 'S', 'name' => 'Special', 'description' => 'Has special rules described in item description'],
        ];

        foreach ($properties as $property) {
            DB::table('item_properties')->updateOrInsert(
                ['code' => $property['code']],
                $property
            );
        }
    }
}

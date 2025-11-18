<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the item_properties table with weapon properties.
 *
 * This seeder populates weapon properties used in D&D 5e combat,
 * such as Versatile, Finesse, Reach, etc. Each property includes
 * a code, name, and detailed description of its mechanical effects.
 */
class ItemPropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('item_properties')->insert([
            [
                'code' => 'V',
                'name' => 'Versatile',
                'description' => 'This weapon can be used with one or two hands. A damage value in parentheses appears with the property—the damage when the weapon is used with two hands to make a melee attack.',
            ],
            [
                'code' => 'M',
                'name' => 'Martial',
                'description' => 'This weapon requires martial weapon proficiency to use.',
            ],
            [
                'code' => 'A',
                'name' => 'Ammunition',
                'description' => 'You can use a weapon that has the ammunition property to make a ranged attack only if you have ammunition to fire from the weapon. Each time you attack with the weapon, you expend one piece of ammunition.',
            ],
            [
                'code' => 'LD',
                'name' => 'Loading',
                'description' => 'Because of the time required to load this weapon, you can fire only one piece of ammunition from it when you use an action, bonus action, or reaction to fire it, regardless of the number of attacks you can normally make.',
            ],
            [
                'code' => 'F',
                'name' => 'Finesse',
                'description' => 'When making an attack with a finesse weapon, you use your choice of your Strength or Dexterity modifier for the attack and damage rolls. You must use the same modifier for both rolls.',
            ],
            [
                'code' => 'H',
                'name' => 'Heavy',
                'description' => 'Small creatures have disadvantage on attack rolls with heavy weapons. A heavy weapon\'s size and bulk make it too large for a Small creature to use effectively.',
            ],
            [
                'code' => 'L',
                'name' => 'Light',
                'description' => 'A light weapon is small and easy to handle, making it ideal for use when fighting with two weapons.',
            ],
            [
                'code' => 'R',
                'name' => 'Reach',
                'description' => 'This weapon adds 5 feet to your reach when you attack with it, as well as when determining your reach for opportunity attacks with it.',
            ],
            [
                'code' => 'T',
                'name' => 'Thrown',
                'description' => 'If a weapon has the thrown property, you can throw the weapon to make a ranged attack. If the weapon is a melee weapon, you use the same ability modifier for that attack roll and damage roll that you would use for a melee attack with the weapon.',
            ],
            [
                'code' => '2H',
                'name' => 'Two-Handed',
                'description' => 'This weapon requires two hands when you attack with it.',
            ],
            [
                'code' => 'S',
                'name' => 'Special',
                'description' => 'A weapon with the special property has unusual rules governing its use, explained in the weapon\'s description.',
            ],
        ]);
    }
}

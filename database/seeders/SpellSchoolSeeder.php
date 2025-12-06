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
 *
 * Descriptions sourced from Player's Handbook (2014) p.203.
 */
class SpellSchoolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $schools = [
            [
                'code' => 'A',
                'name' => 'Abjuration',
                'description' => 'Abjuration spells are protective in nature, though some of them have aggressive uses. They create magical barriers, negate harmful effects, harm trespassers, or banish creatures to other planes of existence.',
            ],
            [
                'code' => 'C',
                'name' => 'Conjuration',
                'description' => 'Conjuration spells involve the transportation of objects and creatures from one location to another. Some spells summon creatures or objects to the caster\'s side, whereas others allow the caster to teleport to another location.',
            ],
            [
                'code' => 'D',
                'name' => 'Divination',
                'description' => 'Divination spells reveal information, whether in the form of secrets long forgotten, glimpses of the future, the locations of hidden things, the truth behind illusions, or visions of distant people or places.',
            ],
            [
                'code' => 'EN',
                'name' => 'Enchantment',
                'description' => 'Enchantment spells affect the minds of others, influencing or controlling their behavior. Such spells can make enemies see the caster as a friend, force creatures to take a course of action, or even control another creature like a puppet.',
            ],
            [
                'code' => 'EV',
                'name' => 'Evocation',
                'description' => 'Evocation spells manipulate magical energy to produce a desired effect. Some call up blasts of fire or lightning. Others channel positive energy to heal wounds.',
            ],
            [
                'code' => 'I',
                'name' => 'Illusion',
                'description' => 'Illusion spells deceive the senses or minds of others. They cause people to see things that are not there, to miss things that are there, to hear phantom noises, or to remember things that never happened.',
            ],
            [
                'code' => 'N',
                'name' => 'Necromancy',
                'description' => 'Necromancy spells manipulate the energies of life and death. Such spells can grant an extra reserve of life force, drain the life energy from another creature, create the undead, or even bring the dead back to life.',
            ],
            [
                'code' => 'T',
                'name' => 'Transmutation',
                'description' => 'Transmutation spells change the properties of a creature, object, or environment. They might turn an enemy into a harmless creature, bolster the strength of an ally, make an object move at the caster\'s command, or enhance a creature\'s innate healing abilities.',
            ],
        ];

        foreach ($schools as $school) {
            DB::table('spell_schools')->updateOrInsert(
                ['code' => $school['code']],
                $school
            );
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MulticlassSpellSlotSeeder extends Seeder
{
    public function run(): void
    {
        // PHB p165 - Multiclass Spellcaster: Spell Slots per Spell Level
        $slots = [
            ['caster_level' => 1, 'slots_1st' => 2, 'slots_2nd' => 0, 'slots_3rd' => 0, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 2, 'slots_1st' => 3, 'slots_2nd' => 0, 'slots_3rd' => 0, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 3, 'slots_1st' => 4, 'slots_2nd' => 2, 'slots_3rd' => 0, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 4, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 0, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 5, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 2, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 6, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 0, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 7, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 1, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 8, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 2, 'slots_5th' => 0, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 9, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 1, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 10, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 0, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 11, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 12, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 0, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 13, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 14, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 0, 'slots_9th' => 0],
            ['caster_level' => 15, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 1, 'slots_9th' => 0],
            ['caster_level' => 16, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 1, 'slots_9th' => 0],
            ['caster_level' => 17, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 2, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 1, 'slots_9th' => 1],
            ['caster_level' => 18, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 3, 'slots_6th' => 1, 'slots_7th' => 1, 'slots_8th' => 1, 'slots_9th' => 1],
            ['caster_level' => 19, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 3, 'slots_6th' => 2, 'slots_7th' => 1, 'slots_8th' => 1, 'slots_9th' => 1],
            ['caster_level' => 20, 'slots_1st' => 4, 'slots_2nd' => 3, 'slots_3rd' => 3, 'slots_4th' => 3, 'slots_5th' => 3, 'slots_6th' => 2, 'slots_7th' => 2, 'slots_8th' => 1, 'slots_9th' => 1],
        ];

        DB::table('multiclass_spell_slots')->insert($slots);
    }
}

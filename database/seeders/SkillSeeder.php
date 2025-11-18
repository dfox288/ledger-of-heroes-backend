<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the skills table with all 18 D&D skills.
 *
 * This seeder populates the 18 standard skills in D&D 5e, each linked
 * to its governing ability score. Skills are used for ability checks
 * and determine character proficiencies.
 *
 * Note: This seeder depends on AbilityScoreSeeder running first.
 */
class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get ability score IDs for FK references
        $str = DB::table('ability_scores')->where('code', 'STR')->value('id');
        $dex = DB::table('ability_scores')->where('code', 'DEX')->value('id');
        $con = DB::table('ability_scores')->where('code', 'CON')->value('id');
        $int = DB::table('ability_scores')->where('code', 'INT')->value('id');
        $wis = DB::table('ability_scores')->where('code', 'WIS')->value('id');
        $cha = DB::table('ability_scores')->where('code', 'CHA')->value('id');

        DB::table('skills')->insert([
            ['name' => 'Acrobatics', 'ability_score_id' => $dex],
            ['name' => 'Animal Handling', 'ability_score_id' => $wis],
            ['name' => 'Arcana', 'ability_score_id' => $int],
            ['name' => 'Athletics', 'ability_score_id' => $str],
            ['name' => 'Deception', 'ability_score_id' => $cha],
            ['name' => 'History', 'ability_score_id' => $int],
            ['name' => 'Insight', 'ability_score_id' => $wis],
            ['name' => 'Intimidation', 'ability_score_id' => $cha],
            ['name' => 'Investigation', 'ability_score_id' => $int],
            ['name' => 'Medicine', 'ability_score_id' => $wis],
            ['name' => 'Nature', 'ability_score_id' => $int],
            ['name' => 'Perception', 'ability_score_id' => $wis],
            ['name' => 'Performance', 'ability_score_id' => $cha],
            ['name' => 'Persuasion', 'ability_score_id' => $cha],
            ['name' => 'Religion', 'ability_score_id' => $int],
            ['name' => 'Sleight of Hand', 'ability_score_id' => $dex],
            ['name' => 'Stealth', 'ability_score_id' => $dex],
            ['name' => 'Survival', 'ability_score_id' => $wis],
        ]);
    }
}

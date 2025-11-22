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
            ['name' => 'Acrobatics', 'slug' => 'acrobatics', 'ability_score_id' => $dex],
            ['name' => 'Animal Handling', 'slug' => 'animal-handling', 'ability_score_id' => $wis],
            ['name' => 'Arcana', 'slug' => 'arcana', 'ability_score_id' => $int],
            ['name' => 'Athletics', 'slug' => 'athletics', 'ability_score_id' => $str],
            ['name' => 'Deception', 'slug' => 'deception', 'ability_score_id' => $cha],
            ['name' => 'History', 'slug' => 'history', 'ability_score_id' => $int],
            ['name' => 'Insight', 'slug' => 'insight', 'ability_score_id' => $wis],
            ['name' => 'Intimidation', 'slug' => 'intimidation', 'ability_score_id' => $cha],
            ['name' => 'Investigation', 'slug' => 'investigation', 'ability_score_id' => $int],
            ['name' => 'Medicine', 'slug' => 'medicine', 'ability_score_id' => $wis],
            ['name' => 'Nature', 'slug' => 'nature', 'ability_score_id' => $int],
            ['name' => 'Perception', 'slug' => 'perception', 'ability_score_id' => $wis],
            ['name' => 'Performance', 'slug' => 'performance', 'ability_score_id' => $cha],
            ['name' => 'Persuasion', 'slug' => 'persuasion', 'ability_score_id' => $cha],
            ['name' => 'Religion', 'slug' => 'religion', 'ability_score_id' => $int],
            ['name' => 'Sleight of Hand', 'slug' => 'sleight-of-hand', 'ability_score_id' => $dex],
            ['name' => 'Stealth', 'slug' => 'stealth', 'ability_score_id' => $dex],
            ['name' => 'Survival', 'slug' => 'survival', 'ability_score_id' => $wis],
        ]);
    }
}

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
            ['name' => 'Acrobatics', 'slug' => 'acrobatics', 'full_slug' => 'core:acrobatics', 'ability_score_id' => $dex],
            ['name' => 'Animal Handling', 'slug' => 'animal-handling', 'full_slug' => 'core:animal-handling', 'ability_score_id' => $wis],
            ['name' => 'Arcana', 'slug' => 'arcana', 'full_slug' => 'core:arcana', 'ability_score_id' => $int],
            ['name' => 'Athletics', 'slug' => 'athletics', 'full_slug' => 'core:athletics', 'ability_score_id' => $str],
            ['name' => 'Deception', 'slug' => 'deception', 'full_slug' => 'core:deception', 'ability_score_id' => $cha],
            ['name' => 'History', 'slug' => 'history', 'full_slug' => 'core:history', 'ability_score_id' => $int],
            ['name' => 'Insight', 'slug' => 'insight', 'full_slug' => 'core:insight', 'ability_score_id' => $wis],
            ['name' => 'Intimidation', 'slug' => 'intimidation', 'full_slug' => 'core:intimidation', 'ability_score_id' => $cha],
            ['name' => 'Investigation', 'slug' => 'investigation', 'full_slug' => 'core:investigation', 'ability_score_id' => $int],
            ['name' => 'Medicine', 'slug' => 'medicine', 'full_slug' => 'core:medicine', 'ability_score_id' => $wis],
            ['name' => 'Nature', 'slug' => 'nature', 'full_slug' => 'core:nature', 'ability_score_id' => $int],
            ['name' => 'Perception', 'slug' => 'perception', 'full_slug' => 'core:perception', 'ability_score_id' => $wis],
            ['name' => 'Performance', 'slug' => 'performance', 'full_slug' => 'core:performance', 'ability_score_id' => $cha],
            ['name' => 'Persuasion', 'slug' => 'persuasion', 'full_slug' => 'core:persuasion', 'ability_score_id' => $cha],
            ['name' => 'Religion', 'slug' => 'religion', 'full_slug' => 'core:religion', 'ability_score_id' => $int],
            ['name' => 'Sleight of Hand', 'slug' => 'sleight-of-hand', 'full_slug' => 'core:sleight-of-hand', 'ability_score_id' => $dex],
            ['name' => 'Stealth', 'slug' => 'stealth', 'full_slug' => 'core:stealth', 'ability_score_id' => $dex],
            ['name' => 'Survival', 'slug' => 'survival', 'full_slug' => 'core:survival', 'ability_score_id' => $wis],
        ]);
    }
}

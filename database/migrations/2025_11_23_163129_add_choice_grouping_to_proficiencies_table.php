<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Groups proficiency choices together so that related options can be identified.
     *
     * Example: Fighter skill proficiencies with numSkills=2:
     * - choice_group: "skill_choice_1"
     * - 8 skill options each with choice_option: 1-8
     * - quantity field now stores how many to pick from the group (was duplicated per skill)
     *
     * Before: 8 skills each saying "is_choice=true, quantity=2" (confusing)
     * After: 8 skills in "skill_choice_1" group, pick 2 from the set (clear)
     */
    public function up(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->string('choice_group')->nullable()->after('is_choice')
                ->comment('Groups related proficiency options together (e.g., "skill_choice_1")');
            $table->integer('choice_option')->nullable()->after('choice_group')
                ->comment('Option number within a choice group (sequential: 1, 2, 3...)');
            // Note: quantity field remains but changes meaning:
            // OLD: duplicated on each skill ("this skill is part of choose-2")
            // NEW: stored once per group ("choose 2 from this group")
            // This will be handled in the importer logic

            $table->index('choice_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proficiencies', function (Blueprint $table) {
            $table->dropIndex(['choice_group']);
            $table->dropColumn(['choice_group', 'choice_option']);
        });
    }
};

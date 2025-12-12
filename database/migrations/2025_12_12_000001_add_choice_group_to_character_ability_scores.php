<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add choice_group column to character_ability_scores table.
 * This links resolved ability score choices to EntityChoice records.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add choice_group column (keeping modifier_id for backwards compatibility)
        if (! Schema::hasColumn('character_ability_scores', 'choice_group')) {
            Schema::table('character_ability_scores', function (Blueprint $table) {
                $table->string('choice_group', 50)->nullable()->after('source');
                $table->index('choice_group', 'char_ability_score_choice_group_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('character_ability_scores', function (Blueprint $table) {
            $table->dropIndex('char_ability_score_choice_group_idx');
            $table->dropColumn('choice_group');
        });
    }
};

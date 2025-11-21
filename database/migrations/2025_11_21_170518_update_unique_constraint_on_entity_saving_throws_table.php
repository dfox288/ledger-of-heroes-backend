<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entity_saving_throws', function (Blueprint $table) {
            // Drop old unique constraint
            $table->dropUnique('unique_entity_ability_save');

            // Add new unique constraint that includes save_modifier
            $table->unique(
                ['entity_type', 'entity_id', 'ability_score_id', 'is_initial_save', 'save_modifier'],
                'unique_entity_ability_save_modifier'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_saving_throws', function (Blueprint $table) {
            // Drop new unique constraint
            $table->dropUnique('unique_entity_ability_save_modifier');

            // Restore old unique constraint (without save_modifier)
            $table->unique(
                ['entity_type', 'entity_id', 'ability_score_id', 'is_initial_save'],
                'unique_entity_ability_save'
            );
        });
    }
};

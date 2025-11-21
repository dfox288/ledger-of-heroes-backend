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
        // MySQL doesn't support direct ENUM modification, so we need to recreate the column
        // First drop the unique constraint that includes save_modifier
        Schema::table('entity_saving_throws', function (Blueprint $table) {
            $table->dropUnique('unique_entity_ability_save_modifier');
        });

        // Then drop and recreate the column with new enum values
        Schema::table('entity_saving_throws', function (Blueprint $table) {
            $table->dropColumn('save_modifier');
        });

        Schema::table('entity_saving_throws', function (Blueprint $table) {
            $table->enum('save_modifier', ['none', 'advantage', 'disadvantage'])
                ->default('none')
                ->after('is_initial_save')
                ->comment('none = standard save; advantage = grants advantage; disadvantage = imposes disadvantage; NULL = undetermined (data quality flag)');
        });

        // Finally, recreate the unique constraint with the new column
        Schema::table('entity_saving_throws', function (Blueprint $table) {
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
            $table->dropColumn('save_modifier');
        });

        Schema::table('entity_saving_throws', function (Blueprint $table) {
            $table->enum('save_modifier', ['advantage', 'disadvantage'])
                ->nullable()
                ->after('is_initial_save');
        });
    }
};

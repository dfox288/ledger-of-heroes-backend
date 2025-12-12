<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add action_cost column to optional_features and class_features tables.
 *
 * Tracks D&D 5e action economy (action, bonus action, reaction, free, passive).
 * For optional_features, parsed from casting_time during import.
 * For class_features, nullable (XML doesn't have explicit action cost data).
 *
 * Related: Issue #505
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('optional_features', function (Blueprint $table) {
            $table->string('action_cost', 20)->nullable()->after('casting_time')
                ->comment('action, bonus_action, reaction, free, passive');
        });

        Schema::table('class_features', function (Blueprint $table) {
            $table->string('action_cost', 20)->nullable()->after('resets_on')
                ->comment('action, bonus_action, reaction, free, passive');
        });
    }

    public function down(): void
    {
        Schema::table('optional_features', function (Blueprint $table) {
            $table->dropColumn('action_cost');
        });

        Schema::table('class_features', function (Blueprint $table) {
            $table->dropColumn('action_cost');
        });
    }
};

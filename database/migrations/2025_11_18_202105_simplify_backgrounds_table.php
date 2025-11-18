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
        Schema::table('backgrounds', function (Blueprint $table) {
            // Drop obsolete columns (data will use polymorphic tables instead)
            $table->dropColumn([
                'description',
                'skill_proficiencies',
                'tool_proficiencies',
                'languages',
                'equipment',
                'feature_name',
                'feature_description',
            ]);

            // Add unique constraint on name
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backgrounds', function (Blueprint $table) {
            // Restore columns for rollback
            $table->text('description')->nullable();
            $table->text('skill_proficiencies')->nullable();
            $table->text('tool_proficiencies')->nullable();
            $table->text('languages')->nullable();
            $table->text('equipment')->nullable();
            $table->text('feature_name')->nullable();
            $table->text('feature_description')->nullable();

            // Drop unique constraint
            $table->dropUnique(['name']);
        });
    }
};

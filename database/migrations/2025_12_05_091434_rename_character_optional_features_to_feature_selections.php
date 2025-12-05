<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renames character_optional_features to feature_selections as part of
     * the Character API Phase 2 restructuring (Issue #173).
     *
     * "Feature Selections" better describes what these are: class-granted
     * choices like invocations, maneuvers, and metamagic options.
     */
    public function up(): void
    {
        Schema::rename('character_optional_features', 'feature_selections');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('feature_selections', 'character_optional_features');
    }
};

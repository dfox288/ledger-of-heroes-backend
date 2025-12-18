<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds infrastructure for subclass variant choices (terrain, totem animals, etc.):
     * - character_classes.subclass_choices: JSON column to store variant selections
     * - class_features.choice_group: marks mutually exclusive variant features
     */
    public function up(): void
    {
        // Add JSON column to character_classes for storing variant choices
        // Example: {"terrain": "arctic"} for Circle of the Land
        Schema::table('character_classes', function (Blueprint $table) {
            $table->json('subclass_choices')->nullable()->after('subclass_slug');
        });

        // Add choice_group column to class_features to identify variant features
        // Features with the same choice_group are mutually exclusive
        Schema::table('class_features', function (Blueprint $table) {
            $table->string('choice_group', 100)->nullable()->after('is_multiclass_only');
        });

        // Mark Circle of the Land terrain features with choice_group = 'terrain'
        $this->markTerrainFeatures();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('character_classes', function (Blueprint $table) {
            $table->dropColumn('subclass_choices');
        });

        Schema::table('class_features', function (Blueprint $table) {
            $table->dropColumn('choice_group');
        });
    }

    /**
     * Mark Circle of the Land terrain features with their choice group.
     */
    private function markTerrainFeatures(): void
    {
        $terrainFeatures = [
            'Arctic (Circle of the Land)',
            'Coast (Circle of the Land)',
            'Desert (Circle of the Land)',
            'Forest (Circle of the Land)',
            'Grassland (Circle of the Land)',
            'Mountain (Circle of the Land)',
            'Swamp (Circle of the Land)',
            'Underdark (Circle of the Land)',
        ];

        DB::table('class_features')
            ->whereIn('feature_name', $terrainFeatures)
            ->update(['choice_group' => 'terrain']);
    }
};

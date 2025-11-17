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
        // Ability Score Bonuses - polymorphic relationship with FK-based polymorphism
        Schema::create('ability_score_bonuses', function (Blueprint $table) {
            // No surrogate ID - composite PK instead
            $table->unsignedBigInteger('ability_score_id'); // FK to ability_scores
            $table->unsignedTinyInteger('bonus'); // +1, +2, etc.

            // Polymorphic FKs - only ONE should be set per row
            // Use 0 as default for nullable semantics to work with composite PK
            $table->unsignedBigInteger('race_id')->default(0); // FK to races, 0 = not applicable
            $table->unsignedBigInteger('class_id')->default(0); // FK to classes, 0 = not applicable
            $table->unsignedBigInteger('background_id')->default(0); // FK to backgrounds, 0 = not applicable
            $table->unsignedBigInteger('feat_id')->default(0); // FK to feats, 0 = not applicable

            // Foreign keys - only create for non-zero values (handled at application level)
            // Cannot use FK constraints with 0 defaults as valid "null" semantics
            // We'll enforce data integrity at the application layer

            // Composite primary key - ensures unique ability per source
            $table->primary(['ability_score_id', 'race_id', 'class_id', 'background_id', 'feat_id'], 'ability_bonuses_pk');

            // Index the polymorphic FKs for query performance
            $table->index('race_id');
            $table->index('class_id');
            $table->index('background_id');
            $table->index('feat_id');

            // NO timestamps
        });

        // Skill Proficiencies - polymorphic relationship with FK-based polymorphism
        Schema::create('skill_proficiencies', function (Blueprint $table) {
            // No surrogate ID - composite PK instead
            $table->unsignedBigInteger('skill_id'); // FK to skills

            // Polymorphic FKs - only ONE should be set per row
            // Use 0 as default for nullable semantics to work with composite PK
            $table->unsignedBigInteger('race_id')->default(0); // FK to races, 0 = not applicable
            $table->unsignedBigInteger('class_id')->default(0); // FK to classes, 0 = not applicable
            $table->unsignedBigInteger('background_id')->default(0); // FK to backgrounds, 0 = not applicable
            $table->unsignedBigInteger('feat_id')->default(0); // FK to feats, 0 = not applicable

            // Foreign keys - only create for non-zero values (handled at application level)
            // Cannot use FK constraints with 0 defaults as valid "null" semantics
            // We'll enforce data integrity at the application layer

            // Composite primary key
            $table->primary(['skill_id', 'race_id', 'class_id', 'background_id', 'feat_id'], 'skill_proficiencies_pk');

            // Index the polymorphic FKs for query performance
            $table->index('race_id');
            $table->index('class_id');
            $table->index('background_id');
            $table->index('feat_id');

            // NO timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skill_proficiencies');
        Schema::dropIfExists('ability_score_bonuses');
    }
};

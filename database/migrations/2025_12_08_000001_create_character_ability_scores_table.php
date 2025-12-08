<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates character_ability_scores table for tracking racial ability score bonuses.
 * Supports both fixed and choice-based ability score modifiers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_ability_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('ability_score_code', 3);
            $table->tinyInteger('bonus')->unsigned();
            $table->string('source', 20)->default('race');
            $table->unsignedBigInteger('modifier_id')->nullable();
            $table->timestamp('created_at')->nullable();

            // Include modifier_id to allow same ability from different modifiers (e.g., fixed +2 CHA and choice +1 CHA)
            $table->unique(['character_id', 'ability_score_code', 'modifier_id'], 'char_ability_score_unique');
            $table->index('ability_score_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_ability_scores');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tracks which optional features (invocations, maneuvers, metamagic, etc.)
     * a character has selected. Each row represents one selected feature.
     */
    public function up(): void
    {
        Schema::create('character_optional_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('optional_feature_id')->constrained()->cascadeOnDelete();

            // Track which class/subclass granted this choice
            // NULL class means the choice came from a feat or other non-class source
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('subclass_name')->nullable(); // "Battle Master", "Way of Four Elements"

            // When the choice was made (character level at time of selection)
            $table->unsignedTinyInteger('level_acquired')->default(1);

            // Usage tracking for features with limited uses (some invocations, etc.)
            $table->unsignedTinyInteger('uses_remaining')->nullable();
            $table->unsignedTinyInteger('max_uses')->nullable();

            $table->timestamps();

            // A character can only select each optional feature once
            $table->unique(['character_id', 'optional_feature_id'], 'char_opt_feature_unique');

            // Efficient lookups by character and class
            $table->index('character_id');
            $table->index(['character_id', 'class_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_optional_features');
    }
};

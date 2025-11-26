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
        Schema::create('optional_features', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('feature_type');  // eldritch_invocation, maneuver, etc.

            // Requirements
            $table->unsignedTinyInteger('level_requirement')->nullable();
            $table->string('prerequisite_text')->nullable();  // Original text for display

            // Content
            $table->text('description');

            // Spell-like properties (for Elemental Disciplines, Arcane Shots)
            $table->string('casting_time')->nullable();
            $table->string('range')->nullable();
            $table->string('duration')->nullable();
            $table->foreignId('spell_school_id')->nullable()->constrained('spell_schools')->nullOnDelete();

            // Resource costs
            $table->string('resource_type')->nullable();  // ki_points, sorcery_points, etc.
            $table->unsignedTinyInteger('resource_cost')->nullable();

            // Indexes
            $table->index('feature_type');
            $table->index('level_requirement');
            $table->index('resource_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optional_features');
    }
};

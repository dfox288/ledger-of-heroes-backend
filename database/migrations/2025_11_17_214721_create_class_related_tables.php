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
        // Class Level Progression Table - Spell slot progression
        Schema::create('class_level_progression', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id'); // FK to classes
            $table->unsignedTinyInteger('level'); // 1-20
            $table->unsignedTinyInteger('cantrips_known')->nullable(); // 0 or null for non-casters
            $table->unsignedTinyInteger('spell_slots_1st')->nullable();
            $table->unsignedTinyInteger('spell_slots_2nd')->nullable();
            $table->unsignedTinyInteger('spell_slots_3rd')->nullable();
            $table->unsignedTinyInteger('spell_slots_4th')->nullable();
            $table->unsignedTinyInteger('spell_slots_5th')->nullable();
            $table->unsignedTinyInteger('spell_slots_6th')->nullable();
            $table->unsignedTinyInteger('spell_slots_7th')->nullable();
            $table->unsignedTinyInteger('spell_slots_8th')->nullable();
            $table->unsignedTinyInteger('spell_slots_9th')->nullable();

            // Foreign key
            $table->foreign('class_id')
                ->references('id')
                ->on('classes')
                ->onDelete('cascade');

            // Unique constraint
            $table->unique(['class_id', 'level']);

            // Indexes
            $table->index('class_id');
            $table->index('level');

            // NO timestamps
        });

        // Class Features Table - Features gained at each level
        Schema::create('class_features', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id'); // FK to classes (can be base class or subclass)
            $table->unsignedTinyInteger('level'); // 1-20
            $table->string('feature_name', 255); // "Spellcasting", "Magical Tinkering", "Extra Attack"
            $table->boolean('is_optional')->default(false); // True for optional features (multiclass, variant rules)
            $table->text('description'); // Full feature text
            $table->unsignedSmallInteger('sort_order')->default(0); // Display order for multiple features at same level

            // Foreign key
            $table->foreign('class_id')
                ->references('id')
                ->on('classes')
                ->onDelete('cascade');

            // Indexes
            $table->index('class_id');
            $table->index('level');
            $table->index('is_optional');

            // NO timestamps
        });

        // Class Counters Table - Resource tracking like Ki, Rage
        Schema::create('class_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_id'); // FK to classes
            $table->unsignedTinyInteger('level'); // 1-20
            $table->string('counter_name', 100); // "Spells Known", "Rage Uses", "Ki Points", "Defensive Field"
            $table->unsignedSmallInteger('counter_value'); // The numeric value at this level
            $table->char('reset_timing', 1)->nullable(); // 'L' = long rest, 'S' = short rest, null = doesn't reset

            // Foreign key
            $table->foreign('class_id')
                ->references('id')
                ->on('classes')
                ->onDelete('cascade');

            // Indexes
            $table->index('class_id');
            $table->index('level');
            $table->index('counter_name');

            // NO timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_counters');
        Schema::dropIfExists('class_features');
        Schema::dropIfExists('class_level_progression');
    }
};

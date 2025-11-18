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
        Schema::create('spell_effects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('spell_id'); // FK to spells
            $table->string('effect_type', 50); // "damage", "healing", "buff", "debuff", "control", etc.
            $table->text('description')->nullable(); // Additional effect details
            $table->string('dice_formula', 50)->nullable(); // "1d6", "8d6", etc.
            $table->integer('base_value')->nullable(); // Base value for effects without dice
            $table->string('scaling_type', 50)->nullable(); // "character_level", "spell_slot", "none"
            $table->integer('min_character_level')->nullable(); // Minimum character level for scaling
            $table->integer('min_spell_slot')->nullable(); // Minimum spell slot level
            $table->string('scaling_increment', 50)->nullable(); // How the effect scales (e.g., "1d6", "+1")

            // Foreign keys
            $table->foreign('spell_id')
                ->references('id')
                ->on('spells')
                ->onDelete('cascade');

            // Indexes
            $table->index('spell_id');
            $table->index('effect_type');

            // NO timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spell_effects');
    }
};

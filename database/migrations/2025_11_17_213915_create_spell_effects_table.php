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
            $table->string('damage_dice', 50)->nullable(); // "1d6", "8d6", etc.
            $table->unsignedBigInteger('damage_type_id')->nullable(); // FK to damage_types
            $table->unsignedBigInteger('save_ability_id')->nullable(); // FK to ability_scores (DEX save, WIS save, etc.)
            $table->string('save_effect', 100)->nullable(); // "half damage", "negates", etc.
            $table->text('description')->nullable(); // Additional effect details

            // Foreign keys
            $table->foreign('spell_id')
                  ->references('id')
                  ->on('spells')
                  ->onDelete('cascade');

            $table->foreign('damage_type_id')
                  ->references('id')
                  ->on('damage_types')
                  ->onDelete('restrict');

            $table->foreign('save_ability_id')
                  ->references('id')
                  ->on('ability_scores')
                  ->onDelete('restrict');

            // Indexes
            $table->index('spell_id');
            $table->index('effect_type');
            $table->index('damage_type_id');

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

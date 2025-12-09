<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds starting wealth fields for the gold alternative to starting equipment.
     * D&D 5e allows players to choose starting gold instead of fixed equipment.
     *
     * Example: Fighter has "5d4x10" = 5d4 dice × 10 gp multiplier = 50-200 gp (avg 125)
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            // The dice formula (e.g., "5d4", "2d4")
            $table->string('starting_wealth_dice', 10)->nullable()->after('spellcasting_ability_id');
            // The multiplier (e.g., 10 for "×10 gp", 1 for Monk's "5d4 gp")
            $table->unsignedSmallInteger('starting_wealth_multiplier')->nullable()->after('starting_wealth_dice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn(['starting_wealth_dice', 'starting_wealth_multiplier']);
        });
    }
};

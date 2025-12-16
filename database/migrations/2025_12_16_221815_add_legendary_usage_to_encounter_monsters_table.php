<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds legendary usage tracking columns for DM screen boss fights.
     * - legendary_actions_used: Resets at monster's turn start
     * - legendary_resistance_used: Consumed throughout the day
     */
    public function up(): void
    {
        Schema::table('encounter_monsters', function (Blueprint $table) {
            $table->unsignedTinyInteger('legendary_actions_used')->default(0)->after('max_hp');
            $table->unsignedTinyInteger('legendary_resistance_used')->default(0)->after('legendary_actions_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('encounter_monsters', function (Blueprint $table) {
            $table->dropColumn(['legendary_actions_used', 'legendary_resistance_used']);
        });
    }
};

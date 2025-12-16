<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds structured legendary metadata to the monsters table.
 *
 * These fields provide direct access to legendary action count and
 * legendary resistance uses without parsing intro text at runtime.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/724
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monsters', function (Blueprint $table) {
            // Number of legendary actions the monster can take per round
            // Extracted from intro text like "Legendary Actions (3/Turn)"
            $table->tinyInteger('legendary_actions_per_round')
                ->unsigned()
                ->nullable()
                ->after('charisma');

            // Number of legendary resistance uses per day
            // Extracted from trait name like "Legendary Resistance (3/Day)"
            $table->tinyInteger('legendary_resistance_uses')
                ->unsigned()
                ->nullable()
                ->after('legendary_actions_per_round');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monsters', function (Blueprint $table) {
            $table->dropColumn(['legendary_actions_per_round', 'legendary_resistance_uses']);
        });
    }
};

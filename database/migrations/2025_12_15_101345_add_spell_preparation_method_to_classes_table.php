<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds spell_preparation_method column to track how a class handles spells:
     * - 'known': Permanently known spells (Bard, Sorcerer, Warlock, Ranger)
     * - 'spellbook': Spells learned via spellbook, prepare subset (Wizard)
     * - 'prepared': Prepare from full class list (Cleric, Druid, Paladin, Artificer)
     * - null: Non-spellcasters
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('spell_preparation_method', 20)->nullable()
                ->after('spellcasting_ability_id')
                ->comment('known, spellbook, prepared, or null for non-casters');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('spell_preparation_method');
        });
    }
};

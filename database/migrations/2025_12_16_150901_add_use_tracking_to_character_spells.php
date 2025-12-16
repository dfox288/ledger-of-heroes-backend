<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds limited-use tracking to character spells.
 *
 * This supports innate spellcasting from racial traits (e.g., Drow Magic)
 * where spells can only be cast a limited number of times per rest.
 *
 * Examples:
 * - Drow Magic: Faerie Fire 1/long rest at level 3
 * - Tiefling Infernal Legacy: Hellish Rebuke 1/long rest at level 3
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_spells', function (Blueprint $table) {
            $table->smallInteger('max_uses')->nullable()->after('source');
            $table->smallInteger('uses_remaining')->nullable()->after('max_uses');
            $table->string('resets_on', 20)->nullable()->after('uses_remaining');
        });
    }

    public function down(): void
    {
        Schema::table('character_spells', function (Blueprint $table) {
            $table->dropColumn(['max_uses', 'uses_remaining', 'resets_on']);
        });
    }
};

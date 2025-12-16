<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds reset timing to entity_traits for limited-use racial traits.
 *
 * Examples:
 * - Dragonborn Breath Weapon: recharges on short rest
 * - Drow innate spells: once per long rest
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entity_traits', function (Blueprint $table) {
            $table->string('resets_on', 20)->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('entity_traits', function (Blueprint $table) {
            $table->dropColumn('resets_on');
        });
    }
};

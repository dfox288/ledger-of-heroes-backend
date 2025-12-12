<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds attack_data column to support monster traits migration from
     * monster_traits table. This column stores parsed attack information
     * for monster special traits that include attack mechanics.
     */
    public function up(): void
    {
        Schema::table('entity_traits', function (Blueprint $table) {
            $table->text('attack_data')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_traits', function (Blueprint $table) {
            $table->dropColumn('attack_data');
        });
    }
};

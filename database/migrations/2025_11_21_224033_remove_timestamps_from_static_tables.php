<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove created_at and updated_at columns from static reference data tables.
     * These tables contain D&D 5e game content that doesn't require change tracking.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('entity_spells', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->timestamps();
        });

        Schema::table('entity_spells', function (Blueprint $table) {
            $table->timestamps();
        });
    }
};

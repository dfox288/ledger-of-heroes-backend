<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Changes counter_value from unsigned to signed to support -1 for "Unlimited".
     * This is needed for Barbarian's Rage at level 20 (PHB p.49: "your rage becomes unlimited").
     */
    public function up(): void
    {
        Schema::table('class_counters', function (Blueprint $table) {
            // Change from unsignedSmallInteger to signed smallInteger
            // This allows -1 to represent "Unlimited"
            $table->smallInteger('counter_value')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_counters', function (Blueprint $table) {
            // Revert to unsigned (will fail if -1 values exist)
            $table->unsignedSmallInteger('counter_value')->change();
        });
    }
};

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
        Schema::table('class_level_progression', function (Blueprint $table) {
            $table->unsignedTinyInteger('spells_known')
                ->nullable()
                ->after('spell_slots_9th')
                ->comment('Number of spells known at this level (for limited-known casters)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_level_progression', function (Blueprint $table) {
            $table->dropColumn('spells_known');
        });
    }
};

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
        Schema::table('entity_saving_throws', function (Blueprint $table) {
            $table->unsignedTinyInteger('dc')->nullable()->after('ability_score_id')
                ->comment('Difficulty Class for the saving throw (8-30, typically 10-20)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_saving_throws', function (Blueprint $table) {
            $table->dropColumn('dc');
        });
    }
};

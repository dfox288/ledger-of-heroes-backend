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
        Schema::table('entity_modifiers', function (Blueprint $table) {
            $table->foreignId('secondary_ability_score_id')
                ->nullable()
                ->after('ability_score_id')
                ->constrained('ability_scores')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_modifiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('secondary_ability_score_id');
        });
    }
};

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
        Schema::table('entity_spells', function (Blueprint $table) {
            // Composite index for spell filtering queries
            // Optimizes: Monster::whereHas('entitySpells', fn($q) => $q->where('spell_id', X))
            // Query pattern: WHERE reference_type = 'App\Models\Monster' AND spell_id = X
            $table->index(['reference_type', 'spell_id'], 'idx_entity_spells_type_spell');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_spells', function (Blueprint $table) {
            $table->dropIndex('idx_entity_spells_type_spell');
        });
    }
};

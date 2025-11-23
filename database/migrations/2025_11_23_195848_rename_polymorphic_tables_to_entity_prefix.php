<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Rename polymorphic tables to use entity_ prefix for consistency
        Schema::rename('proficiencies', 'entity_proficiencies');
        Schema::rename('traits', 'entity_traits');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to original table names
        Schema::rename('entity_proficiencies', 'proficiencies');
        Schema::rename('entity_traits', 'traits');
    }
};

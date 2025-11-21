<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Renames 'modifiers' table to 'entity_modifiers' for consistency with other
     * polymorphic relationship tables (entity_sources, entity_saving_throws, etc.)
     */
    public function up(): void
    {
        Schema::rename('modifiers', 'entity_modifiers');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('entity_modifiers', 'modifiers');
    }
};

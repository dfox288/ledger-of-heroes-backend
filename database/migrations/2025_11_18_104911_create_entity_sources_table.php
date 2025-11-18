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
        Schema::create('entity_sources', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference to any entity (spell, item, race, etc.)
            $table->string('entity_type', 50); // 'spell', 'item', 'race', 'feat', 'background', 'class', 'monster'
            $table->unsignedBigInteger('entity_id');

            // Reference to sources table
            $table->unsignedBigInteger('source_id');

            // Page numbers specific to this source (e.g., "148, 150", "211-213")
            $table->string('pages', 100)->nullable();

            // Foreign key to sources
            $table->foreign('source_id')
                  ->references('id')
                  ->on('sources')
                  ->onDelete('cascade');

            // Indexes for efficient querying
            $table->index(['entity_type', 'entity_id']); // Find all sources for an entity
            $table->index('source_id'); // Find all entities from a source

            // Unique constraint - same entity can't reference same source twice
            $table->unique(['entity_type', 'entity_id', 'source_id'], 'entity_sources_unique');

            // NO timestamps - static compendium data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_sources');
    }
};

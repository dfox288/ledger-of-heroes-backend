<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates polymorphic pivot table for entity senses (Monster, Race).
     */
    public function up(): void
    {
        Schema::create('entity_senses', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference to entity (Monster, Race)
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');

            // Sense type reference
            $table->unsignedBigInteger('sense_id');

            // Sense details
            $table->unsignedSmallInteger('range_feet'); // 60, 120, etc.
            $table->boolean('is_limited')->default(false); // "blind beyond this radius"
            $table->string('notes', 100)->nullable(); // Form restrictions, deafened conditions, etc.

            // Foreign keys
            $table->foreign('sense_id')
                ->references('id')
                ->on('senses')
                ->onDelete('cascade');

            // Indexes
            $table->index(['reference_type', 'reference_id']);
            $table->index('sense_id');

            // Unique constraint: one sense type per entity
            $table->unique(['reference_type', 'reference_id', 'sense_id'], 'entity_sense_unique');

            // No timestamps - static compendium data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_senses');
    }
};

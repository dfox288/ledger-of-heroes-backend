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
        Schema::create('entity_conditions', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference to entity (spell, monster, item, etc.)
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');

            // Condition reference
            $table->unsignedBigInteger('condition_id');

            // Effect type: inflicts, immunity, resistance, advantage
            $table->string('effect_type');

            // Foreign keys
            $table->foreign('condition_id')
                  ->references('id')
                  ->on('conditions')
                  ->onDelete('cascade');

            // Indexes
            $table->index(['reference_type', 'reference_id']);
            $table->index('condition_id');
            $table->index('effect_type');

            // No timestamps - static compendium data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_conditions');
    }
};

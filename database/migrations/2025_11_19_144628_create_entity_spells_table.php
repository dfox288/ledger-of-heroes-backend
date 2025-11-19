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
        Schema::create('entity_spells', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');

            // Spell details
            $table->unsignedBigInteger('spell_id');
            $table->unsignedBigInteger('ability_score_id')->nullable();
            $table->integer('level_requirement')->nullable();
            $table->string('usage_limit')->nullable();
            $table->boolean('is_cantrip')->default(false);

            // Foreign keys
            $table->foreign('spell_id')->references('id')->on('spells')->onDelete('cascade');
            $table->foreign('ability_score_id')->references('id')->on('ability_scores')->onDelete('set null');

            // Indexes
            $table->index(['reference_type', 'reference_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_spells');
    }
};

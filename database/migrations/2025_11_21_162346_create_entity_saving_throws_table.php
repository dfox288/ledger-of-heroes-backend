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
        Schema::create('entity_saving_throws', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship (spell, monster, item, etc.)
            $table->morphs('entity');

            // Which ability score is required
            $table->foreignId('ability_score_id')
                ->constrained('ability_scores')
                ->cascadeOnDelete();

            // What happens on successful save
            $table->string('save_effect', 50)->nullable()->comment('negates, half_damage, ends_effect, reduced_duration');

            // Initial save vs recurring save
            $table->boolean('is_initial_save')->default(true)->comment('false = recurring save (e.g., end of each turn)');

            $table->timestamps();

            // Prevent duplicate entries
            $table->unique(['entity_type', 'entity_id', 'ability_score_id', 'is_initial_save'], 'unique_entity_ability_save');

            // Index for ability score queries (entity index already created by morphs())
            $table->index('ability_score_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_saving_throws');
    }
};

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
        Schema::create('proficiencies', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference
            $table->string('reference_type'); // 'race', 'background', 'class', 'feat'
            $table->unsignedBigInteger('reference_id');

            // Proficiency type
            $table->string('proficiency_type'); // 'skill', 'weapon', 'armor', 'tool', 'language', 'saving_throw'

            // Nullable FKs depending on proficiency_type
            $table->unsignedBigInteger('skill_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('ability_score_id')->nullable();
            $table->string('proficiency_name')->nullable(); // For free-form entries

            // Foreign keys
            $table->foreign('skill_id')
                  ->references('id')
                  ->on('skills')
                  ->onDelete('cascade');

            $table->foreign('item_id')
                  ->references('id')
                  ->on('items')
                  ->onDelete('cascade');

            $table->foreign('ability_score_id')
                  ->references('id')
                  ->on('ability_scores')
                  ->onDelete('cascade');

            // Indexes
            $table->index(['reference_type', 'reference_id']);
            $table->index('proficiency_type');
            $table->index('skill_id');
            $table->index('item_id');

            // NO timestamps - static compendium data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proficiencies');
    }
};

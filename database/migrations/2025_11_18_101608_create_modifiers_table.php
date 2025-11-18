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
        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference
            $table->string('reference_type'); // 'feat', 'race', 'item', 'class', 'background', 'spell'
            $table->unsignedBigInteger('reference_id');

            // Modifier data
            $table->string('modifier_category'); // 'ability_score', 'skill', 'speed', 'initiative', 'armor_class', 'damage_resistance', 'saving_throw'

            // Nullable FKs depending on modifier_category
            $table->unsignedBigInteger('ability_score_id')->nullable();
            $table->unsignedBigInteger('skill_id')->nullable();
            $table->unsignedBigInteger('damage_type_id')->nullable();

            $table->string('value'); // '+1', '+2', '+5', '+10 feet', 'advantage', 'proficiency', 'resistance'
            $table->text('condition')->nullable(); // "while wearing armor", "against magic"

            // Foreign keys
            $table->foreign('ability_score_id')->references('id')->on('ability_scores')->onDelete('cascade');
            $table->foreign('skill_id')->references('id')->on('skills')->onDelete('cascade');
            $table->foreign('damage_type_id')->references('id')->on('damage_types')->onDelete('cascade');

            // Indexes
            $table->index(['reference_type', 'reference_id']);
            $table->index('modifier_category');

            // NO timestamps - static compendium data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifiers');
    }
};

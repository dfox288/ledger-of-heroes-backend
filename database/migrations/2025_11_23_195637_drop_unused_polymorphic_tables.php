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
        // Drop unused tables that were never populated
        // These tables were replaced by entity_modifiers, proficiencies, and entity_spells
        Schema::dropIfExists('ability_score_bonuses');
        Schema::dropIfExists('skill_proficiencies');
        Schema::dropIfExists('monster_spellcasting');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate ability_score_bonuses table (FK-based polymorphism pattern - deprecated)
        Schema::create('ability_score_bonuses', function (Blueprint $table) {
            $table->unsignedBigInteger('ability_score_id');
            $table->unsignedTinyInteger('bonus');
            $table->unsignedBigInteger('race_id')->default(0);
            $table->unsignedBigInteger('class_id')->default(0);
            $table->unsignedBigInteger('background_id')->default(0);
            $table->unsignedBigInteger('feat_id')->default(0);
            $table->primary(['ability_score_id', 'race_id', 'class_id', 'background_id', 'feat_id'], 'ability_bonuses_pk');
            $table->index('race_id');
            $table->index('class_id');
            $table->index('background_id');
            $table->index('feat_id');
        });

        // Recreate skill_proficiencies table (FK-based polymorphism pattern - deprecated)
        Schema::create('skill_proficiencies', function (Blueprint $table) {
            $table->unsignedBigInteger('skill_id');
            $table->unsignedBigInteger('race_id')->default(0);
            $table->unsignedBigInteger('class_id')->default(0);
            $table->unsignedBigInteger('background_id')->default(0);
            $table->unsignedBigInteger('feat_id')->default(0);
            $table->primary(['skill_id', 'race_id', 'class_id', 'background_id', 'feat_id'], 'skill_proficiencies_pk');
            $table->index('race_id');
            $table->index('class_id');
            $table->index('background_id');
            $table->index('feat_id');
        });

        // Recreate monster_spellcasting table
        Schema::create('monster_spellcasting', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monster_id');
            $table->text('description');
            $table->string('spell_slots', 100)->nullable();
            $table->string('spellcasting_ability', 50)->nullable();
            $table->unsignedTinyInteger('spell_save_dc')->nullable();
            $table->tinyInteger('spell_attack_bonus')->nullable();
            $table->foreign('monster_id')->references('id')->on('monsters')->onDelete('cascade');
            $table->index('monster_id');
        });
    }
};

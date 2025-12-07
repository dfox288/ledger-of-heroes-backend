<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the characters table (player characters).
 * Uses slug-based references instead of foreign keys for portability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 255);
            // Slug-based references for portability (export/import)
            $table->string('race_slug', 150)->nullable();
            $table->string('background_slug', 150)->nullable();
            $table->integer('experience_points')->unsigned()->default(0);
            $table->tinyInteger('strength')->unsigned()->nullable();
            $table->tinyInteger('dexterity')->unsigned()->nullable();
            $table->tinyInteger('constitution')->unsigned()->nullable();
            $table->tinyInteger('intelligence')->unsigned()->nullable();
            $table->tinyInteger('wisdom')->unsigned()->nullable();
            $table->tinyInteger('charisma')->unsigned()->nullable();
            $table->string('alignment', 255)->nullable();
            $table->boolean('has_inspiration')->default(false);
            $table->string('ability_score_method', 20)->default('manual');
            $table->smallInteger('max_hit_points')->unsigned()->nullable();
            $table->smallInteger('current_hit_points')->unsigned()->nullable();
            $table->smallInteger('temp_hit_points')->unsigned()->default(0);
            $table->tinyInteger('death_save_successes')->unsigned()->default(0);
            $table->tinyInteger('death_save_failures')->unsigned()->default(0);
            $table->tinyInteger('armor_class_override')->unsigned()->nullable();
            $table->tinyInteger('asi_choices_remaining')->unsigned()->default(0);
            $table->string('portrait_url', 2048)->nullable();
            $table->timestamps();

            $table->index('race_slug');
            $table->index('background_slug');
        });

        // Multiclass spell slot progression table (static data)
        Schema::create('multiclass_spell_slots', function (Blueprint $table) {
            $table->tinyInteger('caster_level')->unsigned()->primary();
            $table->tinyInteger('slots_1st')->unsigned()->default(0);
            $table->tinyInteger('slots_2nd')->unsigned()->default(0);
            $table->tinyInteger('slots_3rd')->unsigned()->default(0);
            $table->tinyInteger('slots_4th')->unsigned()->default(0);
            $table->tinyInteger('slots_5th')->unsigned()->default(0);
            $table->tinyInteger('slots_6th')->unsigned()->default(0);
            $table->tinyInteger('slots_7th')->unsigned()->default(0);
            $table->tinyInteger('slots_8th')->unsigned()->default(0);
            $table->tinyInteger('slots_9th')->unsigned()->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multiclass_spell_slots');
        Schema::dropIfExists('characters');
    }
};

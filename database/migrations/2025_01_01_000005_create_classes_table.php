<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates classes and all class-related tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->string('name', 100);
            $table->unsignedBigInteger('parent_class_id')->nullable();
            $table->tinyInteger('hit_die')->unsigned();
            $table->text('description');
            $table->string('archetype', 100)->nullable();
            $table->string('primary_ability', 100)->nullable();
            $table->foreignId('spellcasting_ability_id')->nullable()
                ->constrained('ability_scores')->restrictOnDelete();

            $table->index('parent_class_id');
            $table->index('hit_die');
            $table->index('slug', 'classes_slug_idx');

            $table->foreign('parent_class_id')->references('id')->on('classes')->cascadeOnDelete();
        });

        Schema::create('class_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('level')->unsigned();
            $table->string('feature_name', 255);
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_multiclass_only')->default(false)
                ->comment('Features only relevant when multiclassing into this class');
            $table->unsignedBigInteger('parent_feature_id')->nullable();
            $table->text('description');
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->string('resets_on', 255)->nullable();

            $table->index('level');
            $table->index('is_optional');
            $table->index('parent_feature_id');

            $table->foreign('parent_feature_id')->references('id')->on('class_features')->nullOnDelete();
        });

        Schema::create('class_feature_special_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_feature_id')->constrained()->cascadeOnDelete();
            $table->string('tag', 255);

            $table->index('tag');
        });

        Schema::create('class_level_progression', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('level')->unsigned();
            $table->tinyInteger('cantrips_known')->unsigned()->nullable();
            $table->tinyInteger('spell_slots_1st')->unsigned()->nullable();
            $table->tinyInteger('spell_slots_2nd')->unsigned()->nullable();
            $table->tinyInteger('spell_slots_3rd')->unsigned()->nullable();
            $table->tinyInteger('spell_slots_4th')->unsigned()->nullable();
            $table->tinyInteger('spell_slots_5th')->unsigned()->nullable();
            $table->tinyInteger('spell_slots_6th')->unsigned()->nullable();
            $table->tinyInteger('spell_slots_7th')->unsigned()->nullable();
            $table->tinyInteger('spell_slots_8th')->unsigned()->nullable();
            $table->tinyInteger('spell_slots_9th')->unsigned()->nullable();
            $table->tinyInteger('spells_known')->unsigned()->nullable()
                ->comment('Number of spells known at this level (for limited-known casters)');

            $table->unique(['class_id', 'level']);
            $table->index('level');
        });

        Schema::create('class_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('level')->unsigned();
            $table->string('counter_name', 100);
            $table->smallInteger('counter_value');
            $table->char('reset_timing', 1)->nullable();

            $table->index('level');
            $table->index('counter_name');
        });

        Schema::create('class_spells', function (Blueprint $table) {
            $table->foreignId('class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spell_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('level_learned')->unsigned()->nullable();

            $table->primary(['class_id', 'spell_id']);
            $table->index('spell_id');
            $table->index('level_learned');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_spells');
        Schema::dropIfExists('class_counters');
        Schema::dropIfExists('class_level_progression');
        Schema::dropIfExists('class_feature_special_tags');
        Schema::dropIfExists('class_features');
        Schema::dropIfExists('classes');
    }
};

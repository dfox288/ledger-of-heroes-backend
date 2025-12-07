<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates all character-related child tables.
 * Uses slug-based references for portability (export/import).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Character Classes (multiclass support)
        Schema::create('character_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('class_slug', 150);
            $table->string('subclass_slug', 150)->nullable();
            $table->tinyInteger('level')->unsigned()->default(1);
            $table->boolean('is_primary')->default(false);
            $table->tinyInteger('order')->unsigned()->default(1);
            $table->tinyInteger('hit_dice_spent')->unsigned()->default(0);
            $table->timestamps();

            $table->unique(['character_id', 'class_slug']);
            $table->index('class_slug');
        });

        // Character Spells
        Schema::create('character_spells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('spell_slug', 150);
            // Using string instead of enum for SQLite compatibility
            $table->string('preparation_status', 20)->default('known');
            $table->string('source', 20)->default('class');
            $table->tinyInteger('level_acquired')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['character_id', 'spell_slug']);
            $table->index('spell_slug');
        });

        // Character Equipment
        Schema::create('character_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('item_slug', 150)->nullable();
            $table->string('custom_name', 255)->nullable();
            $table->text('custom_description')->nullable();
            $table->smallInteger('quantity')->unsigned()->default(1);
            $table->boolean('equipped')->default(false);
            // Using string instead of enum for SQLite compatibility
            $table->string('location', 20)->default('backpack');
            $table->timestamp('created_at')->nullable();

            $table->index('item_slug');
        });

        // Character Languages
        Schema::create('character_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('language_slug', 150);
            // Using string instead of enum for SQLite compatibility
            $table->string('source', 20)->default('race');
            $table->timestamp('created_at')->nullable();

            $table->unique(['character_id', 'language_slug']);
            $table->index('language_slug');
        });

        // Character Proficiencies
        Schema::create('character_proficiencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('proficiency_type_slug', 150)->nullable();
            $table->string('skill_slug', 150)->nullable();
            // Using string instead of enum for SQLite compatibility
            $table->string('source', 20)->default('class');
            $table->string('choice_group', 255)->nullable();
            $table->boolean('expertise')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index('skill_slug');
            $table->index('proficiency_type_slug');
        });

        // Character Conditions
        Schema::create('character_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('condition_slug', 150);
            $table->tinyInteger('level')->unsigned()->nullable();
            $table->string('source', 255)->nullable();
            $table->string('duration', 255)->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'condition_slug']);
            $table->index('condition_slug');
        });

        // Character Features (tracked features with uses)
        Schema::create('character_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('feature_type', 50);
            $table->unsignedBigInteger('feature_id')->nullable();
            $table->string('feature_slug', 150)->nullable();
            // Using string instead of enum for SQLite compatibility
            $table->string('source', 20)->default('class');
            $table->tinyInteger('level_acquired')->unsigned()->default(1);
            $table->tinyInteger('uses_remaining')->unsigned()->nullable();
            $table->tinyInteger('max_uses')->unsigned()->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['feature_type', 'feature_id']);
            $table->index('feature_slug');
        });

        // Feature Selections (optional features chosen by character)
        Schema::create('feature_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('optional_feature_slug', 150);
            $table->string('class_slug', 150)->nullable();
            $table->string('subclass_name', 255)->nullable();
            $table->tinyInteger('level_acquired')->unsigned()->default(1);
            $table->tinyInteger('uses_remaining')->unsigned()->nullable();
            $table->tinyInteger('max_uses')->unsigned()->nullable();
            $table->timestamps();

            $table->unique(['character_id', 'optional_feature_slug'], 'char_opt_feature_slug_unique');
            $table->index('optional_feature_slug');
            $table->index('class_slug');
        });

        // Character Notes
        Schema::create('character_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->string('category', 20);
            $table->string('title', 255)->nullable();
            $table->text('content');
            $table->smallInteger('sort_order')->unsigned()->default(0);
            $table->timestamps();

            $table->index(['character_id', 'category']);
        });

        // Character Spell Slots
        Schema::create('character_spell_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('spell_level')->unsigned();
            $table->tinyInteger('max_slots')->unsigned()->default(0);
            $table->tinyInteger('used_slots')->unsigned()->default(0);
            $table->string('slot_type', 255)->default('standard');
            $table->timestamps();

            $table->unique(['character_id', 'spell_level', 'slot_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_spell_slots');
        Schema::dropIfExists('character_notes');
        Schema::dropIfExists('feature_selections');
        Schema::dropIfExists('character_features');
        Schema::dropIfExists('character_conditions');
        Schema::dropIfExists('character_proficiencies');
        Schema::dropIfExists('character_languages');
        Schema::dropIfExists('character_equipment');
        Schema::dropIfExists('character_spells');
        Schema::dropIfExists('character_classes');
    }
};

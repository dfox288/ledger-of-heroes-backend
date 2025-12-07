<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates all foundational lookup/reference tables.
 * These are static tables with few rows that other tables reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sources - publication sources (PHB, DMG, etc.)
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 255);
            $table->string('publisher', 100)->default('Wizards of the Coast');
            $table->smallInteger('publication_year')->unsigned()->nullable();
            $table->string('url', 500)->nullable();
            $table->string('author', 255)->nullable();
            $table->string('artist', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('category', 100)->nullable();
            $table->text('description')->nullable();
        });

        // Ability Scores (STR, DEX, CON, INT, WIS, CHA)
        Schema::create('ability_scores', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 20);
        });

        // Sizes (T, S, M, L, H, G)
        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 1)->unique();
            $table->string('name', 20);
        });

        // Damage Types (fire, cold, etc.)
        Schema::create('damage_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name', 50)->unique();
        });

        // Spell Schools (evocation, abjuration, etc.)
        Schema::create('spell_schools', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name', 50);
            $table->text('description')->nullable();
        });

        // Conditions (blinded, charmed, etc.)
        Schema::create('conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->text('description');
        });

        // Languages (Common, Elvish, etc.)
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique();
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->string('script', 255)->nullable()->comment('e.g., "Dwarvish script", "Elvish script"');
            $table->text('typical_speakers')->nullable()->comment('e.g., "Dragons, dragonborn"');
            $table->text('description')->nullable();
        });

        // Senses (darkvision, blindsight, etc.)
        Schema::create('senses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->string('name', 50);
        });

        // Skills (linked to ability scores)
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->string('name', 50)->unique();
            $table->foreignId('ability_score_id')->constrained('ability_scores')->restrictOnDelete();
        });

        // Item Types (weapon, armor, etc.)
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 50)->unique();
            $table->string('description', 255)->nullable();
        });

        // Item Properties (finesse, versatile, etc.)
        Schema::create('item_properties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->text('description');
        });

        // Proficiency Types (weapons, armor, tools, etc.)
        Schema::create('proficiency_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->string('name', 255);
            $table->string('category', 255);
            $table->string('subcategory', 255)->nullable();
            // item_id FK added after items table exists
            $table->unsignedBigInteger('item_id')->nullable();

            $table->index('category');
            $table->index('subcategory');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proficiency_types');
        Schema::dropIfExists('item_properties');
        Schema::dropIfExists('item_types');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('senses');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('conditions');
        Schema::dropIfExists('spell_schools');
        Schema::dropIfExists('damage_types');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('ability_scores');
        Schema::dropIfExists('sources');
    }
};

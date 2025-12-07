<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates monsters and all monster-related tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monsters', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->string('name', 255);
            $table->string('sort_name', 255)->nullable();
            $table->foreignId('size_id')->constrained('sizes')->restrictOnDelete();
            $table->string('type', 50);
            $table->string('alignment', 50)->nullable();
            $table->tinyInteger('armor_class')->unsigned();
            $table->string('armor_type', 100)->nullable();
            $table->smallInteger('hit_points_average')->unsigned();
            $table->string('hit_dice', 50);
            $table->tinyInteger('speed_walk')->unsigned()->default(0);
            $table->tinyInteger('speed_fly')->unsigned()->nullable();
            $table->tinyInteger('speed_swim')->unsigned()->nullable();
            $table->tinyInteger('speed_burrow')->unsigned()->nullable();
            $table->tinyInteger('speed_climb')->unsigned()->nullable();
            $table->boolean('can_hover')->default(false);
            $table->tinyInteger('strength')->unsigned();
            $table->tinyInteger('dexterity')->unsigned();
            $table->tinyInteger('constitution')->unsigned();
            $table->tinyInteger('intelligence')->unsigned();
            $table->tinyInteger('wisdom')->unsigned();
            $table->tinyInteger('charisma')->unsigned();
            $table->string('challenge_rating', 10);
            $table->integer('experience_points')->unsigned();
            $table->text('description')->nullable();
            $table->boolean('is_npc')->default(false);
            $table->tinyInteger('passive_perception')->unsigned()->nullable();
            $table->string('languages', 255)->nullable();

            $table->index('type');
            $table->index('challenge_rating');
            $table->index('slug', 'monsters_slug_idx');
            $table->index('challenge_rating', 'monsters_cr_idx');
            $table->index('type', 'monsters_type_idx');
            $table->index('size_id', 'monsters_size_idx');
        });

        Schema::create('monster_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 20);
            $table->string('name', 255);
            $table->text('description');
            $table->text('attack_data')->nullable();
            $table->string('recharge', 100)->nullable();
            $table->smallInteger('sort_order')->unsigned()->default(0);

            $table->index('action_type');
        });

        Schema::create('monster_legendary_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description');
            $table->tinyInteger('action_cost')->unsigned()->default(1);
            $table->boolean('is_lair_action')->default(false);
            $table->text('attack_data')->nullable();
            $table->string('recharge', 100)->nullable();
            $table->smallInteger('sort_order')->unsigned()->default(0);

            $table->index('is_lair_action');
        });

        Schema::create('monster_traits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description');
            $table->text('attack_data')->nullable();
            $table->smallInteger('sort_order')->unsigned()->default(0);
        });

        Schema::create('monster_spells', function (Blueprint $table) {
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spell_id')->constrained()->restrictOnDelete();
            $table->string('usage_type', 20);
            $table->string('usage_limit', 50)->nullable();

            $table->primary(['monster_id', 'spell_id', 'usage_type']);
            $table->index('spell_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monster_spells');
        Schema::dropIfExists('monster_traits');
        Schema::dropIfExists('monster_legendary_actions');
        Schema::dropIfExists('monster_actions');
        Schema::dropIfExists('monsters');
    }
};

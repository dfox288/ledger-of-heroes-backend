<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates spells and spell_effects tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spells', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->string('name', 255);
            $table->tinyInteger('level')->unsigned();
            $table->foreignId('spell_school_id')->constrained('spell_schools')->restrictOnDelete();
            $table->string('casting_time', 100);
            $table->string('range', 100);
            $table->string('components', 50);
            $table->text('material_components')->nullable();
            $table->string('duration', 100);
            $table->boolean('needs_concentration')->default(false);
            $table->boolean('is_ritual')->default(false);
            $table->text('description');
            $table->text('higher_levels')->nullable();

            $table->index('level');
            $table->index('needs_concentration');
            $table->index('is_ritual');
            $table->index('slug', 'spells_slug_idx');
            $table->index('level', 'spells_level_idx');
        });

        Schema::create('spell_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spell_id')->constrained()->cascadeOnDelete();
            $table->string('effect_type', 50);
            $table->text('description')->nullable();
            $table->string('dice_formula', 50)->nullable();
            $table->integer('base_value')->nullable();
            $table->string('scaling_type', 50)->nullable();
            $table->integer('min_character_level')->nullable();
            $table->integer('min_spell_slot')->nullable();
            $table->string('scaling_increment', 50)->nullable();
            $table->tinyInteger('projectile_count')->unsigned()->nullable()
                ->comment('Base number of projectiles/targets at minimum spell slot');
            $table->tinyInteger('projectile_per_level')->unsigned()->nullable()
                ->comment('Additional projectiles per spell slot level above base');
            $table->string('projectile_name', 50)->nullable()
                ->comment('Display name for projectiles (dart, ray, beam, target)');
            $table->foreignId('damage_type_id')->nullable()->constrained('damage_types')->restrictOnDelete();

            $table->index('effect_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spell_effects');
        Schema::dropIfExists('spells');
    }
};

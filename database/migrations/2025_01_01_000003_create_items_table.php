<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates items table and item-related tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->foreignId('item_type_id')->constrained('item_types')->restrictOnDelete();
            $table->text('description');
            $table->decimal('weight', 8, 2)->nullable();
            $table->integer('cost_cp')->unsigned()->nullable();
            $table->string('rarity', 20)->nullable();
            $table->string('detail', 255)->nullable();
            $table->string('damage_dice', 20)->nullable();
            $table->foreignId('damage_type_id')->nullable()->constrained('damage_types')->restrictOnDelete();
            $table->integer('range_normal')->unsigned()->nullable();
            $table->integer('range_long')->unsigned()->nullable();
            $table->string('versatile_damage', 20)->nullable();
            $table->tinyInteger('armor_class')->unsigned()->nullable();
            $table->tinyInteger('strength_requirement')->unsigned()->nullable();
            $table->boolean('stealth_disadvantage')->default(false);
            $table->boolean('requires_attunement')->default(false);
            $table->string('charges_max', 50)->nullable();
            $table->string('recharge_formula', 50)->nullable()
                ->comment('Recharge rate: "1d6+1", "all", "1d3", etc.');
            $table->string('recharge_timing', 50)->nullable()
                ->comment('When charges regenerate: "dawn", "dusk", "short rest", "long rest"');
            $table->boolean('is_magic')->default(false);

            $table->index('rarity');
            $table->index('requires_attunement');
            $table->index('charges_max');
            $table->index('slug', 'items_slug_idx');
        });

        // Pivot table for item-property many-to-many
        Schema::create('item_property', function (Blueprint $table) {
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('item_properties')->restrictOnDelete();

            $table->primary(['item_id', 'property_id']);
            $table->index('property_id');
        });

        // Item abilities (spells, actions, etc. that items can have)
        Schema::create('item_abilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('ability_type', 20);
            $table->foreignId('spell_id')->nullable()->constrained('spells')->restrictOnDelete();
            $table->string('name', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('roll_formula', 50)->nullable();
            $table->smallInteger('charges_cost')->unsigned()->nullable();
            $table->string('usage_limit', 100)->nullable();
            $table->tinyInteger('save_dc')->unsigned()->nullable();
            $table->tinyInteger('attack_bonus')->nullable();
            $table->smallInteger('sort_order')->unsigned()->default(0);

            $table->index('ability_type');
            $table->index('spell_id');
        });

        // Add FK to proficiency_types now that items exists
        Schema::table('proficiency_types', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('proficiency_types', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });

        Schema::dropIfExists('item_abilities');
        Schema::dropIfExists('item_property');
        Schema::dropIfExists('items');
    }
};

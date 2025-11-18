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
        Schema::create('items', function (Blueprint $table) {
            // Core identification (5 columns)
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 255)->unique();
            $table->unsignedBigInteger('item_type_id');
            $table->text('description');

            // Common properties (3 columns)
            $table->decimal('weight', 8, 2)->nullable(); // In pounds
            $table->unsignedInteger('cost_cp')->nullable(); // Cost in copper pieces
            $table->string('rarity', 20)->nullable(); // Common, Uncommon, Rare, Very Rare, Legendary, Artifact

            // Weapon properties (5 columns)
            $table->string('damage_dice', 20)->nullable(); // "1d8", "2d6", etc.
            $table->unsignedBigInteger('damage_type_id')->nullable();
            $table->string('weapon_range', 50)->nullable(); // "Melee", "Ranged", "5/20 ft"
            $table->string('versatile_damage', 20)->nullable(); // "1d10" for versatile weapons
            $table->text('weapon_properties')->nullable(); // JSON: ["finesse", "light", "thrown"]

            // Armor properties (3 columns)
            $table->unsignedTinyInteger('armor_class')->nullable(); // AC bonus or base AC
            $table->unsignedTinyInteger('strength_requirement')->nullable(); // Minimum STR needed
            $table->boolean('stealth_disadvantage')->default(false);

            // Magic item properties (1 column)
            $table->boolean('requires_attunement')->default(false);

            // Removed: source_id and source_pages - using entity_sources polymorphic table instead

            // Foreign keys
            $table->foreign('item_type_id')
                  ->references('id')
                  ->on('item_types')
                  ->onDelete('restrict');

            $table->foreign('damage_type_id')
                  ->references('id')
                  ->on('damage_types')
                  ->onDelete('restrict');

            // Indexes
            $table->index('item_type_id');
            $table->index('rarity');
            $table->index('requires_attunement');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

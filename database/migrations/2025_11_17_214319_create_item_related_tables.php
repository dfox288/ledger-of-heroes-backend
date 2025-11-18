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
        // 1. Item Properties Lookup Table
        Schema::create('item_properties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->text('description');

            // NO timestamps - static compendium data
        });

        // Data seeding moved to DatabaseSeeder

        // 2. Item-Property Junction Table (Composite PK)
        Schema::create('item_property', function (Blueprint $table) {
            // No surrogate ID - composite PK instead
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('property_id');

            // Composite primary key
            $table->primary(['item_id', 'property_id']);

            // Foreign keys
            $table->foreign('item_id')
                  ->references('id')
                  ->on('items')
                  ->onDelete('cascade');

            $table->foreign('property_id')
                  ->references('id')
                  ->on('item_properties')
                  ->onDelete('restrict');

            // Index for reverse lookups
            $table->index('property_id');

            // NO timestamps - static compendium data
        });

        // 3. Item Abilities Table (Magic Items)
        Schema::create('item_abilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('ability_type', 20);
            $table->unsignedBigInteger('spell_id')->nullable();
            $table->string('name', 255)->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('charges_cost')->nullable();
            $table->string('usage_limit', 100)->nullable();
            $table->unsignedTinyInteger('save_dc')->nullable();
            $table->tinyInteger('attack_bonus')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Foreign keys
            $table->foreign('item_id')
                  ->references('id')
                  ->on('items')
                  ->onDelete('cascade');

            $table->foreign('spell_id')
                  ->references('id')
                  ->on('spells')
                  ->onDelete('restrict');

            // Indexes
            $table->index('item_id');
            $table->index('ability_type');
            $table->index('spell_id');

            // NO timestamps - static compendium data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_abilities');
        Schema::dropIfExists('item_property');
        Schema::dropIfExists('item_properties');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Seed item properties
        DB::table('item_properties')->insert([
            [
                'code' => 'V',
                'name' => 'Versatile',
                'description' => 'This weapon can be used with one or two hands. A damage value in parentheses appears with the property—the damage when the weapon is used with two hands to make a melee attack.',
            ],
            [
                'code' => 'M',
                'name' => 'Martial',
                'description' => 'This weapon requires martial weapon proficiency to use.',
            ],
            [
                'code' => 'A',
                'name' => 'Ammunition',
                'description' => 'You can use a weapon that has the ammunition property to make a ranged attack only if you have ammunition to fire from the weapon. Each time you attack with the weapon, you expend one piece of ammunition.',
            ],
            [
                'code' => 'LD',
                'name' => 'Loading',
                'description' => 'Because of the time required to load this weapon, you can fire only one piece of ammunition from it when you use an action, bonus action, or reaction to fire it, regardless of the number of attacks you can normally make.',
            ],
            [
                'code' => 'F',
                'name' => 'Finesse',
                'description' => 'When making an attack with a finesse weapon, you use your choice of your Strength or Dexterity modifier for the attack and damage rolls. You must use the same modifier for both rolls.',
            ],
            [
                'code' => 'H',
                'name' => 'Heavy',
                'description' => 'Small creatures have disadvantage on attack rolls with heavy weapons. A heavy weapon\'s size and bulk make it too large for a Small creature to use effectively.',
            ],
            [
                'code' => 'L',
                'name' => 'Light',
                'description' => 'A light weapon is small and easy to handle, making it ideal for use when fighting with two weapons.',
            ],
            [
                'code' => 'R',
                'name' => 'Reach',
                'description' => 'This weapon adds 5 feet to your reach when you attack with it, as well as when determining your reach for opportunity attacks with it.',
            ],
            [
                'code' => 'T',
                'name' => 'Thrown',
                'description' => 'If a weapon has the thrown property, you can throw the weapon to make a ranged attack. If the weapon is a melee weapon, you use the same ability modifier for that attack roll and damage roll that you would use for a melee attack with the weapon.',
            ],
            [
                'code' => '2H',
                'name' => 'Two-Handed',
                'description' => 'This weapon requires two hands when you attack with it.',
            ],
            [
                'code' => 'S',
                'name' => 'Special',
                'description' => 'A weapon with the special property has unusual rules governing its use, explained in the weapon\'s description.',
            ],
        ]);

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

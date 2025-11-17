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
        // 1. Monster Traits Table
        Schema::create('monster_traits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monster_id'); // FK to monsters
            $table->string('name', 255); // "Dive Attack", "Amphibious", "Pack Tactics"
            $table->text('description'); // Full trait description
            $table->text('attack_data')->nullable(); // Raw attack string "Dive Attack||1d6"
            $table->unsignedSmallInteger('sort_order')->default(0); // Display order

            // Foreign key
            $table->foreign('monster_id')
                  ->references('id')
                  ->on('monsters')
                  ->onDelete('cascade');

            // Index
            $table->index('monster_id');

            // NO timestamps
        });

        // 2. Monster Actions Table
        Schema::create('monster_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monster_id'); // FK to monsters
            $table->string('action_type', 20); // 'action', 'reaction', 'bonus_action'
            $table->string('name', 255); // "Multiattack", "Tentacle", "Parry"
            $table->text('description'); // Full action description
            $table->text('attack_data')->nullable(); // "Bludgeoning Damage|+9|2d6+5"
            $table->string('recharge', 100)->nullable(); // "3/DAY", "5-6", "Recharge after Short Rest"
            $table->unsignedSmallInteger('sort_order')->default(0); // Display order

            // Foreign key
            $table->foreign('monster_id')
                  ->references('id')
                  ->on('monsters')
                  ->onDelete('cascade');

            // Indexes
            $table->index('monster_id');
            $table->index('action_type');

            // NO timestamps
        });

        // 3. Monster Legendary Actions Table
        Schema::create('monster_legendary_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monster_id'); // FK to monsters
            $table->string('name', 255); // "Detect", "Tail Swipe", "Legendary Actions (3/Turn)"
            $table->text('description'); // Full action description
            $table->unsignedTinyInteger('action_cost')->default(1); // Costs 1, 2, or 3 legendary actions
            $table->boolean('is_lair_action')->default(false); // True if this is a lair action
            $table->text('attack_data')->nullable(); // "Psychic Damage||3d6"
            $table->string('recharge', 100)->nullable(); // "3/TURN"
            $table->unsignedSmallInteger('sort_order')->default(0); // Display order

            // Foreign key
            $table->foreign('monster_id')
                  ->references('id')
                  ->on('monsters')
                  ->onDelete('cascade');

            // Indexes
            $table->index('monster_id');
            $table->index('is_lair_action');

            // NO timestamps
        });

        // 4. Monster Spellcasting Table
        Schema::create('monster_spellcasting', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('monster_id'); // FK to monsters
            $table->text('description'); // Full spellcasting ability text
            $table->string('spell_slots', 100)->nullable(); // Comma-separated "0,4,3,3,3,2,1,1,1,1"
            $table->string('spellcasting_ability', 50)->nullable(); // "Charisma", "Intelligence", "Wisdom"
            $table->unsignedTinyInteger('spell_save_dc')->nullable(); // Spell save DC
            $table->tinyInteger('spell_attack_bonus')->nullable(); // Spell attack modifier

            // Foreign key
            $table->foreign('monster_id')
                  ->references('id')
                  ->on('monsters')
                  ->onDelete('cascade');

            // Index
            $table->index('monster_id');

            // NO timestamps
        });

        // 5. Monster Spells Junction Table (Composite PK)
        Schema::create('monster_spells', function (Blueprint $table) {
            // No surrogate ID - composite PK instead
            $table->unsignedBigInteger('monster_id'); // FK to monsters
            $table->unsignedBigInteger('spell_id'); // FK to spells
            $table->string('usage_type', 20); // 'at_will', '1/day', '3/day', 'slot'
            $table->string('usage_limit', 50)->nullable(); // "1/day", "3/day each"

            // Composite primary key
            $table->primary(['monster_id', 'spell_id', 'usage_type']);

            // Foreign keys
            $table->foreign('monster_id')
                  ->references('id')
                  ->on('monsters')
                  ->onDelete('cascade');

            $table->foreign('spell_id')
                  ->references('id')
                  ->on('spells')
                  ->onDelete('restrict');

            // Index for reverse lookups
            $table->index('spell_id');

            // NO timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monster_spells');
        Schema::dropIfExists('monster_spellcasting');
        Schema::dropIfExists('monster_legendary_actions');
        Schema::dropIfExists('monster_actions');
        Schema::dropIfExists('monster_traits');
    }
};

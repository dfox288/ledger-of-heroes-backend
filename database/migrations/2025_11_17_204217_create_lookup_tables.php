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
        // 1. Spell Schools - NO timestamps
        Schema::create('spell_schools', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name', 50);
        });

        DB::table('spell_schools')->insert([
            ['code' => 'A', 'name' => 'Abjuration'],
            ['code' => 'C', 'name' => 'Conjuration'],
            ['code' => 'D', 'name' => 'Divination'],
            ['code' => 'EN', 'name' => 'Enchantment'],
            ['code' => 'EV', 'name' => 'Evocation'],
            ['code' => 'I', 'name' => 'Illusion'],
            ['code' => 'N', 'name' => 'Necromancy'],
            ['code' => 'T', 'name' => 'Transmutation'],
        ]);

        // 2. Damage Types - NO timestamps
        Schema::create('damage_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
        });

        DB::table('damage_types')->insert([
            ['name' => 'Acid'],
            ['name' => 'Bludgeoning'],
            ['name' => 'Cold'],
            ['name' => 'Fire'],
            ['name' => 'Force'],
            ['name' => 'Lightning'],
            ['name' => 'Necrotic'],
            ['name' => 'Piercing'],
            ['name' => 'Poison'],
            ['name' => 'Psychic'],
            ['name' => 'Radiant'],
            ['name' => 'Slashing'],
            ['name' => 'Thunder'],
        ]);

        // 3. Sizes - NO timestamps
        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 1)->unique();
            $table->string('name', 20);
        });

        DB::table('sizes')->insert([
            ['code' => 'T', 'name' => 'Tiny'],
            ['code' => 'S', 'name' => 'Small'],
            ['code' => 'M', 'name' => 'Medium'],
            ['code' => 'L', 'name' => 'Large'],
            ['code' => 'H', 'name' => 'Huge'],
            ['code' => 'G', 'name' => 'Gargantuan'],
        ]);

        // 4. Ability Scores - NO timestamps (MUST be before skills table)
        Schema::create('ability_scores', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 20);
        });

        DB::table('ability_scores')->insert([
            ['code' => 'STR', 'name' => 'Strength'],
            ['code' => 'DEX', 'name' => 'Dexterity'],
            ['code' => 'CON', 'name' => 'Constitution'],
            ['code' => 'INT', 'name' => 'Intelligence'],
            ['code' => 'WIS', 'name' => 'Wisdom'],
            ['code' => 'CHA', 'name' => 'Charisma'],
        ]);

        // 5. Skills - NO timestamps (depends on ability_scores)
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->unsignedBigInteger('ability_score_id');

            $table->foreign('ability_score_id')
                ->references('id')
                ->on('ability_scores')
                ->onDelete('restrict');
        });

        // Get ability score IDs for FK references
        $str = DB::table('ability_scores')->where('code', 'STR')->value('id');
        $dex = DB::table('ability_scores')->where('code', 'DEX')->value('id');
        $con = DB::table('ability_scores')->where('code', 'CON')->value('id');
        $int = DB::table('ability_scores')->where('code', 'INT')->value('id');
        $wis = DB::table('ability_scores')->where('code', 'WIS')->value('id');
        $cha = DB::table('ability_scores')->where('code', 'CHA')->value('id');

        DB::table('skills')->insert([
            ['name' => 'Acrobatics', 'ability_score_id' => $dex],
            ['name' => 'Animal Handling', 'ability_score_id' => $wis],
            ['name' => 'Arcana', 'ability_score_id' => $int],
            ['name' => 'Athletics', 'ability_score_id' => $str],
            ['name' => 'Deception', 'ability_score_id' => $cha],
            ['name' => 'History', 'ability_score_id' => $int],
            ['name' => 'Insight', 'ability_score_id' => $wis],
            ['name' => 'Intimidation', 'ability_score_id' => $cha],
            ['name' => 'Investigation', 'ability_score_id' => $int],
            ['name' => 'Medicine', 'ability_score_id' => $wis],
            ['name' => 'Nature', 'ability_score_id' => $int],
            ['name' => 'Perception', 'ability_score_id' => $wis],
            ['name' => 'Performance', 'ability_score_id' => $cha],
            ['name' => 'Persuasion', 'ability_score_id' => $cha],
            ['name' => 'Religion', 'ability_score_id' => $int],
            ['name' => 'Sleight of Hand', 'ability_score_id' => $dex],
            ['name' => 'Stealth', 'ability_score_id' => $dex],
            ['name' => 'Survival', 'ability_score_id' => $wis],
        ]);

        // 6. Item Types - NO timestamps
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
        });

        DB::table('item_types')->insert([
            ['name' => 'Weapon'],
            ['name' => 'Armor'],
            ['name' => 'Potion'],
            ['name' => 'Scroll'],
            ['name' => 'Wand'],
            ['name' => 'Ring'],
            ['name' => 'Rod'],
            ['name' => 'Staff'],
            ['name' => 'Wondrous Item'],
            ['name' => 'Adventuring Gear'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skills');
        Schema::dropIfExists('item_types');
        Schema::dropIfExists('ability_scores');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('damage_types');
        Schema::dropIfExists('spell_schools');
    }
};

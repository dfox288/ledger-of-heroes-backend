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
        // 1. Spell Schools - NO timestamps
        Schema::create('spell_schools', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name', 50);
        });

        // Data seeding moved to DatabaseSeeder

        // 2. Damage Types - NO timestamps
        Schema::create('damage_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name', 50)->unique();
        });

        // Data seeding moved to DatabaseSeeder

        // 3. Sizes - NO timestamps
        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 1)->unique();
            $table->string('name', 20);
        });

        // Data seeding moved to DatabaseSeeder

        // 4. Ability Scores - NO timestamps (MUST be before skills table)
        Schema::create('ability_scores', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name', 20);
        });

        // Data seeding moved to DatabaseSeeder

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

        // Data seeding moved to DatabaseSeeder

        // 6. Item Types - NO timestamps
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
        });

        // Data seeding moved to DatabaseSeeder
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

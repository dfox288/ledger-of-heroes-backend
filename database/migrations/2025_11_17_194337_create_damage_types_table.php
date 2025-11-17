<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('damage_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->timestamps();
        });

        DB::table('damage_types')->insert([
            ['code' => 'acid', 'name' => 'Acid'],
            ['code' => 'bludgeoning', 'name' => 'Bludgeoning'],
            ['code' => 'cold', 'name' => 'Cold'],
            ['code' => 'fire', 'name' => 'Fire'],
            ['code' => 'force', 'name' => 'Force'],
            ['code' => 'lightning', 'name' => 'Lightning'],
            ['code' => 'necrotic', 'name' => 'Necrotic'],
            ['code' => 'piercing', 'name' => 'Piercing'],
            ['code' => 'poison', 'name' => 'Poison'],
            ['code' => 'psychic', 'name' => 'Psychic'],
            ['code' => 'radiant', 'name' => 'Radiant'],
            ['code' => 'slashing', 'name' => 'Slashing'],
            ['code' => 'thunder', 'name' => 'Thunder'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_types');
    }
};

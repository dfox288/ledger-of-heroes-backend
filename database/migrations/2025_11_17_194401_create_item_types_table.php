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
        Schema::create('item_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 5)->unique(); // M, R, A, G, W, etc.
            $table->string('name', 50);
            $table->string('category', 50); // weapon, armor, gear, etc.
            $table->timestamps();
        });

        DB::table('item_types')->insert([
            ['code' => 'M', 'name' => 'Melee Weapon', 'category' => 'weapon'],
            ['code' => 'R', 'name' => 'Ranged Weapon', 'category' => 'weapon'],
            ['code' => 'A', 'name' => 'Ammunition', 'category' => 'weapon'],
            ['code' => 'LA', 'name' => 'Light Armor', 'category' => 'armor'],
            ['code' => 'MA', 'name' => 'Medium Armor', 'category' => 'armor'],
            ['code' => 'HA', 'name' => 'Heavy Armor', 'category' => 'armor'],
            ['code' => 'S', 'name' => 'Shield', 'category' => 'armor'],
            ['code' => 'G', 'name' => 'Adventuring Gear', 'category' => 'gear'],
            ['code' => 'W', 'name' => 'Wondrous Item', 'category' => 'magic'],
            ['code' => 'P', 'name' => 'Potion', 'category' => 'magic'],
            ['code' => 'SC', 'name' => 'Scroll', 'category' => 'magic'],
            ['code' => 'RD', 'name' => 'Rod', 'category' => 'magic'],
            ['code' => 'ST', 'name' => 'Staff', 'category' => 'magic'],
            ['code' => 'WD', 'name' => 'Wand', 'category' => 'magic'],
            ['code' => 'RG', 'name' => 'Ring', 'category' => 'magic'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_types');
    }
};

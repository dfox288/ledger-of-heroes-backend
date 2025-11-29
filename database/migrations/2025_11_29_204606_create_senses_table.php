<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates lookup table for the 4 D&D 5e sense types.
     */
    public function up(): void
    {
        Schema::create('senses', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 50);
        });

        // Seed the 4 core sense types
        DB::table('senses')->insert([
            ['id' => 1, 'slug' => 'darkvision', 'name' => 'Darkvision'],
            ['id' => 2, 'slug' => 'blindsight', 'name' => 'Blindsight'],
            ['id' => 3, 'slug' => 'tremorsense', 'name' => 'Tremorsense'],
            ['id' => 4, 'slug' => 'truesight', 'name' => 'Truesight'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('senses');
    }
};

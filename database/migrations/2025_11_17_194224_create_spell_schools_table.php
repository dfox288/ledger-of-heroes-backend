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
        Schema::create('spell_schools', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique(); // A, C, D, E, EV, I, N, T
            $table->string('name', 50); // Abjuration, Conjuration, etc.
            $table->timestamps();
        });

        // Seed with standard D&D 5e schools
        DB::table('spell_schools')->insert([
            ['code' => 'A', 'name' => 'Abjuration'],
            ['code' => 'C', 'name' => 'Conjuration'],
            ['code' => 'D', 'name' => 'Divination'],
            ['code' => 'E', 'name' => 'Enchantment'],
            ['code' => 'EV', 'name' => 'Evocation'],
            ['code' => 'I', 'name' => 'Illusion'],
            ['code' => 'N', 'name' => 'Necromancy'],
            ['code' => 'T', 'name' => 'Transmutation'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spell_schools');
    }
};

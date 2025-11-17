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
        Schema::create('source_books', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique(); // PHB, XGE, DMG, etc.
            $table->string('name', 255);
            $table->string('abbreviation', 20);
            $table->date('release_date')->nullable();
            $table->string('publisher', 100)->default('Wizards of the Coast');
            $table->timestamps();
        });

        // Seed common source books
        DB::table('source_books')->insert([
            ['code' => 'PHB', 'name' => "Player's Handbook", 'abbreviation' => 'PHB', 'release_date' => '2014-08-19', 'publisher' => 'Wizards of the Coast'],
            ['code' => 'DMG', 'name' => "Dungeon Master's Guide", 'abbreviation' => 'DMG', 'release_date' => '2014-12-09', 'publisher' => 'Wizards of the Coast'],
            ['code' => 'MM', 'name' => 'Monster Manual', 'abbreviation' => 'MM', 'release_date' => '2014-09-30', 'publisher' => 'Wizards of the Coast'],
            ['code' => 'XGE', 'name' => "Xanathar's Guide to Everything", 'abbreviation' => 'XGE', 'release_date' => '2017-11-21', 'publisher' => 'Wizards of the Coast'],
            ['code' => 'TCE', 'name' => "Tasha's Cauldron of Everything", 'abbreviation' => 'TCE', 'release_date' => '2020-11-17', 'publisher' => 'Wizards of the Coast'],
            ['code' => 'VGTM', 'name' => "Volo's Guide to Monsters", 'abbreviation' => 'VGTM', 'release_date' => '2016-11-15', 'publisher' => 'Wizards of the Coast'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('source_books');
    }
};

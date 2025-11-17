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
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 255);
            $table->string('publisher', 100)->default('Wizards of the Coast');
            $table->unsignedSmallInteger('publication_year');
            $table->string('edition', 20);

            // NO timestamps - static compendium data doesn't need created_at/updated_at
        });

        // Seed core sourcebooks
        DB::table('sources')->insert([
            ['code' => 'PHB', 'name' => 'Player\'s Handbook', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e'],
            ['code' => 'DMG', 'name' => 'Dungeon Master\'s Guide', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e'],
            ['code' => 'MM', 'name' => 'Monster Manual', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2014, 'edition' => '5e'],
            ['code' => 'XGE', 'name' => 'Xanathar\'s Guide to Everything', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2017, 'edition' => '5e'],
            ['code' => 'TCE', 'name' => 'Tasha\'s Cauldron of Everything', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2020, 'edition' => '5e'],
            ['code' => 'VGTM', 'name' => 'Volo\'s Guide to Monsters', 'publisher' => 'Wizards of the Coast', 'publication_year' => 2016, 'edition' => '5e'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};

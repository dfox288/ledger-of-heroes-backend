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
        Schema::create('item_rarities', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->timestamps();
        });

        DB::table('item_rarities')->insert([
            ['code' => 'common', 'name' => 'Common'],
            ['code' => 'uncommon', 'name' => 'Uncommon'],
            ['code' => 'rare', 'name' => 'Rare'],
            ['code' => 'very_rare', 'name' => 'Very Rare'],
            ['code' => 'legendary', 'name' => 'Legendary'],
            ['code' => 'artifact', 'name' => 'Artifact'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_rarities');
    }
};

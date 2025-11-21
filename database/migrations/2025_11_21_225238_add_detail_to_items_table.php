<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add detail column to store raw subcategory information from XML.
     * Examples: "firearm, renaissance", "druidic focus", "artisan tools"
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('detail', 255)->nullable()->after('rarity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('detail');
        });
    }
};

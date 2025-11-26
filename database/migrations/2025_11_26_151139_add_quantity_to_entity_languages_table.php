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
        Schema::table('entity_languages', function (Blueprint $table) {
            // For choice slots: how many languages the player can choose
            // e.g., "Two of your choice" → quantity = 2
            $table->unsignedTinyInteger('quantity')->default(1)->after('is_choice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entity_languages', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Increase category column length from 20 to 50 to support free-form
     * user-created categories like "Session Notes", "Important NPCs", etc.
     */
    public function up(): void
    {
        Schema::table('character_notes', function (Blueprint $table) {
            $table->string('category', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('character_notes', function (Blueprint $table) {
            $table->string('category', 20)->change();
        });
    }
};

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
        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type'); // Race, Feat, Item, etc.
            $table->unsignedBigInteger('reference_id');
            $table->enum('modifier_type', ['ability_score', 'bonus', 'speed']);
            $table->string('target', 50); // "strength", "initiative", "walking"
            $table->string('value', 20); // "+2", "+1d4", "disadvantage"
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifiers');
    }
};

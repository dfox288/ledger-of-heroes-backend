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
        Schema::create('class_spell', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spell_id')->constrained('spells')->onDelete('cascade');
            $table->string('class_name', 50); // "Wizard", "Cleric", "Sorcerer"
            $table->string('subclass_name', 100)->nullable(); // "Eldritch Knight", "Arcane Trickster"
            $table->timestamps();

            // Prevent duplicate spell-class assignments
            $table->unique(['spell_id', 'class_name', 'subclass_name']);
            $table->index('class_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_spell');
    }
};

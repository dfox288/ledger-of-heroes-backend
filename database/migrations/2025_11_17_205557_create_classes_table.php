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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100); // Barbarian, Fighter, Circle of the Moon, etc.
            $table->unsignedBigInteger('parent_class_id')->nullable(); // NULL for base classes, FK for subclasses
            $table->unsignedTinyInteger('hit_die'); // 6, 8, 10, 12
            $table->text('description'); // Class description and features
            $table->string('primary_ability', 100)->nullable(); // "Strength" or "Strength or Dexterity"
            $table->unsignedBigInteger('spellcasting_ability_id')->nullable(); // FK to ability_scores (for spellcasters)
            $table->unsignedBigInteger('source_id'); // FK to sources
            $table->string('source_pages', 50); // Multi-page support

            // Self-referential FK for subclasses
            $table->foreign('parent_class_id')
                ->references('id')
                ->on('classes')
                ->onDelete('cascade'); // Delete subclasses if parent is deleted

            $table->foreign('spellcasting_ability_id')
                ->references('id')
                ->on('ability_scores')
                ->onDelete('restrict');

            $table->foreign('source_id')
                ->references('id')
                ->on('sources')
                ->onDelete('restrict');

            $table->index('parent_class_id');
            $table->index('hit_die');

            // NO timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};

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
        Schema::create('proficiency_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category'); // weapon, armor, tool, vehicle, language, gaming_set, musical_instrument
            $table->unsignedBigInteger('item_id')->nullable();

            // Foreign key to items table (for weapons/armor that exist in items)
            $table->foreign('item_id')
                  ->references('id')
                  ->on('items')
                  ->onDelete('set null');

            // Index on category for filtering
            $table->index('category');

            // No timestamps - static reference data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proficiency_types');
    }
};

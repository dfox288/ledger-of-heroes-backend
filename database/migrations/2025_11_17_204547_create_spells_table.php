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
        Schema::create('spells', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->unsignedTinyInteger('level'); // 0-9
            $table->unsignedBigInteger('spell_school_id'); // FK to spell_schools
            $table->string('casting_time', 100); // "1 action", "1 bonus action", "1 minute", etc.
            $table->string('range', 100); // "Self", "Touch", "30 feet", "Unlimited", etc.
            $table->string('components', 50); // "V", "S", "M", "V, S", "V, S, M"
            $table->text('material_components')->nullable(); // Description of material components
            $table->string('duration', 100); // "Instantaneous", "1 minute", "Concentration, up to 1 minute"
            $table->boolean('needs_concentration')->default(false); // CRITICAL: This field was missing before
            $table->boolean('is_ritual')->default(false);
            $table->text('description'); // Full spell description
            $table->text('higher_levels')->nullable(); // "At Higher Levels" description
            $table->unsignedBigInteger('source_id'); // FK to sources (NOT source_book_id)
            $table->string('source_pages', 50); // "148, 150" (NOT single integer)

            // Foreign keys
            $table->foreign('spell_school_id')
                ->references('id')
                ->on('spell_schools')
                ->onDelete('restrict');

            $table->foreign('source_id')
                ->references('id')
                ->on('sources')
                ->onDelete('restrict');

            // Indexes for common queries
            $table->index('level');
            $table->index('spell_school_id');
            $table->index('needs_concentration');
            $table->index('is_ritual');

            // NO timestamps - static compendium data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spells');
    }
};

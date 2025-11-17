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
            $table->string('name');
            $table->string('slug')->unique(); // URL-safe version: "acid-splash"
            $table->unsignedTinyInteger('level'); // 0-9 (0 = cantrip)
            $table->foreignId('school_id')->constrained('spell_schools')->onDelete('restrict');
            $table->boolean('is_ritual')->default(false);

            // Casting details
            $table->string('casting_time', 100); // "1 action", "1 minute", "1 bonus action"
            $table->string('range', 100); // "60 feet", "Self", "Touch"
            $table->string('duration', 100); // "Instantaneous", "Concentration, up to 1 minute"

            // Component parsing
            $table->boolean('has_verbal_component')->default(false);
            $table->boolean('has_somatic_component')->default(false);
            $table->boolean('has_material_component')->default(false);
            $table->string('material_description', 500)->nullable();
            $table->unsignedInteger('material_cost_gp')->nullable();
            $table->boolean('material_consumed')->default(false);

            // Description
            $table->text('description'); // Full spell description from XML

            // Source tracking
            $table->foreignId('source_book_id')->constrained('source_books')->onDelete('cascade');
            $table->unsignedSmallInteger('source_page')->nullable();

            $table->timestamps();

            // Indexes for common queries
            $table->index('level');
            $table->index('school_id');
            $table->index(['level', 'school_id']);
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

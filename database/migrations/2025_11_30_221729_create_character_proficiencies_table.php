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
        Schema::create('character_proficiencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();

            // Polymorphic proficiency (skill OR proficiency type)
            $table->foreignId('proficiency_type_id')->nullable()->constrained('proficiency_types');
            $table->foreignId('skill_id')->nullable()->constrained('skills');

            $table->enum('source', ['race', 'class', 'background', 'feat'])->default('class');
            $table->boolean('expertise')->default(false);

            $table->timestamp('created_at')->nullable();

            $table->index('character_id');
            $table->index('proficiency_type_id');
            $table->index('skill_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_proficiencies');
    }
};

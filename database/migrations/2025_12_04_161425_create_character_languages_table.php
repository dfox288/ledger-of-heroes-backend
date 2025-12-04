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
        Schema::create('character_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('language_id')->constrained();
            $table->enum('source', ['race', 'background', 'feat'])->default('race');
            $table->timestamp('created_at')->nullable();

            $table->unique(['character_id', 'language_id']); // Can't know same language twice
            $table->index('character_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_languages');
    }
};

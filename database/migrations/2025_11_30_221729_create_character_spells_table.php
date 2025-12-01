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
        Schema::create('character_spells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spell_id')->constrained('spells');

            // Spell state
            $table->enum('preparation_status', ['known', 'prepared', 'always_prepared'])->default('known');
            $table->enum('source', ['class', 'race', 'feat', 'item'])->default('class');
            $table->unsignedTinyInteger('level_acquired')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->unique(['character_id', 'spell_id']);
            $table->index('character_id');
            $table->index('spell_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_spells');
    }
};

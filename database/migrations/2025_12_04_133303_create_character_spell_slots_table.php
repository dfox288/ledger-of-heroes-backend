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
        Schema::create('character_spell_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('spell_level');
            $table->unsignedTinyInteger('max_slots')->default(0);
            $table->unsignedTinyInteger('used_slots')->default(0);
            $table->string('slot_type')->default('standard');
            $table->timestamps();

            $table->unique(['character_id', 'spell_level', 'slot_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_spell_slots');
    }
};

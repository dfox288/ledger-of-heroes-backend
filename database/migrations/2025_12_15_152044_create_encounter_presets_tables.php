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
        Schema::create('encounter_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('encounter_preset_monsters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_preset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monster_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encounter_preset_monsters');
        Schema::dropIfExists('encounter_presets');
    }
};

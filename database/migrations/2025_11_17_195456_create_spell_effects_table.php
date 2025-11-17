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
        Schema::create('spell_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spell_id')->constrained('spells')->onDelete('cascade');
            $table->enum('effect_type', ['damage', 'healing', 'buff', 'debuff', 'utility']);
            $table->string('dice_formula', 50)->nullable(); // "1d6", "2d8+5", "1d6 per slot level"
            $table->enum('scaling_type', ['none', 'character_level', 'spell_slot_level'])->default('none');
            $table->unsignedTinyInteger('scaling_trigger')->nullable(); // Level at which scaling occurs
            $table->foreignId('damage_type_id')->nullable()->constrained('damage_types')->onDelete('set null');
            $table->timestamps();

            $table->index('spell_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spell_effects');
    }
};

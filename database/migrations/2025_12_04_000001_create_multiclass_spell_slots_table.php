<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multiclass_spell_slots', function (Blueprint $table) {
            $table->unsignedTinyInteger('caster_level')->primary();
            $table->unsignedTinyInteger('slots_1st')->default(0);
            $table->unsignedTinyInteger('slots_2nd')->default(0);
            $table->unsignedTinyInteger('slots_3rd')->default(0);
            $table->unsignedTinyInteger('slots_4th')->default(0);
            $table->unsignedTinyInteger('slots_5th')->default(0);
            $table->unsignedTinyInteger('slots_6th')->default(0);
            $table->unsignedTinyInteger('slots_7th')->default(0);
            $table->unsignedTinyInteger('slots_8th')->default(0);
            $table->unsignedTinyInteger('slots_9th')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multiclass_spell_slots');
    }
};

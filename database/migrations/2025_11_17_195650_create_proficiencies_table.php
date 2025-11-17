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
        Schema::create('proficiencies', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type'); // Race, Background, Class
            $table->unsignedBigInteger('reference_id');
            $table->enum('proficiency_type', ['skill', 'tool', 'weapon', 'armor', 'language', 'saving_throw']);
            $table->string('name', 100);
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
            $table->index('proficiency_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proficiencies');
    }
};

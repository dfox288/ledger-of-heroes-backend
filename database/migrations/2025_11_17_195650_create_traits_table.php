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
        Schema::create('traits', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type'); // Race, Background, Feat, Class
            $table->unsignedBigInteger('reference_id');
            $table->string('name');
            $table->string('category', 50)->nullable(); // "description", "feature", etc.
            $table->text('description');
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('traits');
    }
};

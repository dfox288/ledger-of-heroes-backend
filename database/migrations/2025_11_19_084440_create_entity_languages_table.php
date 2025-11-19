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
        Schema::create('entity_languages', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference to entity (Race, Background, Class, etc.)
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');

            // Language reference (nullable for "choose one language" slots)
            $table->unsignedBigInteger('language_id')->nullable();

            // Is this a choice slot or a fixed language?
            $table->boolean('is_choice')->default(false)->comment('true = player chooses, false = fixed language');

            // Foreign keys
            $table->foreign('language_id')->references('id')->on('languages')->onDelete('cascade');

            // Indexes
            $table->index(['reference_type', 'reference_id']);
            $table->index('language_id');

            // NO timestamps - static compendium data
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_languages');
    }
};

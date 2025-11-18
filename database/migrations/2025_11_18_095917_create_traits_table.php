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

            // Polymorphic reference
            $table->string('reference_type'); // 'race', 'background', 'class'
            $table->unsignedBigInteger('reference_id');

            // Trait data
            $table->string('name');
            $table->string('category')->nullable(); // 'species', 'subspecies', 'description', 'feature'
            $table->text('description');
            $table->integer('sort_order')->default(0);

            // Indexes
            $table->index(['reference_type', 'reference_id']);
            $table->index('category');

            // NO timestamps - static compendium data
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

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
        Schema::create('class_spells', function (Blueprint $table) {
            // No surrogate ID - composite PK instead
            $table->unsignedBigInteger('class_id'); // FK to classes
            $table->unsignedBigInteger('spell_id'); // FK to spells
            $table->unsignedTinyInteger('level_learned')->nullable(); // Optional: level when spell is learned

            // Composite primary key
            $table->primary(['class_id', 'spell_id']);

            // Foreign keys
            $table->foreign('class_id')
                ->references('id')
                ->on('classes')
                ->onDelete('cascade');

            $table->foreign('spell_id')
                ->references('id')
                ->on('spells')
                ->onDelete('cascade');

            // Indexes
            $table->index('spell_id');
            $table->index('level_learned');

            // NO timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_spells');
    }
};

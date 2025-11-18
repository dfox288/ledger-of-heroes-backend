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
        // Random tables
        Schema::create('random_tables', function (Blueprint $table) {
            $table->id();

            // Polymorphic reference
            $table->string('reference_type'); // 'background', 'class', 'race'
            $table->unsignedBigInteger('reference_id');

            // Table data
            $table->string('table_name'); // 'Personality Trait', 'Ideal', 'Size Modifier'
            $table->string('dice_type'); // 'd6', 'd8', 'd10', 'd100', '2d8'
            $table->text('description')->nullable(); // Optional context

            // Indexes
            $table->index(['reference_type', 'reference_id']);

            // NO timestamps
        });

        // Random table entries
        Schema::create('random_table_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('random_table_id');

            $table->string('roll_value'); // '1', '2', '01-10' (for d100 ranges)
            $table->text('result'); // The actual table entry text
            $table->integer('sort_order')->default(0);

            // Foreign key
            $table->foreign('random_table_id')
                  ->references('id')
                  ->on('random_tables')
                  ->onDelete('cascade');

            // Indexes
            $table->index('random_table_id');
            $table->index('sort_order');

            // NO timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('random_table_entries');
        Schema::dropIfExists('random_tables');
    }
};

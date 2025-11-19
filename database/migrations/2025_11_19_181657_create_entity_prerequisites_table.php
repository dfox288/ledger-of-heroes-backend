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
        Schema::create('entity_prerequisites', function (Blueprint $table) {
            $table->id();

            // Polymorphic - which entity HAS this prerequisite?
            $table->string('reference_type'); // App\Models\Feat, App\Models\Item, etc.
            $table->unsignedBigInteger('reference_id');

            // Polymorphic - what entity IS the prerequisite?
            $table->string('prerequisite_type')->nullable(); // App\Models\AbilityScore, App\Models\Race, etc.
            $table->unsignedBigInteger('prerequisite_id')->nullable();

            // Additional constraint data
            $table->unsignedTinyInteger('minimum_value')->nullable(); // For ability scores: STR >= 13
            $table->text('description')->nullable(); // For free-form: "Spellcasting feature"

            // Logical grouping for complex AND/OR conditions
            $table->unsignedTinyInteger('group_id')->default(1);

            // Indexes for performance
            $table->index(['reference_type', 'reference_id']);
            $table->index(['prerequisite_type', 'prerequisite_id']);
            $table->index('group_id');

            // NO timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_prerequisites');
    }
};

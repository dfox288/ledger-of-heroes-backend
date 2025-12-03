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
        Schema::create('equipment_choice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_item_id')->constrained('entity_items')->cascadeOnDelete();
            $table->foreignId('proficiency_type_id')->nullable()->constrained('proficiency_types')->nullOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->index('entity_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipment_choice_items');
    }
};

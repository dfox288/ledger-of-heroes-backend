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
        Schema::create('character_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items');

            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('equipped')->default(false);
            $table->enum('location', ['equipped', 'backpack', 'stored'])->default('backpack');

            $table->timestamp('created_at')->nullable();

            $table->index('character_id');
            $table->index('item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('character_equipment');
    }
};

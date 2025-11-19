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
        Schema::create('entity_items', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->boolean('is_choice')->default(false);
            $table->text('choice_description')->nullable();

            $table->index(['reference_type', 'reference_id']);
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_items');
    }
};

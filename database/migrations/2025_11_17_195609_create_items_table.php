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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('item_type_id')->constrained('item_types')->onDelete('restrict');
            $table->foreignId('rarity_id')->nullable()->constrained('item_rarities')->onDelete('set null');
            $table->decimal('weight_lbs', 8, 2)->nullable();
            $table->decimal('value_gp', 10, 2)->nullable();
            $table->text('description');
            $table->boolean('attunement_required')->default(false);
            $table->string('attunement_requirements', 500)->nullable(); // "by a spellcaster"
            $table->foreignId('source_book_id')->constrained('source_books')->onDelete('cascade');
            $table->unsignedSmallInteger('source_page')->nullable();
            $table->timestamps();

            $table->index('item_type_id');
            $table->index('rarity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};

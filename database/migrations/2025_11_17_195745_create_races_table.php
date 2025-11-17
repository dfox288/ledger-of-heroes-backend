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
        Schema::create('races', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('size_id')->constrained('sizes')->onDelete('restrict');
            $table->unsignedTinyInteger('speed')->default(30); // Base walking speed in feet
            $table->foreignId('source_book_id')->constrained('source_books')->onDelete('cascade');
            $table->unsignedSmallInteger('source_page')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('races');
    }
};

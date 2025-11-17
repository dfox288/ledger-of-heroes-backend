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
        // Races table
        Schema::create('races', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedBigInteger('size_id');
            $table->unsignedTinyInteger('speed')->default(30);
            $table->text('description');
            $table->unsignedBigInteger('source_id');
            $table->string('source_pages', 50);

            $table->foreign('size_id')
                  ->references('id')
                  ->on('sizes')
                  ->onDelete('restrict');

            $table->foreign('source_id')
                  ->references('id')
                  ->on('sources')
                  ->onDelete('restrict');

            $table->index('size_id');

            // NO timestamps
        });

        // Backgrounds table
        Schema::create('backgrounds', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description');
            $table->text('skill_proficiencies')->nullable();
            $table->text('tool_proficiencies')->nullable();
            $table->text('languages')->nullable();
            $table->text('equipment')->nullable();
            $table->text('feature_name')->nullable();
            $table->text('feature_description')->nullable();
            $table->unsignedBigInteger('source_id');
            $table->string('source_pages', 50);

            $table->foreign('source_id')
                  ->references('id')
                  ->on('sources')
                  ->onDelete('restrict');

            // NO timestamps
        });

        // Feats table
        Schema::create('feats', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('prerequisites')->nullable();
            $table->text('description');
            $table->unsignedBigInteger('source_id');
            $table->string('source_pages', 50);

            $table->foreign('source_id')
                  ->references('id')
                  ->on('sources')
                  ->onDelete('restrict');

            // NO timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feats');
        Schema::dropIfExists('backgrounds');
        Schema::dropIfExists('races');
    }
};

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
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('level')->default(1);
            $table->unsignedInteger('experience_points')->default(0);

            // Core choices (all nullable for wizard-style creation)
            $table->foreignId('race_id')->nullable()->constrained('races')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('background_id')->nullable()->constrained('backgrounds')->nullOnDelete();

            // Ability scores (nullable for wizard-style creation, range 3-20)
            $table->unsignedTinyInteger('strength')->nullable();
            $table->unsignedTinyInteger('dexterity')->nullable();
            $table->unsignedTinyInteger('constitution')->nullable();
            $table->unsignedTinyInteger('intelligence')->nullable();
            $table->unsignedTinyInteger('wisdom')->nullable();
            $table->unsignedTinyInteger('charisma')->nullable();

            // Hit points
            $table->unsignedSmallInteger('max_hit_points')->nullable();
            $table->unsignedSmallInteger('current_hit_points')->nullable();
            $table->unsignedSmallInteger('temp_hit_points')->default(0);

            // Armor class (cached, nullable until equipment set)
            $table->unsignedTinyInteger('armor_class')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('race_id');
            $table->index('class_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};

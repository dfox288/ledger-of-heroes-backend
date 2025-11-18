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
        Schema::create('monsters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->unsignedBigInteger('size_id'); // FK to sizes
            $table->string('type', 50); // Beast, Humanoid, Dragon, etc.
            $table->string('alignment', 50)->nullable(); // Lawful Good, Neutral Evil, Unaligned, etc.

            // Armor Class
            $table->unsignedTinyInteger('armor_class');
            $table->string('armor_type', 100)->nullable(); // "natural armor", "plate mail"

            // Hit Points
            $table->unsignedSmallInteger('hit_points_average');
            $table->string('hit_dice', 50); // "8d8 + 16"

            // Speed
            $table->unsignedTinyInteger('speed_walk')->default(0);
            $table->unsignedTinyInteger('speed_fly')->nullable();
            $table->unsignedTinyInteger('speed_swim')->nullable();
            $table->unsignedTinyInteger('speed_burrow')->nullable();
            $table->unsignedTinyInteger('speed_climb')->nullable();
            $table->boolean('can_hover')->default(false);

            // Ability Scores
            $table->unsignedTinyInteger('strength');
            $table->unsignedTinyInteger('dexterity');
            $table->unsignedTinyInteger('constitution');
            $table->unsignedTinyInteger('intelligence');
            $table->unsignedTinyInteger('wisdom');
            $table->unsignedTinyInteger('charisma');

            // Challenge Rating
            $table->string('challenge_rating', 10); // "0", "1/8", "1/4", "1/2", "1", "2", etc.
            $table->unsignedInteger('experience_points');

            // Description and lore
            $table->text('description')->nullable();

            // Source attribution
            $table->unsignedBigInteger('source_id'); // FK to sources
            $table->string('source_pages', 50); // Multi-page support

            // Foreign keys
            $table->foreign('size_id')
                ->references('id')
                ->on('sizes')
                ->onDelete('restrict');

            $table->foreign('source_id')
                ->references('id')
                ->on('sources')
                ->onDelete('restrict');

            // Indexes
            $table->index('size_id');
            $table->index('type');
            $table->index('challenge_rating');

            // NO timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monsters');
    }
};

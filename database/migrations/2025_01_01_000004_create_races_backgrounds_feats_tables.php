<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates races, backgrounds, and feats tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('races', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->unsignedBigInteger('parent_race_id')->nullable();
            $table->boolean('subrace_required')->default(true);
            $table->string('name', 100);
            $table->foreignId('size_id')->constrained('sizes')->restrictOnDelete();
            $table->tinyInteger('speed')->unsigned()->default(30);
            $table->smallInteger('fly_speed')->unsigned()->nullable();
            $table->smallInteger('swim_speed')->unsigned()->nullable();
            $table->smallInteger('climb_speed')->unsigned()->nullable();

            $table->index('parent_race_id');
            $table->index('slug', 'races_slug_idx');

            $table->foreign('parent_race_id')->references('id')->on('races')->cascadeOnDelete();
        });

        Schema::create('backgrounds', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->string('name', 100)->unique();

            $table->index('slug', 'backgrounds_slug_idx');
        });

        Schema::create('feats', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 255)->unique();
            $table->string('full_slug', 150)->nullable()->unique();
            $table->string('name', 100);
            $table->text('prerequisites_text')->nullable();
            $table->text('description');
            $table->string('resets_on', 255)->nullable();

            $table->index('slug', 'feats_slug_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feats');
        Schema::dropIfExists('backgrounds');
        Schema::dropIfExists('races');
    }
};

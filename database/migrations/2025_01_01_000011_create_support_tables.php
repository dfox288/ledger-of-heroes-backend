<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates support tables: tags, media, sessions, tokens, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tags (Spatie laravel-tags)
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->json('slug');
            $table->string('type', 255)->nullable();
            $table->integer('order_column')->nullable();
            $table->timestamps();
        });

        // Taggables pivot (Spatie laravel-tags)
        Schema::create('taggables', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('taggable_type', 255);
            $table->unsignedBigInteger('taggable_id');

            $table->unique(['tag_id', 'taggable_id', 'taggable_type']);
            $table->index(['taggable_type', 'taggable_id']);
        });

        // Media (Spatie laravel-medialibrary)
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('model_type', 255);
            $table->unsignedBigInteger('model_id');
            $table->uuid('uuid')->nullable()->unique();
            $table->string('collection_name', 255);
            $table->string('name', 255);
            $table->string('file_name', 255);
            $table->string('mime_type', 255)->nullable();
            $table->string('disk', 255);
            $table->string('conversions_disk', 255)->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->integer('order_column')->unsigned()->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index('order_column');
        });

        // Sessions and password_reset_tokens are created in Laravel's default user migration

        // Personal Access Tokens (Laravel Sanctum)
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type', 255);
            $table->unsignedBigInteger('tokenable_id');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id']);
        });

        // jobs, job_batches, and failed_jobs are created in Laravel's default jobs migration
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        // sessions, password_reset_tokens, jobs, job_batches, failed_jobs
        // are dropped in Laravel's default migrations
        Schema::dropIfExists('media');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
    }
};

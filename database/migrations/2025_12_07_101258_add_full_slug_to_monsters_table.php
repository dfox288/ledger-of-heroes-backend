<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add full_slug column to monsters table for slug-based character references.
 *
 * The full_slug column stores source-prefixed slugs in the format "{source_code}:{slug}"
 * (e.g., "mm:goblin", "phb:ancient-red-dragon"). This enables character data to be
 * decoupled from database IDs, surviving sourcebook reimports.
 *
 * Related to Issue #303 - completing the full_slug rollout from Issue #288.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monsters', function (Blueprint $table): void {
            $table->string('full_slug', 150)->nullable()->unique()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monsters', function (Blueprint $table): void {
            $table->dropColumn('full_slug');
        });
    }
};

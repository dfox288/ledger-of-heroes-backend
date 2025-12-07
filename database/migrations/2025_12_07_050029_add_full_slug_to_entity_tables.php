<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add full_slug column to entity tables for slug-based character references.
 *
 * The full_slug column stores source-prefixed slugs in the format "{source_code}:{slug}"
 * (e.g., "phb:high-elf", "xge:shadow-blade"). This enables character data to be
 * decoupled from database IDs, surviving sourcebook reimports.
 *
 * @see docs/plans/2025-01-07-slug-based-character-references-design.md
 */
return new class extends Migration
{
    /**
     * Entity tables that need the full_slug column.
     *
     * - Tables with HasSources trait: races, backgrounds, classes, spells, items,
     *   optional_features, feats (get source from entity_sources pivot)
     * - Universal lookup tables: languages, skills, proficiency_types, conditions,
     *   senses (will use 'core:' prefix since they're not source-dependent)
     */
    private const ENTITY_TABLES = [
        'races',
        'backgrounds',
        'classes',
        'spells',
        'items',
        'languages',
        'skills',
        'proficiency_types',
        'conditions',
        'optional_features',
        'feats',
        'senses',
    ];

    public function up(): void
    {
        foreach (self::ENTITY_TABLES as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->string('full_slug', 150)->nullable()->unique()->after('slug');
            });
        }
    }

    public function down(): void
    {
        foreach (self::ENTITY_TABLES as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->dropColumn('full_slug');
            });
        }
    }
};

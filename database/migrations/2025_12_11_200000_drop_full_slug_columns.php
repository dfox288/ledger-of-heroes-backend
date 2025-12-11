<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the redundant full_slug column from all entity tables.
 *
 * The slug column will now contain source-prefixed values (e.g., phb:acid-splash).
 * Data will be re-imported fresh after this migration.
 *
 * @see https://github.com/dfox288/ledger-of-heroes/issues/506
 */
return new class extends Migration
{
    /**
     * Tables that have the full_slug column to drop.
     */
    private array $tables = [
        'spells',
        'races',
        'classes',
        'backgrounds',
        'feats',
        'items',
        'monsters',
        'optional_features',
        'languages',
        'conditions',
        'senses',
        'skills',
        'proficiency_types',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropUnique(['full_slug']);
                $table->dropColumn('full_slug');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->string('full_slug', 150)->nullable()->unique()->after('slug');
            });
        }
    }
};

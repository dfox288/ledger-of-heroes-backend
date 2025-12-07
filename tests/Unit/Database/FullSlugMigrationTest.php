<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-db')]
class FullSlugMigrationTest extends TestCase
{
    use RefreshDatabase;

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

    #[Test]
    public function all_entity_tables_have_full_slug_column(): void
    {
        foreach (self::ENTITY_TABLES as $table) {
            $this->assertTrue(
                Schema::hasColumn($table, 'full_slug'),
                "Table {$table} should have full_slug column"
            );
        }
    }

    #[Test]
    public function full_slug_columns_have_unique_indexes(): void
    {
        foreach (self::ENTITY_TABLES as $table) {
            $hasUniqueIndex = $this->tableHasUniqueIndexOnColumn($table, 'full_slug');

            $this->assertTrue(
                $hasUniqueIndex,
                "Table {$table} should have a unique index on full_slug"
            );
        }
    }

    /**
     * Check if a table has a unique index on a specific column.
     * Works with SQLite (used in tests).
     */
    private function tableHasUniqueIndexOnColumn(string $table, string $column): bool
    {
        // SQLite: Query pragma_index_list and pragma_index_info
        $indexes = DB::select("PRAGMA index_list({$table})");

        foreach ($indexes as $index) {
            if ($index->unique) {
                $columns = DB::select("PRAGMA index_info({$index->name})");
                foreach ($columns as $col) {
                    if ($col->name === $column) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackgroundsTableSimplifiedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function backgrounds_table_has_minimal_schema()
    {
        $columns = Schema::getColumnListing('backgrounds');

        // Only these columns should exist
        $this->assertContains('id', $columns);
        $this->assertContains('name', $columns);

        // These columns should NOT exist (moved to polymorphic tables)
        $removedColumns = [
            'description',
            'skill_proficiencies',
            'tool_proficiencies',
            'languages',
            'equipment',
            'feature_name',
            'feature_description',
        ];

        foreach ($removedColumns as $col) {
            $this->assertNotContains($col, $columns, "Column '$col' should be removed");
        }
    }

    #[Test]
    public function backgrounds_table_has_unique_name_constraint()
    {
        $indexes = Schema::getIndexes('backgrounds');

        $uniqueIndex = collect($indexes)->first(fn($idx) => $idx['columns'] === ['name'] && $idx['unique']);

        $this->assertNotNull($uniqueIndex, 'Name column should have unique constraint');
    }

    #[Test]
    public function backgrounds_table_does_not_have_timestamps()
    {
        $columns = Schema::getColumnListing('backgrounds');

        $this->assertNotContains('created_at', $columns);
        $this->assertNotContains('updated_at', $columns);
    }
}

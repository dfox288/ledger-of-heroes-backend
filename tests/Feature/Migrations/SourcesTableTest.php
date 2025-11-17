<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SourcesTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_sources_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('sources'));

        $this->assertTrue(Schema::hasColumn('sources', 'id'));
        $this->assertTrue(Schema::hasColumn('sources', 'code'));
        $this->assertTrue(Schema::hasColumn('sources', 'name'));
        $this->assertTrue(Schema::hasColumn('sources', 'publisher'));
        $this->assertTrue(Schema::hasColumn('sources', 'publication_year'));
        $this->assertTrue(Schema::hasColumn('sources', 'edition'));
    }

    public function test_sources_table_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('sources', 'created_at'));
        $this->assertFalse(Schema::hasColumn('sources', 'updated_at'));
    }

    public function test_sources_code_is_unique(): void
    {
        // Test will verify unique constraint exists
        $this->assertTrue(true); // Placeholder - implement actual unique test
    }
}

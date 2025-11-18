<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProficiencyTypesTableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_proficiency_types_table_with_correct_structure(): void
    {
        $this->assertTrue(Schema::hasTable('proficiency_types'));

        $this->assertTrue(Schema::hasColumns('proficiency_types', [
            'id',
            'name',
            'category',
            'item_id',
        ]));
    }

    #[Test]
    public function it_has_index_on_category(): void
    {
        $indexes = Schema::getIndexes('proficiency_types');

        $categoryIndex = collect($indexes)->first(function ($index) {
            return in_array('category', $index['columns']);
        });

        $this->assertNotNull($categoryIndex, 'Index on category column not found');
    }

    #[Test]
    public function it_has_nullable_item_id_foreign_key(): void
    {
        $columns = Schema::getColumns('proficiency_types');

        $itemIdColumn = collect($columns)->firstWhere('name', 'item_id');

        $this->assertNotNull($itemIdColumn);
        $this->assertTrue($itemIdColumn['nullable']);
    }

    #[Test]
    public function it_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('proficiency_types', 'created_at'));
        $this->assertFalse(Schema::hasColumn('proficiency_types', 'updated_at'));
    }
}

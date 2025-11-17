<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ItemsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('items'));

        $expectedColumns = [
            'id', 'name', 'slug', 'item_type_id', 'rarity_id',
            'weight_lbs', 'value_gp', 'description', 'attunement_required',
            'attunement_requirements', 'source_book_id', 'source_page',
            'created_at', 'updated_at'
        ];

        $this->assertTrue(Schema::hasColumns('items', $expectedColumns));
    }
}

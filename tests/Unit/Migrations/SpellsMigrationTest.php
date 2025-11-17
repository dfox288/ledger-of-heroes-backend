<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpellsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_spells_table_exists_with_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('spells'));

        $expectedColumns = [
            'id', 'name', 'slug', 'level', 'school_id', 'is_ritual',
            'casting_time', 'range', 'duration', 'has_verbal_component',
            'has_somatic_component', 'has_material_component',
            'material_description', 'material_cost_gp', 'material_consumed',
            'description', 'source_book_id', 'source_page',
            'created_at', 'updated_at'
        ];

        $this->assertTrue(Schema::hasColumns('spells', $expectedColumns));
    }

    public function test_spells_foreign_keys_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('spells', 'school_id'));
        $this->assertTrue(Schema::hasColumn('spells', 'source_book_id'));
    }
}

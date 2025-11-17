<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PolymorphicTablesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_traits_table_has_polymorphic_columns(): void
    {
        $this->assertTrue(Schema::hasTable('traits'));
        $this->assertTrue(Schema::hasColumns('traits', [
            'id', 'reference_type', 'reference_id', 'name', 'category', 'description',
            'created_at', 'updated_at'
        ]));
    }

    public function test_modifiers_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('modifiers'));
        $this->assertTrue(Schema::hasColumns('modifiers', [
            'id', 'reference_type', 'reference_id', 'modifier_type', 'target',
            'value', 'created_at', 'updated_at'
        ]));
    }

    public function test_proficiencies_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('proficiencies'));
        $this->assertTrue(Schema::hasColumns('proficiencies', [
            'id', 'reference_type', 'reference_id', 'proficiency_type', 'name',
            'created_at', 'updated_at'
        ]));
    }
}

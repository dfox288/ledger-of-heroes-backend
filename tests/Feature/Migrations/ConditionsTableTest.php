<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConditionsTableTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_conditions_table_with_correct_structure(): void
    {
        $this->assertTrue(Schema::hasTable('conditions'));

        $this->assertTrue(Schema::hasColumns('conditions', [
            'id',
            'name',
            'slug',
            'description',
        ]));
    }

    #[Test]
    public function it_has_unique_constraint_on_slug(): void
    {
        $indexes = Schema::getIndexes('conditions');

        $uniqueIndex = collect($indexes)->first(function ($index) {
            return $index['unique'] && in_array('slug', $index['columns']);
        });

        $this->assertNotNull($uniqueIndex, 'Unique index on slug column not found');
    }

    #[Test]
    public function it_does_not_have_timestamps(): void
    {
        $this->assertFalse(Schema::hasColumn('conditions', 'created_at'));
        $this->assertFalse(Schema::hasColumn('conditions', 'updated_at'));
    }
}

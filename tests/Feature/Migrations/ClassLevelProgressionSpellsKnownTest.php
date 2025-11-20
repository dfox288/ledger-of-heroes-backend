<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassLevelProgressionSpellsKnownTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function class_level_progression_table_has_spells_known_column(): void
    {
        $this->assertTrue(
            Schema::hasColumn('class_level_progression', 'spells_known'),
            'class_level_progression table should have spells_known column'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function spells_known_column_is_nullable(): void
    {
        $columns = Schema::getColumns('class_level_progression');
        $spellsKnownColumn = collect($columns)->firstWhere('name', 'spells_known');

        $this->assertNotNull($spellsKnownColumn, 'spells_known column should exist');
        $this->assertTrue($spellsKnownColumn['nullable'], 'spells_known should be nullable');
    }
}

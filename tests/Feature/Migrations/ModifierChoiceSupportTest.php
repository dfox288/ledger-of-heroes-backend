<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ModifierChoiceSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_modifiers_table_has_choice_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('modifiers', 'is_choice'));
        $this->assertTrue(Schema::hasColumn('modifiers', 'choice_count'));
        $this->assertTrue(Schema::hasColumn('modifiers', 'choice_constraint'));
    }

    public function test_choice_columns_have_correct_types(): void
    {
        $isChoice = Schema::getColumnType('modifiers', 'is_choice');
        $choiceCount = Schema::getColumnType('modifiers', 'choice_count');
        $choiceConstraint = Schema::getColumnType('modifiers', 'choice_constraint');

        // SQLite returns 'tinyint' for boolean, MySQL returns 'boolean'
        $this->assertContains($isChoice, ['boolean', 'tinyint']);
        $this->assertEquals('integer', $choiceCount);
        // SQLite returns 'varchar' for string, MySQL returns 'string'
        $this->assertContains($choiceConstraint, ['string', 'varchar']);
    }
}

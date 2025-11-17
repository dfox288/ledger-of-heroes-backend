<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClassSpellMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_spell_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('class_spell'));
        $this->assertTrue(Schema::hasColumns('class_spell', [
            'id', 'spell_id', 'class_name', 'subclass_name', 'created_at', 'updated_at'
        ]));
    }
}

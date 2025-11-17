<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CharacterOptionsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_races_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('races'));
        $this->assertTrue(Schema::hasColumns('races', [
            'id', 'name', 'slug', 'size_id', 'speed', 'source_book_id', 'source_page'
        ]));
    }

    public function test_backgrounds_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('backgrounds'));
        $this->assertTrue(Schema::hasColumns('backgrounds', [
            'id', 'name', 'slug', 'source_book_id', 'source_page'
        ]));
    }

    public function test_feats_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('feats'));
        $this->assertTrue(Schema::hasColumns('feats', [
            'id', 'name', 'slug', 'description', 'source_book_id', 'source_page'
        ]));
    }
}

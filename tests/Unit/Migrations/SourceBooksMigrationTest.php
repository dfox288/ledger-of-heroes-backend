<?php

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SourceBooksMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_books_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasTable('source_books'));
        $this->assertTrue(Schema::hasColumns('source_books', [
            'id', 'code', 'name', 'abbreviation', 'release_date', 'publisher', 'created_at', 'updated_at'
        ]));
    }

    public function test_source_books_code_is_unique(): void
    {
        // Test uniqueness constraint by attempting to insert duplicate code
        DB::table('source_books')->insert([
            'code' => 'TEST',
            'name' => 'Test Book',
            'abbreviation' => 'TEST',
            'release_date' => '2024-01-01',
            'publisher' => 'Test Publisher',
        ]);

        // Attempting to insert another record with the same code should fail
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('source_books')->insert([
            'code' => 'TEST',
            'name' => 'Another Test Book',
            'abbreviation' => 'TEST2',
            'release_date' => '2024-01-02',
            'publisher' => 'Another Publisher',
        ]);
    }

    public function test_source_books_table_is_seeded_with_common_books(): void
    {
        // Verify that 6 common D&D sourcebooks are seeded
        $count = DB::table('source_books')->count();
        $this->assertEquals(6, $count);

        // Verify specific books exist
        $phb = DB::table('source_books')->where('code', 'PHB')->first();
        $this->assertNotNull($phb);
        $this->assertEquals("Player's Handbook", $phb->name);
        $this->assertEquals('2014-08-19', $phb->release_date);

        $xge = DB::table('source_books')->where('code', 'XGE')->first();
        $this->assertNotNull($xge);
        $this->assertEquals("Xanathar's Guide to Everything", $xge->name);

        $tce = DB::table('source_books')->where('code', 'TCE')->first();
        $this->assertNotNull($tce);
        $this->assertEquals("Tasha's Cauldron of Everything", $tce->name);
    }
}

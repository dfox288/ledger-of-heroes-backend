<?php

namespace Tests\Feature\Commands;

use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportItemsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_items_from_xml_file(): void
    {
        $this->artisan('import:items', ['file' => 'import-files/items-base-phb.xml'])
            ->expectsOutput('Importing items from: import-files/items-base-phb.xml')
            ->assertSuccessful();

        $this->assertGreaterThan(0, Item::count());
    }

    public function test_shows_error_for_missing_file(): void
    {
        $this->artisan('import:items', ['file' => 'nonexistent.xml'])
            ->expectsOutput('Error: File not found')
            ->assertFailed();
    }

    public function test_shows_progress_during_import(): void
    {
        $this->artisan('import:items', ['file' => 'import-files/items-base-phb.xml'])
            ->expectsOutput('Import complete!')
            ->assertSuccessful();
    }
}

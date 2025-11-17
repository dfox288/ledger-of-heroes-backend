<?php

namespace Tests\Feature\Commands;

use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportSpellsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_spells_from_xml_file(): void
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml'])
            ->expectsOutput('Importing spells from: import-files/spells-phb.xml')
            ->assertSuccessful();

        $this->assertGreaterThan(0, Spell::count());
    }

    public function test_shows_error_for_missing_file(): void
    {
        $this->artisan('import:spells', ['file' => 'nonexistent.xml'])
            ->expectsOutput('Error: File not found')
            ->assertFailed();
    }

    public function test_shows_import_complete_message(): void
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml'])
            ->expectsOutput('Import complete!')
            ->assertSuccessful();
    }

    public function test_shows_count_of_imported_spells(): void
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml'])
            ->expectsOutputToContain('Imported')
            ->expectsOutputToContain('spells')
            ->assertSuccessful();
    }
}

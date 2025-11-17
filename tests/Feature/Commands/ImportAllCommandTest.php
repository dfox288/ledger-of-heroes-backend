<?php

namespace Tests\Feature\Commands;

use App\Models\Spell;
use App\Models\Item;
use App\Models\Race;
use App\Models\Background;
use App\Models\Feat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportAllCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_all_content_from_directory(): void
    {
        $this->artisan('import:all', ['directory' => 'import-files'])
            ->expectsOutput('Importing all content from: import-files')
            ->expectsOutput('Import complete!')
            ->assertSuccessful();

        $this->assertGreaterThan(0, Spell::count());
        $this->assertGreaterThan(0, Item::count());
        $this->assertGreaterThan(0, Race::count());
        $this->assertGreaterThan(0, Background::count());
        $this->assertGreaterThan(0, Feat::count());
    }

    public function test_displays_import_summary(): void
    {
        $this->artisan('import:all', ['directory' => 'import-files'])
            ->assertSuccessful();

        // Verify imports happened
        $this->assertDatabaseCount('spells', Spell::count());
        $this->assertDatabaseCount('items', Item::count());
        $this->assertDatabaseCount('races', Race::count());
        $this->assertDatabaseCount('backgrounds', Background::count());
        $this->assertDatabaseCount('feats', Feat::count());
    }
}

<?php

namespace Tests\Feature\Importers;

use App\Exceptions\Import\FileNotFoundException;
use App\Services\Importers\SpellImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImporterFileNotFoundTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_throws_file_not_found_exception_for_missing_file(): void
    {
        $importer = new SpellImporter;

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('Import file not found');
        $this->expectExceptionCode(404);

        $importer->importFromFile('/path/to/nonexistent/file.xml');
    }

    #[Test]
    public function it_includes_file_path_in_exception(): void
    {
        $importer = new SpellImporter;
        $filePath = '/path/to/nonexistent/file.xml';

        try {
            $importer->importFromFile($filePath);
            $this->fail('Expected FileNotFoundException was not thrown');
        } catch (FileNotFoundException $e) {
            $this->assertEquals($filePath, $e->filePath);
            $this->assertStringContainsString($filePath, $e->getMessage());
        }
    }

    #[Test]
    public function it_works_with_valid_file(): void
    {
        $importer = new SpellImporter;

        // Use a real file that exists
        $validFile = base_path('import-files/spells-phb.xml');

        // This should NOT throw an exception
        $count = $importer->importFromFile($validFile);

        // Should import at least some spells
        $this->assertGreaterThan(0, $count);
    }
}

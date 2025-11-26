<?php

namespace Tests\Feature\Importers;

use App\Models\Source;
use App\Services\Importers\SourceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SourceImporterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Disable seeding - this test creates its own source data.
     */
    protected $seed = false;

    private SourceImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new SourceImporter;
    }

    #[Test]
    public function it_imports_a_source_with_all_fields(): void
    {
        $data = [
            'name' => "Player's Handbook (2014)",
            'code' => 'PHB',
            'url' => 'https://dndbeyond.com/phb',
            'author' => 'Jeremy Crawford, Mike Mearls',
            'artist' => 'Kate Irwin',
            'publisher' => 'Wizards of the Coast',
            'website' => 'https://dndbeyond.com/',
            'category' => 'Core Rulebooks',
            'publication_year' => 2014,
            'description' => 'The essential reference for D&D players.',
        ];

        $source = $this->importer->import($data);

        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals("Player's Handbook (2014)", $source->name);
        $this->assertEquals('PHB', $source->code);
        $this->assertEquals('https://dndbeyond.com/phb', $source->url);
        $this->assertEquals('Jeremy Crawford, Mike Mearls', $source->author);
        $this->assertEquals('Kate Irwin', $source->artist);
        $this->assertEquals('Wizards of the Coast', $source->publisher);
        $this->assertEquals('https://dndbeyond.com/', $source->website);
        $this->assertEquals('Core Rulebooks', $source->category);
        $this->assertEquals(2014, $source->publication_year);
        $this->assertEquals('The essential reference for D&D players.', $source->description);
    }

    #[Test]
    public function it_imports_a_source_with_minimal_fields(): void
    {
        $data = [
            'name' => 'Minimal Source',
            'code' => 'MIN',
            'url' => null,
            'author' => null,
            'artist' => null,
            'publisher' => 'Wizards of the Coast',
            'website' => null,
            'category' => null,
            'publication_year' => null,
            'description' => null,
        ];

        $source = $this->importer->import($data);

        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals('Minimal Source', $source->name);
        $this->assertEquals('MIN', $source->code);
        $this->assertNull($source->url);
        $this->assertNull($source->author);
    }

    #[Test]
    public function it_updates_existing_source_on_reimport(): void
    {
        // Ensure clean state - delete any existing PHB source from prior tests
        Source::where('code', 'PHB')->delete();

        // Create initial source
        Source::create([
            'code' => 'PHB',
            'name' => 'Old Name',
            'publisher' => 'Old Publisher',
            'publication_year' => 2010,
        ]);

        // Import updated data
        $data = [
            'name' => "Player's Handbook (2014)",
            'code' => 'PHB',
            'url' => 'https://dndbeyond.com/phb',
            'author' => 'New Author',
            'artist' => null,
            'publisher' => 'Wizards of the Coast',
            'website' => null,
            'category' => 'Core Rulebooks',
            'publication_year' => 2014,
            'description' => 'Updated description.',
        ];

        $source = $this->importer->import($data);

        // Should update, not create a second PHB
        $this->assertCount(1, Source::where('code', 'PHB')->get());
        $this->assertEquals("Player's Handbook (2014)", $source->name);
        $this->assertEquals('Wizards of the Coast', $source->publisher);
        $this->assertEquals(2014, $source->publication_year);
        $this->assertEquals('New Author', $source->author);
    }

    #[Test]
    public function it_imports_from_xml_file(): void
    {
        // Use actual PHB source file
        $filePath = base_path('import-files/source-phb.xml');

        if (! file_exists($filePath)) {
            $this->markTestSkipped('source-phb.xml not found in import-files/');
        }

        $count = $this->importer->importFromFile($filePath);

        $this->assertEquals(1, $count);

        $source = Source::where('code', 'PHB')->first();
        $this->assertNotNull($source);
        $this->assertStringContainsString("Player's Handbook", $source->name);
        $this->assertEquals(2014, $source->publication_year);
        $this->assertEquals('Core Rulebooks', $source->category);
    }

    #[Test]
    public function it_is_idempotent_across_multiple_imports(): void
    {
        // Ensure clean state - delete any existing sources from prior tests
        Source::query()->delete();

        $data = [
            'name' => 'Test Source',
            'code' => 'TST',
            'url' => null,
            'author' => null,
            'artist' => null,
            'publisher' => 'Wizards of the Coast',
            'website' => null,
            'category' => null,
            'publication_year' => 2020,
            'description' => null,
        ];

        // Import multiple times
        $this->importer->import($data);
        $this->importer->import($data);
        $this->importer->import($data);

        // Should only have one record (the one we just imported)
        $this->assertCount(1, Source::all());
    }
}

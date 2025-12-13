<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('importers')]
class ClassImporterBardicInspirationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private ClassImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new ClassImporter;
    }

    #[Test]
    public function it_imports_bard_with_bardic_inspiration_counters(): void
    {
        // Parse the Bard XML
        $xmlPath = base_path('import-files/class-bard-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $bardData = $classes[0];

        // Import the base Bard class
        $bard = $this->importer->import($bardData);

        // Assert base class created
        $this->assertInstanceOf(CharacterClass::class, $bard);
        $this->assertEquals('Bard', $bard->name);

        // Assert Bardic Inspiration counters imported
        $counters = $bard->counters;
        $bardicInspirationCounters = $counters->where('counter_name', 'Bardic Inspiration');

        // Should have counters for all 20 levels
        $this->assertCount(20, $bardicInspirationCounters);
    }

    #[Test]
    public function it_sets_long_rest_reset_for_levels_1_to_4(): void
    {
        $xmlPath = base_path('import-files/class-bard-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $bardData = $classes[0];

        $bard = $this->importer->import($bardData);

        // Levels 1-4 should reset on long rest
        for ($level = 1; $level <= 4; $level++) {
            $counter = $bard->counters()
                ->where('counter_name', 'Bardic Inspiration')
                ->where('level', $level)
                ->first();

            $this->assertNotNull($counter, "Missing Bardic Inspiration counter at level {$level}");
            $this->assertEquals('L', $counter->reset_timing, "Level {$level} should reset on long rest");
        }
    }

    #[Test]
    public function it_sets_short_rest_reset_for_levels_5_and_above(): void
    {
        $xmlPath = base_path('import-files/class-bard-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $bardData = $classes[0];

        $bard = $this->importer->import($bardData);

        // Levels 5+ should reset on short rest (Font of Inspiration)
        for ($level = 5; $level <= 20; $level++) {
            $counter = $bard->counters()
                ->where('counter_name', 'Bardic Inspiration')
                ->where('level', $level)
                ->first();

            $this->assertNotNull($counter, "Missing Bardic Inspiration counter at level {$level}");
            $this->assertEquals('S', $counter->reset_timing, "Level {$level} should reset on short rest (Font of Inspiration)");
        }
    }

    #[Test]
    public function it_uses_placeholder_value_for_bardic_inspiration(): void
    {
        $xmlPath = base_path('import-files/class-bard-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $bardData = $classes[0];

        $bard = $this->importer->import($bardData);

        // All Bardic Inspiration counters should have value 1 (placeholder)
        // Actual uses = CHA modifier is computed at runtime
        $counter = $bard->counters()
            ->where('counter_name', 'Bardic Inspiration')
            ->where('level', 1)
            ->first();

        $this->assertNotNull($counter);
        $this->assertEquals(1, $counter->counter_value);
    }
}

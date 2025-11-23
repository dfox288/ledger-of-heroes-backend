<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Services\Importers\ClassImporter;
use App\Services\Importers\MergeMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassImporterMergeTest extends TestCase
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
    public function it_merges_subclasses_from_multiple_sources_without_duplication()
    {
        // Step 1: Import PHB Barbarian (has Path of the Berserker, Path of the Totem Warrior)
        $phbData = [
            'name' => 'Barbarian',
            'hit_die' => 12,
            'traits' => [],
            'proficiencies' => [],
            'features' => [],
            'spell_progression' => [],
            'counters' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [
                [
                    'name' => 'Path of the Berserker',
                    'features' => [],
                    'counters' => [],
                ],
                [
                    'name' => 'Path of the Totem Warrior',
                    'features' => [],
                    'counters' => [],
                ],
            ],
        ];

        $barbarian = $this->importer->import($phbData);

        $this->assertEquals(2, $barbarian->subclasses()->count());

        // Step 2: Merge XGE Barbarian (adds Path of the Ancestral Guardian, Path of the Storm Herald)
        $xgeData = [
            'name' => 'Barbarian',
            'hit_die' => 12,
            'traits' => [],
            'proficiencies' => [],
            'features' => [],
            'spell_progression' => [],
            'counters' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [
                [
                    'name' => 'Path of the Ancestral Guardian',
                    'features' => [],
                    'counters' => [],
                ],
                [
                    'name' => 'Path of the Storm Herald',
                    'features' => [],
                    'counters' => [],
                ],
            ],
        ];

        $barbarian = $this->importer->importWithMerge($xgeData, MergeMode::MERGE);

        // Should now have 4 subclasses total
        $this->assertEquals(4, $barbarian->subclasses()->count());

        // Verify subclass names
        $subclassNames = $barbarian->subclasses()->pluck('name')->toArray();
        $this->assertContains('Path of the Berserker', $subclassNames);
        $this->assertContains('Path of the Totem Warrior', $subclassNames);
        $this->assertContains('Path of the Ancestral Guardian', $subclassNames);
        $this->assertContains('Path of the Storm Herald', $subclassNames);
    }

    #[Test]
    public function it_skips_duplicate_subclasses_when_merging()
    {
        // Create Barbarian with Path of the Berserker
        $barbarian = $this->importer->import([
            'name' => 'Barbarian',
            'hit_die' => 12,
            'traits' => [],
            'proficiencies' => [],
            'features' => [],
            'spell_progression' => [],
            'counters' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [
                [
                    'name' => 'Path of the Berserker',
                    'features' => [],
                    'counters' => [],
                ],
            ],
        ]);

        $this->assertEquals(1, $barbarian->subclasses()->count());

        // Try to merge duplicate subclass
        $duplicateData = [
            'name' => 'Barbarian',
            'hit_die' => 12,
            'traits' => [],
            'proficiencies' => [],
            'features' => [],
            'spell_progression' => [],
            'counters' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [
                [
                    'name' => 'Path of the Berserker', // Duplicate!
                    'features' => [],
                    'counters' => [],
                ],
                [
                    'name' => 'Path of the Totem Warrior', // New
                    'features' => [],
                    'counters' => [],
                ],
            ],
        ];

        $barbarian = $this->importer->importWithMerge($duplicateData, MergeMode::MERGE);

        // Should only add 1 new subclass (Totem Warrior), skipping Berserker
        $this->assertEquals(2, $barbarian->subclasses()->count());
    }

    #[Test]
    public function it_skips_import_in_skip_if_exists_mode()
    {
        // Create Barbarian
        $barbarian = $this->importer->import([
            'name' => 'Barbarian',
            'hit_die' => 12,
            'traits' => [],
            'proficiencies' => [],
            'features' => [],
            'spell_progression' => [],
            'counters' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [],
        ]);

        $originalId = $barbarian->id;

        // Try to import again with SKIP_IF_EXISTS
        $result = $this->importer->importWithMerge([
            'name' => 'Barbarian',
            'hit_die' => 12,
            'traits' => [],
            'proficiencies' => [],
            'features' => [],
            'spell_progression' => [],
            'counters' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [],
        ], MergeMode::SKIP_IF_EXISTS);

        // Should return existing class, not create new one
        $this->assertEquals($originalId, $result->id);
        $this->assertEquals(1, CharacterClass::where('slug', 'barbarian')->count());
    }
}

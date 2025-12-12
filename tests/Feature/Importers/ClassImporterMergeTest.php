<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Services\Importers\ClassImporter;
use App\Services\Importers\MergeMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
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
    public function it_merges_classes_with_different_source_prefixes()
    {
        // This tests the fix for issue where PHB creates phb:barbarian
        // but XGE generates xge:barbarian and doesn't find the existing class

        // Step 1: Import PHB Barbarian (sources array gives PHB prefix)
        $phbData = [
            'name' => 'Barbarian',
            'hit_die' => 12,
            'traits' => [
                [
                    'name' => 'Rage',
                    'description' => 'You can rage.',
                    'sources' => [['code' => 'PHB', 'page' => '46']],
                ],
            ],
            'proficiencies' => [],
            'features' => [],
            'spell_progression' => [],
            'counters' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [
                [
                    'name' => 'Path of the Berserker',
                    'features' => [
                        [
                            'name' => 'Frenzy',
                            'level' => 3,
                            'is_optional' => false,
                            'description' => 'You can go into a frenzy.',
                            'sort_order' => 0,
                            'sources' => [['code' => 'PHB', 'page' => '49']],
                        ],
                    ],
                    'counters' => [],
                ],
            ],
        ];

        $barbarian = $this->importer->import($phbData);

        // Verify PHB created the class with phb: prefix
        $this->assertEquals('phb:barbarian', $barbarian->slug);
        $this->assertEquals(1, $barbarian->subclasses()->count());

        // Step 2: Merge XGE Barbarian (different source prefix)
        $xgeData = [
            'name' => 'Barbarian',
            'hit_die' => 12,
            'traits' => [
                [
                    'name' => 'Additional Primal Paths',
                    'description' => 'XGE adds more paths.',
                    'sources' => [['code' => 'XGE', 'page' => '9']],
                ],
            ],
            'proficiencies' => [],
            'features' => [],
            'spell_progression' => [],
            'counters' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [
                [
                    'name' => 'Path of the Ancestral Guardian',
                    'features' => [
                        [
                            'name' => 'Ancestral Protectors',
                            'level' => 3,
                            'is_optional' => false,
                            'description' => 'Spectral warriors appear.',
                            'sort_order' => 0,
                            'sources' => [['code' => 'XGE', 'page' => '10']],
                        ],
                    ],
                    'counters' => [],
                ],
            ],
        ];

        // XGE would generate xge:barbarian, but should find existing phb:barbarian by name
        $result = $this->importer->importWithMerge($xgeData, MergeMode::MERGE);

        // Should merge into the existing class (same ID, same slug)
        $this->assertEquals($barbarian->id, $result->id);
        $this->assertEquals('phb:barbarian', $result->slug);

        // Should now have 2 subclasses
        $this->assertEquals(2, $result->subclasses()->count());

        // Verify no duplicate base class was created
        $this->assertEquals(1, CharacterClass::where('name', 'Barbarian')
            ->whereNull('parent_class_id')
            ->count());
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
        // No sources in test data → defaults to 'core' prefix
        $this->assertEquals(1, CharacterClass::where('slug', 'core:barbarian')->count());
    }

    #[Test]
    public function it_merges_features_from_supplement_without_duplicating_existing()
    {
        // Step 1: Import PHB Warlock with base pact boons
        $phbData = [
            'name' => 'Warlock',
            'hit_die' => 8,
            'traits' => [],
            'proficiencies' => [],
            'features' => [
                [
                    'name' => 'Pact Boon',
                    'level' => 3,
                    'is_optional' => false,
                    'description' => 'At 3rd level, your patron bestows a gift upon you.',
                    'sort_order' => 0,
                ],
                [
                    'name' => 'Pact Boon: Pact of the Chain',
                    'level' => 3,
                    'is_optional' => true,
                    'description' => 'You learn the find familiar spell.',
                    'sort_order' => 1,
                ],
                [
                    'name' => 'Pact Boon: Pact of the Blade',
                    'level' => 3,
                    'is_optional' => true,
                    'description' => 'You can create a pact weapon.',
                    'sort_order' => 2,
                ],
                [
                    'name' => 'Pact Boon: Pact of the Tome',
                    'level' => 3,
                    'is_optional' => true,
                    'description' => 'Your patron gives you a grimoire.',
                    'sort_order' => 3,
                ],
            ],
            'spell_progression' => [],
            'counters' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [],
        ];

        $warlock = $this->importer->import($phbData);

        $this->assertEquals(4, $warlock->features()->count());

        // Step 2: Merge TCE Warlock with Pact of the Talisman (new feature)
        $tceData = [
            'name' => 'Warlock',
            'hit_die' => 8,
            'traits' => [],
            'proficiencies' => [],
            'features' => [
                // This is the new feature from TCE
                [
                    'name' => 'Pact Boon: Pact of the Talisman',
                    'level' => 3,
                    'is_optional' => true,
                    'description' => 'Your patron gives you an amulet, a talisman.',
                    'sort_order' => 4,
                ],
                // This is a duplicate - should NOT be added again
                [
                    'name' => 'Pact Boon: Pact of the Chain',
                    'level' => 3,
                    'is_optional' => true,
                    'description' => 'You learn the find familiar spell.',
                    'sort_order' => 1,
                ],
            ],
            'spell_progression' => [],
            'counters' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [],
        ];

        $warlock = $this->importer->importWithMerge($tceData, MergeMode::MERGE);

        // Should now have 5 features (4 original + 1 new Talisman)
        $this->assertEquals(5, $warlock->features()->count());

        // Verify feature names
        $featureNames = $warlock->features()->pluck('feature_name')->toArray();
        $this->assertContains('Pact Boon', $featureNames);
        $this->assertContains('Pact Boon: Pact of the Chain', $featureNames);
        $this->assertContains('Pact Boon: Pact of the Blade', $featureNames);
        $this->assertContains('Pact Boon: Pact of the Tome', $featureNames);
        $this->assertContains('Pact Boon: Pact of the Talisman', $featureNames);
    }

    #[Test]
    public function it_merges_counters_from_supplement_without_duplicating_existing()
    {
        // Step 1: Import base class with features AND counters
        // (Must have features so existingClassMissingBaseData returns false)
        $phbData = [
            'name' => 'TestClass',
            'hit_die' => 10,
            'traits' => [],
            'proficiencies' => [],
            'features' => [
                [
                    'name' => 'Some Feature',
                    'level' => 1,
                    'is_optional' => false,
                    'description' => 'A feature.',
                    'sort_order' => 0,
                ],
            ],
            'spell_progression' => [],
            'counters' => [
                [
                    'name' => 'Existing Counter',
                    'level' => 1,
                    'value' => 2,
                    'reset_timing' => 'short_rest',
                ],
                [
                    'name' => 'Existing Counter',
                    'level' => 5,
                    'value' => 3,
                    'reset_timing' => 'short_rest',
                ],
            ],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [],
        ];

        $class = $this->importer->import($phbData);

        $this->assertEquals(2, $class->counters()->count());
        $this->assertEquals(1, $class->features()->count());

        // Step 2: Merge supplement with new counter
        $supplementData = [
            'name' => 'TestClass',
            'hit_die' => 10,
            'traits' => [],
            'proficiencies' => [],
            'features' => [],
            'spell_progression' => [],
            'counters' => [
                // New counter from supplement
                [
                    'name' => 'New Counter',
                    'level' => 3,
                    'value' => 1,
                    'reset_timing' => 'long_rest',
                ],
                // Duplicate - should NOT be added again
                [
                    'name' => 'Existing Counter',
                    'level' => 1,
                    'value' => 2,
                    'reset_timing' => 'short_rest',
                ],
            ],
            'spell_progression' => [],
            'equipment' => ['wealth' => null, 'items' => []],
            'subclasses' => [],
        ];

        $class = $this->importer->importWithMerge($supplementData, MergeMode::MERGE);

        // Should now have 3 counters (2 original + 1 new)
        $this->assertEquals(3, $class->counters()->count());

        // Verify counter names
        $counterNames = $class->counters()->pluck('counter_name')->unique()->toArray();
        $this->assertContains('Existing Counter', $counterNames);
        $this->assertContains('New Counter', $counterNames);
    }
}

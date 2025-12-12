<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class ClassImporterTest extends TestCase
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
    public function it_imports_base_fighter_class()
    {
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class
        $fighter = $this->importer->import($fighterData);

        // Assert base class created
        $this->assertInstanceOf(CharacterClass::class, $fighter);
        $this->assertEquals('Fighter', $fighter->name);
        $this->assertEquals('phb:fighter', $fighter->slug);
        $this->assertEquals(10, $fighter->hit_die);
        $this->assertNull($fighter->parent_class_id);

        // Assert sources imported
        $this->assertGreaterThan(0, $fighter->sources()->count());
        $entitySource = $fighter->sources()->first();
        $this->assertEquals('PHB', $entitySource->source->code);

        // Assert proficiencies imported
        $this->assertGreaterThan(0, $fighter->proficiencies()->count());

        // Check for specific proficiencies
        $proficiencies = $fighter->proficiencies;
        $proficiencyNames = $proficiencies->pluck('proficiency_name')->toArray();

        // Armor
        $this->assertContains('Light Armor', $proficiencyNames);
        $this->assertContains('Medium Armor', $proficiencyNames);
        $this->assertContains('Heavy Armor', $proficiencyNames);
        $this->assertContains('Shields', $proficiencyNames);

        // Weapons
        $this->assertContains('Simple Weapons', $proficiencyNames);
        $this->assertContains('Martial Weapons', $proficiencyNames);

        // Saving throws
        $savingThrows = $proficiencies->where('proficiency_type', 'saving_throw');
        $this->assertCount(2, $savingThrows);
        $savingThrowNames = $savingThrows->pluck('proficiency_name')->toArray();
        $this->assertContains('Strength', $savingThrowNames);
        $this->assertContains('Constitution', $savingThrowNames);

        // Assert traits imported (flavor text)
        $this->assertGreaterThan(0, $fighter->traits()->count());
        $trait = $fighter->traits->first();
        $this->assertEquals('Fighter', $trait->name);
        $this->assertStringContainsString('unparalleled mastery with weapons', $trait->description);
    }

    #[Test]
    public function it_imports_fighter_features()
    {
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class
        $fighter = $this->importer->import($fighterData);

        // Assert features imported
        $this->assertGreaterThan(0, $fighter->features()->count());

        // Check for specific features
        $features = $fighter->features;
        $featureNames = $features->pluck('feature_name')->toArray();

        // Level 1 features
        $this->assertContains('Second Wind', $featureNames);
        $this->assertContains('Fighting Style', $featureNames);

        // Level 2 features
        $this->assertContains('Action Surge', $featureNames);

        // Level 3 features
        $this->assertContains('Martial Archetype', $featureNames);

        // Level 5 feature
        $this->assertContains('Extra Attack', $featureNames);

        // Check feature attributes
        $secondWind = $features->where('feature_name', 'Second Wind')->first();
        $this->assertNotNull($secondWind);
        $this->assertEquals(1, $secondWind->level);
        $this->assertFalse($secondWind->is_optional);
        $this->assertStringContainsString('bonus action', $secondWind->description);

        // Check optional feature
        $archery = $features->where('feature_name', 'Fighting Style: Archery')->first();
        $this->assertNotNull($archery);
        $this->assertTrue($archery->is_optional);
        $this->assertEquals(1, $archery->level);
    }

    #[Test]
    public function it_imports_fighter_counters()
    {
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class
        $fighter = $this->importer->import($fighterData);

        // Assert counters imported
        $this->assertGreaterThan(0, $fighter->counters()->count());

        // Check for specific counters
        $counters = $fighter->counters;
        $counterNames = $counters->pluck('counter_name')->toArray();

        // Base Fighter counters
        $this->assertContains('Second Wind', $counterNames);
        $this->assertContains('Action Surge', $counterNames);
        $this->assertContains('Indomitable', $counterNames);

        // Check Second Wind counter
        $secondWind = $counters->where('counter_name', 'Second Wind')->where('level', 1)->first();
        $this->assertNotNull($secondWind);
        $this->assertEquals(1, $secondWind->counter_value);
        $this->assertEquals('S', $secondWind->reset_timing);

        // Check Action Surge counter progression
        $actionSurgeLevel2 = $counters->where('counter_name', 'Action Surge')->where('level', 2)->first();
        $this->assertNotNull($actionSurgeLevel2);
        $this->assertEquals(1, $actionSurgeLevel2->counter_value);
        $this->assertEquals('S', $actionSurgeLevel2->reset_timing);

        $actionSurgeLevel17 = $counters->where('counter_name', 'Action Surge')->where('level', 17)->first();
        $this->assertNotNull($actionSurgeLevel17);
        $this->assertEquals(2, $actionSurgeLevel17->counter_value);

        // Check Indomitable progression
        $indomitableLevel9 = $counters->where('counter_name', 'Indomitable')->where('level', 9)->first();
        $this->assertNotNull($indomitableLevel9);
        $this->assertEquals(1, $indomitableLevel9->counter_value);
        $this->assertEquals('L', $indomitableLevel9->reset_timing);

        $indomitableLevel17 = $counters->where('counter_name', 'Indomitable')->where('level', 17)->first();
        $this->assertNotNull($indomitableLevel17);
        $this->assertEquals(3, $indomitableLevel17->counter_value);
    }

    #[Test]
    public function it_imports_fighter_subclasses()
    {
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class FIRST
        $fighter = $this->importer->import($fighterData);

        // Verify subclasses were detected in parser
        $this->assertArrayHasKey('subclasses', $fighterData);
        $this->assertGreaterThan(0, count($fighterData['subclasses']));

        // Check for expected subclasses
        $subclassNames = array_column($fighterData['subclasses'], 'name');
        $this->assertContains('Battle Master', $subclassNames);
        $this->assertContains('Champion', $subclassNames);
        $this->assertContains('Eldritch Knight', $subclassNames);

        // Import subclasses
        $battleMaster = null;
        $champion = null;
        $eldritchKnight = null;

        foreach ($fighterData['subclasses'] as $subclassData) {
            $subclass = $this->importer->importSubclass($fighter, $subclassData);

            if ($subclassData['name'] === 'Battle Master') {
                $battleMaster = $subclass;
            } elseif ($subclassData['name'] === 'Champion') {
                $champion = $subclass;
            } elseif ($subclassData['name'] === 'Eldritch Knight') {
                $eldritchKnight = $subclass;
            }
        }

        // Assert Battle Master created with correct attributes
        $this->assertNotNull($battleMaster);
        $this->assertEquals('Battle Master', $battleMaster->name);
        $this->assertEquals('phb:fighter-battle-master', $battleMaster->slug);
        $this->assertEquals($fighter->id, $battleMaster->parent_class_id);

        // Assert Battle Master has its own features
        $this->assertGreaterThan(0, $battleMaster->features()->count());
        $bmFeatures = $battleMaster->features->pluck('feature_name')->toArray();
        $this->assertContains('Combat Superiority (Battle Master)', $bmFeatures);
        $this->assertContains('Student of War (Battle Master)', $bmFeatures);

        // Assert Battle Master has subclass-specific counters
        $this->assertGreaterThan(0, $battleMaster->counters()->count());
        $bmCounters = $battleMaster->counters->pluck('counter_name')->toArray();
        $this->assertContains('Superiority Die', $bmCounters);

        // Check Superiority Die progression
        $superiorityDieLevel3 = $battleMaster->counters()->where('counter_name', 'Superiority Die')->where('level', 3)->first();
        $this->assertNotNull($superiorityDieLevel3);
        $this->assertEquals(4, $superiorityDieLevel3->counter_value);

        $superiorityDieLevel15 = $battleMaster->counters()->where('counter_name', 'Superiority Die')->where('level', 15)->first();
        $this->assertNotNull($superiorityDieLevel15);
        $this->assertEquals(6, $superiorityDieLevel15->counter_value);

        // Assert Champion created
        $this->assertNotNull($champion);
        $this->assertEquals('phb:fighter-champion', $champion->slug);
        $this->assertEquals($fighter->id, $champion->parent_class_id);

        // Assert Eldritch Knight created
        $this->assertNotNull($eldritchKnight);
        $this->assertEquals('phb:fighter-eldritch-knight', $eldritchKnight->slug);
        $this->assertEquals($fighter->id, $eldritchKnight->parent_class_id);

        // Verify we can query all subclasses from parent
        $this->assertEquals(3, $fighter->subclasses()->count());
    }

    #[Test]
    public function it_imports_skill_proficiencies_as_choices_when_num_skills_present()
    {
        // Parse the Fighter XML (has numSkills)
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the Fighter class
        $fighter = $this->importer->import($fighterData);

        // Get all proficiencies (fixed data)
        $proficiencies = $fighter->proficiencies;
        $this->assertGreaterThan(0, $proficiencies->count());

        // Get skill choices from EntityChoice table
        $skillChoices = \App\Models\EntityChoice::where('reference_type', \App\Models\CharacterClass::class)
            ->where('reference_id', $fighter->id)
            ->where('choice_type', 'proficiency')
            ->where('proficiency_type', 'skill')
            ->get();
        $this->assertGreaterThan(0, $skillChoices->count(), 'Should have skill choices');

        // All skills should be in the same choice group
        $choiceGroup = $skillChoices[0]->choice_group;
        $this->assertNotNull($choiceGroup, 'Skills should have a choice_group');
        $this->assertEquals('skill_choice_1', $choiceGroup);

        // All skills should have same choice_group with sequential choice_option
        foreach ($skillChoices as $index => $skill) {
            $this->assertEquals($choiceGroup, $skill->choice_group, "Skill at index {$index} should have same choice_group");
            $this->assertEquals($index + 1, $skill->choice_option, "Skill at index {$index} should have sequential choice_option");
        }

        // First choice option should have quantity=2 (how many to pick from group)
        // Remaining options should have quantity=1 (default for restricted choices)
        $this->assertEquals(2, $skillChoices[0]->quantity, 'First skill choice should have quantity=2');
        for ($i = 1; $i < $skillChoices->count(); $i++) {
            $this->assertEquals(1, $skillChoices[$i]->quantity, "Skill at index {$i} should have quantity=1");
        }

        // Saving throws should be in proficiencies table (not choices)
        $savingThrows = $proficiencies->where('proficiency_type', 'saving_throw');
        $this->assertCount(2, $savingThrows);

        // Armor proficiencies should be in proficiencies table (not choices)
        $armor = $proficiencies->where('proficiency_type', 'armor');
        $this->assertGreaterThan(0, $armor->count());

        // Weapon proficiencies should be in proficiencies table (not choices)
        $weapons = $proficiencies->where('proficiency_type', 'weapon');
        $this->assertGreaterThan(0, $weapons->count());
    }

    #[Test]
    public function it_imports_starting_equipment_for_class()
    {
        // Load Barbarian XML (has wealth tag and starting equipment)
        $xmlPath = base_path('import-files/class-barbarian-phb.xml');
        $this->assertFileExists($xmlPath);

        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $barbarianData = $classes[0];

        // Import the Barbarian class
        $barbarian = $this->importer->import($barbarianData);

        // Assert: equipment was imported (fixed items in entity_items)
        $this->assertGreaterThan(0, $barbarian->equipment()->count(), 'Barbarian should have equipment');

        // Assert: verify equipment choices in EntityChoice table
        $equipmentChoices = \App\Models\EntityChoice::where('reference_type', \App\Models\CharacterClass::class)
            ->where('reference_id', $barbarian->id)
            ->where('choice_type', 'equipment')
            ->get();
        $this->assertGreaterThan(0, $equipmentChoices->count(), 'Should have at least one equipment choice');

        // Assert: all fixed equipment has description
        foreach ($barbarian->equipment as $item) {
            $this->assertNotEmpty($item->description, 'Equipment item should have description');
            $this->assertIsInt($item->quantity, 'Equipment item should have quantity');
            $this->assertGreaterThan(0, $item->quantity, 'Equipment quantity should be at least 1');
        }
    }

    #[Test]
    public function it_imports_subclass_source_attribution()
    {
        // Issue #141: Subclasses should have their own source attribution
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class (which also imports subclasses)
        $fighter = $this->importer->import($fighterData);

        // Assert base class has sources
        $this->assertGreaterThan(0, $fighter->sources()->count(), 'Base class should have sources');

        // Import subclasses and verify they have sources
        foreach ($fighterData['subclasses'] as $subclassData) {
            $subclass = $this->importer->importSubclass($fighter, $subclassData);

            // Assert subclass has sources
            $this->assertGreaterThan(
                0,
                $subclass->sources()->count(),
                "Subclass {$subclass->name} should have sources"
            );

            // Assert PHB is among the sources (all Fighter subclasses in PHB are from PHB)
            $sourceCodes = $subclass->sources->pluck('source.code')->toArray();
            $this->assertContains('PHB', $sourceCodes, "Subclass {$subclass->name} should have PHB source");
        }

        // Specifically check Battle Master (all features on same page)
        $battleMaster = CharacterClass::where('slug', 'phb:fighter-battle-master')->first();
        $this->assertNotNull($battleMaster);
        $this->assertGreaterThan(0, $battleMaster->sources()->count(), 'Battle Master should have sources');

        // Check that source has page number (Battle Master features are all on p.73)
        $battleMasterSource = $battleMaster->sources()->first();
        $this->assertNotNull($battleMasterSource->pages, 'Battle Master source should have page number');
        $this->assertEquals('73', $battleMasterSource->pages, 'Battle Master should be on page 73');

        // Specifically check Eldritch Knight (features span multiple pages - tests page merging)
        $eldritchKnight = CharacterClass::where('slug', 'phb:fighter-eldritch-knight')->first();
        $this->assertNotNull($eldritchKnight);
        $eldritchKnightSource = $eldritchKnight->sources()->first();
        $this->assertNotNull($eldritchKnightSource->pages, 'Eldritch Knight source should have page number');
        // Eldritch Knight features are on pages 74 and 75 - should be merged
        $this->assertStringContainsString('74', $eldritchKnightSource->pages, 'Eldritch Knight should include page 74');
        $this->assertStringContainsString('75', $eldritchKnightSource->pages, 'Eldritch Knight should include page 75');
    }

    #[Test]
    public function it_populates_slug_for_subclasses()
    {
        // Issue #305: Subclasses should have slug populated with source prefix
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class (which also imports subclasses)
        $fighter = $this->importer->import($fighterData);

        // Import subclasses and verify they have slug
        foreach ($fighterData['subclasses'] as $subclassData) {
            $subclass = $this->importer->importSubclass($fighter, $subclassData);

            // Assert subclass has slug populated
            $this->assertNotNull(
                $subclass->slug,
                "Subclass {$subclass->name} should have slug populated"
            );

            // Assert slug format: {source_code}:{slug}
            $this->assertStringContainsString(
                ':',
                $subclass->slug,
                "Subclass {$subclass->name} slug should contain colon separator"
            );

            // Assert slug starts with lowercase source code
            $this->assertStringStartsWith(
                'phb:',
                $subclass->slug,
                "Subclass {$subclass->name} slug should start with 'phb:'"
            );
        }

        // Specifically check Battle Master
        $battleMaster = CharacterClass::where('slug', 'phb:fighter-battle-master')->first();
        $this->assertNotNull($battleMaster);
        $this->assertEquals(
            'phb:fighter-battle-master',
            $battleMaster->slug,
            'Battle Master should have slug phb:fighter-battle-master'
        );

        // Specifically check Champion
        $champion = CharacterClass::where('slug', 'phb:fighter-champion')->first();
        $this->assertNotNull($champion);
        $this->assertEquals(
            'phb:fighter-champion',
            $champion->slug,
            'Champion should have slug phb:fighter-champion'
        );

        // Specifically check Eldritch Knight
        $eldritchKnight = CharacterClass::where('slug', 'phb:fighter-eldritch-knight')->first();
        $this->assertNotNull($eldritchKnight);
        $this->assertEquals(
            'phb:fighter-eldritch-knight',
            $eldritchKnight->slug,
            'Eldritch Knight should have slug phb:fighter-eldritch-knight'
        );
    }

    #[Test]
    public function it_extracts_sources_from_features_for_base_classes_without_traits()
    {
        // Issue: Sidekick classes (Expert, Spellcaster, Warrior) have no traits
        // but DO have features with source text. Sources should be extracted
        // from features when no traits are present.

        // Create test data simulating a Sidekick-style class (features only, no traits)
        $testData = [
            'name' => 'Test Sidekick',
            'hit_die' => 8,
            'traits' => [], // No traits - like Sidekick classes
            'proficiencies' => [],
            'features' => [
                [
                    'name' => 'Starting Test Sidekick',
                    'level' => 1,
                    'sort_order' => 1,
                    'description' => 'This is a test sidekick class. Source: Tasha\'s Cauldron of Everything p. 142',
                    'is_optional' => false,
                    'sources' => [
                        ['code' => 'TCE', 'page' => '142'],
                    ],
                ],
                [
                    'name' => 'Bonus Feature',
                    'level' => 2,
                    'sort_order' => 2,
                    'description' => 'Another feature. Source: Tasha\'s Cauldron of Everything p. 143',
                    'is_optional' => false,
                    'sources' => [
                        ['code' => 'TCE', 'page' => '143'],
                    ],
                ],
            ],
        ];

        // Import the class
        $class = $this->importer->import($testData);

        // Assert class was created
        $this->assertInstanceOf(CharacterClass::class, $class);
        $this->assertEquals('Test Sidekick', $class->name);
        $this->assertEquals('tce:test-sidekick', $class->slug);

        // Assert features were imported
        $this->assertEquals(2, $class->features()->count(), 'Should have 2 features');

        // Assert sources were extracted from features (the key test)
        $this->assertGreaterThan(
            0,
            $class->sources()->count(),
            'Base class with no traits should extract sources from features'
        );

        // Assert TCE is among the sources
        $sourceCodes = $class->sources->pluck('source.code')->toArray();
        $this->assertContains('TCE', $sourceCodes, 'Should have TCE source from features');

        // Assert slug was generated with source prefix
        $this->assertEquals(
            'tce:test-sidekick',
            $class->slug,
            'Should have slug with TCE prefix'
        );
    }

    #[Test]
    public function it_preserves_distinct_choice_options_for_or_alternatives()
    {
        // Issue #535: Equipment choices with "(a) X or (b) Y" should create
        // separate options, not group both items under choice_option=1
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <class>
        <name>TestCleric</name>
        <hd>8</hd>
        <proficiency>Wisdom, Charisma</proficiency>
        <autolevel level="1">
            <feature>
                <name>Starting TestCleric</name>
                <text>You start with the following equipment, in addition to any equipment provided by your background.

• (a) a mace or (b) a warhammer
• (a) scale mail or (b) leather armor or (c) chain mail
• (a) a light crossbow and 20 bolts or (b) any simple weapon
• (a) a priest's pack or (b) an explorer's pack
• A shield and a holy symbol

Source: PHB, page 999</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $classes = $parser->parse($xml);
        $classData = $classes[0];

        // Import the class
        $class = $this->importer->import($classData);

        // Get all equipment choices for this class
        $equipmentChoices = \App\Models\EntityChoice::where('reference_type', \App\Models\CharacterClass::class)
            ->where('reference_id', $class->id)
            ->where('choice_type', 'equipment')
            ->orderBy('choice_group')
            ->orderBy('choice_option')
            ->get();

        // Group by choice_group to analyze each choice
        $byGroup = $equipmentChoices->groupBy('choice_group');

        // Choice 1: "(a) a mace or (b) a warhammer" - should have 2 distinct options
        $choice1 = $byGroup->get('choice_1');
        $this->assertNotNull($choice1, 'Should have choice_1 group');

        // Get unique choice_option values within choice_1
        $choice1Options = $choice1->pluck('choice_option')->unique()->values();
        $this->assertCount(
            2,
            $choice1Options,
            'choice_1 should have 2 distinct choice_options (1 for mace, 2 for warhammer). '.
            'Got options: '.$choice1Options->implode(', ').
            '. Descriptions: '.$choice1->pluck('description')->implode(', ')
        );
        $this->assertTrue($choice1Options->contains(1), 'choice_1 should have option 1');
        $this->assertTrue($choice1Options->contains(2), 'choice_1 should have option 2');

        // Choice 2: "(a) scale mail or (b) leather armor or (c) chain mail" - should have 3 distinct options
        $choice2 = $byGroup->get('choice_2');
        $this->assertNotNull($choice2, 'Should have choice_2 group');

        $choice2Options = $choice2->pluck('choice_option')->unique()->values();
        $this->assertCount(
            3,
            $choice2Options,
            'choice_2 should have 3 distinct choice_options. '.
            'Got options: '.$choice2Options->implode(', ').
            '. Descriptions: '.$choice2->pluck('description')->implode(', ')
        );

        // Verify each option maps to correct item
        $maceChoice = $choice1->firstWhere('choice_option', 1);
        $warhammerChoice = $choice1->firstWhere('choice_option', 2);

        $this->assertNotNull($maceChoice, 'Should have mace at choice_option=1');
        $this->assertNotNull($warhammerChoice, 'Should have warhammer at choice_option=2');
        $this->assertStringContainsString('mace', strtolower($maceChoice->description));
        $this->assertStringContainsString('warhammer', strtolower($warhammerChoice->description));
    }
}

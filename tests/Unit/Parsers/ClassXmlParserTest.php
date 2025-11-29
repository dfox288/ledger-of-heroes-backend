<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class ClassXmlParserTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ClassXmlParser;
    }

    #[Test]
    public function it_parses_fighter_base_class()
    {
        // Load real Fighter XML from file
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $this->assertFileExists($xmlPath, 'Fighter XML file should exist');

        $xml = file_get_contents($xmlPath);
        $this->assertNotEmpty($xml, 'Fighter XML should not be empty');

        // Parse the XML
        $classes = $this->parser->parse($xml);

        // Assert: result is array
        $this->assertIsArray($classes);

        // Assert: at least one class (base Fighter)
        $this->assertGreaterThanOrEqual(1, count($classes));

        // Assert: first element is Fighter
        $fighter = $classes[0];
        $this->assertArrayHasKey('name', $fighter);
        $this->assertEquals('Fighter', $fighter['name']);

        // Assert: hit die is 10
        $this->assertArrayHasKey('hit_die', $fighter);
        $this->assertEquals(10, $fighter['hit_die']);
    }

    #[Test]
    public function it_parses_fighter_proficiencies()
    {
        // Load real Fighter XML from file
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $fighter = $classes[0];

        // Assert: proficiencies key exists
        $this->assertArrayHasKey('proficiencies', $fighter);
        $this->assertIsArray($fighter['proficiencies']);

        // Assert: should have multiple proficiencies (armor, weapons, skills)
        $this->assertGreaterThan(0, count($fighter['proficiencies']));

        // Assert: proficiency structure
        $firstProf = $fighter['proficiencies'][0];
        $this->assertArrayHasKey('type', $firstProf);
        $this->assertArrayHasKey('name', $firstProf);
        $this->assertArrayHasKey('proficiency_type_id', $firstProf);

        // Assert: specific proficiencies exist
        $profNames = array_column($fighter['proficiencies'], 'name');
        $this->assertContains('Light Armor', $profNames);
        $this->assertContains('Simple Weapons', $profNames);

        // Assert: Fighter gets 2 skill choices
        $this->assertArrayHasKey('skill_choices', $fighter);
        $this->assertEquals(2, $fighter['skill_choices']);
    }

    #[Test]
    public function it_parses_skill_proficiencies_with_global_choice_quantity()
    {
        $xml = <<<'XML'
        <compendium>
            <class>
                <name>Barbarian</name>
                <hd>12</hd>
                <proficiency>Strength, Constitution, Athletics, Animal Handling, Intimidation, Nature, Perception, Survival</proficiency>
                <numSkills>2</numSkills>
            </class>
        </compendium>
        XML;

        $classes = $this->parser->parse($xml);
        $proficiencies = $classes[0]['proficiencies'];

        // Find skill proficiencies
        $skillProfs = array_filter($proficiencies, fn ($p) => $p['type'] === 'skill');

        // Should have 6 skill options (Athletics, Animal Handling, etc.)
        $this->assertCount(6, $skillProfs);

        // All should be marked as choices
        foreach ($skillProfs as $prof) {
            $this->assertTrue($prof['is_choice'], "Skill {$prof['name']} should be a choice");
        }

        // NEW BEHAVIOR: Only first skill in group has quantity
        // All skills should be in same choice group
        $choiceGroups = array_unique(array_column($skillProfs, 'choice_group'));
        $this->assertCount(1, $choiceGroups, 'All skills should be in one choice group');

        // First skill should have quantity=2, others should be null
        $skillsArray = array_values($skillProfs);
        $this->assertEquals(2, $skillsArray[0]['quantity'], 'First skill should have quantity=2');
        $this->assertNull($skillsArray[1]['quantity'], 'Second skill should have null quantity');
        $this->assertNull($skillsArray[2]['quantity'], 'Third skill should have null quantity');

        // All skills should have choice_option numbers (1, 2, 3, 4, 5, 6)
        $choiceOptions = array_column($skillProfs, 'choice_option');
        sort($choiceOptions);
        $this->assertEquals([1, 2, 3, 4, 5, 6], $choiceOptions, 'Skills should have sequential choice_option numbers');

        // Saving throws should NOT be choices
        $savingThrows = array_filter($proficiencies, fn ($p) => $p['type'] === 'saving_throw');
        foreach ($savingThrows as $prof) {
            $this->assertFalse($prof['is_choice'], "Saving throw {$prof['name']} should not be a choice");
        }

        // Verify saving throws are correct (Strength, Constitution)
        $savingThrowNames = array_column($savingThrows, 'name');
        $this->assertContains('Strength', $savingThrowNames);
        $this->assertContains('Constitution', $savingThrowNames);
    }

    #[Test]
    public function it_parses_fighter_traits()
    {
        // Load real Fighter XML from file
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $fighter = $classes[0];

        // Assert: traits key exists
        $this->assertArrayHasKey('traits', $fighter);
        $this->assertIsArray($fighter['traits']);

        // Assert: should have multiple traits (flavor text)
        $this->assertGreaterThan(0, count($fighter['traits']));

        // Assert: trait structure
        $firstTrait = $fighter['traits'][0];
        $this->assertArrayHasKey('name', $firstTrait);
        $this->assertArrayHasKey('description', $firstTrait);
        $this->assertArrayHasKey('category', $firstTrait);
        $this->assertArrayHasKey('sort_order', $firstTrait);

        // Assert: specific traits exist
        $traitNames = array_column($fighter['traits'], 'name');
        $this->assertContains('Fighter', $traitNames);
        $this->assertContains('Well-Rounded Specialists', $traitNames);

        // Assert: traits have source citations extracted
        $fighterTrait = array_values(array_filter($fighter['traits'], fn ($t) => $t['name'] === 'Fighter'))[0];
        $this->assertArrayHasKey('sources', $fighterTrait);
        $this->assertNotEmpty($fighterTrait['sources'], 'Fighter trait should have source citations');
    }

    #[Test]
    public function it_parses_fighter_features_from_autolevel()
    {
        // Load real Fighter XML from file
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $fighter = $classes[0];

        // Assert: features key exists
        $this->assertArrayHasKey('features', $fighter);
        $this->assertIsArray($fighter['features']);

        // Assert: should have multiple features
        $this->assertGreaterThan(0, count($fighter['features']));

        // Assert: feature structure
        $firstFeature = $fighter['features'][0];
        $this->assertArrayHasKey('level', $firstFeature);
        $this->assertArrayHasKey('name', $firstFeature);
        $this->assertArrayHasKey('description', $firstFeature);
        $this->assertArrayHasKey('is_optional', $firstFeature);

        // Assert: specific features exist at correct levels
        $featuresByLevel = [];
        foreach ($fighter['features'] as $feature) {
            $featuresByLevel[$feature['level']][] = $feature['name'];
        }

        // Fighter should have "Fighting Style" at level 1
        $this->assertArrayHasKey(1, $featuresByLevel);
        $level1Features = array_filter($featuresByLevel[1], fn ($name) => str_contains($name, 'Fighting Style'));
        $this->assertNotEmpty($level1Features, 'Fighter should have Fighting Style at level 1');

        // Fighter should have "Action Surge" at level 2
        $this->assertArrayHasKey(2, $featuresByLevel);
        $this->assertContains('Action Surge', $featuresByLevel[2]);
    }

    #[Test]
    public function it_parses_fighter_spell_slots_for_eldritch_knight()
    {
        // Load real Fighter XML from file
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $fighter = $classes[0];

        // Assert: spell_progression key exists
        $this->assertArrayHasKey('spell_progression', $fighter);
        $this->assertIsArray($fighter['spell_progression']);

        // Assert: Base Fighter should NOT have spell slots (they're all optional="YES" for Eldritch Knight)
        // Optional slots belong to subclasses only
        $this->assertEmpty($fighter['spell_progression'], 'Base Fighter should not have spell progression (Eldritch Knight only)');

        // Assert: Eldritch Knight subclass should exist and have features
        $subclasses = $fighter['subclasses'];
        $eldritchKnight = array_values(array_filter($subclasses, fn ($s) => $s['name'] === 'Eldritch Knight'))[0] ?? null;

        $this->assertNotNull($eldritchKnight, 'Should detect Eldritch Knight subclass');
        $this->assertGreaterThan(0, count($eldritchKnight['features']), 'Eldritch Knight should have features');
    }

    #[Test]
    public function it_parses_fighter_counters()
    {
        // Load real Fighter XML from file
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $fighter = $classes[0];

        // Assert: counters key exists
        $this->assertArrayHasKey('counters', $fighter);
        $this->assertIsArray($fighter['counters']);

        // Assert: should have multiple counters (Second Wind, Action Surge, etc.)
        $this->assertGreaterThan(0, count($fighter['counters']));

        // Assert: counter structure
        $firstCounter = $fighter['counters'][0];
        $this->assertArrayHasKey('level', $firstCounter);
        $this->assertArrayHasKey('name', $firstCounter);
        $this->assertArrayHasKey('value', $firstCounter);
        $this->assertArrayHasKey('reset_timing', $firstCounter);

        // Assert: specific counters exist
        $counterNames = array_column($fighter['counters'], 'name');
        $this->assertContains('Second Wind', $counterNames);
        $this->assertContains('Action Surge', $counterNames);

        // Assert: Action Surge counter has correct reset timing (Short rest)
        $actionSurgeCounters = array_filter($fighter['counters'], fn ($c) => $c['name'] === 'Action Surge');
        $this->assertNotEmpty($actionSurgeCounters);
        $actionSurge = array_values($actionSurgeCounters)[0];
        $this->assertEquals('short_rest', $actionSurge['reset_timing']);
        $this->assertEquals(1, $actionSurge['value']);
    }

    #[Test]
    public function it_detects_fighter_subclasses()
    {
        // Load real Fighter XML from file
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $fighter = $classes[0];

        // Assert: subclasses key exists
        $this->assertArrayHasKey('subclasses', $fighter);
        $this->assertIsArray($fighter['subclasses']);

        // Assert: Fighter should have 3 subclasses (Battle Master, Champion, Eldritch Knight)
        $this->assertCount(3, $fighter['subclasses']);

        // Assert: specific subclasses exist
        $subclassNames = array_column($fighter['subclasses'], 'name');
        $this->assertContains('Battle Master', $subclassNames);
        $this->assertContains('Champion', $subclassNames);
        $this->assertContains('Eldritch Knight', $subclassNames);

        // Assert: subclass structure includes features and counters
        $battleMaster = array_values(array_filter($fighter['subclasses'], fn ($s) => $s['name'] === 'Battle Master'))[0];
        $this->assertArrayHasKey('features', $battleMaster);
        $this->assertArrayHasKey('counters', $battleMaster);

        // Battle Master should have features
        $this->assertGreaterThan(0, count($battleMaster['features']));
    }

    #[Test]
    public function it_parses_starting_equipment_from_class()
    {
        $xml = <<<'XML'
<compendium>
    <class>
        <name>Barbarian</name>
        <hd>12</hd>
        <wealth>2d4x10</wealth>
        <autolevel level="1">
            <feature optional="YES">
                <name>Starting Barbarian</name>
                <text>You begin play with the following equipment:
• (a) a greataxe or (b) any martial melee weapon
• (a) two handaxes or (b) any simple weapon
• An explorer's pack, and four javelins

If you forgo this starting equipment, you start with 2d4 × 10 gp to buy your equipment.</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);

        // Assert: equipment key exists
        $this->assertArrayHasKey('equipment', $classes[0]);
        $equipment = $classes[0]['equipment'];

        // Assert: wealth formula is extracted
        $this->assertArrayHasKey('wealth', $equipment);
        $this->assertEquals('2d4x10', $equipment['wealth']);

        // Assert: equipment items were parsed
        $this->assertArrayHasKey('items', $equipment);
        $this->assertNotEmpty($equipment['items']);

        // Assert: should extract choice groups: (a) X or (b) Y
        // Expected: 4 choices + 2 non-choice items = 6 total items
        $this->assertGreaterThanOrEqual(4, count($equipment['items']));

        // Assert: each item has required structure
        foreach ($equipment['items'] as $item) {
            $this->assertArrayHasKey('description', $item);
            $this->assertArrayHasKey('is_choice', $item);
            $this->assertArrayHasKey('quantity', $item);
        }

        // Assert: verify choice parsing
        $choices = array_filter($equipment['items'], fn ($item) => $item['is_choice']);
        $this->assertGreaterThan(0, count($choices), 'Should have at least one choice item');

        // Assert: verify quantity extraction from "four javelins"
        $javelins = array_filter($equipment['items'], fn ($item) => str_contains($item['description'], 'javelins'));
        if (! empty($javelins)) {
            $javelin = array_values($javelins)[0];
            $this->assertEquals(4, $javelin['quantity'], 'Four javelins should have quantity=4');
        }
    }

    #[Test]
    public function it_extracts_archetype_from_fighter()
    {
        // Load real Fighter XML from file
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $fighter = $classes[0];

        // Assert: archetype is extracted
        $this->assertArrayHasKey('archetype', $fighter);
        $this->assertEquals('Martial Archetype', $fighter['archetype']);
    }

    #[Test]
    public function it_extracts_archetype_from_cleric()
    {
        // Load real Cleric XML from file
        $xmlPath = base_path('import-files/class-cleric-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $cleric = $classes[0];

        // Assert: archetype is extracted
        $this->assertArrayHasKey('archetype', $cleric);
        $this->assertEquals('Divine Domain', $cleric['archetype']);
    }

    #[Test]
    public function it_extracts_archetype_from_wizard()
    {
        // Load real Wizard XML from file
        $xmlPath = base_path('import-files/class-wizard-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $wizard = $classes[0];

        // Assert: archetype is extracted
        $this->assertArrayHasKey('archetype', $wizard);
        $this->assertEquals('Arcane Tradition', $wizard['archetype']);
    }

    #[Test]
    public function it_extracts_archetype_from_barbarian()
    {
        // Load real Barbarian XML from file
        $xmlPath = base_path('import-files/class-barbarian-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $barbarian = $classes[0];

        // Assert: archetype is extracted
        $this->assertArrayHasKey('archetype', $barbarian);
        $this->assertEquals('Primal Path', $barbarian['archetype']);
    }
}

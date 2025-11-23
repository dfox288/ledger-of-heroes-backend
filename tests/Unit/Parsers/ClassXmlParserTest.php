<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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

        // All should have same quantity (2)
        foreach ($skillProfs as $prof) {
            $this->assertTrue($prof['is_choice'], "Skill {$prof['name']} should be a choice");
            $this->assertEquals(2, $prof['quantity'], "All skills should have quantity=2 (choose 2 from list)");
        }

        // Should have 6 skill options (Athletics, Animal Handling, etc.)
        $this->assertCount(6, $skillProfs);

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

        // Assert: should have spell slots (Eldritch Knight subclass)
        // Format: <slots optional="YES">2,2</slots> at level 3 means 2 cantrips, 2 1st-level slots
        $this->assertGreaterThan(0, count($fighter['spell_progression']));

        // Assert: spell slot structure
        $firstSlot = $fighter['spell_progression'][0];
        $this->assertArrayHasKey('level', $firstSlot);
        $this->assertArrayHasKey('cantrips_known', $firstSlot);

        // Assert: Eldritch Knight starts spell slots at level 3
        $level3Slots = array_filter($fighter['spell_progression'], fn ($slot) => $slot['level'] === 3);
        $this->assertNotEmpty($level3Slots, 'Fighter (Eldritch Knight) should have spell slots at level 3');

        $level3Slot = array_values($level3Slots)[0];
        $this->assertEquals(2, $level3Slot['cantrips_known'], 'Level 3 Eldritch Knight should know 2 cantrips');
        $this->assertEquals(2, $level3Slot['spell_slots_1st'], 'Level 3 Eldritch Knight should have 2 1st-level slots');
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
}

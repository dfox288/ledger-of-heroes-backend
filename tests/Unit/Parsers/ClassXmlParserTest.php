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
    public function it_parses_starting_equipment_with_you_start_with_variant()
    {
        // Artificer (and some other non-PHB classes) use "You start with" instead of "You begin play with"
        // This test ensures the parser handles both variants correctly
        $xml = <<<'XML'
<compendium>
    <class>
        <name>Artificer</name>
        <hd>8</hd>
        <wealth>5d4x10</wealth>
        <autolevel level="1">
            <feature optional="YES">
                <name>Starting Artificer</name>
                <text>As a 1st-level Artificer, you begin play with 8 + your Constitution modifier hit points.

You are proficient with the following items, in addition to any proficiencies provided by your race or background.

	• Armor: light armor, medium armor, shields
	• Weapons: simple weapons
	• Tools: thieves' tools, tinker's tools, one type of artisan's tools of your choice
	• Skills: Choose 2 from Arcana, History, Investigation, Medicine, Nature, Perception, Sleight of Hand

You start with the following equipment, in addition to the equipment granted by your background:

	• any two simple weapons of your choice
	• a light crossbow and 20 bolts
	• your choice of studded leather armor or scale mail
	• thieves' tools and a dungeoneer's pack

If you forgo this starting equipment, as well as the items offered by your background, you start with 5d4 × 10 gp to buy your equipment.</text>
            </feature>
        </autolevel>
    </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);

        // Assert: equipment key exists
        $this->assertArrayHasKey('equipment', $classes[0]);
        $equipment = $classes[0]['equipment'];

        // Assert: equipment items were parsed
        $this->assertArrayHasKey('items', $equipment);
        $this->assertNotEmpty($equipment['items'], 'Should have equipment items');

        // Assert: should have 7 equipment items (4 bullet points split into 7 items)
        // - "any two simple weapons of your choice" = 1 item (choice)
        // - "a light crossbow and 20 bolts" = 2 items (crossbow + bolts)
        // - "your choice of studded leather armor or scale mail" = 2 items (one per option in choice)
        // - "thieves' tools and a dungeoneer's pack" = 2 items (tools + pack)
        $this->assertCount(7, $equipment['items'], 'Should have exactly 7 equipment items');

        // Assert: verify equipment descriptions are actual equipment, not proficiencies
        $descriptions = array_column($equipment['items'], 'description');

        // Should contain equipment items
        $this->assertTrue(
            collect($descriptions)->contains(fn ($d) => str_contains(strtolower($d), 'simple weapon')),
            'Should have simple weapons equipment'
        );
        $this->assertTrue(
            collect($descriptions)->contains(fn ($d) => str_contains(strtolower($d), 'crossbow')),
            'Should have crossbow equipment'
        );
        $this->assertTrue(
            collect($descriptions)->contains(fn ($d) => str_contains(strtolower($d), 'armor') || str_contains(strtolower($d), 'scale mail')),
            'Should have armor equipment'
        );
        $this->assertTrue(
            collect($descriptions)->contains(fn ($d) => str_contains(strtolower($d), 'dungeoneer')),
            'Should have dungeoneer pack'
        );

        // Should NOT contain proficiency text
        $this->assertFalse(
            collect($descriptions)->contains(fn ($d) => str_contains(strtolower($d), 'skills:')),
            'Should not include Skills proficiency text'
        );
        $this->assertFalse(
            collect($descriptions)->contains(fn ($d) => str_contains(strtolower($d), 'hit points')),
            'Should not include HP text'
        );
        $this->assertFalse(
            collect($descriptions)->contains(fn ($d) => $d === 'History' || $d === 'Investigation'),
            'Should not include skill names as equipment'
        );
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

    #[Test]
    public function it_adds_unlimited_rage_at_level_20_for_barbarian()
    {
        // Load real Barbarian XML from file
        $xmlPath = base_path('import-files/class-barbarian-phb.xml');
        $xml = file_get_contents($xmlPath);

        // Parse the XML
        $classes = $this->parser->parse($xml);
        $barbarian = $classes[0];

        // Assert: counters key exists
        $this->assertArrayHasKey('counters', $barbarian);

        // Get all Rage counters
        $rageCounters = array_filter($barbarian['counters'], fn ($c) => $c['name'] === 'Rage');
        $this->assertNotEmpty($rageCounters, 'Should have Rage counters');

        // Find level 20 rage counter
        $level20Rage = array_filter($rageCounters, fn ($c) => $c['level'] === 20);
        $this->assertNotEmpty($level20Rage, 'Should have Rage counter at level 20');

        // Assert: level 20 should be "Unlimited" (represented as -1 or special value)
        $level20 = array_values($level20Rage)[0];
        $this->assertEquals(-1, $level20['value'], 'Level 20 Rage should be Unlimited (represented as -1)');
    }

    #[Test]
    public function it_parses_artisan_tool_choice_as_choice_proficiency()
    {
        // Artificer has: Thieves' Tools, Tinker's Tools, one type of Artisan's Tools of your choice
        $xml = <<<'XML'
        <compendium>
            <class>
                <name>Artificer</name>
                <hd>8</hd>
                <tools>Thieves' Tools, Tinker's Tools, one type of Artisan's Tools of your choice</tools>
            </class>
        </compendium>
        XML;

        $classes = $this->parser->parse($xml);
        $proficiencies = $classes[0]['proficiencies'];

        // Filter to only tool proficiencies
        $toolProfs = array_values(array_filter($proficiencies, fn ($p) => $p['type'] === 'tool'));

        // Should have 3 tool proficiencies: 2 fixed + 1 choice (with subcategory reference)
        $this->assertCount(3, $toolProfs, 'Should have 3 tool proficiencies');

        // Find the artisan tools choice
        $artisanChoices = array_values(array_filter($toolProfs, fn ($p) => ($p['is_choice'] ?? false) === true));
        $this->assertCount(1, $artisanChoices, 'Should have exactly 1 artisan tool choice entry');

        $artisanChoice = $artisanChoices[0];

        // Verify the choice proficiency structure
        $this->assertTrue($artisanChoice['is_choice'], 'Should be marked as a choice');
        $this->assertEquals('tool_choice_1', $artisanChoice['choice_group'], 'Should have choice_group');
        $this->assertEquals(1, $artisanChoice['quantity'], 'Should have quantity=1 (pick one)');
        $this->assertEquals('artisan', $artisanChoice['proficiency_subcategory'], 'Should reference artisan subcategory');
        $this->assertNull($artisanChoice['proficiency_type_id'], 'Should not have specific type ID (it\'s a choice)');
        $this->assertStringContainsString('Artisan', $artisanChoice['name'], 'Should preserve original description');

        // Thieves' Tools and Tinker's Tools should be fixed proficiencies (not choices)
        $fixedTools = array_values(array_filter($toolProfs, fn ($p) => ($p['is_choice'] ?? false) === false));
        $this->assertCount(2, $fixedTools, 'Should have 2 fixed tool proficiencies');

        $fixedNames = array_column($fixedTools, 'name');
        $this->assertContains("Thieves' Tools", $fixedNames, "Thieves' Tools should be a fixed proficiency");
        $this->assertContains("Tinker's Tools", $fixedNames, "Tinker's Tools should be a fixed proficiency");
    }

    #[Test]
    public function it_parses_musical_instrument_choice_as_choice_proficiency()
    {
        // Bard has: Three musical instruments of your choice
        $xml = <<<'XML'
        <compendium>
            <class>
                <name>Bard</name>
                <hd>8</hd>
                <tools>Three musical instruments of your choice</tools>
            </class>
        </compendium>
        XML;

        $classes = $this->parser->parse($xml);
        $proficiencies = $classes[0]['proficiencies'];

        // Filter to only tool proficiencies
        $toolProfs = array_values(array_filter($proficiencies, fn ($p) => $p['type'] === 'tool'));

        // Should have 1 tool proficiency: the musical instrument choice
        $this->assertCount(1, $toolProfs, 'Should have 1 tool proficiency');

        // Find the musical instrument choice
        $instrumentChoices = array_values(array_filter($toolProfs, fn ($p) => ($p['is_choice'] ?? false) === true));
        $this->assertCount(1, $instrumentChoices, 'Should have exactly 1 musical instrument choice entry');

        $instrumentChoice = $instrumentChoices[0];

        // Verify the choice proficiency structure
        $this->assertTrue($instrumentChoice['is_choice'], 'Should be marked as a choice');
        $this->assertEquals('tool_choice_1', $instrumentChoice['choice_group'], 'Should have choice_group');
        $this->assertEquals(3, $instrumentChoice['quantity'], 'Should have quantity=3 (pick three)');
        $this->assertEquals('musical_instrument', $instrumentChoice['proficiency_subcategory'], 'Should reference musical_instrument subcategory');
        $this->assertNull($instrumentChoice['proficiency_type_id'], 'Should not have specific type ID (it\'s a choice)');
        $this->assertStringContainsString('musical instrument', strtolower($instrumentChoice['name']), 'Should preserve original description');
    }

    #[Test]
    public function it_parses_single_musical_instrument_choice()
    {
        // Test "one musical instrument of your choice" pattern
        $xml = <<<'XML'
        <compendium>
            <class>
                <name>TestClass</name>
                <hd>8</hd>
                <tools>one musical instrument of your choice</tools>
            </class>
        </compendium>
        XML;

        $classes = $this->parser->parse($xml);
        $proficiencies = $classes[0]['proficiencies'];

        $toolProfs = array_values(array_filter($proficiencies, fn ($p) => $p['type'] === 'tool'));
        $this->assertCount(1, $toolProfs, 'Should have 1 tool proficiency');

        $instrumentChoice = $toolProfs[0];
        $this->assertTrue($instrumentChoice['is_choice'], 'Should be marked as a choice');
        $this->assertEquals(1, $instrumentChoice['quantity'], 'Should have quantity=1');
        $this->assertEquals('musical_instrument', $instrumentChoice['proficiency_subcategory'], 'Should reference musical_instrument subcategory');
    }

    #[Test]
    public function it_parses_any_musical_instrument_choice()
    {
        // Test "any musical instrument" pattern
        $xml = <<<'XML'
        <compendium>
            <class>
                <name>TestClass</name>
                <hd>8</hd>
                <tools>any musical instrument</tools>
            </class>
        </compendium>
        XML;

        $classes = $this->parser->parse($xml);
        $proficiencies = $classes[0]['proficiencies'];

        $toolProfs = array_values(array_filter($proficiencies, fn ($p) => $p['type'] === 'tool'));
        $this->assertCount(1, $toolProfs, 'Should have 1 tool proficiency');

        $instrumentChoice = $toolProfs[0];
        $this->assertTrue($instrumentChoice['is_choice'], 'Should be marked as a choice');
        $this->assertEquals(1, $instrumentChoice['quantity'], 'Should have quantity=1');
        $this->assertEquals('musical_instrument', $instrumentChoice['proficiency_subcategory'], 'Should reference musical_instrument subcategory');
    }

    #[Test]
    public function it_does_not_treat_specific_instrument_as_choice()
    {
        // Specific instruments like "Lute" should be fixed proficiencies, not choices
        $xml = <<<'XML'
        <compendium>
            <class>
                <name>TestClass</name>
                <hd>8</hd>
                <tools>Lute, Lyre</tools>
            </class>
        </compendium>
        XML;

        $classes = $this->parser->parse($xml);
        $proficiencies = $classes[0]['proficiencies'];

        $toolProfs = array_values(array_filter($proficiencies, fn ($p) => $p['type'] === 'tool'));
        $this->assertCount(2, $toolProfs, 'Should have 2 tool proficiencies');

        // Both should be fixed proficiencies, not choices
        foreach ($toolProfs as $prof) {
            $this->assertFalse($prof['is_choice'] ?? false, "{$prof['name']} should NOT be marked as a choice");
            $this->assertArrayNotHasKey('proficiency_subcategory', $prof, "{$prof['name']} should not have proficiency_subcategory");
        }
    }
}

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
}

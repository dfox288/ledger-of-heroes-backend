<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassXmlParserProficiencyChoicesTest extends TestCase
{
    #[Test]
    public function it_marks_skills_as_choices_when_num_skills_present(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <proficiency>Strength, Constitution, Acrobatics, Animal Handling, Athletics, History</proficiency>
    <numSkills>2</numSkills>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        $proficiencies = $data[0]['proficiencies'];

        // Should have 2 saving throws + 4 skills
        $this->assertCount(6, $proficiencies);

        // Find skill proficiencies
        $skills = array_filter($proficiencies, fn ($p) => $p['type'] === 'skill');
        $this->assertCount(4, $skills);

        // All skills should be marked as choices with quantity
        foreach ($skills as $skill) {
            $this->assertTrue($skill['is_choice'], "Skill {$skill['name']} should be marked as choice");
            $this->assertEquals(2, $skill['quantity'], "Skill {$skill['name']} should have quantity=2");
        }

        // Saving throws should NOT be choices
        $savingThrows = array_filter($proficiencies, fn ($p) => $p['type'] === 'saving_throw');
        $this->assertCount(2, $savingThrows);

        foreach ($savingThrows as $save) {
            $this->assertFalse($save['is_choice'], "Saving throw {$save['name']} should not be choice");
        }
    }

    #[Test]
    public function it_does_not_mark_skills_as_choices_when_num_skills_absent(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <proficiency>Dexterity, Intelligence, Acrobatics, Athletics, Deception, Insight, Intimidation, Investigation, Perception, Performance, Persuasion, Sleight Of Hand, Stealth</proficiency>
    <numSkills>4</numSkills>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        $proficiencies = $data[0]['proficiencies'];

        // Find skill proficiencies
        $skills = array_filter($proficiencies, fn ($p) => $p['type'] === 'skill');

        // All skills should be marked as choices
        foreach ($skills as $skill) {
            $this->assertTrue($skill['is_choice']);
            $this->assertEquals(4, $skill['quantity']);
        }
    }

    #[Test]
    public function it_handles_class_without_num_skills(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Barbarian</name>
    <hd>12</hd>
    <proficiency>Strength, Constitution</proficiency>
  </class>
</compendium>
XML;

        $parser = new ClassXmlParser;
        $data = $parser->parse($xml);

        $proficiencies = $data[0]['proficiencies'];

        // Only saving throws, no skills
        $this->assertCount(2, $proficiencies);

        foreach ($proficiencies as $prof) {
            $this->assertEquals('saving_throw', $prof['type']);
            $this->assertFalse($prof['is_choice'] ?? false);
        }
    }
}

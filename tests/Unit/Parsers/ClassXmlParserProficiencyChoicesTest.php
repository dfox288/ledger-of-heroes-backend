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

        // All skills should be in the same choice group
        $skills = array_values($skills); // Re-index
        $choiceGroup = $skills[0]['choice_group'];
        $this->assertEquals('skill_choice_1', $choiceGroup);

        // All skills should be marked as choices with same group
        foreach ($skills as $index => $skill) {
            $this->assertTrue($skill['is_choice'], "Skill {$skill['name']} should be marked as choice");
            $this->assertEquals($choiceGroup, $skill['choice_group'], "Skill {$skill['name']} should have same choice_group");
            $this->assertEquals($index + 1, $skill['choice_option'], "Skill {$skill['name']} should have sequential choice_option");
        }

        // Only first skill in group should have quantity
        $this->assertEquals(2, $skills[0]['quantity'], 'First skill should have quantity=2');
        $this->assertNull($skills[1]['quantity'], 'Second skill should have null quantity');
        $this->assertNull($skills[2]['quantity'], 'Third skill should have null quantity');
        $this->assertNull($skills[3]['quantity'], 'Fourth skill should have null quantity');

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
        $skills = array_values(array_filter($proficiencies, fn ($p) => $p['type'] === 'skill'));

        // All skills should be marked as choices in same group
        $this->assertGreaterThan(0, count($skills));
        $choiceGroup = $skills[0]['choice_group'];
        $this->assertEquals('skill_choice_1', $choiceGroup);

        foreach ($skills as $index => $skill) {
            $this->assertTrue($skill['is_choice']);
            $this->assertEquals($choiceGroup, $skill['choice_group']);
            $this->assertEquals($index + 1, $skill['choice_option']);
        }

        // Only first skill should have quantity
        $this->assertEquals(4, $skills[0]['quantity']);
        for ($i = 1; $i < count($skills); $i++) {
            $this->assertNull($skills[$i]['quantity'], "Skill at index {$i} should have null quantity");
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

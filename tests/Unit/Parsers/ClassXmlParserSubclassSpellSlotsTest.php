<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ClassXmlParserSubclassSpellSlotsTest extends TestCase
{
    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ClassXmlParser;
    }

    #[Test]
    public function it_assigns_optional_spell_slots_to_arcane_trickster_subclass()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <spellAbility>Intelligence</spellAbility>
    <slotsReset>L</slotsReset>
    <autolevel level="3">
      <slots optional="YES">3,2</slots>
    </autolevel>
    <autolevel level="4">
      <slots optional="YES">3,3</slots>
    </autolevel>
    <autolevel level="7">
      <slots optional="YES">3,4,2</slots>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Arcane Trickster</name>
        <text>Some rogues enhance their fine-honed skills of stealth and agility with magic.</text>
      </feature>
      <feature optional="YES">
        <name>Spellcasting (Arcane Trickster)</name>
        <text>When you reach 3rd level, you gain the ability to cast spells.

Spell Slots:
The Arcane Trickster Spellcasting table shows how many spell slots you have to cast your wizard spells of 1st level and higher.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Base class should have NO spell progression
        $this->assertEmpty($result[0]['spell_progression'], 'Base Rogue should not have spell progression');
        $this->assertArrayNotHasKey('spellcasting_ability', $result[0], 'Base Rogue should not have spellcasting ability');

        // Should detect subclass
        $this->assertNotEmpty($result[0]['subclasses'], 'Should detect Arcane Trickster subclass');
        $this->assertCount(1, $result[0]['subclasses']);

        // Subclass should have spell progression
        $subclass = $result[0]['subclasses'][0];
        $this->assertEquals('Arcane Trickster', $subclass['name']);
        $this->assertArrayHasKey('spell_progression', $subclass, 'Arcane Trickster should have spell progression');
        $this->assertNotEmpty($subclass['spell_progression'], 'Arcane Trickster spell progression should not be empty');

        // Verify spell progression data
        $this->assertCount(3, $subclass['spell_progression'], 'Should have 3 levels of spell progression');

        // Check level 3
        $level3 = collect($subclass['spell_progression'])->firstWhere('level', 3);
        $this->assertNotNull($level3, 'Should have level 3 spell progression');
        $this->assertEquals(3, $level3['cantrips_known']);
        $this->assertEquals(2, $level3['spell_slots_1st']);
        $this->assertEquals(0, $level3['spell_slots_2nd']);

        // Check level 7 (has 2nd level slots)
        $level7 = collect($subclass['spell_progression'])->firstWhere('level', 7);
        $this->assertNotNull($level7, 'Should have level 7 spell progression');
        $this->assertEquals(3, $level7['cantrips_known']);
        $this->assertEquals(4, $level7['spell_slots_1st']);
        $this->assertEquals(2, $level7['spell_slots_2nd']);

        // Subclass should have spellcasting ability
        $this->assertArrayHasKey('spellcasting_ability', $subclass, 'Arcane Trickster should have spellcasting ability');
        $this->assertEquals('Intelligence', $subclass['spellcasting_ability']);
    }

    #[Test]
    public function it_assigns_optional_spell_slots_to_eldritch_knight_subclass()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <spellAbility>Intelligence</spellAbility>
    <slotsReset>L</slotsReset>
    <autolevel level="3">
      <slots optional="YES">2,2</slots>
    </autolevel>
    <autolevel level="4">
      <slots optional="YES">2,3</slots>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Martial Archetype: Eldritch Knight</name>
        <text>The archetypal Eldritch Knight combines the martial mastery common to all fighters with a careful study of magic.</text>
      </feature>
      <feature optional="YES">
        <name>Spellcasting (Eldritch Knight)</name>
        <text>When you reach 3rd level, you augment your martial prowess with the ability to cast spells.

Spell Slots:
The Eldritch Knight Spellcasting table shows how many spell slots you have to cast your wizard spells of 1st level and higher.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Base class should have NO spell progression
        $this->assertEmpty($result[0]['spell_progression'], 'Base Fighter should not have spell progression');
        $this->assertArrayNotHasKey('spellcasting_ability', $result[0], 'Base Fighter should not have spellcasting ability');

        // Should detect subclass
        $this->assertNotEmpty($result[0]['subclasses'], 'Should detect Eldritch Knight subclass');
        $subclass = $result[0]['subclasses'][0];
        $this->assertEquals('Eldritch Knight', $subclass['name']);

        // Subclass should have spell progression
        $this->assertArrayHasKey('spell_progression', $subclass, 'Eldritch Knight should have spell progression');
        $this->assertCount(2, $subclass['spell_progression'], 'Should have 2 levels of spell progression');

        // Subclass should have spellcasting ability
        $this->assertArrayHasKey('spellcasting_ability', $subclass, 'Eldritch Knight should have spellcasting ability');
        $this->assertEquals('Intelligence', $subclass['spellcasting_ability']);
    }

    #[Test]
    public function it_handles_class_with_no_spellcasting_subclass()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Thief</name>
        <text>You hone your skills in the larcenous arts.</text>
      </feature>
      <feature optional="YES">
        <name>Fast Hands</name>
        <text>You can use the bonus action granted by your Cunning Action to make a Sleight of Hand check.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Should detect subclass
        $this->assertNotEmpty($result[0]['subclasses'], 'Should detect Thief subclass');
        $subclass = $result[0]['subclasses'][0];
        $this->assertEquals('Thief', $subclass['name']);

        // Subclass should NOT have spell progression (no Spellcasting feature)
        $this->assertArrayNotHasKey('spell_progression', $subclass, 'Thief should not have spell progression');
        $this->assertArrayNotHasKey('spellcasting_ability', $subclass, 'Thief should not have spellcasting ability');
    }

    #[Test]
    public function it_matches_subclass_name_from_spellcasting_feature_correctly()
    {
        // Test the pattern matching for "Spellcasting (SubclassName)"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>TestClass</name>
    <hd>8</hd>
    <spellAbility>Wisdom</spellAbility>
    <autolevel level="3">
      <slots optional="YES">2,2</slots>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Archetype: Magic User</name>
        <text>You gain magic.</text>
      </feature>
      <feature optional="YES">
        <name>Spellcasting (Magic User)</name>
        <text>The Magic User Spellcasting table shows your spell slots.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        $subclass = $result[0]['subclasses'][0];
        $this->assertEquals('Magic User', $subclass['name']);
        $this->assertArrayHasKey('spell_progression', $subclass, 'Should match "Magic User" from feature name');
    }

    #[Test]
    public function it_handles_multiple_subclasses_with_only_one_spellcaster()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <spellAbility>Intelligence</spellAbility>
    <autolevel level="3">
      <slots optional="YES">3,2</slots>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Thief</name>
        <text>Thief description.</text>
      </feature>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Arcane Trickster</name>
        <text>Arcane Trickster description.</text>
      </feature>
      <feature optional="YES">
        <name>Spellcasting (Arcane Trickster)</name>
        <text>The Arcane Trickster Spellcasting table shows spell slots.</text>
      </feature>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Assassin</name>
        <text>Assassin description.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        $this->assertCount(3, $result[0]['subclasses'], 'Should detect all 3 subclasses');

        // Find each subclass
        $subclasses = collect($result[0]['subclasses']);
        $thief = $subclasses->firstWhere('name', 'Thief');
        $arcaneTrickster = $subclasses->firstWhere('name', 'Arcane Trickster');
        $assassin = $subclasses->firstWhere('name', 'Assassin');

        // Only Arcane Trickster should have spell progression
        $this->assertArrayNotHasKey('spell_progression', $thief, 'Thief should not have spell progression');
        $this->assertArrayHasKey('spell_progression', $arcaneTrickster, 'Arcane Trickster should have spell progression');
        $this->assertArrayNotHasKey('spell_progression', $assassin, 'Assassin should not have spell progression');

        // Only Arcane Trickster should have spellcasting ability
        $this->assertArrayNotHasKey('spellcasting_ability', $thief);
        $this->assertArrayHasKey('spellcasting_ability', $arcaneTrickster);
        $this->assertEquals('Intelligence', $arcaneTrickster['spellcasting_ability']);
        $this->assertArrayNotHasKey('spellcasting_ability', $assassin);
    }

    #[Test]
    public function it_handles_spells_known_counters_for_subclass()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <spellAbility>Intelligence</spellAbility>
    <autolevel level="3">
      <slots optional="YES">3,2</slots>
      <counter>
        <name>Spells Known</name>
        <value>3</value>
      </counter>
    </autolevel>
    <autolevel level="4">
      <slots optional="YES">3,3</slots>
      <counter>
        <name>Spells Known</name>
        <value>4</value>
      </counter>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Arcane Trickster</name>
        <text>Magic rogue.</text>
      </feature>
      <feature optional="YES">
        <name>Spellcasting (Arcane Trickster)</name>
        <text>The Arcane Trickster Spellcasting table shows spell slots.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        $subclass = $result[0]['subclasses'][0];
        $this->assertArrayHasKey('spell_progression', $subclass);

        // Check that spells_known is included
        $level3 = collect($subclass['spell_progression'])->firstWhere('level', 3);
        $this->assertArrayHasKey('spells_known', $level3, 'Should include spells_known counter');
        $this->assertEquals(3, $level3['spells_known']);

        $level4 = collect($subclass['spell_progression'])->firstWhere('level', 4);
        $this->assertArrayHasKey('spells_known', $level4);
        $this->assertEquals(4, $level4['spells_known']);
    }
}

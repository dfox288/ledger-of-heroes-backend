<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ClassXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ClassXmlParserSubclassFilteringTest extends TestCase
{
    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ClassXmlParser;
    }

    #[Test]
    public function it_filters_subclass_features_from_base_class()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <autolevel level="1">
      <feature>
        <name>Sneak Attack</name>
        <text>Base class feature available to all rogues.</text>
      </feature>
      <feature>
        <name>Expertise</name>
        <text>Another base class feature.</text>
      </feature>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Arcane Trickster</name>
        <text>Subclass introduction feature.</text>
      </feature>
      <feature optional="YES">
        <name>Spellcasting (Arcane Trickster)</name>
        <text>Subclass-specific feature.</text>
      </feature>
      <feature optional="YES">
        <name>Mage Hand Legerdemain (Arcane Trickster)</name>
        <text>Another subclass-specific feature.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Base class should only have Sneak Attack and Expertise
        $baseFeatureNames = array_column($result[0]['features'], 'name');
        $this->assertCount(2, $baseFeatureNames, 'Base class should only have 2 features');
        $this->assertContains('Sneak Attack', $baseFeatureNames);
        $this->assertContains('Expertise', $baseFeatureNames);
        $this->assertNotContains('Roguish Archetype: Arcane Trickster', $baseFeatureNames);
        $this->assertNotContains('Spellcasting (Arcane Trickster)', $baseFeatureNames);
        $this->assertNotContains('Mage Hand Legerdemain (Arcane Trickster)', $baseFeatureNames);

        // Should detect Arcane Trickster subclass
        $this->assertCount(1, $result[0]['subclasses']);
        $this->assertEquals('Arcane Trickster', $result[0]['subclasses'][0]['name']);

        // Subclass should have 3 features
        $subclassFeatures = $result[0]['subclasses'][0]['features'];
        $this->assertCount(3, $subclassFeatures, 'Arcane Trickster should have 3 features');

        $subclassFeatureNames = array_column($subclassFeatures, 'name');
        $this->assertContains('Roguish Archetype: Arcane Trickster', $subclassFeatureNames);
        $this->assertContains('Spellcasting (Arcane Trickster)', $subclassFeatureNames);
        $this->assertContains('Mage Hand Legerdemain (Arcane Trickster)', $subclassFeatureNames);
    }

    #[Test]
    public function it_handles_multiple_subclasses()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <autolevel level="1">
      <feature>
        <name>Sneak Attack</name>
        <text>Base feature.</text>
      </feature>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Arcane Trickster</name>
        <text>Arcane Trickster intro.</text>
      </feature>
      <feature optional="YES">
        <name>Spellcasting (Arcane Trickster)</name>
        <text>Arcane Trickster feature.</text>
      </feature>
      <feature optional="YES">
        <name>Roguish Archetype: Assassin</name>
        <text>Assassin intro.</text>
      </feature>
      <feature optional="YES">
        <name>Assassinate (Assassin)</name>
        <text>Assassin feature.</text>
      </feature>
      <feature optional="YES">
        <name>Roguish Archetype: Thief</name>
        <text>Thief intro.</text>
      </feature>
      <feature optional="YES">
        <name>Fast Hands (Thief)</name>
        <text>Thief feature.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Base class should only have Sneak Attack
        $baseFeatureNames = array_column($result[0]['features'], 'name');
        $this->assertCount(1, $baseFeatureNames, 'Base class should only have Sneak Attack');
        $this->assertEquals('Sneak Attack', $baseFeatureNames[0]);

        // Should detect 3 subclasses
        $this->assertCount(3, $result[0]['subclasses']);

        $subclassNames = array_column($result[0]['subclasses'], 'name');
        $this->assertContains('Arcane Trickster', $subclassNames);
        $this->assertContains('Assassin', $subclassNames);
        $this->assertContains('Thief', $subclassNames);

        // Each subclass should have 2 features
        foreach ($result[0]['subclasses'] as $subclass) {
            $this->assertCount(2, $subclass['features'], "{$subclass['name']} should have 2 features");
        }
    }

    #[Test]
    public function it_handles_fighter_martial_archetype_pattern()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <autolevel level="1">
      <feature>
        <name>Second Wind</name>
        <text>Base fighter feature.</text>
      </feature>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Martial Archetype: Battle Master</name>
        <text>Battle Master intro.</text>
      </feature>
      <feature optional="YES">
        <name>Combat Superiority (Battle Master)</name>
        <text>Battle Master feature.</text>
      </feature>
      <feature optional="YES">
        <name>Martial Archetype: Champion</name>
        <text>Champion intro.</text>
      </feature>
      <feature optional="YES">
        <name>Improved Critical (Champion)</name>
        <text>Champion feature.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Base class should only have Second Wind
        $baseFeatureNames = array_column($result[0]['features'], 'name');
        $this->assertCount(1, $baseFeatureNames);
        $this->assertEquals('Second Wind', $baseFeatureNames[0]);

        // Should detect 2 subclasses
        $this->assertCount(2, $result[0]['subclasses']);

        $subclassNames = array_column($result[0]['subclasses'], 'name');
        $this->assertContains('Battle Master', $subclassNames);
        $this->assertContains('Champion', $subclassNames);
    }

    #[Test]
    public function it_handles_wizard_arcane_tradition_pattern()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Wizard</name>
    <hd>6</hd>
    <autolevel level="1">
      <feature>
        <name>Spellcasting</name>
        <text>Base wizard spellcasting.</text>
      </feature>
    </autolevel>
    <autolevel level="2">
      <feature optional="YES">
        <name>Arcane Tradition: School of Evocation</name>
        <text>Evocation intro.</text>
      </feature>
      <feature optional="YES">
        <name>Evocation Savant (School of Evocation)</name>
        <text>Evocation feature.</text>
      </feature>
      <feature optional="YES">
        <name>Arcane Tradition: School of Abjuration</name>
        <text>Abjuration intro.</text>
      </feature>
      <feature optional="YES">
        <name>Abjuration Savant (School of Abjuration)</name>
        <text>Abjuration feature.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Base class should only have Spellcasting
        $baseFeatureNames = array_column($result[0]['features'], 'name');
        $this->assertCount(1, $baseFeatureNames);
        $this->assertEquals('Spellcasting', $baseFeatureNames[0]);

        // Should detect 2 subclasses
        $this->assertCount(2, $result[0]['subclasses']);

        $subclassNames = array_column($result[0]['subclasses'], 'name');
        $this->assertContains('School of Evocation', $subclassNames);
        $this->assertContains('School of Abjuration', $subclassNames);
    }

    #[Test]
    public function it_does_not_filter_features_with_numbers_in_parentheses()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <autolevel level="3">
      <feature>
        <name>Sneak Attack (2)</name>
        <text>Sneak attack increases to 2d6.</text>
      </feature>
    </autolevel>
    <autolevel level="5">
      <feature>
        <name>Sneak Attack (3)</name>
        <text>Sneak attack increases to 3d6.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Features with numbers in parentheses should remain in base class
        $baseFeatureNames = array_column($result[0]['features'], 'name');
        $this->assertCount(2, $baseFeatureNames);
        $this->assertContains('Sneak Attack (2)', $baseFeatureNames);
        $this->assertContains('Sneak Attack (3)', $baseFeatureNames);

        // Should NOT detect any subclasses
        $this->assertEmpty($result[0]['subclasses']);
    }

    #[Test]
    public function it_preserves_feature_sort_order_after_filtering()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <autolevel level="1">
      <feature>
        <name>First Feature</name>
        <text>Base feature 1.</text>
      </feature>
    </autolevel>
    <autolevel level="2">
      <feature>
        <name>Second Feature</name>
        <text>Base feature 2.</text>
      </feature>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Thief</name>
        <text>Subclass feature - should be filtered.</text>
      </feature>
      <feature>
        <name>Third Feature</name>
        <text>Base feature 3.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Base class should have 3 features in correct order
        $baseFeatureNames = array_column($result[0]['features'], 'name');
        $this->assertEquals(['First Feature', 'Second Feature', 'Third Feature'], $baseFeatureNames);

        // Sort order values should be sequential
        $sortOrders = array_column($result[0]['features'], 'sort_order');
        $this->assertEquals([0, 1, 3], $sortOrders, 'Sort order should preserve original positions (skipping filtered index 2)');
    }

    #[Test]
    public function it_does_not_assign_features_to_subclass_when_subclass_name_is_substring()
    {
        // Regression test: "Spell Thief (Arcane Trickster)" was incorrectly assigned to "Thief"
        // because "Thief" is a substring of "Spell Thief"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <autolevel level="3">
      <feature optional="YES">
        <name>Roguish Archetype: Arcane Trickster</name>
        <text>Arcane Trickster intro.</text>
      </feature>
      <feature optional="YES">
        <name>Roguish Archetype: Thief</name>
        <text>Thief intro.</text>
      </feature>
      <feature optional="YES">
        <name>Fast Hands (Thief)</name>
        <text>Thief L3 feature.</text>
      </feature>
    </autolevel>
    <autolevel level="17">
      <feature optional="YES">
        <name>Spell Thief (Arcane Trickster)</name>
        <text>Arcane Trickster L17 feature - steals spells.</text>
      </feature>
      <feature optional="YES">
        <name>Thief's Reflexes (Thief)</name>
        <text>Thief L17 feature - two turns in first round.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $result = $this->parser->parse($xml);

        // Should detect both subclasses
        $this->assertCount(2, $result[0]['subclasses']);

        // Find each subclass
        $arcaneTrickster = collect($result[0]['subclasses'])->firstWhere('name', 'Arcane Trickster');
        $thief = collect($result[0]['subclasses'])->firstWhere('name', 'Thief');

        $this->assertNotNull($arcaneTrickster, 'Arcane Trickster subclass should exist');
        $this->assertNotNull($thief, 'Thief subclass should exist');

        // Arcane Trickster should have "Spell Thief (Arcane Trickster)"
        $atFeatureNames = array_column($arcaneTrickster['features'], 'name');
        $this->assertContains('Spell Thief (Arcane Trickster)', $atFeatureNames,
            'Arcane Trickster should have Spell Thief feature');

        // Thief should NOT have "Spell Thief (Arcane Trickster)" - it should only have its own features
        $thiefFeatureNames = array_column($thief['features'], 'name');
        $this->assertNotContains('Spell Thief (Arcane Trickster)', $thiefFeatureNames,
            'Thief should NOT have Spell Thief - that belongs to Arcane Trickster only');
        $this->assertContains('Roguish Archetype: Thief', $thiefFeatureNames);
        $this->assertContains('Fast Hands (Thief)', $thiefFeatureNames);
        $this->assertContains("Thief's Reflexes (Thief)", $thiefFeatureNames);

        // Thief should have exactly 3 features (intro + Fast Hands + Thief's Reflexes)
        $this->assertCount(3, $thief['features'],
            'Thief should have exactly 3 features, not 4 (Spell Thief should not be included)');
    }
}

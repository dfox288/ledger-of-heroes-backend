<?php

namespace Tests\Feature\Importers;

use App\Models\AbilityScore;
use App\Models\CharacterClass;
use App\Models\Modifier;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('importers')]
class ClassImporterUnarmoredDefenseTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private ClassImporter $importer;

    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new ClassImporter;
        $this->parser = new ClassXmlParser;
    }

    #[Test]
    public function it_parses_barbarian_unarmored_defense_from_text(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Barbarian</name>
    <hd>12</hd>
    <autolevel level="1">
      <feature>
        <name>Unarmored Defense</name>
        <text>While you are not wearing any armor, your Armor Class equals 10 + your Dexterity modifier + your Constitution modifier. You can use a shield and still gain this benefit.

Source: Player's Handbook (2014) p. 48</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Barbarian')->first();
        $this->assertNotNull($class, 'Barbarian class should exist');

        // Check for ac_unarmored modifier
        $acModifier = Modifier::where('reference_type', CharacterClass::class)
            ->where('reference_id', $class->id)
            ->where('modifier_category', 'ac_unarmored')
            ->first();

        $this->assertNotNull($acModifier, 'Should have ac_unarmored modifier');
        $this->assertEquals('10', $acModifier->value, 'Base AC should be 10');

        // Primary ability should be Dexterity
        $dex = AbilityScore::where('code', 'DEX')->first();
        $this->assertEquals($dex->id, $acModifier->ability_score_id, 'Primary ability should be DEX');

        // Secondary ability should be Constitution
        $con = AbilityScore::where('code', 'CON')->first();
        $this->assertEquals($con->id, $acModifier->secondary_ability_score_id, 'Secondary ability should be CON');

        // Condition should indicate shield is allowed
        $this->assertStringContainsString('allows_shield: true', $acModifier->condition);
    }

    #[Test]
    public function it_parses_monk_unarmored_defense_from_text(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Monk</name>
    <hd>8</hd>
    <autolevel level="1">
      <feature>
        <name>Unarmored Defense</name>
        <text>Beginning at 1st level, while you are wearing no armor and not wielding a shield, your AC equals 10 + your Dexterity modifier + your Wisdom modifier.

Source: Player's Handbook (2014) p. 76</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Monk')->first();
        $this->assertNotNull($class, 'Monk class should exist');

        // Check for ac_unarmored modifier
        $acModifier = Modifier::where('reference_type', CharacterClass::class)
            ->where('reference_id', $class->id)
            ->where('modifier_category', 'ac_unarmored')
            ->first();

        $this->assertNotNull($acModifier, 'Should have ac_unarmored modifier');
        $this->assertEquals('10', $acModifier->value, 'Base AC should be 10');

        // Primary ability should be Dexterity
        $dex = AbilityScore::where('code', 'DEX')->first();
        $this->assertEquals($dex->id, $acModifier->ability_score_id, 'Primary ability should be DEX');

        // Secondary ability should be Wisdom
        $wis = AbilityScore::where('code', 'WIS')->first();
        $this->assertEquals($wis->id, $acModifier->secondary_ability_score_id, 'Secondary ability should be WIS');

        // Condition should indicate shield is NOT allowed (monk can't use shield)
        $this->assertStringContainsString('allows_shield: false', $acModifier->condition);
    }

    #[Test]
    public function it_parses_draconic_resilience_from_text(): void
    {
        // Using a non-optional feature to test the AC parsing logic
        // The real Draconic Resilience is a subclass feature, but we're testing the text parsing
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Sorcerer</name>
    <hd>6</hd>
    <proficiency>Constitution, Charisma</proficiency>
    <autolevel level="1">
      <feature>
        <name>Draconic Resilience</name>
        <text>As magic flows through your body, it causes physical traits of your dragon ancestors to emerge. At 1st level, your hit point maximum increases by 1 and increases by 1 again whenever you gain a level in this class.
Additionally, parts of your skin are covered by a thin sheen of dragon-like scales. When you aren't wearing armor, your AC equals 13 + your Dexterity modifier.

Source: Player's Handbook (2014) p. 102</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Sorcerer')->first();
        $this->assertNotNull($class, 'Sorcerer class should exist');

        // Check for ac_unarmored modifier
        $acModifier = Modifier::where('reference_type', CharacterClass::class)
            ->where('reference_id', $class->id)
            ->where('modifier_category', 'ac_unarmored')
            ->first();

        $this->assertNotNull($acModifier, 'Should have ac_unarmored modifier');
        $this->assertEquals('13', $acModifier->value, 'Base AC should be 13');

        // Primary ability should be Dexterity
        $dex = AbilityScore::where('code', 'DEX')->first();
        $this->assertEquals($dex->id, $acModifier->ability_score_id, 'Primary ability should be DEX');

        // No secondary ability for Draconic Resilience
        $this->assertNull($acModifier->secondary_ability_score_id, 'Should have no secondary ability');
    }

    #[Test]
    public function it_does_not_create_modifier_for_unrelated_features(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <autolevel level="1">
      <feature>
        <name>Fighting Style</name>
        <text>You adopt a particular style of fighting as your specialty.

Source: Player's Handbook (2014) p. 72</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Fighter')->first();
        $this->assertNotNull($class, 'Fighter class should exist');

        // Should NOT have ac_unarmored modifier
        $acModifier = Modifier::where('reference_type', CharacterClass::class)
            ->where('reference_id', $class->id)
            ->where('modifier_category', 'ac_unarmored')
            ->first();

        $this->assertNull($acModifier, 'Should not have ac_unarmored modifier');
    }
}

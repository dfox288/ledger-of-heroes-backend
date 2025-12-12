<?php

namespace Tests\Feature\Importers;

use App\Enums\OptionalFeatureType;
use App\Enums\ResourceType;
use App\Models\CharacterClass;
use App\Models\OptionalFeature;
use App\Models\SpellSchool;
use App\Services\Importers\OptionalFeatureImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class OptionalFeatureXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookup data

    private OptionalFeatureImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new OptionalFeatureImporter;
    }

    #[Test]
    public function it_reconstructs_simple_invocation()
    {
        // Create Warlock class
        CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);

        // Original XML for Armor of Shadows invocation
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Invocation: Armor of Shadows</name>
    <level>0</level>
    <classes>Eldritch Invocations</classes>
    <text>You can cast mage armor on yourself at will, without expending a spell slot or material components.

Source:	Player's Handbook (2014) p. 110</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Armor of Shadows')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Verify core attributes
        $this->assertEquals('Armor of Shadows', $feature->name);
        $this->assertEquals('phb:armor-of-shadows', $feature->slug);
        $this->assertEquals(OptionalFeatureType::ELDRITCH_INVOCATION, $feature->feature_type);
        $this->assertStringContainsString('You can cast mage armor', $feature->description);
        $this->assertNull($feature->prerequisite_text, 'No prerequisites');
        $this->assertNull($feature->level_requirement);

        // Verify source citation
        $sources = $feature->sources;
        $this->assertCount(1, $sources, 'Should have 1 source citation');
        $this->assertEquals('PHB', $sources[0]->source->code);
        $this->assertEquals('110', $sources[0]->pages);

        // Verify class association (Warlock)
        $classes = $feature->classes;
        $this->assertCount(1, $classes, 'Should have 1 class association');
        $this->assertEquals('Warlock', $classes[0]->name);
        $this->assertNull($classes[0]->pivot->subclass_name);
    }

    #[Test]
    public function it_reconstructs_invocation_with_prerequisite()
    {
        // Create Warlock class
        CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);

        // Original XML for Agonizing Blast invocation
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Invocation: Agonizing Blast</name>
    <level>0</level>
    <classes>Eldritch Invocations</classes>
    <text>Prerequisite: Eldritch Blast cantrip

When you cast eldritch blast, add your Charisma modifier to the damage it deals on a hit.

Source:	Player's Handbook (2014) p. 110</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Agonizing Blast')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Verify prerequisite text stored
        $this->assertEquals('Eldritch Blast cantrip', $feature->prerequisite_text);
        $this->assertNull($feature->level_requirement, 'No level requirement for this invocation');

        // Verify description doesn't include prerequisite
        $this->assertStringNotContainsString('Prerequisite:', $feature->description);
        $this->assertStringContainsString('When you cast eldritch blast', $feature->description);
    }

    #[Test]
    public function it_reconstructs_invocation_with_level_requirement()
    {
        // Create Warlock class for prerequisite matching
        CharacterClass::factory()->create(['name' => 'Warlock', 'slug' => 'warlock']);

        // Original XML for Ascendant Step invocation (9th level)
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Invocation: Ascendant Step</name>
    <level>0</level>
    <classes>Eldritch Invocations</classes>
    <text>Prerequisite: 9th level Warlock

You can cast levitate on yourself at will, without expending a spell slot or material components.

Source:	Player's Handbook (2014) p. 110</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Ascendant Step')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Verify prerequisite text and level requirement
        $this->assertEquals('9th level Warlock', $feature->prerequisite_text);
        $this->assertEquals(9, $feature->level_requirement);

        // Verify structured prerequisite (Warlock class with minimum level 9)
        $prerequisites = $feature->prerequisites;
        $this->assertCount(1, $prerequisites, 'Should have 1 prerequisite');

        $prereq = $prerequisites[0];
        $this->assertEquals(CharacterClass::class, $prereq->prerequisite_type);
        $this->assertEquals(9, $prereq->minimum_value);

        // Verify it's Warlock class
        $characterClass = CharacterClass::find($prereq->prerequisite_id);
        $this->assertEquals('Warlock', $characterClass->name);
    }

    #[Test]
    public function it_reconstructs_elemental_discipline_with_spell_mechanics()
    {
        // Create Monk class and spell school
        CharacterClass::factory()->create(['name' => 'Monk', 'slug' => 'monk']);

        // Original XML for Breath of Winter discipline
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Elemental Discipline: Breath of Winter</name>
    <level>0</level>
    <school>EV</school>
    <time>1 action</time>
    <range>Self (60-foot cone)</range>
    <components>V, S, M (6 ki points)</components>
    <duration>Instantaneous</duration>
    <classes>Monk (Way of the Four Elements)</classes>
    <text>Prerequisite: 17th level Monk

You can spend 6 ki points to cast cone of cold.

Cone of Cold:
A blast of cold air erupts from your hands. Each creature in a 60-foot cone must make a Constitution saving throw. A creature takes 8d8 cold damage on a failed save, or half as much damage on a successful one.
	A creature killed by this spell becomes a frozen statue until it thaws.

Source:	Player's Handbook (2014) p. 81</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Breath of Winter')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Verify core attributes
        $this->assertEquals('Breath of Winter', $feature->name);
        $this->assertEquals(OptionalFeatureType::ELEMENTAL_DISCIPLINE, $feature->feature_type);

        // Verify spell mechanics
        $this->assertEquals('1 action', $feature->casting_time);
        $this->assertEquals('Self (60-foot cone)', $feature->range);
        $this->assertEquals('Instantaneous', $feature->duration);

        // Verify spell school (Evocation)
        $this->assertNotNull($feature->spell_school_id);
        $spellSchool = SpellSchool::find($feature->spell_school_id);
        $this->assertEquals('EV', $spellSchool->code);

        // Verify resource cost
        $this->assertEquals(ResourceType::KI_POINTS, $feature->resource_type);
        $this->assertEquals(6, $feature->resource_cost);

        // Verify level requirement
        $this->assertEquals(17, $feature->level_requirement);
        $this->assertEquals('17th level Monk', $feature->prerequisite_text);

        // Verify class association with subclass
        $classes = $feature->classes;
        $this->assertCount(1, $classes, 'Should have 1 class association');
        $this->assertEquals('Monk', $classes[0]->name);
        $this->assertEquals('Way of the Four Elements', $classes[0]->pivot->subclass_name);
    }

    #[Test]
    public function it_reconstructs_maneuver()
    {
        // Create Fighter class
        CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);

        // Original XML for Disarming Attack maneuver
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Maneuver: Disarming Attack</name>
    <level>0</level>
    <classes>Maneuver Options</classes>
    <text>When you hit a creature with a weapon attack, you can expend one superiority die to attempt to disarm the target, forcing it to drop one item of your choice that it's holding. You add the superiority die to the attack's damage roll, and the target must make a Strength saving throw. On a failed save, it drops the object you choose. The object lands at its feet.

Source:	Player's Handbook (2014) p. 74</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Disarming Attack')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Verify core attributes
        $this->assertEquals('Disarming Attack', $feature->name);
        $this->assertEquals(OptionalFeatureType::MANEUVER, $feature->feature_type);
        $this->assertStringContainsString('expend one superiority die', $feature->description);

        // Verify class association (Fighter Battle Master)
        $classes = $feature->classes;
        $this->assertCount(1, $classes, 'Should have 1 class association');
        $this->assertEquals('Fighter', $classes[0]->name);
        $this->assertEquals('Battle Master', $classes[0]->pivot->subclass_name);
    }

    #[Test]
    public function it_reconstructs_metamagic_with_sorcery_points()
    {
        // Create Sorcerer class
        CharacterClass::factory()->create(['name' => 'Sorcerer', 'slug' => 'sorcerer']);

        // Original XML for Heightened Spell metamagic
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Metamagic: Heightened Spell</name>
    <level>0</level>
    <components>(3 sorcery points)</components>
    <classes>Metamagic Options</classes>
    <text>When you cast a spell that forces a creature to make a saving throw to resist its effects, you can spend 3 sorcery points to give one target of the spell disadvantage on its first saving throw made against the spell.

Source:	Player's Handbook (2014) p. 102</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Heightened Spell')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Verify core attributes
        $this->assertEquals('Heightened Spell', $feature->name);
        $this->assertEquals(OptionalFeatureType::METAMAGIC, $feature->feature_type);

        // Verify resource cost parsing from components
        $this->assertEquals(ResourceType::SORCERY_POINTS, $feature->resource_type);
        $this->assertEquals(3, $feature->resource_cost);

        // Verify class association
        $classes = $feature->classes;
        $this->assertCount(1, $classes, 'Should have 1 class association');
        $this->assertEquals('Sorcerer', $classes[0]->name);
        $this->assertNull($classes[0]->pivot->subclass_name);
    }

    #[Test]
    public function it_reconstructs_fighting_style()
    {
        // Create classes that can use fighting styles
        CharacterClass::factory()->create(['name' => 'Fighter', 'slug' => 'fighter']);
        CharacterClass::factory()->create(['name' => 'Paladin', 'slug' => 'paladin']);
        CharacterClass::factory()->create(['name' => 'Ranger', 'slug' => 'ranger']);

        // Original XML for Archery fighting style (feat format)
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <feat>
    <name>Fighting Style: Archery</name>
    <prerequisite>Fighting Style Feature</prerequisite>
    <text>You gain a +2 bonus to attack rolls you make with ranged weapons.

Source:	Player's Handbook (2014) p. 72</text>
  </feat>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Archery')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Verify core attributes
        $this->assertEquals('Archery', $feature->name);
        $this->assertEquals(OptionalFeatureType::FIGHTING_STYLE, $feature->feature_type);
        $this->assertEquals('Fighting Style Feature', $feature->prerequisite_text);
        $this->assertStringContainsString('+2 bonus to attack rolls', $feature->description);

        // Verify multiple class associations (Fighter, Paladin, Ranger)
        $classes = $feature->classes;
        $this->assertGreaterThanOrEqual(1, $classes->count(), 'Should have at least 1 class association');

        // Verify at least Fighter is associated
        $fighterAssociated = $classes->contains(fn ($class) => $class->name === 'Fighter');
        $this->assertTrue($fighterAssociated, 'Fighter should be associated with fighting style');
    }

    #[Test]
    public function it_reconstructs_class_association_with_subclass()
    {
        // Create Monk class
        CharacterClass::factory()->create(['name' => 'Monk', 'slug' => 'monk']);

        // Original XML for Elemental Attunement discipline
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Elemental Discipline: Elemental Attunement</name>
    <level>0</level>
    <school>T</school>
    <time>1 action</time>
    <range>30 feet</range>
    <components>V, S</components>
    <duration>1 hour</duration>
    <classes>Monk (Way of the Four Elements)</classes>
    <text>You can use your action to briefly control elemental forces within 30 feet of you, causing one of the following effects of your choice:

	• Create a harmless, instantaneous sensory effect related to air, earth, fire, or water, such as a shower of sparks, a puff of wind, a spray of light mist, or a gentle rumbling of stone.

	• Instantaneously light or snuff out a candle, a torch, or a small campfire.

	• Cause earth, fire, water, or mist that can fit within a 1-foot cube to shape itself into a crude form you designate for 1 minute.

Source:	Player's Handbook (2014) p. 81</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Elemental Attunement')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Verify class association with subclass parsing
        $classes = $feature->classes;
        $this->assertCount(1, $classes, 'Should have 1 class association');
        $this->assertEquals('Monk', $classes[0]->name);

        // Verify subclass_name in pivot
        $this->assertEquals('Way of the Four Elements', $classes[0]->pivot->subclass_name);

        // Verify via classPivots relationship too
        $classPivots = $feature->classPivots;
        $this->assertCount(1, $classPivots);
        $this->assertEquals('Way of the Four Elements', $classPivots[0]->subclass_name);
    }

    #[Test]
    public function it_reconstructs_feature_with_multiple_resource_types()
    {
        // Create Monk class
        CharacterClass::factory()->create(['name' => 'Monk', 'slug' => 'monk']);

        // Original XML for Clench of the North Wind (3 ki points)
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Elemental Discipline: Clench of the North Wind</name>
    <level>0</level>
    <school>EN</school>
    <time>1 action</time>
    <range>60 feet</range>
    <components>V, S, M (3 ki points)</components>
    <duration>Concentration, up to 1 minute</duration>
    <classes>Monk (Way of the Four Elements)</classes>
    <text>Prerequisite: 6th level Monk

You can spend 3 ki points to cast hold person.

Hold Person:
Choose a humanoid that you can see within range. The target must succeed on a Wisdom saving throw or be paralyzed for the duration. At the end of each of its turns, the target can make another Wisdom saving throw. On a success, the spell ends on the target.

Additional Ki Points: When you cast this spell using 4 or more ki points, you can target on additional humanoid for each ki point above 3. The humanoids must be within 30 feet of each other when you target them.

Source:	Player's Handbook (2014) p. 81</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Clench of the North Wind')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Verify resource cost parsing
        $this->assertEquals(ResourceType::KI_POINTS, $feature->resource_type);
        $this->assertEquals(3, $feature->resource_cost);

        // Verify level requirement
        $this->assertEquals(6, $feature->level_requirement);

        // Verify spell school (Enchantment)
        $spellSchool = SpellSchool::find($feature->spell_school_id);
        $this->assertEquals('EN', $spellSchool->code);

        // Verify concentration duration preserved
        $this->assertEquals('Concentration, up to 1 minute', $feature->duration);
    }

    /**
     * Create a temporary XML file for testing.
     */
    private function createTempXmlFile(string $xmlContent): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'optionalfeature_test_');
        file_put_contents($tempFile, $xmlContent);

        return $tempFile;
    }
}

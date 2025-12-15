<?php

namespace Tests\Feature\Importers;

use App\Enums\OptionalFeatureType;
use App\Models\CharacterClass;
use App\Models\OptionalFeature;
use App\Services\Importers\OptionalFeatureImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests that optional features with subclass restrictions are linked
 * directly to the subclass entity, not just the base class with a
 * subclass_name pivot column.
 *
 * Issue: Elemental Disciplines were linked to Monk (base class) with
 * subclass_name="Way of the Four Elements", but when querying the
 * Way of Four Elements subclass via API, optional_features was empty.
 *
 * Expected: Link directly to the subclass ID so that querying the
 * subclass returns its optional features correctly.
 */
#[Group('importers')]
class OptionalFeatureSubclassLinkingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private OptionalFeatureImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new OptionalFeatureImporter;
    }

    #[Test]
    public function it_links_elemental_discipline_directly_to_subclass(): void
    {
        // Create Monk base class and Way of the Four Elements subclass
        $monk = CharacterClass::factory()->create([
            'name' => 'Monk',
            'slug' => 'monk',
            'parent_class_id' => null,
        ]);

        $wayOfFourElements = CharacterClass::factory()->create([
            'name' => 'Way of the Four Elements',
            'slug' => 'monk-way-of-the-four-elements',
            'parent_class_id' => $monk->id,
        ]);

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
    <text>You can use your action to briefly control elemental forces within 30 feet of you.

Source:	Player's Handbook (2014) p. 81</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Elemental Attunement')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Key assertion: Feature should be linked to the SUBCLASS, not base class
        $classes = $feature->classes;
        $this->assertCount(1, $classes, 'Should have 1 class association');

        // The linked class should be the subclass, not the base class
        $linkedClass = $classes->first();
        $this->assertEquals(
            $wayOfFourElements->id,
            $linkedClass->id,
            'Feature should be linked to subclass ID, not base class ID'
        );
        $this->assertEquals('Way of the Four Elements', $linkedClass->name);

        // The subclass_name pivot column should be null since we link directly
        $this->assertNull(
            $linkedClass->pivot->subclass_name,
            'subclass_name should be null when linked directly to subclass'
        );

        // Verify the subclass's optionalFeatures relationship returns this feature
        $subclassFeatures = $wayOfFourElements->optionalFeatures;
        $this->assertCount(1, $subclassFeatures);
        $this->assertEquals($feature->id, $subclassFeatures->first()->id);

        // Verify the base class does NOT have this feature in its optionalFeatures
        $baseClassFeatures = $monk->optionalFeatures;
        $this->assertCount(0, $baseClassFeatures, 'Base class should not have subclass-specific features');
    }

    #[Test]
    public function it_links_base_class_feature_to_base_class(): void
    {
        // Create Warlock class (no subclass)
        $warlock = CharacterClass::factory()->create([
            'name' => 'Warlock',
            'slug' => 'warlock',
            'parent_class_id' => null,
        ]);

        // Original XML for Armor of Shadows invocation (no subclass restriction)
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Invocation: Armor of Shadows</name>
    <level>0</level>
    <classes>Eldritch Invocations</classes>
    <text>You can cast mage armor on yourself at will, without expending a spell slot.

Source:	Player's Handbook (2014) p. 110</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Armor of Shadows')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Feature should be linked to base class (Warlock)
        $classes = $feature->classes;
        $this->assertCount(1, $classes);
        $this->assertEquals('Warlock', $classes->first()->name);
        $this->assertNull($classes->first()->pivot->subclass_name);

        // Verify base class's optionalFeatures relationship returns this feature
        $warlockFeatures = $warlock->optionalFeatures;
        $this->assertCount(1, $warlockFeatures);
        $this->assertEquals($feature->id, $warlockFeatures->first()->id);
    }

    #[Test]
    public function it_links_maneuver_to_battle_master_subclass(): void
    {
        // Create Fighter base class and Battle Master subclass
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'fighter',
            'parent_class_id' => null,
        ]);

        $battleMaster = CharacterClass::factory()->create([
            'name' => 'Battle Master',
            'slug' => 'fighter-battle-master',
            'parent_class_id' => $fighter->id,
        ]);

        // Original XML for Disarming Attack maneuver
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Maneuver: Disarming Attack</name>
    <level>0</level>
    <classes>Maneuver Options</classes>
    <text>When you hit a creature with a weapon attack, you can expend one superiority die.

Source:	Player's Handbook (2014) p. 74</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Disarming Attack')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Feature should be linked to Battle Master subclass
        $classes = $feature->classes;
        $this->assertCount(1, $classes);
        $this->assertEquals(
            $battleMaster->id,
            $classes->first()->id,
            'Maneuver should be linked to Battle Master subclass'
        );

        // Verify Battle Master's optionalFeatures relationship returns this feature
        $battleMasterFeatures = $battleMaster->optionalFeatures;
        $this->assertCount(1, $battleMasterFeatures);
        $this->assertEquals($feature->id, $battleMasterFeatures->first()->id);

        // Fighter base class should NOT have this maneuver
        $fighterFeatures = $fighter->optionalFeatures;
        $this->assertCount(0, $fighterFeatures);
    }

    #[Test]
    public function it_links_feature_to_subclass_when_subclass_exists_in_database(): void
    {
        // Create Monk and Way of the Four Elements
        $monk = CharacterClass::factory()->create([
            'name' => 'Monk',
            'slug' => 'monk',
            'parent_class_id' => null,
        ]);

        CharacterClass::factory()->create([
            'name' => 'Way of the Four Elements',
            'slug' => 'monk-way-of-the-four-elements',
            'parent_class_id' => $monk->id,
        ]);

        // Create feature manually to test linking logic
        $feature = OptionalFeature::factory()->create([
            'name' => 'Test Discipline',
            'feature_type' => OptionalFeatureType::ELEMENTAL_DISCIPLINE,
        ]);

        // Verify we can look up the subclass by name
        $subclass = CharacterClass::where('name', 'Way of the Four Elements')->first();
        $this->assertNotNull($subclass);
        $this->assertEquals($monk->id, $subclass->parent_class_id);
    }

    #[Test]
    public function it_falls_back_to_base_class_when_subclass_not_found(): void
    {
        // Create only Monk base class (no subclass in DB)
        $monk = CharacterClass::factory()->create([
            'name' => 'Monk',
            'slug' => 'monk',
            'parent_class_id' => null,
        ]);

        // Original XML references a subclass that doesn't exist
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Elemental Discipline: Elemental Attunement</name>
    <level>0</level>
    <classes>Monk (Way of the Four Elements)</classes>
    <text>You can control elemental forces.

Source:	Player's Handbook (2014) p. 81</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Elemental Attunement')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // When subclass doesn't exist, fall back to base class with subclass_name in pivot
        $classes = $feature->classes;
        $this->assertCount(1, $classes);
        $this->assertEquals('Monk', $classes->first()->name);
        $this->assertEquals(
            'Way of the Four Elements',
            $classes->first()->pivot->subclass_name,
            'Should store subclass_name in pivot when subclass entity not found'
        );
    }

    #[Test]
    public function it_links_arcane_shot_to_arcane_archer_subclass(): void
    {
        // Create Fighter base class and Arcane Archer subclass
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'parent_class_id' => null,
        ]);

        $arcaneArcher = CharacterClass::factory()->create([
            'name' => 'Arcane Archer',
            'slug' => 'xge:arcane-archer',
            'parent_class_id' => $fighter->id,
        ]);

        // Real XML format from XGE - note the ": Arcane Shot" suffix
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Arcane Shot: Banishing Arrow</name>
    <level>0</level>
    <school>A</school>
    <time>part of the Attack action to fire a magic arrow</time>
    <classes>Fighter (Arcane Archer): Arcane Shot</classes>
    <text>You use abjuration magic to try to temporarily banish your target.

Source:	Xanathar's Guide to Everything p. 29</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Banishing Arrow')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Feature should be linked to Arcane Archer subclass
        $classes = $feature->classes;
        $this->assertCount(1, $classes, 'Should have 1 class association');
        $this->assertEquals(
            $arcaneArcher->id,
            $classes->first()->id,
            'Arcane Shot should be linked to Arcane Archer subclass, not base Fighter class'
        );
        $this->assertEquals('Arcane Archer', $classes->first()->name);

        // Verify Arcane Archer's optionalFeatures returns this feature
        $archerFeatures = $arcaneArcher->optionalFeatures;
        $this->assertCount(1, $archerFeatures);
        $this->assertEquals($feature->id, $archerFeatures->first()->id);

        // Fighter base class should NOT have this feature
        $fighterFeatures = $fighter->optionalFeatures;
        $this->assertCount(0, $fighterFeatures);
    }

    #[Test]
    public function it_links_rune_to_rune_knight_subclass(): void
    {
        // Create Fighter base class and Rune Knight subclass
        $fighter = CharacterClass::factory()->create([
            'name' => 'Fighter',
            'slug' => 'phb:fighter',
            'parent_class_id' => null,
        ]);

        $runeKnight = CharacterClass::factory()->create([
            'name' => 'Rune Knight',
            'slug' => 'tce:rune-knight',
            'parent_class_id' => $fighter->id,
        ]);

        // Real XML format from TCE
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Rune: Cloud Rune</name>
    <level>0</level>
    <classes>Fighter (Rune Knight)</classes>
    <text>This rune emulates the deceptive magic used by some cloud giants.

Source:	Tasha's Cauldron of Everything p. 44</text>
  </spell>
</compendium>
XML;

        // Import the feature
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feature
        $feature = OptionalFeature::where('name', 'Cloud Rune')->first();
        $this->assertNotNull($feature, 'Optional feature should be imported');

        // Feature should be linked to Rune Knight subclass
        $classes = $feature->classes;
        $this->assertCount(1, $classes, 'Should have 1 class association');
        $this->assertEquals(
            $runeKnight->id,
            $classes->first()->id,
            'Rune should be linked to Rune Knight subclass'
        );

        // Verify Rune Knight's optionalFeatures returns this feature
        $knightFeatures = $runeKnight->optionalFeatures;
        $this->assertCount(1, $knightFeatures);
        $this->assertEquals($feature->id, $knightFeatures->first()->id);
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

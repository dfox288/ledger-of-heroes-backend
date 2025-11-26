<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\Spell;
use App\Services\Importers\SpellClassMappingImporter;
use App\Services\Parsers\SpellClassMappingParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class SpellClassMappingImporterTest extends TestCase
{
    use RefreshDatabase;

    private SpellClassMappingImporter $importer;

    protected $seed = true; // Seed database before each test

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new SpellClassMappingImporter(new SpellClassMappingParser);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_adds_class_associations_to_existing_spell()
    {
        // Create classes and spell
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'parent_class_id' => null]);
        $paladin = CharacterClass::factory()->create(['name' => 'Paladin', 'parent_class_id' => null]);
        $deathDomain = CharacterClass::factory()->create(['name' => 'Death Domain', 'parent_class_id' => $cleric->id]);
        $oathbreaker = CharacterClass::factory()->create(['name' => 'Oathbreaker', 'parent_class_id' => $paladin->id]);

        $spell = Spell::factory()->create(['name' => 'Animate Dead', 'slug' => 'animate-dead']);
        $spell->classes()->attach($wizard->id);

        // Create XML that adds two more classes
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Animate Dead</name>
    <classes>Cleric (Death Domain), Paladin (Oathbreaker)</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);

        // Import
        $stats = $this->importer->import($filePath);

        // Verify stats
        $this->assertEquals(1, $stats['processed']);
        $this->assertEquals(1, $stats['spells_found']);
        $this->assertEquals(2, $stats['classes_added']); // Added 2 new classes
        $this->assertEmpty($stats['spells_not_found']);

        // Verify spell now has 3 class associations (original + 2 new)
        $spell->refresh();
        $this->assertCount(3, $spell->classes);

        $classNames = $spell->classes->pluck('name')->toArray();
        $this->assertContains('Wizard', $classNames); // Original class preserved
        $this->assertContains('Death Domain', $classNames);
        $this->assertContains('Oathbreaker', $classNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_duplicate_existing_class_associations()
    {
        // Create classes
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'parent_class_id' => null]);
        $paladin = CharacterClass::factory()->create(['name' => 'Paladin', 'parent_class_id' => null]);
        $deathDomain = CharacterClass::factory()->create(['name' => 'Death Domain', 'parent_class_id' => $cleric->id]);
        $oathbreaker = CharacterClass::factory()->create(['name' => 'Oathbreaker', 'parent_class_id' => $paladin->id]);

        // Create spell with Death Domain already assigned
        $spell = Spell::factory()->create(['name' => 'Blight', 'slug' => 'blight']);
        $spell->classes()->attach($deathDomain->id);

        // XML that tries to add Death Domain again + Oathbreaker
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Blight</name>
    <classes>Cleric (Death Domain), Paladin (Oathbreaker)</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);

        // Import
        $stats = $this->importer->import($filePath);

        // Verify stats - only 1 new class added (Oathbreaker)
        $this->assertEquals(1, $stats['classes_added']);

        // Verify spell has 2 unique class associations (no duplicates)
        $spell->refresh();
        $this->assertCount(2, $spell->classes);

        $classNames = $spell->classes->pluck('name')->toArray();
        $this->assertContains('Death Domain', $classNames);
        $this->assertContains('Oathbreaker', $classNames);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_base_class_without_subclass()
    {
        // Create base classes
        $wizard = CharacterClass::factory()->create(['name' => 'Wizard', 'parent_class_id' => null]);
        $sorcerer = CharacterClass::factory()->create(['name' => 'Sorcerer', 'parent_class_id' => null]);

        $spell = Spell::factory()->create(['name' => 'Fireball', 'slug' => 'fireball']);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Fireball</name>
    <classes>Wizard, Sorcerer</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);
        $stats = $this->importer->import($filePath);

        $this->assertEquals(2, $stats['classes_added']);

        $spell->refresh();
        $classNames = $spell->classes->pluck('name')->toArray();
        $this->assertContains('Wizard', $classNames);
        $this->assertContains('Sorcerer', $classNames);

        // Ensure we got BASE classes, not subclasses
        foreach ($spell->classes as $class) {
            $this->assertNull($class->parent_class_id, "Expected base class, got subclass: {$class->name}");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_skips_spells_not_found_in_database()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Nonexistent Spell</name>
    <classes>Wizard</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);
        $stats = $this->importer->import($filePath);

        $this->assertEquals(1, $stats['processed']);
        $this->assertEquals(0, $stats['spells_found']);
        $this->assertEquals(0, $stats['classes_added']);
        $this->assertEquals(['Nonexistent Spell'], $stats['spells_not_found']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_processes_multiple_spells()
    {
        // Create classes
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'parent_class_id' => null]);
        $paladin = CharacterClass::factory()->create(['name' => 'Paladin', 'parent_class_id' => null]);
        $deathDomain = CharacterClass::factory()->create(['name' => 'Death Domain', 'parent_class_id' => $cleric->id]);
        $oathbreaker = CharacterClass::factory()->create(['name' => 'Oathbreaker', 'parent_class_id' => $paladin->id]);

        $spell1 = Spell::factory()->create(['name' => 'Animate Dead', 'slug' => 'animate-dead']);
        $spell2 = Spell::factory()->create(['name' => 'Blight', 'slug' => 'blight']);
        $spell3 = Spell::factory()->create(['name' => 'Confusion', 'slug' => 'confusion']);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Animate Dead</name>
    <classes>Cleric (Death Domain), Paladin (Oathbreaker)</classes>
  </spell>
  <spell>
    <name>Blight</name>
    <classes>Cleric (Death Domain)</classes>
  </spell>
  <spell>
    <name>Confusion</name>
    <classes>Paladin (Oathbreaker)</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);
        $stats = $this->importer->import($filePath);

        $this->assertEquals(3, $stats['processed']);
        $this->assertEquals(3, $stats['spells_found']);
        $this->assertEquals(4, $stats['classes_added']); // 2 + 1 + 1
        $this->assertEmpty($stats['spells_not_found']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_uses_fuzzy_matching_for_spell_names()
    {
        // Create classes
        $cleric = CharacterClass::factory()->create(['name' => 'Cleric', 'parent_class_id' => null]);
        $arcanaDomain = CharacterClass::factory()->create(['name' => 'Arcana Domain', 'parent_class_id' => $cleric->id]);

        // Create spell with full name
        $spell = Spell::factory()->create([
            'name' => "Leomund's Secret Chest",
            'slug' => 'leomunds-secret-chest',
        ]);

        // XML uses shortened name
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Secret Chest</name>
    <classes>Cleric (Arcana Domain)</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);
        $stats = $this->importer->import($filePath);

        $this->assertEquals(1, $stats['spells_found']);
        $this->assertEquals(1, $stats['classes_added']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_subclass_aliases()
    {
        // Create classes
        $druid = CharacterClass::factory()->create(['name' => 'Druid', 'parent_class_id' => null]);
        $circleOfLand = CharacterClass::factory()->create(['name' => 'Circle of the Land', 'parent_class_id' => $druid->id]);

        // Create spell
        $spell = Spell::factory()->create(['name' => 'Barkskin', 'slug' => 'barkskin']);

        // XML uses terrain variant name (should map to Circle of the Land)
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <spell>
    <name>Barkskin</name>
    <classes>Druid (Forest), Druid (Coast)</classes>
  </spell>
</compendium>
XML;

        $filePath = $this->createTempXmlFile($xml);
        $stats = $this->importer->import($filePath);

        $this->assertEquals(1, $stats['spells_found']);
        // Both "Forest" and "Coast" should map to single "Circle of the Land" subclass
        $this->assertEquals(1, $stats['classes_added']);

        $spell->refresh();
        $this->assertCount(1, $spell->classes);
        $this->assertEquals('Circle of the Land', $spell->classes->first()->name);
    }

    /**
     * Create a temporary XML file for testing.
     */
    private function createTempXmlFile(string $content): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'spell_mapping_test_');
        file_put_contents($filePath, $content);

        // Register for cleanup after test
        $this->beforeApplicationDestroyed(function () use ($filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        });

        return $filePath;
    }
}

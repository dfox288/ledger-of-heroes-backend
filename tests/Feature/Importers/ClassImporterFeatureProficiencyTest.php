<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\Proficiency;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('importers')]
class ClassImporterFeatureProficiencyTest extends TestCase
{
    use RefreshDatabase;

    protected $seeder = \Database\Seeders\LookupSeeder::class;

    private ClassImporter $importer;

    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = app(ClassImporter::class);
        $this->parser = app(ClassXmlParser::class);
    }

    #[Test]
    public function imports_proficiencies_from_hexblade_hex_warrior_feature(): void
    {
        $xml = $this->getHexbladeXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        // Find the Hexblade subclass
        $hexblade = CharacterClass::where('name', 'The Hexblade')->first();
        $this->assertNotNull($hexblade, 'The Hexblade subclass should exist');

        // Find the Hex Warrior feature
        $hexWarriorFeature = ClassFeature::where('class_id', $hexblade->id)
            ->where('feature_name', 'Hex Warrior (The Hexblade)')
            ->first();
        $this->assertNotNull($hexWarriorFeature, 'Hex Warrior feature should exist');

        // Check that proficiencies are linked to the feature
        $proficiencies = Proficiency::where('reference_type', ClassFeature::class)
            ->where('reference_id', $hexWarriorFeature->id)
            ->get();

        $this->assertCount(3, $proficiencies, 'Should have 3 proficiencies linked');

        // Verify the specific proficiencies
        $profNames = $proficiencies->pluck('proficiency_name')->toArray();
        $this->assertContains('medium armor', $profNames);
        $this->assertContains('shields', $profNames);
        $this->assertContains('martial weapons', $profNames);

        // Verify proficiency types
        $armorProfs = $proficiencies->where('proficiency_type', 'armor');
        $weaponProfs = $proficiencies->where('proficiency_type', 'weapon');

        $this->assertCount(2, $armorProfs, 'Should have 2 armor proficiencies (medium armor + shields)');
        $this->assertCount(1, $weaponProfs, 'Should have 1 weapon proficiency (martial weapons)');
    }

    #[Test]
    public function does_not_create_duplicate_proficiencies_on_reimport(): void
    {
        $xml = $this->getHexbladeXml();
        $classes = $this->parser->parse($xml);

        // Import twice
        $this->importer->import($classes[0]);
        $this->importer->import($classes[0]);

        $hexblade = CharacterClass::where('name', 'The Hexblade')->first();
        $hexWarriorFeature = ClassFeature::where('class_id', $hexblade->id)
            ->where('feature_name', 'Hex Warrior (The Hexblade)')
            ->first();

        $proficiencies = Proficiency::where('reference_type', ClassFeature::class)
            ->where('reference_id', $hexWarriorFeature->id)
            ->get();

        $this->assertCount(3, $proficiencies, 'Should not create duplicates on reimport');
    }

    #[Test]
    public function does_not_match_prerequisite_proficiency_text(): void
    {
        // Text like "requires proficiency with X" should NOT create proficiency records
        $xml = $this->getFeatureWithPrerequisiteXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $subclass = CharacterClass::where('name', 'Test Subclass')->first();
        $feature = ClassFeature::where('class_id', $subclass->id)
            ->where('feature_name', 'Prerequisite Feature (Test Subclass)')
            ->first();
        $this->assertNotNull($feature);

        $proficiencies = Proficiency::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->get();

        $this->assertCount(0, $proficiencies, 'Should not create proficiencies from prerequisite text');
    }

    #[Test]
    public function imports_single_proficiency_from_feature(): void
    {
        $xml = $this->getSingleProficiencyXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $subclass = CharacterClass::where('name', 'Test Subclass')->first();
        $feature = ClassFeature::where('class_id', $subclass->id)
            ->where('feature_name', 'Single Prof Feature (Test Subclass)')
            ->first();
        $this->assertNotNull($feature);

        $proficiencies = Proficiency::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->get();

        $this->assertCount(1, $proficiencies, 'Should have 1 proficiency linked');
        $this->assertEquals('heavy armor', $proficiencies->first()->proficiency_name);
        $this->assertEquals('armor', $proficiencies->first()->proficiency_type);
    }

    private function getHexbladeXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Warlock</name>
    <hd>8</hd>
    <proficiency>Wisdom, Charisma</proficiency>
    <spellAbility>Charisma</spellAbility>
    <autolevel level="1">
      <feature optional="YES">
        <name>Otherworldly Patron: The Hexblade</name>
        <text>You have made your pact with a mysterious entity from the Shadowfell.

Source: Xanathar's Guide to Everything p. 55</text>
      </feature>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Hex Warrior (The Hexblade)</name>
        <text>At 1st level, you acquire the training necessary to effectively arm yourself for battle. You gain proficiency with medium armor, shields, and martial weapons.
	The influence of your patron also allows you to mystically channel your will through a particular weapon.

Source: Xanathar's Guide to Everything p. 55</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }

    private function getFeatureWithPrerequisiteXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <proficiency>Strength, Constitution</proficiency>
    <autolevel level="3">
      <feature optional="YES">
        <name>Martial Archetype: Test Subclass</name>
        <text>A test subclass.</text>
      </feature>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Prerequisite Feature (Test Subclass)</name>
        <text>This feature requires proficiency with heavy armor to use effectively. It does not grant any proficiencies.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }

    private function getSingleProficiencyXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <proficiency>Strength, Constitution</proficiency>
    <autolevel level="3">
      <feature optional="YES">
        <name>Martial Archetype: Test Subclass</name>
        <text>A test subclass.</text>
      </feature>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Single Prof Feature (Test Subclass)</name>
        <text>At 3rd level, you become an expert in defensive combat. You gain proficiency with heavy armor.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }
}

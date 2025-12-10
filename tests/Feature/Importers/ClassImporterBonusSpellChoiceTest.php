<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('importers')]
class ClassImporterBonusSpellChoiceTest extends TestCase
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

        // Create Druid base class for spell list reference
        CharacterClass::factory()->create([
            'name' => 'Druid',
            'slug' => 'druid',
            'full_slug' => 'phb:druid',
            'parent_class_id' => null,
        ]);
    }

    #[Test]
    public function imports_cantrip_choice_from_feature_text(): void
    {
        $xml = $this->getNatureDomainXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        // Find the Nature Domain subclass
        $natureDomain = CharacterClass::where('name', 'Nature Domain')->first();
        $this->assertNotNull($natureDomain, 'Nature Domain subclass should exist');

        // Find the Acolyte of Nature feature
        $feature = ClassFeature::where('class_id', $natureDomain->id)
            ->where('feature_name', 'Acolyte of Nature (Nature Domain)')
            ->first();
        $this->assertNotNull($feature, 'Acolyte of Nature feature should exist');

        // Check that a cantrip choice is linked to this feature
        $spellChoice = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('is_choice', true)
            ->first();

        $this->assertNotNull($spellChoice, 'Should have a spell choice record');
        $this->assertNull($spellChoice->spell_id, 'spell_id should be null for choices');
        $this->assertTrue((bool) $spellChoice->is_cantrip, 'Should be marked as cantrip');
        $this->assertEquals(1, $spellChoice->choice_count, 'Should choose 1 cantrip');
        $this->assertEquals(0, $spellChoice->max_level, 'max_level should be 0 for cantrips');

        // Check that it references the Druid spell list
        $druid = CharacterClass::where('name', 'Druid')->whereNull('parent_class_id')->first();
        $this->assertEquals($druid->id, $spellChoice->class_id, 'Should reference Druid spell list');
    }

    #[Test]
    public function imports_wizard_cantrip_choice(): void
    {
        // Create Wizard base class
        CharacterClass::factory()->create([
            'name' => 'Wizard',
            'slug' => 'wizard',
            'full_slug' => 'phb:wizard',
            'parent_class_id' => null,
        ]);

        $xml = $this->getHighElfMagicXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $subclass = CharacterClass::where('name', 'High Elf Magic')->first();
        $feature = ClassFeature::where('class_id', $subclass->id)
            ->where('feature_name', 'Cantrip (High Elf Magic)')
            ->first();

        $spellChoice = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('is_choice', true)
            ->first();

        $this->assertNotNull($spellChoice);
        $wizard = CharacterClass::where('name', 'Wizard')->whereNull('parent_class_id')->first();
        $this->assertEquals($wizard->id, $spellChoice->class_id);
    }

    #[Test]
    public function does_not_create_duplicate_spell_choices_on_reimport(): void
    {
        $xml = $this->getNatureDomainXml();
        $classes = $this->parser->parse($xml);

        // Import twice
        $this->importer->import($classes[0]);
        $this->importer->import($classes[0]);

        $natureDomain = CharacterClass::where('name', 'Nature Domain')->first();
        $feature = ClassFeature::where('class_id', $natureDomain->id)
            ->where('feature_name', 'Acolyte of Nature (Nature Domain)')
            ->first();

        // Should only have 1 spell choice record
        $count = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('is_choice', true)
            ->count();

        $this->assertEquals(1, $count, 'Should not create duplicate spell choices on reimport');
    }

    #[Test]
    public function does_not_import_spell_choice_when_class_not_found(): void
    {
        // Don't create Bard class - should skip gracefully
        $xml = $this->getBardCantripXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $subclass = CharacterClass::where('name', 'Test Domain')->first();
        $feature = ClassFeature::where('class_id', $subclass->id)
            ->where('feature_name', 'Bard Cantrip (Test Domain)')
            ->first();

        // Should not have any spell choice since Bard class doesn't exist
        $count = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->count();

        $this->assertEquals(0, $count, 'Should not create spell choice when class not found');
    }

    #[Test]
    public function postprocessing_links_spell_choices_when_class_imported_later(): void
    {
        // Simulate alphabetical import order: Cleric (with Nature Domain) imports BEFORE Druid exists
        // This is the real-world scenario where class-cleric-phb.xml comes before class-druid-phb.xml

        // First, remove the Druid class that setUp created
        CharacterClass::where('name', 'Druid')->delete();

        // Import Cleric/Nature Domain when Druid doesn't exist
        $xml = $this->getNatureDomainXml();
        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        // Verify no spell choice was created (because Druid didn't exist)
        $natureDomain = CharacterClass::where('name', 'Nature Domain')->first();
        $feature = ClassFeature::where('class_id', $natureDomain->id)
            ->where('feature_name', 'Acolyte of Nature (Nature Domain)')
            ->first();

        $initialCount = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('is_choice', true)
            ->count();

        $this->assertEquals(0, $initialCount, 'No spell choice should exist before Druid is imported');

        // Now create the Druid class (simulating later alphabetical import)
        $druid = CharacterClass::factory()->create([
            'name' => 'Druid',
            'slug' => 'druid',
            'full_slug' => 'phb:druid',
            'parent_class_id' => null,
        ]);

        // Run the postprocessing logic directly (same as linkBonusSpellChoices in ImportAllDataCommand)
        $this->runBonusSpellChoicePostprocessing();

        // Now verify the spell choice was linked by postprocessing
        $spellChoice = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('is_choice', true)
            ->first();

        $this->assertNotNull($spellChoice, 'Postprocessing should have linked the spell choice');
        $this->assertEquals($druid->id, $spellChoice->class_id, 'Should reference Druid spell list');
        $this->assertTrue((bool) $spellChoice->is_cantrip, 'Should be marked as cantrip');
        $this->assertEquals(1, $spellChoice->choice_count, 'Should choose 1 cantrip');
    }

    /**
     * Run the same postprocessing logic as ImportAllDataCommand::linkBonusSpellChoices().
     */
    private function runBonusSpellChoicePostprocessing(): void
    {
        $pattern = '/(?:you\s+(?:learn|know|gain)\s+)?one\s+(\w+)\s+(cantrip|spell)\s+of\s+your\s+choice/i';

        $features = ClassFeature::where('description', 'like', '%cantrip of your choice%')
            ->orWhere('description', 'like', '%spell of your choice%')
            ->get();

        foreach ($features as $feature) {
            $existingChoice = EntitySpell::where('reference_type', ClassFeature::class)
                ->where('reference_id', $feature->id)
                ->where('is_choice', true)
                ->exists();

            if ($existingChoice) {
                continue;
            }

            if (preg_match($pattern, $feature->description, $matches)) {
                $className = ucfirst(strtolower($matches[1]));
                $spellType = strtolower($matches[2]);
                $isCantrip = $spellType === 'cantrip';

                $spellListClass = CharacterClass::where('name', $className)
                    ->whereNull('parent_class_id')
                    ->first();

                if ($spellListClass) {
                    EntitySpell::create([
                        'reference_type' => ClassFeature::class,
                        'reference_id' => $feature->id,
                        'spell_id' => null,
                        'is_choice' => true,
                        'is_cantrip' => $isCantrip,
                        'choice_count' => 1,
                        'max_level' => $isCantrip ? 0 : null,
                        'choice_group' => 'feature_spell_choice',
                        'class_id' => $spellListClass->id,
                    ]);
                }
            }
        }
    }

    private function getNatureDomainXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Cleric</name>
    <hd>8</hd>
    <proficiency>Wisdom, Charisma</proficiency>
    <spellAbility>Wisdom</spellAbility>
    <autolevel level="1">
      <slots>3,2</slots>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Divine Domain: Nature Domain</name>
        <text>Gods of nature are as varied as the natural world itself.

Source: Player's Handbook (2014) p. 62</text>
      </feature>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Acolyte of Nature (Nature Domain)</name>
        <text>At 1st level, you learn one druid cantrip of your choice. This cantrip counts as a cleric cantrip for you, but it doesn't count against the number of cleric cantrips you know. You also gain proficiency in one of the following skills of your choice: Animal Handling, Nature, or Survival.

Source: Player's Handbook (2014) p. 62</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }

    private function getHighElfMagicXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <proficiency>Strength, Constitution</proficiency>
    <autolevel level="1">
      <feature optional="YES">
        <name>Martial Archetype: High Elf Magic</name>
        <text>You have magical training.

Source: Test</text>
      </feature>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Cantrip (High Elf Magic)</name>
        <text>You know one wizard cantrip of your choice.

Source: Test</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }

    private function getBardCantripXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <class>
    <name>Cleric</name>
    <hd>8</hd>
    <proficiency>Wisdom, Charisma</proficiency>
    <spellAbility>Wisdom</spellAbility>
    <autolevel level="1">
      <slots>3,2</slots>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Divine Domain: Test Domain</name>
        <text>Test domain.

Source: Test</text>
      </feature>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Bard Cantrip (Test Domain)</name>
        <text>You learn one bard cantrip of your choice.

Source: Test</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }
}

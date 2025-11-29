<?php

namespace Tests\Feature\Importers;

use App\Services\Importers\BackgroundImporter;
use App\Services\Parsers\BackgroundXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class BackgroundXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    private BackgroundXmlParser $parser;

    private BackgroundImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BackgroundXmlParser;
        $this->importer = new BackgroundImporter;
    }

    #[Test]
    public function it_reconstructs_simple_background()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight, Religion</proficiency>
    <trait>
      <name>Description</name>
      <text>You have spent your life in the service of a temple.

Source: Player's Handbook (2014) p. 127</text>
    </trait>
    <trait>
      <name>Feature: Shelter of the Faithful</name>
      <text>You command the respect of those who share your faith.</text>
    </trait>
  </background>
</compendium>
XML;

        // Act: Parse → Import → Reload
        $backgrounds = $this->parser->parse($originalXml);
        $background = $this->importer->import($backgrounds[0]);
        $background->load(['traits', 'proficiencies', 'sources.source']);

        // Assert: Core data
        $this->assertEquals('Acolyte', $background->name);

        // Assert: Proficiencies
        $this->assertCount(2, $background->proficiencies);
        $this->assertTrue($background->proficiencies->contains('proficiency_name', 'Insight'));
        $this->assertTrue($background->proficiencies->contains('proficiency_name', 'Religion'));

        // Assert: Traits
        $this->assertCount(2, $background->traits);

        $descTrait = $background->traits->where('name', 'Description')->first();
        $this->assertNotNull($descTrait);
        $this->assertStringContainsString('temple', $descTrait->description);

        $featureTrait = $background->traits->where('category', 'feature')->first();
        $this->assertEquals('Feature: Shelter of the Faithful', $featureTrait->name);

        // Assert: Sources
        $this->assertCount(1, $background->sources);
        $this->assertEquals('PHB', $background->sources->first()->source->code);
        $this->assertEquals('127', $background->sources->first()->pages);
    }

    #[Test]
    public function it_reconstructs_background_with_tool_proficiencies()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Criminal</name>
    <proficiency>Deception, Stealth</proficiency>
    <trait>
      <name>Description</name>
      <text>You are an experienced criminal.

• Tool Proficiencies: One type of gaming set, thieves' tools

Source: Player's Handbook (2014) p. 129</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($originalXml);
        $background = $this->importer->import($backgrounds[0]);
        $background->load(['proficiencies']);

        // Should have 2 skill proficiencies
        $skills = $background->proficiencies->where('proficiency_type', 'skill');
        $this->assertCount(2, $skills);

        // Note: Tool proficiencies are in trait text, not <proficiency> tag
        // This is intentional - XML structure varies
    }

    #[Test]
    public function it_reconstructs_background_with_characteristics_and_data_tables()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight, Religion</proficiency>
    <trait>
      <name>Description</name>
      <text>Test description

Source: Player's Handbook (2014) p. 127</text>
    </trait>
    <trait>
      <name>Suggested Characteristics</name>
      <text>Acolytes are shaped by their experiences.

d8 | Personality Trait
1 | I idolize a hero of my faith
2 | I can find common ground
3 | I see omens everywhere

d6 | Ideal
1 | Tradition
2 | Charity

d6 | Bond
1 | I would die for a relic
2 | I seek revenge

d6 | Flaw
1 | I judge harshly
2 | I trust too much</text>
      <roll description="Personality Trait">1d8</roll>
      <roll description="Ideal">1d6</roll>
      <roll description="Bond">1d6</roll>
      <roll description="Flaw">1d6</roll>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($originalXml);
        $background = $this->importer->import($backgrounds[0]);
        $background->load(['traits.dataTables.entries']);

        // Assert: Characteristics trait exists
        $charTrait = $background->traits->where('category', 'characteristics')->first();
        $this->assertNotNull($charTrait);

        // Assert: 4 data tables extracted
        $this->assertCount(4, $charTrait->dataTables);

        $tables = $charTrait->dataTables;
        $this->assertTrue($tables->contains('table_name', 'Personality Trait'));
        $this->assertTrue($tables->contains('table_name', 'Ideal'));
        $this->assertTrue($tables->contains('table_name', 'Bond'));
        $this->assertTrue($tables->contains('table_name', 'Flaw'));

        // Assert: Dice types correct (from headerless table format "d8 | Personality Trait")
        $personalityTable = $tables->firstWhere('table_name', 'Personality Trait');
        $this->assertEquals('d8', $personalityTable->dice_type);

        $idealTable = $tables->firstWhere('table_name', 'Ideal');
        $this->assertEquals('d6', $idealTable->dice_type);

        // Assert: Entries parsed
        $this->assertGreaterThan(0, $personalityTable->entries->count());
    }

    #[Test]
    public function it_handles_backgrounds_without_feature_trait()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Test Background</name>
    <proficiency>Insight</proficiency>
    <trait>
      <name>Description</name>
      <text>Just a description.

Source: Player's Handbook (2014) p. 100</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($originalXml);
        $background = $this->importer->import($backgrounds[0]);
        $background->load(['traits']);

        $this->assertCount(1, $background->traits);
        $this->assertNull($background->traits->where('category', 'feature')->first());
    }

    #[Test]
    public function it_updates_existing_background_on_reimport()
    {
        // First import
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight</proficiency>
    <trait><name>Description</name><text>Old text</text></trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($xml);
        $firstImport = $this->importer->import($backgrounds[0]);
        $firstId = $firstImport->id;

        // Second import with updated data
        $xml2 = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight, Religion</proficiency>
    <trait><name>Description</name><text>New text</text></trait>
  </background>
</compendium>
XML;

        $backgrounds2 = $this->parser->parse($xml2);
        $secondImport = $this->importer->import($backgrounds2[0]);

        // Assert: Same ID (updated, not created)
        $this->assertEquals($firstId, $secondImport->id);

        // Assert: Data updated
        $this->assertCount(2, $secondImport->proficiencies);
        $this->assertStringContainsString('New text', $secondImport->traits->first()->description);
    }

    #[Test]
    public function it_reconstructs_background_languages()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
  <background>
    <name>Acolyte</name>
    <proficiency>Insight, Religion</proficiency>
    <trait>
      <name>Description</name>
      <text>You have spent your life in service to a temple.

• Languages: Two extra languages

Source: Player's Handbook (2014) p. 127</text>
    </trait>
  </background>
</compendium>
XML;

        $backgrounds = $this->parser->parse($originalXml);
        $background = $this->importer->import($backgrounds[0]);
        $background->load(['languages']);

        // Assert: Language choice slot created
        $this->assertCount(2, $background->languages, 'Should have 2 language choice slots');

        // Verify both are choice slots (language_id = null, is_choice = true)
        foreach ($background->languages as $entityLang) {
            $this->assertTrue($entityLang->is_choice, 'Should be marked as choice slot');
            $this->assertNull($entityLang->language_id, 'Choice slot should not have language_id');
        }

        // Verify polymorphic relationship
        $this->assertEquals(\App\Models\Background::class, $background->languages->first()->reference_type);
        $this->assertEquals($background->id, $background->languages->first()->reference_id);
    }
}

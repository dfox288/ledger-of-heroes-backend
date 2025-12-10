<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\Proficiency;
use App\Models\Skill;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('importers')]
class ClassImporterFeatureSkillChoiceTest extends TestCase
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
    public function imports_skill_choice_from_feature_text(): void
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

        // Check that skill choices are linked to this feature
        $skillChoices = Proficiency::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('proficiency_type', 'skill')
            ->where('is_choice', true)
            ->get();

        // Should have 3 skill options: Animal Handling, Nature, Survival
        $this->assertCount(3, $skillChoices, 'Should have 3 skill choice options');

        // Check that all skills are present
        $skillNames = $skillChoices->pluck('proficiency_name')->toArray();
        $this->assertContains('Animal Handling', $skillNames);
        $this->assertContains('Nature', $skillNames);
        $this->assertContains('Survival', $skillNames);

        // Check choice metadata
        $firstChoice = $skillChoices->first();
        $this->assertEquals('feature_skill_choice_1', $firstChoice->choice_group);
        $this->assertEquals(1, $firstChoice->quantity, 'Should pick 1 skill from the list');
    }

    #[Test]
    public function imports_skill_choice_with_five_options(): void
    {
        $xml = $this->getCavalierXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        // Find the Cavalier subclass
        $cavalier = CharacterClass::where('name', 'Cavalier')->first();
        $this->assertNotNull($cavalier);

        // Find the Bonus Proficiency feature
        $feature = ClassFeature::where('class_id', $cavalier->id)
            ->where('feature_name', 'Bonus Proficiency (Cavalier)')
            ->first();
        $this->assertNotNull($feature);

        // Check skill choices
        $skillChoices = Proficiency::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('proficiency_type', 'skill')
            ->where('is_choice', true)
            ->get();

        // Should have 5 skill options
        $this->assertCount(5, $skillChoices);

        $skillNames = $skillChoices->pluck('proficiency_name')->toArray();
        $this->assertContains('Animal Handling', $skillNames);
        $this->assertContains('History', $skillNames);
        $this->assertContains('Insight', $skillNames);
        $this->assertContains('Performance', $skillNames);
        $this->assertContains('Persuasion', $skillNames);
    }

    #[Test]
    public function does_not_create_duplicate_skill_choices_on_reimport(): void
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

        // Should still only have 3 skill choices
        $count = Proficiency::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('proficiency_type', 'skill')
            ->count();

        $this->assertEquals(3, $count, 'Should not create duplicate skill choices on reimport');
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

    private function getCavalierXml(): string
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
        <name>Martial Archetype: Cavalier</name>
        <text>The archetypal Cavalier excels at mounted combat.

Source: Xanathar's Guide to Everything p. 30</text>
      </feature>
    </autolevel>
    <autolevel level="3">
      <feature optional="YES">
        <name>Bonus Proficiency (Cavalier)</name>
        <text>When you choose this archetype at 3rd level, you gain proficiency in one of the following skills of your choice: Animal Handling, History, Insight, Performance, or Persuasion. Alternatively, you learn one language of your choice.

Source: Xanathar's Guide to Everything p. 30</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }
}

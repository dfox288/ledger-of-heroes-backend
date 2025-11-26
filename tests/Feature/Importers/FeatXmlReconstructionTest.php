<?php

namespace Tests\Feature\Importers;

use App\Models\AbilityScore;
use App\Models\Feat;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Services\Importers\FeatImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class FeatXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookup data

    private FeatImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new FeatImporter;
    }

    #[Test]
    public function it_reconstructs_simple_feat()
    {
        // Original XML for Alert feat
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <feat>
    <name>Alert</name>
    <text>Always on the lookout for danger, you gain the following benefits:

  • You gain a +5 bonus to initiative.
  • You can't be surprised while you are conscious.
  • Other creatures don't gain advantage on attack rolls against you as a result of being unseen by you.

  Source: Player's Handbook (2014) p. 165</text>
    <modifier category="bonus">initiative +5</modifier>
  </feat>
</compendium>
XML;

        // Import the feat
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feat
        $feat = Feat::where('name', 'Alert')->first();
        $this->assertNotNull($feat, 'Feat should be imported');

        // Verify core attributes
        $this->assertEquals('Alert', $feat->name);
        $this->assertEquals('alert', $feat->slug);
        $this->assertStringContainsString('Always on the lookout for danger', $feat->description);
        $this->assertNull($feat->prerequisites_text, 'Alert has no prerequisites');

        // Verify modifier
        $modifiers = $feat->modifiers;
        $this->assertCount(1, $modifiers, 'Should have 1 modifier');
        $this->assertEquals('initiative', $modifiers[0]->modifier_category);
        $this->assertEquals(5, $modifiers[0]->value);

        // Verify source citation
        $sources = $feat->sources;
        $this->assertCount(1, $sources, 'Should have 1 source citation');
        $this->assertEquals('PHB', $sources[0]->source->code);
        $this->assertEquals('165', $sources[0]->pages);

        // Verify no prerequisites
        $this->assertCount(0, $feat->prerequisites, 'Should have no prerequisites');
    }

    #[Test]
    public function it_reconstructs_feat_with_ability_prerequisite()
    {
        // Original XML for Grappler feat
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <feat>
    <name>Grappler</name>
    <prerequisite>Strength 13 or higher</prerequisite>
    <text>You've developed skills to aid you in grappling.

  Source: Player's Handbook (2014) p. 167</text>
  </feat>
</compendium>
XML;

        // Import the feat
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feat
        $feat = Feat::where('name', 'Grappler')->first();
        $this->assertNotNull($feat, 'Feat should be imported');

        // Verify prerequisites text stored
        $this->assertEquals('Strength 13 or higher', $feat->prerequisites_text);

        // Verify structured prerequisite
        $prerequisites = $feat->prerequisites;
        $this->assertCount(1, $prerequisites, 'Should have 1 prerequisite');

        $prereq = $prerequisites[0];
        $this->assertEquals(AbilityScore::class, $prereq->prerequisite_type);
        $this->assertEquals(13, $prereq->minimum_value);

        // Verify it's Strength
        $abilityScore = AbilityScore::find($prereq->prerequisite_id);
        $this->assertEquals('STR', $abilityScore->code);
    }

    #[Test]
    public function it_reconstructs_feat_with_dual_ability_prerequisite()
    {
        // Original XML for Observant feat
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <feat>
    <name>Observant</name>
    <prerequisite>Intelligence or Wisdom 13 or higher</prerequisite>
    <text>Quick to notice details of your environment.

  Source: Player's Handbook (2014) p. 168</text>
    <modifier category="ability score">Intelligence +1</modifier>
    <modifier category="ability score">Wisdom +1</modifier>
  </feat>
</compendium>
XML;

        // Import the feat
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feat
        $feat = Feat::where('name', 'Observant')->first();
        $this->assertNotNull($feat, 'Feat should be imported');

        // Verify prerequisites text
        $this->assertEquals('Intelligence or Wisdom 13 or higher', $feat->prerequisites_text);

        // Verify 2 ability score prerequisites with OR logic (same group_id)
        $prerequisites = $feat->prerequisites;
        $this->assertCount(2, $prerequisites, 'Should have 2 prerequisites');

        // Both should have same group_id (OR logic)
        $this->assertEquals($prerequisites[0]->group_id, $prerequisites[1]->group_id);

        // Both should have value = 13
        $this->assertEquals(13, $prerequisites[0]->minimum_value);
        $this->assertEquals(13, $prerequisites[1]->minimum_value);

        // Verify they are Intelligence and Wisdom
        $abilityCodes = $prerequisites->map(function ($prereq) {
            return AbilityScore::find($prereq->prerequisite_id)->code;
        })->sort()->values()->toArray();
        $this->assertEquals(['INT', 'WIS'], $abilityCodes);

        // Verify modifiers
        $modifiers = $feat->modifiers;
        $this->assertCount(2, $modifiers, 'Should have 2 modifiers');

        foreach ($modifiers as $modifier) {
            $this->assertEquals('ability_score', $modifier->modifier_category);
            $this->assertEquals(1, $modifier->value);
            $this->assertNotNull($modifier->ability_score_id);
        }
    }

    #[Test]
    public function it_reconstructs_feat_with_race_prerequisites()
    {
        // Create Dwarf race for prerequisite matching
        Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);

        // Original XML for Dwarven Fortitude feat
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <feat>
    <name>Dwarven Fortitude</name>
    <prerequisite>Dwarf</prerequisite>
    <text>You have the blood of dwarf heroes flowing through your veins.

  Source: Xanathar's Guide to Everything (2017) p. 74</text>
  </feat>
</compendium>
XML;

        // Import the feat
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feat
        $feat = Feat::where('name', 'Dwarven Fortitude')->first();
        $this->assertNotNull($feat, 'Feat should be imported');

        // Verify prerequisites text
        $this->assertEquals('Dwarf', $feat->prerequisites_text);

        // Verify structured prerequisite
        $prerequisites = $feat->prerequisites;
        $this->assertCount(1, $prerequisites, 'Should have 1 prerequisite');

        $prereq = $prerequisites[0];
        $this->assertEquals(Race::class, $prereq->prerequisite_type);

        // Verify it's Dwarf race
        $race = Race::find($prereq->prerequisite_id);
        $this->assertEquals('Dwarf', $race->name);
    }

    #[Test]
    public function it_reconstructs_feat_with_multiple_race_prerequisites()
    {
        // Create races for prerequisite matching
        Race::factory()->create(['name' => 'Dwarf', 'slug' => 'dwarf']);
        Race::factory()->create(['name' => 'Gnome', 'slug' => 'gnome']);
        Race::factory()->create(['name' => 'Halfling', 'slug' => 'halfling']);

        // Original XML for Squat Nimbleness feat
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <feat>
    <name>Squat Nimbleness</name>
    <prerequisite>Dwarf, Gnome, Halfling</prerequisite>
    <text>You are uncommonly nimble for your race.

  Source: Xanathar's Guide to Everything (2017) p. 75</text>
  </feat>
</compendium>
XML;

        // Import the feat
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feat
        $feat = Feat::where('name', 'Squat Nimbleness')->first();
        $this->assertNotNull($feat, 'Feat should be imported');

        // Verify prerequisites text
        $this->assertEquals('Dwarf, Gnome, Halfling', $feat->prerequisites_text);

        // Verify 3 race prerequisites with OR logic (same group_id)
        $prerequisites = $feat->prerequisites;
        $this->assertCount(3, $prerequisites, 'Should have 3 prerequisites');

        // All should have same group_id (OR logic)
        $groupIds = $prerequisites->pluck('group_id')->unique();
        $this->assertCount(1, $groupIds, 'All prerequisites should have same group_id');

        // Verify all are Race type
        foreach ($prerequisites as $prereq) {
            $this->assertEquals(Race::class, $prereq->prerequisite_type);
        }

        // Verify race names
        $raceNames = $prerequisites->map(function ($prereq) {
            return Race::find($prereq->prerequisite_id)->name;
        })->sort()->values()->toArray();
        $this->assertEquals(['Dwarf', 'Gnome', 'Halfling'], $raceNames);
    }

    #[Test]
    public function it_reconstructs_feat_with_proficiency_prerequisite()
    {
        // Original XML for Medium Armor Master feat
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <feat>
    <name>Medium Armor Master</name>
    <prerequisite>Proficiency with medium armor</prerequisite>
    <text>You have practiced moving in medium armor.

  Source: Player's Handbook (2014) p. 168</text>
  </feat>
</compendium>
XML;

        // Import the feat
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feat
        $feat = Feat::where('name', 'Medium Armor Master')->first();
        $this->assertNotNull($feat, 'Feat should be imported');

        // Verify prerequisites text
        $this->assertEquals('Proficiency with medium armor', $feat->prerequisites_text);

        // Verify structured prerequisite
        $prerequisites = $feat->prerequisites;
        $this->assertGreaterThanOrEqual(1, $prerequisites->count(), 'Should have at least 1 prerequisite');

        // Find the medium armor prerequisite
        $mediumArmorPrereq = $prerequisites->first(function ($prereq) {
            if ($prereq->prerequisite_type !== ProficiencyType::class) {
                return false;
            }
            $profType = ProficiencyType::find($prereq->prerequisite_id);

            return $profType && str_contains(strtolower($profType->name), 'medium') && str_contains(strtolower($profType->name), 'armor');
        });

        $this->assertNotNull($mediumArmorPrereq, 'Should have Medium Armor proficiency prerequisite');
    }

    #[Test]
    public function it_reconstructs_feat_with_proficiencies()
    {
        // Original XML for Weapon Master feat
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <feat>
    <name>Weapon Master</name>
    <text>You have practiced extensively with a variety of weapons, gaining the following benefits:

  • Increase your Strength or Dexterity score by 1, to a maximum of 20.
  • You gain proficiency with four weapons of your choice.

  Proficiency: longsword, greatsword, longbow, heavy crossbow

  Source: Player's Handbook (2014) p. 170</text>
    <proficiency>longsword, greatsword, longbow, heavy crossbow</proficiency>
  </feat>
</compendium>
XML;

        // Import the feat
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feat
        $feat = Feat::where('name', 'Weapon Master')->first();
        $this->assertNotNull($feat, 'Feat should be imported');

        // Verify proficiencies
        $proficiencies = $feat->proficiencies;
        $this->assertGreaterThanOrEqual(1, $proficiencies->count(), 'Should have proficiency records');

        // The proficiency element contains comma-separated weapons
        // Parser should extract this as a single proficiency entry
        $firstProf = $proficiencies->first();
        $this->assertNotNull($firstProf);
        $this->assertStringContainsString('longsword', strtolower($firstProf->proficiency_name));
    }

    #[Test]
    public function it_reconstructs_feat_with_conditions()
    {
        // Create races for prerequisite matching
        Race::factory()->create(['name' => 'Elf', 'slug' => 'elf']);
        Race::factory()->create(['name' => 'Half-Elf', 'slug' => 'half-elf']);

        // Original XML for Elven Accuracy feat
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <feat>
    <name>Elven Accuracy</name>
    <prerequisite>Elf or Half-Elf</prerequisite>
    <text>When you have advantage on an attack roll using Dexterity, Intelligence, Wisdom, or Charisma, you can reroll one of the dice once.

  Source: Xanathar's Guide to Everything (2017) p. 74</text>
  </feat>
</compendium>
XML;

        // Import the feat
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feat
        $feat = Feat::where('name', 'Elven Accuracy')->first();
        $this->assertNotNull($feat, 'Feat should be imported');

        // Verify conditions (advantage on attack rolls)
        $conditions = $feat->conditions;

        // Parser looks for "you have advantage on" pattern
        $this->assertGreaterThanOrEqual(1, $conditions->count(), 'Should detect advantage condition');

        if ($conditions->count() > 0) {
            $condition = $conditions->first();
            $this->assertEquals('advantage', $condition->effect_type);
            $this->assertNotEmpty($condition->description);
        }
    }

    #[Test]
    public function it_reconstructs_feat_with_modifiers()
    {
        // Original XML for Actor feat
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <feat>
    <name>Actor</name>
    <text>Skilled at mimicry and dramatics, you gain the following benefits:

  • Increase your Charisma score by 1, to a maximum of 20.
  • You have advantage on Charisma (Deception) and Charisma (Performance) checks when trying to pass yourself off as a different person.
  • You can mimic the speech of another person or the sounds made by other creatures.

  Source: Player's Handbook (2014) p. 165</text>
    <modifier category="ability score">Charisma +1</modifier>
  </feat>
</compendium>
XML;

        // Import the feat
        $this->importer->importFromFile($this->createTempXmlFile($originalXml));

        // Retrieve the imported feat
        $feat = Feat::where('name', 'Actor')->first();
        $this->assertNotNull($feat, 'Feat should be imported');

        // Verify modifier
        $modifiers = $feat->modifiers;
        $this->assertCount(1, $modifiers, 'Should have 1 modifier');

        $modifier = $modifiers->first();
        $this->assertEquals('ability_score', $modifier->modifier_category);
        $this->assertEquals(1, $modifier->value);

        // Verify it's Charisma
        $abilityScore = AbilityScore::find($modifier->ability_score_id);
        $this->assertEquals('CHA', $abilityScore->code);

        // Verify advantage conditions
        $conditions = $feat->conditions;
        $this->assertGreaterThanOrEqual(1, $conditions->count(), 'Should detect advantage condition');
    }

    /**
     * Create a temporary XML file for testing.
     */
    private function createTempXmlFile(string $xmlContent): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'feat_test_');
        file_put_contents($tempFile, $xmlContent);

        return $tempFile;
    }
}

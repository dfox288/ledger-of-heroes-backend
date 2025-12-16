<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\EntitySpell;
use App\Models\Spell;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('importers')]
class ClassImporterBonusCantripTest extends TestCase
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
    public function imports_bonus_cantrip_from_feature_text(): void
    {
        // Create the spell that will be granted
        $lightSpell = Spell::factory()->create([
            'name' => 'Light',
            'slug' => 'light',
            'level' => 0,
        ]);

        $xml = $this->getLightDomainXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        // Find the Light Domain subclass
        $lightDomain = CharacterClass::where('name', 'Light Domain')->first();
        $this->assertNotNull($lightDomain, 'Light Domain subclass should exist');

        // Find the Bonus Cantrip feature
        $bonusCantripFeature = ClassFeature::where('class_id', $lightDomain->id)
            ->where('feature_name', 'Bonus Cantrip (Light Domain)')
            ->first();
        $this->assertNotNull($bonusCantripFeature, 'Bonus Cantrip feature should exist');

        // Check that the light cantrip is linked to this feature
        $grantedSpell = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $bonusCantripFeature->id)
            ->where('spell_id', $lightSpell->id)
            ->first();

        $this->assertNotNull($grantedSpell, 'Light cantrip should be linked to the feature');
        $this->assertTrue((bool) $grantedSpell->is_cantrip, 'Should be marked as cantrip');
        $this->assertFalse((bool) $grantedSpell->is_choice, 'Should not be a choice');
    }

    #[Test]
    public function handles_multi_word_cantrip_names(): void
    {
        // Create the spell with multi-word name
        $minorIllusionSpell = Spell::factory()->create([
            'name' => 'Minor Illusion',
            'slug' => 'minor-illusion',
            'level' => 0,
        ]);

        $xml = $this->getTrickeryDomainXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        // Find the Trickery Domain subclass
        $trickeryDomain = CharacterClass::where('name', 'Trickery Domain')->first();
        $this->assertNotNull($trickeryDomain);

        // Find the feature that grants the cantrip
        $feature = ClassFeature::where('class_id', $trickeryDomain->id)
            ->where('feature_name', 'Blessing of the Trickster (Trickery Domain)')
            ->first();
        $this->assertNotNull($feature, 'Blessing of the Trickster feature should exist');

        // Check that minor illusion is linked
        $grantedSpell = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('spell_id', $minorIllusionSpell->id)
            ->first();

        $this->assertNotNull($grantedSpell, 'Minor Illusion cantrip should be linked');
    }

    #[Test]
    public function does_not_create_duplicate_cantrip_on_reimport(): void
    {
        $lightSpell = Spell::factory()->create([
            'name' => 'Light',
            'slug' => 'light',
            'level' => 0,
        ]);

        $xml = $this->getLightDomainXml();
        $classes = $this->parser->parse($xml);

        // Import twice
        $this->importer->import($classes[0]);
        $this->importer->import($classes[0]);

        $lightDomain = CharacterClass::where('name', 'Light Domain')->first();
        $bonusCantripFeature = ClassFeature::where('class_id', $lightDomain->id)
            ->where('feature_name', 'Bonus Cantrip (Light Domain)')
            ->first();

        // Should only have one entity_spell record
        $count = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $bonusCantripFeature->id)
            ->where('spell_id', $lightSpell->id)
            ->count();

        $this->assertEquals(1, $count, 'Should not create duplicate cantrip grants on reimport');
    }

    private function getLightDomainXml(): string
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
        <name>Divine Domain: Light Domain</name>
        <text>The Light domain focuses on the divine fire.

Source: Player's Handbook (2014) p. 61</text>
      </feature>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Bonus Cantrip (Light Domain)</name>
        <text>When you choose this domain at 1st level, you gain the light cantrip if you don't already know it.

Source: Player's Handbook (2014) p. 61</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }

    #[Test]
    public function imports_cantrip_with_learn_pattern(): void
    {
        // Warlock uses "you learn the X cantrip" instead of "you gain"
        Spell::factory()->create([
            'name' => 'Spare the Dying',
            'slug' => 'spare-the-dying',
            'level' => 0,
        ]);

        $xml = $this->getUndyingWarlockXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $undying = CharacterClass::where('name', 'The Undying')->first();
        $this->assertNotNull($undying);

        $feature = ClassFeature::where('class_id', $undying->id)
            ->where('feature_name', 'Among the Dead (The Undying)')
            ->first();
        $this->assertNotNull($feature);

        $grantedSpell = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->first();

        $this->assertNotNull($grantedSpell, 'Spare the Dying cantrip should be linked');
        $this->assertEquals('Spare the Dying', $grantedSpell->spell->name);
    }

    #[Test]
    public function imports_multiple_cantrips_from_single_feature(): void
    {
        // The Celestial grants "sacred flame and light cantrips"
        Spell::factory()->create([
            'name' => 'Sacred Flame',
            'slug' => 'sacred-flame',
            'level' => 0,
        ]);
        Spell::factory()->create([
            'name' => 'Light',
            'slug' => 'light',
            'level' => 0,
        ]);

        $xml = $this->getCelestialWarlockXml();

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $celestial = CharacterClass::where('name', 'The Celestial')->first();
        $this->assertNotNull($celestial);

        $feature = ClassFeature::where('class_id', $celestial->id)
            ->where('feature_name', 'Bonus Cantrips (The Celestial)')
            ->first();
        $this->assertNotNull($feature);

        $grantedSpells = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->with('spell')
            ->get();

        $this->assertCount(2, $grantedSpells, 'Should have 2 cantrips linked');

        $spellNames = $grantedSpells->pluck('spell.name')->toArray();
        $this->assertContains('Sacred Flame', $spellNames);
        $this->assertContains('Light', $spellNames);
    }

    private function getTrickeryDomainXml(): string
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
        <name>Divine Domain: Trickery Domain</name>
        <text>Gods of trickery are mischief-makers.

Source: Player's Handbook (2014) p. 63</text>
      </feature>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Blessing of the Trickster (Trickery Domain)</name>
        <text>Starting when you choose this domain at 1st level, you gain the minor illusion cantrip. You can also use your action to touch a willing creature.

Source: Player's Handbook (2014) p. 63</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }

    private function getUndyingWarlockXml(): string
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
        <name>Otherworldly Patron: The Undying</name>
        <text>Death holds no sway over your patron.

Source: Sword Coast Adventurer's Guide p. 139</text>
      </feature>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Among the Dead (The Undying)</name>
        <text>Starting at 1st level, you learn the spare the dying cantrip, which counts as a warlock cantrip for you.

Source: Sword Coast Adventurer's Guide p. 139</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }

    private function getCelestialWarlockXml(): string
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
        <name>Otherworldly Patron: The Celestial</name>
        <text>Your patron is a powerful being of the Upper Planes.

Source: Xanathar's Guide to Everything p. 54</text>
      </feature>
    </autolevel>
    <autolevel level="1">
      <feature optional="YES">
        <name>Bonus Cantrips (The Celestial)</name>
        <text>At 1st level, you learn the sacred flame and light cantrips. They count as warlock cantrips for you.

Source: Xanathar's Guide to Everything p. 54</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;
    }

    // === Post-processing Tests (Issue #683) ===

    #[Test]
    public function postprocessing_links_bonus_cantrips_after_spells_imported(): void
    {
        // Step 1: Import class WITHOUT the spell existing (simulates real import order)
        $xml = $this->getLightDomainXml();
        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $lightDomain = CharacterClass::where('name', 'Light Domain')->first();
        $feature = ClassFeature::where('class_id', $lightDomain->id)
            ->where('feature_name', 'Bonus Cantrip (Light Domain)')
            ->first();

        // Verify no EntitySpell was created initially
        $initialCount = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->count();
        $this->assertEquals(0, $initialCount, 'No entity_spell should exist before spell is imported');

        // Step 2: Create the spell (simulates spells being imported later)
        $lightSpell = Spell::factory()->create([
            'name' => 'Light',
            'slug' => 'phb:light',
            'level' => 0,
        ]);

        // Step 3: Run postprocessing (same logic as ImportAllDataCommand::linkBonusCantrips)
        $this->artisan('import:link-bonus-cantrips');

        // Step 4: Verify the cantrip was linked
        $entitySpell = EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->where('spell_id', $lightSpell->id)
            ->first();

        $this->assertNotNull($entitySpell, 'Postprocessing should have linked the bonus cantrip');
        $this->assertTrue((bool) $entitySpell->is_cantrip, 'Should be marked as cantrip');
    }

    #[Test]
    public function postprocessing_skips_already_linked_cantrips(): void
    {
        // Create spell first so initial import links it
        $lightSpell = Spell::factory()->create([
            'name' => 'Light',
            'slug' => 'phb:light',
            'level' => 0,
        ]);

        $xml = $this->getLightDomainXml();
        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $lightDomain = CharacterClass::where('name', 'Light Domain')->first();
        $feature = ClassFeature::where('class_id', $lightDomain->id)
            ->where('feature_name', 'Bonus Cantrip (Light Domain)')
            ->first();

        // Should have 1 entity_spell from initial import
        $this->assertEquals(1, EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->count());

        // Run postprocessing
        $this->artisan('import:link-bonus-cantrips');

        // Should still have only 1 entity_spell (no duplicate)
        $this->assertEquals(1, EntitySpell::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->count(), 'Should not create duplicate entity_spell records');
    }
}

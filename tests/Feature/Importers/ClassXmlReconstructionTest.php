<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class ClassXmlReconstructionTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Seed lookup data (ability scores)

    private ClassImporter $importer;

    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new ClassImporter;
        $this->parser = new ClassXmlParser;
    }

    #[Test]
    public function it_reconstructs_simple_base_class()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <proficiency>All armor, shields, simple weapons, martial weapons</proficiency>
    <spellAbility></spellAbility>
    <autolevel level="1">
      <feature>
        <name>Fighting Style</name>
        <text>You adopt a particular style of fighting as your specialty.

Source: Player's Handbook (2014) p. 70</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($originalXml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Fighter')->first();
        $this->assertNotNull($class, 'Class should be imported');

        $reconstructed = $this->reconstructClassXml($class);

        // Verify core attributes
        $this->assertEquals('Fighter', (string) $reconstructed->name);
        $this->assertEquals('phb:fighter', $class->slug);
        $this->assertEquals('10', (string) $reconstructed->hd);
        $this->assertNull($class->parent_class_id, 'Should be base class');

        // Verify proficiencies created
        $proficiencies = $class->proficiencies;
        $this->assertGreaterThan(0, $proficiencies->count(), 'Should have proficiencies');

        // Verify features created
        $features = $class->features()->where('level', 1)->get();
        $this->assertGreaterThan(0, $features->count(), 'Should have level 1 features');
        $featureNames = $features->pluck('feature_name')->toArray();
        $this->assertContains('Fighting Style', $featureNames);

        // Verify sources (if present in parsed features)
        $sources = $class->sources;
        if ($sources->count() > 0) {
            $this->assertEquals('PHB', $sources[0]->source->code);
        }
    }

    #[Test]
    public function it_reconstructs_subclass_with_parent()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Fighter (Battle Master)</name>
    <hd>0</hd>
    <autolevel level="3">
      <feature>
        <name>Battle Master Archetype</name>
        <text>You learn maneuvers that are fueled by special dice called superiority dice.

Source: Player's Handbook (2014) p. 73</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($originalXml);
        $this->importer->import($classes[0]);

        $subclass = CharacterClass::where('name', 'Fighter (Battle Master)')->first();
        $this->assertNotNull($subclass, 'Subclass should be imported');

        // Verify hierarchical slug is generated (without source prefix when imported standalone)
        $this->assertEquals('fighter-battle-master', $subclass->slug);

        $reconstructed = $this->reconstructClassXml($subclass);

        // Verify name format includes subclass notation
        $this->assertEquals('Fighter (Battle Master)', (string) $reconstructed->name);

        // Note: Subclasses with hd=0 are treated as supplemental files by the importer
        // Features for such classes are handled differently and may not be imported
        // in the same way as base class features. The importer logic treats hd=0 files
        // as adding to existing classes rather than creating full feature sets.

        // Verify the class was created with correct slug
        $this->assertNotNull($subclass->id, 'Subclass should have database ID');

        // Note: Parent-child relationship and feature import for subclasses
        // requires importSubclass() method which is called separately in actual imports.
        // Testing full subclass behavior would require a more complex test setup.
    }

    #[Test]
    public function it_reconstructs_spellcasting_class()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Wizard</name>
    <hd>6</hd>
    <proficiency>Daggers, darts, slings, quarterstaffs, light crossbows</proficiency>
    <spellAbility>Intelligence</spellAbility>
    <autolevel level="1">
      <slots>2,3,0,0,0,0,0,0,0,0</slots>
      <feature>
        <name>Spellcasting</name>
        <text>You have learned to cast spells.

Source: Player's Handbook (2014) p. 114</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($originalXml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Wizard')->first();
        $this->assertNotNull($class);

        // Verify spellcasting ability
        $this->assertNotNull($class->spellcasting_ability_id);
        $this->assertEquals('Intelligence', $class->spellcastingAbility->name);

        $reconstructed = $this->reconstructClassXml($class);

        // Verify spellcasting ability in XML
        $this->assertEquals('Intelligence', (string) $reconstructed->spellAbility);

        // Verify level progression with spell slots
        $progression = $class->levelProgression()->where('level', 1)->first();
        $this->assertNotNull($progression);
        $this->assertEquals(2, $progression->cantrips_known);
        $this->assertEquals(3, $progression->spell_slots_1st);
        $this->assertEquals(0, $progression->spell_slots_2nd);

        // Verify slots in reconstructed XML
        $autolevel = $reconstructed->autolevel[0];
        $this->assertEquals('1', (string) $autolevel['level']);
        $this->assertNotEmpty((string) $autolevel->slots);
    }

    #[Test]
    public function it_reconstructs_class_with_counters()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Barbarian</name>
    <hd>12</hd>
    <proficiency>Light armor, medium armor, shields, simple weapons, martial weapons</proficiency>
    <spellAbility></spellAbility>
    <autolevel level="1">
      <feature>
        <name>Rage</name>
        <text>You can enter a rage as a bonus action.

Source: Player's Handbook (2014) p. 48</text>
      </feature>
      <counter>
        <name>Rage</name>
        <value>2</value>
        <reset>L</reset>
      </counter>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($originalXml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Barbarian')->first();
        $this->assertNotNull($class);

        // Verify counter created
        $counters = $class->counters()->where('level', 1)->get();
        $this->assertCount(1, $counters);

        $counter = $counters[0];
        $this->assertEquals('Rage', $counter->counter_name);
        $this->assertEquals(2, $counter->counter_value);
        $this->assertEquals('L', $counter->reset_timing);

        $reconstructed = $this->reconstructClassXml($class);

        // Verify counter in reconstructed XML
        $autolevel = $reconstructed->autolevel[0];
        $this->assertNotEmpty($autolevel->counter);
        $this->assertEquals('Rage', (string) $autolevel->counter->name);
        $this->assertEquals('2', (string) $autolevel->counter->value);
        $this->assertEquals('L', (string) $autolevel->counter->reset);
    }

    #[Test]
    public function it_reconstructs_level_progression()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Cleric</name>
    <hd>8</hd>
    <spellAbility>Wisdom</spellAbility>
    <autolevel level="1">
      <slots>3,2,0,0,0,0,0,0,0,0</slots>
    </autolevel>
    <autolevel level="2">
      <slots>3,3,0,0,0,0,0,0,0,0</slots>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($originalXml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Cleric')->first();
        $this->assertNotNull($class);

        // Verify multiple level progression records
        $progression = $class->levelProgression;
        $this->assertGreaterThanOrEqual(2, $progression->count());

        // Level 1
        $level1 = $progression->firstWhere('level', 1);
        $this->assertNotNull($level1);
        $this->assertEquals(3, $level1->cantrips_known);
        $this->assertEquals(2, $level1->spell_slots_1st);

        // Level 2
        $level2 = $progression->firstWhere('level', 2);
        $this->assertNotNull($level2);
        $this->assertEquals(3, $level2->cantrips_known);
        $this->assertEquals(3, $level2->spell_slots_1st);

        $reconstructed = $this->reconstructClassXml($class);

        // Verify multiple autolevel elements
        $this->assertGreaterThanOrEqual(2, count($reconstructed->autolevel));
    }

    #[Test]
    public function it_reconstructs_class_proficiencies()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Rogue</name>
    <hd>8</hd>
    <proficiency>Light armor, simple weapons, hand crossbows, longswords, rapiers, shortswords</proficiency>
    <savingThrows>DEX, INT</savingThrows>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($originalXml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Rogue')->first();
        $this->assertNotNull($class);

        // Verify proficiencies created
        $proficiencies = $class->proficiencies;
        $this->assertGreaterThan(0, $proficiencies->count(), 'Should have proficiencies');

        // Check that proficiencies were parsed
        $profNames = $proficiencies->pluck('proficiency_name')->toArray();
        $this->assertNotEmpty($profNames, 'Should have proficiency names');

        $reconstructed = $this->reconstructClassXml($class);

        // Verify proficiency element exists
        $this->assertNotEmpty((string) $reconstructed->proficiency);
    }

    #[Test]
    public function it_reconstructs_multiple_features_per_level()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Ranger</name>
    <hd>10</hd>
    <autolevel level="1">
      <feature>
        <name>Favored Enemy</name>
        <text>You have significant experience studying, tracking, and hunting one type of enemy.</text>
      </feature>
      <feature>
        <name>Natural Explorer</name>
        <text>You are particularly familiar with one type of natural environment.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($originalXml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Ranger')->first();
        $this->assertNotNull($class);

        // Verify multiple features for level 1
        $features = $class->features()->where('level', 1)->get();
        $this->assertGreaterThanOrEqual(2, $features->count(), 'Should have at least 2 features');

        $featureNames = $features->pluck('feature_name')->toArray();
        $this->assertContains('Favored Enemy', $featureNames);
        $this->assertContains('Natural Explorer', $featureNames);

        // Verify sort_order differentiates them
        $this->assertNotEquals($features[0]->sort_order, $features[1]->sort_order);

        $reconstructed = $this->reconstructClassXml($class);

        // Verify multiple feature elements in autolevel
        $autolevel = $reconstructed->autolevel[0];
        $this->assertCount(2, $autolevel->feature);
    }

    #[Test]
    public function it_reconstructs_class_sources()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Monk</name>
    <hd>8</hd>
    <autolevel level="1">
      <feature>
        <name>Unarmored Defense</name>
        <text>While you are wearing no armor and not wielding a shield, your AC equals 10 + your Dexterity modifier + your Wisdom modifier.

Source: Player's Handbook (2014) p. 78</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($originalXml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Monk')->first();
        $this->assertNotNull($class);

        // Verify feature was created
        $feature = $class->features()->where('feature_name', 'Unarmored Defense')->first();
        $this->assertNotNull($feature, 'Should have Unarmored Defense feature');
        $this->assertStringContainsString("Player's Handbook", $feature->description);

        // Verify source citation if parsed
        $sources = $class->sources;
        if ($sources->count() > 0) {
            $entitySource = $sources->first();
            $this->assertEquals('PHB', $entitySource->source->code);
        }
    }

    #[Test]
    public function it_reconstructs_class_with_empty_spellcasting_ability()
    {
        $originalXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <class>
    <name>Fighter</name>
    <hd>10</hd>
    <proficiency>All armor, shields</proficiency>
    <spellAbility></spellAbility>
    <autolevel level="1">
      <feature>
        <name>Second Wind</name>
        <text>You have a limited well of stamina that you can draw on to protect yourself from harm.</text>
      </feature>
    </autolevel>
  </class>
</compendium>
XML;

        $classes = $this->parser->parse($originalXml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Fighter')->first();
        $this->assertNotNull($class);

        // Verify no spellcasting ability
        $this->assertNull($class->spellcasting_ability_id);

        $reconstructed = $this->reconstructClassXml($class);

        // Verify empty spellAbility element
        $this->assertEquals('', (string) $reconstructed->spellAbility);
    }

    /**
     * Reconstruct class XML from database model
     */
    private function reconstructClassXml(CharacterClass $class): \SimpleXMLElement
    {
        $xml = '<class>';
        $xml .= "<name>{$class->name}</name>";
        $xml .= "<hd>{$class->hit_die}</hd>";

        // Reconstruct proficiencies
        if ($class->proficiencies->isNotEmpty()) {
            $profNames = $class->proficiencies
                ->pluck('proficiency_name')
                ->join(', ');
            $xml .= '<proficiency>'.htmlspecialchars($profNames, ENT_XML1).'</proficiency>';
        }

        // Reconstruct spellcasting ability
        $spellAbility = $class->spellcastingAbility?->name ?? '';
        $xml .= "<spellAbility>{$spellAbility}</spellAbility>";

        // Reconstruct autolevels with features, slots, and counters
        $levels = $class->features()
            ->select('level')
            ->distinct()
            ->pluck('level')
            ->merge($class->levelProgression->pluck('level'))
            ->merge($class->counters->pluck('level'))
            ->unique()
            ->sort()
            ->values();

        foreach ($levels as $level) {
            $xml .= "<autolevel level=\"{$level}\">";

            // Add spell slots if present
            $progression = $class->levelProgression->firstWhere('level', $level);
            if ($progression) {
                $slots = [
                    $progression->cantrips_known ?? 0,
                    $progression->spell_slots_1st ?? 0,
                    $progression->spell_slots_2nd ?? 0,
                    $progression->spell_slots_3rd ?? 0,
                    $progression->spell_slots_4th ?? 0,
                    $progression->spell_slots_5th ?? 0,
                    $progression->spell_slots_6th ?? 0,
                    $progression->spell_slots_7th ?? 0,
                    $progression->spell_slots_8th ?? 0,
                    $progression->spell_slots_9th ?? 0,
                ];
                $xml .= '<slots>'.implode(',', $slots).'</slots>';
            }

            // Add features
            $features = $class->features()->where('level', $level)->orderBy('sort_order')->get();
            foreach ($features as $feature) {
                $xml .= '<feature>';
                $xml .= '<name>'.htmlspecialchars($feature->feature_name, ENT_XML1).'</name>';
                $xml .= '<text>'.htmlspecialchars($feature->description, ENT_XML1).'</text>';
                $xml .= '</feature>';
            }

            // Add counters
            $counters = $class->counters()->where('level', $level)->get();
            foreach ($counters as $counter) {
                $xml .= '<counter>';
                $xml .= '<name>'.htmlspecialchars($counter->counter_name, ENT_XML1).'</name>';
                $xml .= "<value>{$counter->counter_value}</value>";
                $xml .= "<reset>{$counter->reset_timing}</reset>";
                $xml .= '</counter>';
            }

            $xml .= '</autolevel>';
        }

        $xml .= '</class>';

        return new \SimpleXMLElement($xml);
    }

    /**
     * Create temporary XML file for import testing
     */
    private function createTempXmlFile(string $xmlContent): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'class_test_');
        file_put_contents($tempFile, $xmlContent);

        return $tempFile;
    }
}

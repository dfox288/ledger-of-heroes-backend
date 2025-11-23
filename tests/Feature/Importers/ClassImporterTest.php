<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClassImporterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private ClassImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new ClassImporter;
    }

    #[Test]
    public function it_imports_base_fighter_class()
    {
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class
        $fighter = $this->importer->import($fighterData);

        // Assert base class created
        $this->assertInstanceOf(CharacterClass::class, $fighter);
        $this->assertEquals('Fighter', $fighter->name);
        $this->assertEquals('fighter', $fighter->slug);
        $this->assertEquals(10, $fighter->hit_die);
        $this->assertNull($fighter->parent_class_id);

        // Assert sources imported
        $this->assertGreaterThan(0, $fighter->sources()->count());
        $entitySource = $fighter->sources()->first();
        $this->assertEquals('PHB', $entitySource->source->code);

        // Assert proficiencies imported
        $this->assertGreaterThan(0, $fighter->proficiencies()->count());

        // Check for specific proficiencies
        $proficiencies = $fighter->proficiencies;
        $proficiencyNames = $proficiencies->pluck('proficiency_name')->toArray();

        // Armor
        $this->assertContains('Light Armor', $proficiencyNames);
        $this->assertContains('Medium Armor', $proficiencyNames);
        $this->assertContains('Heavy Armor', $proficiencyNames);
        $this->assertContains('Shields', $proficiencyNames);

        // Weapons
        $this->assertContains('Simple Weapons', $proficiencyNames);
        $this->assertContains('Martial Weapons', $proficiencyNames);

        // Saving throws
        $savingThrows = $proficiencies->where('proficiency_type', 'saving_throw');
        $this->assertCount(2, $savingThrows);
        $savingThrowNames = $savingThrows->pluck('proficiency_name')->toArray();
        $this->assertContains('Strength', $savingThrowNames);
        $this->assertContains('Constitution', $savingThrowNames);

        // Assert traits imported (flavor text)
        $this->assertGreaterThan(0, $fighter->traits()->count());
        $trait = $fighter->traits->first();
        $this->assertEquals('Fighter', $trait->name);
        $this->assertStringContainsString('unparalleled mastery with weapons', $trait->description);
    }

    #[Test]
    public function it_imports_fighter_features()
    {
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class
        $fighter = $this->importer->import($fighterData);

        // Assert features imported
        $this->assertGreaterThan(0, $fighter->features()->count());

        // Check for specific features
        $features = $fighter->features;
        $featureNames = $features->pluck('feature_name')->toArray();

        // Level 1 features
        $this->assertContains('Second Wind', $featureNames);
        $this->assertContains('Fighting Style', $featureNames);

        // Level 2 features
        $this->assertContains('Action Surge', $featureNames);

        // Level 3 features
        $this->assertContains('Martial Archetype', $featureNames);

        // Level 5 feature
        $this->assertContains('Extra Attack', $featureNames);

        // Check feature attributes
        $secondWind = $features->where('feature_name', 'Second Wind')->first();
        $this->assertNotNull($secondWind);
        $this->assertEquals(1, $secondWind->level);
        $this->assertFalse($secondWind->is_optional);
        $this->assertStringContainsString('bonus action', $secondWind->description);

        // Check optional feature
        $archery = $features->where('feature_name', 'Fighting Style: Archery')->first();
        $this->assertNotNull($archery);
        $this->assertTrue($archery->is_optional);
        $this->assertEquals(1, $archery->level);
    }

    #[Test]
    public function it_imports_eldritch_knight_spell_slots()
    {
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class
        $fighter = $this->importer->import($fighterData);

        // Assert spell progression imported (Eldritch Knight subclass)
        $this->assertGreaterThan(0, $fighter->levelProgression()->count());

        // Check level 3 progression (first spell slots for Eldritch Knight)
        $level3 = $fighter->levelProgression()->where('level', 3)->first();
        $this->assertNotNull($level3);
        $this->assertEquals(2, $level3->cantrips_known);
        $this->assertEquals(2, $level3->spell_slots_1st);
        $this->assertEquals(0, $level3->spell_slots_2nd);

        // Check level 7 progression (2nd level spells)
        $level7 = $fighter->levelProgression()->where('level', 7)->first();
        $this->assertNotNull($level7);
        $this->assertEquals(2, $level7->cantrips_known);
        $this->assertEquals(4, $level7->spell_slots_1st);
        $this->assertEquals(2, $level7->spell_slots_2nd);
        $this->assertEquals(0, $level7->spell_slots_3rd);

        // Check level 19 progression (highest level)
        $level19 = $fighter->levelProgression()->where('level', 19)->first();
        $this->assertNotNull($level19);
        $this->assertEquals(3, $level19->cantrips_known);
        $this->assertEquals(4, $level19->spell_slots_1st);
        $this->assertEquals(3, $level19->spell_slots_2nd);
        $this->assertEquals(3, $level19->spell_slots_3rd);
        $this->assertEquals(1, $level19->spell_slots_4th);
        $this->assertEquals(0, $level19->spell_slots_5th);
    }

    #[Test]
    public function it_imports_fighter_counters()
    {
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class
        $fighter = $this->importer->import($fighterData);

        // Assert counters imported
        $this->assertGreaterThan(0, $fighter->counters()->count());

        // Check for specific counters
        $counters = $fighter->counters;
        $counterNames = $counters->pluck('counter_name')->toArray();

        // Base Fighter counters
        $this->assertContains('Second Wind', $counterNames);
        $this->assertContains('Action Surge', $counterNames);
        $this->assertContains('Indomitable', $counterNames);

        // Check Second Wind counter
        $secondWind = $counters->where('counter_name', 'Second Wind')->where('level', 1)->first();
        $this->assertNotNull($secondWind);
        $this->assertEquals(1, $secondWind->counter_value);
        $this->assertEquals('S', $secondWind->reset_timing);

        // Check Action Surge counter progression
        $actionSurgeLevel2 = $counters->where('counter_name', 'Action Surge')->where('level', 2)->first();
        $this->assertNotNull($actionSurgeLevel2);
        $this->assertEquals(1, $actionSurgeLevel2->counter_value);
        $this->assertEquals('S', $actionSurgeLevel2->reset_timing);

        $actionSurgeLevel17 = $counters->where('counter_name', 'Action Surge')->where('level', 17)->first();
        $this->assertNotNull($actionSurgeLevel17);
        $this->assertEquals(2, $actionSurgeLevel17->counter_value);

        // Check Indomitable progression
        $indomitableLevel9 = $counters->where('counter_name', 'Indomitable')->where('level', 9)->first();
        $this->assertNotNull($indomitableLevel9);
        $this->assertEquals(1, $indomitableLevel9->counter_value);
        $this->assertEquals('L', $indomitableLevel9->reset_timing);

        $indomitableLevel17 = $counters->where('counter_name', 'Indomitable')->where('level', 17)->first();
        $this->assertNotNull($indomitableLevel17);
        $this->assertEquals(3, $indomitableLevel17->counter_value);
    }

    #[Test]
    public function it_imports_fighter_subclasses()
    {
        // Parse the Fighter XML
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the base Fighter class FIRST
        $fighter = $this->importer->import($fighterData);

        // Verify subclasses were detected in parser
        $this->assertArrayHasKey('subclasses', $fighterData);
        $this->assertGreaterThan(0, count($fighterData['subclasses']));

        // Check for expected subclasses
        $subclassNames = array_column($fighterData['subclasses'], 'name');
        $this->assertContains('Battle Master', $subclassNames);
        $this->assertContains('Champion', $subclassNames);
        $this->assertContains('Eldritch Knight', $subclassNames);

        // Import subclasses
        $battleMaster = null;
        $champion = null;
        $eldritchKnight = null;

        foreach ($fighterData['subclasses'] as $subclassData) {
            $subclass = $this->importer->importSubclass($fighter, $subclassData);

            if ($subclassData['name'] === 'Battle Master') {
                $battleMaster = $subclass;
            } elseif ($subclassData['name'] === 'Champion') {
                $champion = $subclass;
            } elseif ($subclassData['name'] === 'Eldritch Knight') {
                $eldritchKnight = $subclass;
            }
        }

        // Assert Battle Master created with correct attributes
        $this->assertNotNull($battleMaster);
        $this->assertEquals('Battle Master', $battleMaster->name);
        $this->assertEquals('fighter-battle-master', $battleMaster->slug);
        $this->assertEquals($fighter->id, $battleMaster->parent_class_id);

        // Assert Battle Master has its own features
        $this->assertGreaterThan(0, $battleMaster->features()->count());
        $bmFeatures = $battleMaster->features->pluck('feature_name')->toArray();
        $this->assertContains('Combat Superiority (Battle Master)', $bmFeatures);
        $this->assertContains('Student of War (Battle Master)', $bmFeatures);

        // Assert Battle Master has subclass-specific counters
        $this->assertGreaterThan(0, $battleMaster->counters()->count());
        $bmCounters = $battleMaster->counters->pluck('counter_name')->toArray();
        $this->assertContains('Superiority Die', $bmCounters);

        // Check Superiority Die progression
        $superiorityDieLevel3 = $battleMaster->counters()->where('counter_name', 'Superiority Die')->where('level', 3)->first();
        $this->assertNotNull($superiorityDieLevel3);
        $this->assertEquals(4, $superiorityDieLevel3->counter_value);

        $superiorityDieLevel15 = $battleMaster->counters()->where('counter_name', 'Superiority Die')->where('level', 15)->first();
        $this->assertNotNull($superiorityDieLevel15);
        $this->assertEquals(6, $superiorityDieLevel15->counter_value);

        // Assert Champion created
        $this->assertNotNull($champion);
        $this->assertEquals('fighter-champion', $champion->slug);
        $this->assertEquals($fighter->id, $champion->parent_class_id);

        // Assert Eldritch Knight created
        $this->assertNotNull($eldritchKnight);
        $this->assertEquals('fighter-eldritch-knight', $eldritchKnight->slug);
        $this->assertEquals($fighter->id, $eldritchKnight->parent_class_id);

        // Verify we can query all subclasses from parent
        $this->assertEquals(3, $fighter->subclasses()->count());
    }

    #[Test]
    public function it_imports_spells_known_into_spell_progression()
    {
        // Parse the Bard XML (known-spells caster)
        $xmlPath = base_path('import-files/class-bard-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $bardData = $classes[0];

        // Import the Bard class
        $bard = $this->importer->import($bardData);

        // Assert spell progression imported
        $this->assertGreaterThan(0, $bard->levelProgression()->count());

        // Check level 1 progression - should have spells_known
        $level1 = $bard->levelProgression()->where('level', 1)->first();
        $this->assertNotNull($level1);
        $this->assertEquals(4, $level1->spells_known, 'Level 1 should have 4 spells known');
        $this->assertEquals(2, $level1->cantrips_known);
        $this->assertEquals(2, $level1->spell_slots_1st);

        // Check level 5 progression
        $level5 = $bard->levelProgression()->where('level', 5)->first();
        $this->assertNotNull($level5);
        $this->assertEquals(8, $level5->spells_known, 'Level 5 should have 8 spells known');

        // Check level 10 progression
        $level10 = $bard->levelProgression()->where('level', 10)->first();
        $this->assertNotNull($level10);
        $this->assertEquals(14, $level10->spells_known, 'Level 10 should have 14 spells known');

        // Verify NO "Spells Known" counter exists (it should be in progression, not counters)
        $spellsKnownCounter = $bard->counters()
            ->where('counter_name', 'Spells Known')
            ->count();
        $this->assertEquals(0, $spellsKnownCounter, 'Should not have Spells Known counter - it belongs in spell_progression');
    }

    #[Test]
    public function it_imports_skill_proficiencies_as_choices_when_num_skills_present()
    {
        // Parse the Fighter XML (has numSkills)
        $xmlPath = base_path('import-files/class-fighter-phb.xml');
        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $fighterData = $classes[0];

        // Import the Fighter class
        $fighter = $this->importer->import($fighterData);

        // Get all proficiencies
        $proficiencies = $fighter->proficiencies;
        $this->assertGreaterThan(0, $proficiencies->count());

        // Get skill proficiencies
        $skills = $proficiencies->where('proficiency_type', 'skill');
        $this->assertGreaterThan(0, $skills->count(), 'Should have skill proficiencies');

        // All skills should be marked as choices with quantity=2
        foreach ($skills as $skill) {
            $this->assertTrue((bool) $skill->is_choice, "Skill {$skill->proficiency_name} should be marked as choice");
            $this->assertEquals(2, $skill->quantity, "Skill {$skill->proficiency_name} should have quantity=2");
        }

        // Saving throws should NOT be choices
        $savingThrows = $proficiencies->where('proficiency_type', 'saving_throw');
        $this->assertCount(2, $savingThrows);

        foreach ($savingThrows as $save) {
            $this->assertFalse((bool) $save->is_choice, "Saving throw {$save->proficiency_name} should not be choice");
        }

        // Armor proficiencies should NOT be choices
        $armor = $proficiencies->where('proficiency_type', 'armor');
        $this->assertGreaterThan(0, $armor->count());

        foreach ($armor as $armorProf) {
            $this->assertFalse((bool) $armorProf->is_choice, "Armor {$armorProf->proficiency_name} should not be choice");
        }

        // Weapon proficiencies should NOT be choices
        $weapons = $proficiencies->where('proficiency_type', 'weapon');
        $this->assertGreaterThan(0, $weapons->count());

        foreach ($weapons as $weapon) {
            $this->assertFalse((bool) $weapon->is_choice, "Weapon {$weapon->proficiency_name} should not be choice");
        }
    }

    #[Test]
    public function it_imports_starting_equipment_for_class()
    {
        // Load Barbarian XML (has wealth tag and starting equipment)
        $xmlPath = base_path('import-files/class-barbarian-phb.xml');
        $this->assertFileExists($xmlPath);

        $xmlContent = file_get_contents($xmlPath);
        $parser = new ClassXmlParser;
        $classes = $parser->parse($xmlContent);
        $barbarianData = $classes[0];

        // Import the Barbarian class
        $barbarian = $this->importer->import($barbarianData);

        // Assert: equipment was imported
        $this->assertGreaterThan(0, $barbarian->equipment()->count(), 'Barbarian should have equipment');

        // Assert: verify choice structure
        $choices = $barbarian->equipment()->where('is_choice', true)->get();
        $this->assertGreaterThan(0, $choices->count(), 'Should have at least one equipment choice');

        // Assert: all equipment has description
        foreach ($barbarian->equipment as $item) {
            $this->assertNotEmpty($item->description, 'Equipment item should have description');
            $this->assertIsInt($item->quantity, 'Equipment item should have quantity');
            $this->assertGreaterThan(0, $item->quantity, 'Equipment quantity should be at least 1');
        }

        // Assert: non-choice items exist
        $nonChoices = $barbarian->equipment()->where('is_choice', false)->get();
        $this->assertGreaterThan(0, $nonChoices->count(), 'Should have non-choice equipment items');
    }
}

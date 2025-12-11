<?php

namespace Tests\Feature\Importers;

use App\Models\AbilityScore;
use App\Models\Race;
use App\Services\Importers\RaceImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class RaceImporterTest extends TestCase
{
    use RefreshDatabase;

    private RaceImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new RaceImporter;
    }

    #[Test]
    public function it_imports_a_race()
    {
        $raceData = [
            'name' => 'Dragonborn',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Description',
                    'category' => 'description',
                    'description' => 'Born of dragons, as their name proclaims.',
                    'sort_order' => 0,
                ],
            ],
            'sources' => [
                ['code' => 'PHB', 'pages' => '32'],
            ],
        ];

        $race = $this->importer->import($raceData);

        $this->assertInstanceOf(Race::class, $race);
        $this->assertEquals('Dragonborn', $race->name);
        $this->assertEquals(30, $race->speed);

        // Check traits
        $this->assertCount(1, $race->traits);
        $trait = $race->traits->first();
        $this->assertStringContainsString('Born of dragons', $trait->description);

        // Check relationships
        $this->assertNotNull($race->size);
        $this->assertEquals('M', $race->size->code);
        $this->assertEquals('Medium', $race->size->name);

        // Check sources via entity_sources junction table
        $this->assertEquals(1, $race->sources()->count());
        $entitySource = $race->sources()->first();
        $this->assertEquals('PHB', $entitySource->source->code);
        $this->assertEquals('32', $entitySource->pages);
    }

    #[Test]
    public function it_updates_existing_race_on_reimport()
    {
        $raceData = [
            'name' => 'Dragonborn',
            'size_code' => 'M',
            'speed' => 30,
            'traits' => [
                [
                    'name' => 'Description',
                    'category' => 'description',
                    'description' => 'Original description',
                    'sort_order' => 0,
                ],
            ],
            'sources' => [
                ['code' => 'PHB', 'pages' => '32'],
            ],
        ];

        $this->importer->import($raceData);
        $this->assertEquals(1, Race::count());

        // Re-import with updated traits
        $raceData['traits'][0]['description'] = 'Updated description';
        $race = $this->importer->import($raceData);

        $this->assertEquals(1, Race::count());
        $trait = $race->fresh()->traits->first();
        $this->assertStringContainsString('Updated description', $trait->description);
    }

    #[Test]
    public function it_imports_from_xml_file()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Str +2, Cha +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.

Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <ability>Con +2, Wis +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Bold and hardy.

Source: Player's Handbook (2014) p. 19</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $count = $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Should import 3 races: Dragonborn + base Dwarf + Hill subrace
        $this->assertEquals(3, $count);
        $this->assertEquals(3, Race::count());
        $this->assertDatabaseHas('races', ['name' => 'Dragonborn']);
        $this->assertDatabaseHas('races', ['name' => 'Dwarf']);
        $this->assertDatabaseHas('races', ['name' => 'Hill']);
    }

    #[Test]
    public function it_creates_base_race_and_subrace()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>As a hill dwarf, you have keen senses.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $count = $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Should create 2 races: base "Dwarf" + subrace "Hill"
        $this->assertEquals(2, $count);

        // Check base race exists
        $baseRace = Race::where('name', 'Dwarf')
            ->whereNull('parent_race_id')
            ->first();
        $this->assertNotNull($baseRace);

        // Check subrace exists and is linked to base
        $subrace = Race::where('name', 'Hill')
            ->whereNotNull('parent_race_id')
            ->first();
        $this->assertNotNull($subrace);
        $this->assertEquals($baseRace->id, $subrace->parent_race_id);
    }

    #[Test]
    public function it_creates_only_base_race_when_no_subrace()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $count = $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $this->assertEquals(1, $count);

        $race = Race::where('name', 'Dragonborn')->first();
        $this->assertNotNull($race);
        $this->assertNull($race->parent_race_id);
    }

    #[Test]
    public function it_reuses_existing_base_race_for_multiple_subraces()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>Hill dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
  <race>
    <name>Dwarf, Mountain</name>
    <size>M</size>
    <speed>25</speed>
    <trait category="description">
      <name>Description</name>
      <text>Mountain dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $count = $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Should create 3 races: 1 base "Dwarf" + 2 subraces
        $this->assertEquals(3, $count);

        $baseRaces = Race::where('name', 'Dwarf')
            ->whereNull('parent_race_id')
            ->get();
        $this->assertCount(1, $baseRaces, 'Should only create one base Dwarf race');

        $subraces = Race::whereNotNull('parent_race_id')->get();
        $this->assertCount(2, $subraces);
    }

    #[Test]
    public function it_imports_skill_proficiencies()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <proficiency>Perception</proficiency>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Proficiencies are now on the base race (Elf), not the subrace (High)
        $baseRace = Race::where('name', 'Elf')
            ->whereNull('parent_race_id')
            ->first();
        $this->assertNotNull($baseRace);

        $proficiencies = $baseRace->proficiencies;
        $this->assertCount(1, $proficiencies);

        $proficiency = $proficiencies->first();
        $this->assertEquals('skill', $proficiency->proficiency_type);
        $this->assertNotNull($proficiency->skill_id);
        $this->assertEquals('Perception', $proficiency->skill->name);

        // Subrace should have no proficiencies (inherited from parent)
        $subrace = Race::where('name', 'High')
            ->whereNotNull('parent_race_id')
            ->first();
        $this->assertNotNull($subrace);
        $this->assertCount(0, $subrace->proficiencies);
    }

    #[Test]
    public function it_imports_weapon_proficiencies_as_text()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <weapons>Longsword, Shortsword</weapons>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Weapon proficiencies are now on the base race (Elf), not the subrace (High)
        $baseRace = Race::where('name', 'Elf')
            ->whereNull('parent_race_id')
            ->first();
        $proficiencies = $baseRace->proficiencies;

        $this->assertCount(2, $proficiencies);

        $weaponProfs = $proficiencies->where('proficiency_type', 'weapon');
        $this->assertCount(2, $weaponProfs);

        // Should be stored as proficiency_name (items not imported yet)
        $names = $weaponProfs->pluck('proficiency_name')->toArray();
        $this->assertContains('Longsword', $names);
        $this->assertContains('Shortsword', $names);
    }

    #[Test]
    public function it_clears_and_recreates_proficiencies_on_reimport()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <proficiency>Perception</proficiency>
    <weapons>Longsword</weapons>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        // First import - proficiencies are on the base race (Elf)
        $this->importer->importFromFile($tmpFile);
        $baseRace = Race::where('name', 'Elf')
            ->whereNull('parent_race_id')
            ->first();
        $this->assertCount(2, $baseRace->proficiencies);

        // Second import (should clear old proficiencies)
        $this->importer->importFromFile($tmpFile);
        $baseRace->refresh();
        $this->assertCount(2, $baseRace->proficiencies);

        unlink($tmpFile);
    }

    #[Test]
    public function it_imports_race_traits()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
    <trait category="species">
      <name>Breath Weapon</name>
      <text>You can exhale destructive energy.</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Dragonborn')->first();
        $this->assertNotNull($race);

        $traits = $race->traits;
        $this->assertCount(2, $traits);

        $descriptionTrait = $traits->where('name', 'Description')->first();
        $this->assertEquals('description', $descriptionTrait->category);
        $this->assertStringContainsString('Born of dragons', $descriptionTrait->description);

        $speciesTrait = $traits->where('name', 'Breath Weapon')->first();
        $this->assertEquals('species', $speciesTrait->category);
    }

    #[Test]
    public function it_imports_ability_score_bonuses_as_modifiers()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Str +2, Cha +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Dragonborn')->first();
        $modifiers = $race->modifiers;

        $this->assertCount(2, $modifiers);

        $strModifier = $modifiers->where('ability_score_id', AbilityScore::where('code', 'STR')->first()->id)->first();
        $this->assertNotNull($strModifier);
        $this->assertEquals('ability_score', $strModifier->modifier_category);
        $this->assertEquals(2, $strModifier->value);

        $chaModifier = $modifiers->where('ability_score_id', AbilityScore::where('code', 'CHA')->first()->id)->first();
        $this->assertNotNull($chaModifier);
        $this->assertEquals(1, $chaModifier->value);
    }

    #[Test]
    public function it_imports_data_tables_from_trait_rolls_and_links_traits()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Size</name>
      <text>Your size is Medium.
Source: Player's Handbook (2014) p. 32</text>
      <roll description="Size Modifier">2d8</roll>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Dragonborn')->first();

        // Data tables are now accessed through traits
        $sizeTrait = $race->traits->where('name', 'Size')->first();
        $this->assertNotNull($sizeTrait);

        $dataTables = $sizeTrait->dataTables;
        $this->assertCount(1, $dataTables);

        $sizeTable = $dataTables->first();
        $this->assertEquals('Size Modifier', $sizeTable->table_name);
        $this->assertEquals('2d8', $sizeTable->dice_type);

        // Verify the data table references the trait
        $this->assertEquals(\App\Models\CharacterTrait::class, $sizeTable->reference_type);
        $this->assertEquals($sizeTrait->id, $sizeTable->reference_id);

        // IMPORTANT: Verify bidirectional link - trait is linked to data table
        $this->assertNotNull($sizeTrait->entity_data_table_id);
        $this->assertEquals($sizeTable->id, $sizeTrait->entity_data_table_id);
    }

    #[Test]
    public function it_imports_multiple_sources()
    {
        $raceData = [
            'name' => 'Test Race',
            'size_code' => 'M',
            'speed' => 30,
            'ability_bonuses' => [],
            'traits' => [],
            'proficiencies' => [],
            'sources' => [
                ['code' => 'PHB', 'pages' => '35'],
                ['code' => 'ERLW', 'pages' => '67'],
            ],
        ];

        $race = $this->importer->import($raceData);

        $fresh = $race->fresh();
        $this->assertCount(2, $fresh->sources);
        $sourceCodes = $fresh->sources->pluck('source.code')->toArray();
        $this->assertContains('PHB', $sourceCodes);
        $this->assertContains('ERLW', $sourceCodes);
    }

    #[Test]
    public function it_imports_ability_choices()
    {
        $raceData = [
            'name' => 'Test Race',
            'size_code' => 'M',
            'speed' => 30,
            'ability_bonuses' => [
                ['ability' => 'Con', 'value' => '+2'],
            ],
            'ability_choices' => [
                [
                    'is_choice' => true,
                    'choice_count' => 1,
                    'value' => 1,
                    'choice_constraint' => 'any',
                ],
            ],
            'traits' => [],
            'proficiencies' => [],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        $choiceModifier = $race->fresh()->modifiers()->where('is_choice', true)->first();
        $this->assertNotNull($choiceModifier);
        $this->assertTrue($choiceModifier->is_choice);
        $this->assertEquals(1, $choiceModifier->choice_count);
        $this->assertEquals('any', $choiceModifier->choice_constraint);
        $this->assertNull($choiceModifier->ability_score_id);
    }

    #[Test]
    public function it_imports_conditions()
    {
        // Create frightened condition if it doesn't exist
        $condition = \App\Models\Condition::firstOrCreate(
            ['slug' => 'frightened'],
            ['name' => 'Frightened', 'description' => 'Test condition']
        );

        $raceData = [
            'name' => 'Test Race',
            'size_code' => 'M',
            'speed' => 30,
            'ability_bonuses' => [],
            'traits' => [],
            'proficiencies' => [],
            'sources' => [],
            'conditions' => [
                [
                    'condition_name' => 'frightened',
                    'effect_type' => 'advantage',
                ],
            ],
        ];

        $race = $this->importer->import($raceData);

        $this->assertCount(1, $race->fresh()->conditions);
        $raceCondition = $race->conditions->first();
        $this->assertEquals('advantage', $raceCondition->effect_type);
        $this->assertEquals($condition->id, $raceCondition->condition_id);
    }

    #[Test]
    public function it_imports_racial_spells()
    {
        // Create a test spell
        $spell = \App\Models\Spell::factory()->create([
            'name' => 'Thaumaturgy',
            'slug' => 'thaumaturgy',
            'level' => 0,
        ]);

        $charisma = \App\Models\AbilityScore::where('code', 'CHA')->first();

        $raceData = [
            'name' => 'Test Race',
            'size_code' => 'M',
            'speed' => 30,
            'ability_bonuses' => [],
            'traits' => [],
            'proficiencies' => [],
            'sources' => [],
            'spellcasting' => [
                'ability' => 'Charisma',
                'spells' => [
                    [
                        'spell_name' => 'Thaumaturgy',
                        'is_cantrip' => true,
                        'level_requirement' => null,
                        'usage_limit' => null,
                    ],
                ],
            ],
        ];

        $race = $this->importer->import($raceData);

        $this->assertCount(1, $race->fresh()->spells);
        $raceSpell = $race->spells->first();
        $this->assertEquals($spell->id, $raceSpell->id);
        $this->assertEquals($charisma->id, $raceSpell->pivot->ability_score_id);
        $this->assertTrue((bool) $raceSpell->pivot->is_cantrip);
    }

    #[Test]
    public function it_imports_cantrip_choice_from_class_spell_list()
    {
        // Create wizard class for lookup
        $wizard = \App\Models\CharacterClass::factory()->create([
            'slug' => 'wizard',
            'name' => 'Wizard',
        ]);

        $raceData = [
            'name' => 'Test Elf',
            'size_code' => 'M',
            'speed' => 30,
            'ability_bonuses' => [],
            'traits' => [],
            'proficiencies' => [],
            'sources' => [],
            'spellcasting' => [
                'ability' => 'Intelligence',
                'spells' => [
                    [
                        'is_choice' => true,
                        'choice_count' => 1,
                        'class_name' => 'wizard',
                        'max_level' => 0, // cantrip
                        'is_cantrip' => true,
                        'is_ritual_only' => false,
                    ],
                ],
            ],
        ];

        $race = $this->importer->import($raceData);

        // Check that a spell choice record was created
        $spellChoices = \Illuminate\Support\Facades\DB::table('entity_spells')
            ->where('reference_type', \App\Models\Race::class)
            ->where('reference_id', $race->id)
            ->where('is_choice', true)
            ->get();

        $this->assertCount(1, $spellChoices);
        $choice = $spellChoices->first();
        $this->assertNull($choice->spell_id); // No specific spell - it's a choice
        $this->assertTrue((bool) $choice->is_choice);
        $this->assertTrue((bool) $choice->is_cantrip); // max_level=0 means cantrip
        $this->assertEquals(1, $choice->choice_count);
        $this->assertEquals(0, $choice->max_level); // cantrip
        $this->assertEquals($wizard->id, $choice->class_id);
        $this->assertEquals('racial_cantrip', $choice->choice_group);
    }

    #[Test]
    public function it_imports_darkvision_from_traits()
    {
        // Create required sense types
        \App\Models\Sense::firstOrCreate(['slug' => 'darkvision'], ['name' => 'Darkvision']);

        $raceData = [
            'name' => 'Dwarf, Hill',
            'size_code' => 'M',
            'speed' => 25,
            'ability_bonuses' => [],
            'traits' => [
                [
                    'name' => 'Darkvision',
                    'category' => 'species',
                    'description' => 'Accustomed to life underground, you have superior vision in dark and dim conditions. You can see in dim light within 60 feet of you as if it were bright light.',
                    'sort_order' => 0,
                ],
            ],
            'proficiencies' => [],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        $senses = $race->fresh()->senses()->with('sense')->get();
        $this->assertCount(1, $senses);

        $darkvision = $senses->first();
        $this->assertEquals('darkvision', $darkvision->sense->slug);
        $this->assertEquals(60, $darkvision->range_feet);
    }

    #[Test]
    public function it_imports_superior_darkvision_with_120_ft_range()
    {
        // Create required sense types
        \App\Models\Sense::firstOrCreate(['slug' => 'darkvision'], ['name' => 'Darkvision']);

        $raceData = [
            'name' => 'Elf, Drow',
            'size_code' => 'M',
            'speed' => 30,
            'ability_bonuses' => [],
            'traits' => [
                [
                    'name' => 'Superior Darkvision',
                    'category' => 'subspecies',
                    'description' => 'Accustomed to the depths of the Underdark, you have superior vision. You can see in dim light within 120 feet of you as if it were bright light.',
                    'sort_order' => 0,
                ],
            ],
            'proficiencies' => [],
            'sources' => [],
        ];

        $race = $this->importer->import($raceData);

        $senses = $race->fresh()->senses()->with('sense')->get();
        $this->assertCount(1, $senses);

        $darkvision = $senses->first();
        $this->assertEquals('darkvision', $darkvision->sense->slug);
        $this->assertEquals(120, $darkvision->range_feet);
    }

    #[Test]
    public function it_populates_base_race_with_species_traits_from_first_subrace()
    {
        // Create required sense types
        \App\Models\Sense::firstOrCreate(['slug' => 'darkvision'], ['name' => 'Darkvision']);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <ability>Con +2, Wis +1</ability>
    <resist>poison</resist>
    <weapons>Battleaxe, Handaxe</weapons>
    <trait category="description">
      <name>Description</name>
      <text>As a hill dwarf, you have keen senses.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
    <trait category="species">
      <name>Darkvision</name>
      <text>You can see in dim light within 60 feet of you.</text>
    </trait>
    <trait category="species">
      <name>Dwarven Resilience</name>
      <text>You have advantage on saving throws against poison.</text>
    </trait>
    <trait category="subspecies">
      <name>Dwarven Toughness</name>
      <text>Your hit point maximum increases by 1.</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Base "Dwarf" race should have species traits
        $baseRace = Race::where('name', 'Dwarf')
            ->whereNull('parent_race_id')
            ->first();

        $this->assertNotNull($baseRace, 'Base Dwarf race should exist');

        // Base race should have species traits (Darkvision, Dwarven Resilience)
        $baseTraits = $baseRace->traits;
        $this->assertGreaterThanOrEqual(2, $baseTraits->count(), 'Base race should have species traits');

        $baseTraitNames = $baseTraits->pluck('name')->toArray();
        $this->assertContains('Darkvision', $baseTraitNames);
        $this->assertContains('Dwarven Resilience', $baseTraitNames);

        // Base race should NOT have subspecies traits
        $this->assertNotContains('Dwarven Toughness', $baseTraitNames);

        // Subrace should have subspecies traits only
        $subrace = Race::where('name', 'Hill')
            ->whereNotNull('parent_race_id')
            ->first();

        $this->assertNotNull($subrace, 'Hill subrace should exist');

        $subraceTraits = $subrace->traits;
        $subraceTraitNames = $subraceTraits->pluck('name')->toArray();

        // Subrace should have subspecies trait
        $this->assertContains('Dwarven Toughness', $subraceTraitNames);

        // Subrace should NOT duplicate species traits (they come from parent)
        $this->assertNotContains('Darkvision', $subraceTraitNames);
        $this->assertNotContains('Dwarven Resilience', $subraceTraitNames);
    }

    #[Test]
    public function it_populates_base_race_with_modifiers_from_first_subrace()
    {
        // Verify poison damage type exists (seeded by LookupSeeder)
        $poisonType = \App\Models\DamageType::where('name', 'Poison')->first();
        $this->assertNotNull($poisonType, 'Poison damage type should be seeded');

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <ability>Con +2, Wis +1</ability>
    <resist>poison</resist>
    <trait category="description">
      <name>Description</name>
      <text>Hill dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Base "Dwarf" race should have shared modifiers (Con +2)
        $baseRace = Race::where('name', 'Dwarf')
            ->whereNull('parent_race_id')
            ->first();

        $this->assertNotNull($baseRace);

        // Base race should have Con +2 modifier
        $conAbility = AbilityScore::where('code', 'CON')->first();
        $baseConModifier = $baseRace->modifiers()
            ->where('ability_score_id', $conAbility->id)
            ->first();

        $this->assertNotNull($baseConModifier, 'Base race should have Con modifier');
        $this->assertEquals(2, $baseConModifier->value);

        // Base race should have poison resistance
        $poisonResistance = $baseRace->modifiers()
            ->where('modifier_category', 'damage_resistance')
            ->first();

        $this->assertNotNull($poisonResistance, 'Base race should have poison resistance');

        // Subrace should have only subrace-specific modifiers (Wis +1)
        $subrace = Race::where('name', 'Hill')
            ->whereNotNull('parent_race_id')
            ->first();

        $wisAbility = AbilityScore::where('code', 'WIS')->first();
        $subraceWisModifier = $subrace->modifiers()
            ->where('ability_score_id', $wisAbility->id)
            ->first();

        $this->assertNotNull($subraceWisModifier, 'Subrace should have Wis modifier');
        $this->assertEquals(1, $subraceWisModifier->value);

        // Subrace should NOT duplicate base race modifiers
        $subraceConModifier = $subrace->modifiers()
            ->where('ability_score_id', $conAbility->id)
            ->first();

        $this->assertNull($subraceConModifier, 'Subrace should not have Con modifier (inherited from parent)');
    }

    #[Test]
    public function it_populates_base_race_with_proficiencies_from_first_subrace()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Elf, High</name>
    <size>M</size>
    <speed>30</speed>
    <proficiency>Perception</proficiency>
    <weapons>Longsword, Shortsword, Shortbow, Longbow</weapons>
    <trait category="description">
      <name>Description</name>
      <text>High elf.
Source: Player's Handbook (2014) p. 23</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Base "Elf" race should have shared proficiencies
        $baseRace = Race::where('name', 'Elf')
            ->whereNull('parent_race_id')
            ->first();

        $this->assertNotNull($baseRace);

        // Base race should have Perception skill proficiency
        $perceptionProf = $baseRace->proficiencies()
            ->where('proficiency_type', 'skill')
            ->whereHas('skill', fn ($q) => $q->where('name', 'Perception'))
            ->first();

        $this->assertNotNull($perceptionProf, 'Base race should have Perception proficiency');

        // Base race should have weapon proficiencies (Elf Weapon Training)
        $weaponProfs = $baseRace->proficiencies()
            ->where('proficiency_type', 'weapon')
            ->get();

        $this->assertGreaterThanOrEqual(4, $weaponProfs->count(), 'Base race should have weapon proficiencies');
    }

    #[Test]
    public function it_extracts_fly_speed_from_flight_trait()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Aarakocra</name>
        <size>M</size>
        <speed>25</speed>
        <trait>
            <name>Flight</name>
            <text>You have a flying speed of 50 feet. To use this speed, you can't be wearing medium or heavy armor.</text>
        </trait>
    </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Aarakocra')->first();

        $this->assertNotNull($race);
        $this->assertEquals(50, $race->fly_speed);
    }

    #[Test]
    public function it_extracts_swim_speed_from_swim_speed_trait()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Triton</name>
        <size>M</size>
        <speed>30</speed>
        <trait>
            <name>Swim Speed</name>
            <text>You have a swimming speed of 30 feet.</text>
        </trait>
    </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Triton')->first();

        $this->assertNotNull($race);
        $this->assertEquals(30, $race->swim_speed);
    }

    #[Test]
    public function it_extracts_climb_speed_from_cats_claws_trait()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Tabaxi</name>
        <size>M</size>
        <speed>30</speed>
        <trait>
            <name>Cat's Claws</name>
            <text>Because of your claws, you have a climbing speed of 20 feet. In addition, your claws are natural weapons.</text>
        </trait>
    </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Tabaxi')->first();

        $this->assertNotNull($race);
        $this->assertEquals(20, $race->climb_speed);
    }

    #[Test]
    public function it_extracts_fly_speed_equal_to_walking_speed()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Fairy</name>
        <size>S</size>
        <speed>30</speed>
        <trait>
            <name>Flight</name>
            <text>Because of your wings, you have a flying speed equal to your walking speed. You can't use this flying speed if you're wearing medium or heavy armor.</text>
        </trait>
    </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Fairy')->first();

        $this->assertNotNull($race);
        $this->assertEquals(30, $race->fly_speed); // Should equal walking speed
    }

    #[Test]
    public function it_extracts_swim_speed_equal_to_walking_speed()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Grung</name>
        <size>S</size>
        <speed>25</speed>
        <trait>
            <name>Speed</name>
            <text>Your walking speed is 25 feet, and you have a swimming speed equal to your walking speed.</text>
        </trait>
    </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Grung')->first();

        $this->assertNotNull($race);
        $this->assertEquals(25, $race->swim_speed); // Should equal walking speed
    }

    #[Test]
    public function it_extracts_climb_speed_equal_to_walking_speed()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Dhampir</name>
        <size>M</size>
        <speed>35</speed>
        <trait>
            <name>Spider Climb</name>
            <text>You have a climbing speed equal to your walking speed. In addition, at 3rd level, you can move up, down, and across vertical surfaces.</text>
        </trait>
    </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Dhampir')->first();

        $this->assertNotNull($race);
        $this->assertEquals(35, $race->climb_speed); // Should equal walking speed
    }

    #[Test]
    public function it_extracts_swim_speed_from_non_swim_named_trait()
    {
        // Aquatic Elf Ancestry has swim speed in a "Variant: Aquatic" trait
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium>
    <race>
        <name>Half-Elf (Aquatic Elf Ancestry)</name>
        <size>M</size>
        <speed>30</speed>
        <trait>
            <name>Variant: Aquatic</name>
            <text>You have a swimming speed of 30 feet.</text>
        </trait>
    </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Half-Elf (Aquatic Elf Ancestry)')->first();

        $this->assertNotNull($race);
        $this->assertEquals(30, $race->swim_speed);
    }

    #[Test]
    public function it_expands_tiefling_variants_into_separate_subraces()
    {
        // First create the base Tiefling race (from PHB)
        $baseTiefling = Race::factory()->create([
            'name' => 'Tiefling',
            'slug' => 'tiefling',
            'speed' => 30,
        ]);

        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Tiefling, Variants</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Dex +2, Int +1</ability>
    <resist>fire</resist>
    <spellAbility>Charisma</spellAbility>
    <trait category="description">
      <name>Description</name>
      <text>Since not all tieflings are of the blood of Asmodeus, some have traits that differ.

Source:	Player's Handbook (2014) p. 43,
		Sword Coast Adventurer's Guide p. 118</text>
    </trait>
    <trait>
      <name>Age</name>
      <text>Tieflings mature at the same rate as humans.</text>
    </trait>
    <trait category="species">
      <name>Darkvision</name>
      <text>You can see in dim light within 60 feet of you.</text>
    </trait>
    <trait category="species">
      <name>Hellish Resistance</name>
      <text>You have resistance to fire damage.</text>
    </trait>
    <trait category="species">
      <name>Infernal Legacy</name>
      <text>You know the Thaumaturgy cantrip.</text>
    </trait>
    <trait category="subspecies">
      <name>Variant: Appearance</name>
      <text>Your tiefling might not look like other tieflings.</text>
    </trait>
    <trait category="subspecies">
      <name>Variant: Devil's Tongue</name>
      <text>You know the vicious mockery cantrip. This trait replaces the Infernal Legacy trait.</text>
    </trait>
    <trait category="subspecies">
      <name>Variant: Hellfire</name>
      <text>You can cast burning hands. This trait replaces the Infernal Legacy trait.</text>
    </trait>
    <trait category="subspecies">
      <name>Variant: Winged</name>
      <text>You have bat-like wings. You have a flying speed of 30 feet. This replaces the Infernal Legacy trait.</text>
    </trait>
    <trait>
      <name>Languages</name>
      <text>You can speak Common and Infernal.</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $count = $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // The import count includes 4 subraces + 1 base race reference tracking
        // (importFromFile counts base race first occurrence even when it already exists)
        $expectedSubraces = 4;
        $baseRaceTracking = 1;
        $this->assertEquals(
            $expectedSubraces + $baseRaceTracking,
            $count,
            'Should count 4 subraces + 1 base race reference'
        );

        // Verify the actual database state - 4 subraces created
        $subraces = Race::where('parent_race_id', $baseTiefling->id)->get();
        $this->assertCount($expectedSubraces, $subraces, 'Should create exactly 4 Tiefling variant subraces');

        $subraceNames = $subraces->pluck('name')->toArray();
        $this->assertContains('Feral', $subraceNames);
        $this->assertContains("Devil's Tongue", $subraceNames);
        $this->assertContains('Hellfire', $subraceNames);
        $this->assertContains('Winged', $subraceNames);

        // Check slugs are correct
        $this->assertDatabaseHas('races', ['slug' => 'tiefling-feral']);
        $this->assertDatabaseHas('races', ['slug' => 'tiefling-devils-tongue']);
        $this->assertDatabaseHas('races', ['slug' => 'tiefling-hellfire']);
        $this->assertDatabaseHas('races', ['slug' => 'tiefling-winged']);

        // Check Feral has Infernal Legacy trait
        $feral = Race::where('slug', 'tiefling-feral')->first();
        $feralTraitNames = $feral->traits->pluck('name')->toArray();
        $this->assertContains('Infernal Legacy', $feralTraitNames);
        $this->assertContains('Darkvision', $feralTraitNames);
        $this->assertContains('Variant: Appearance', $feralTraitNames);

        // Check Devil's Tongue does NOT have Infernal Legacy but has its own trait
        $devilsTongue = Race::where('slug', 'tiefling-devils-tongue')->first();
        $devilsTongueTraitNames = $devilsTongue->traits->pluck('name')->toArray();
        $this->assertContains("Variant: Devil's Tongue", $devilsTongueTraitNames);
        $this->assertNotContains('Infernal Legacy', $devilsTongueTraitNames);
        $this->assertContains('Darkvision', $devilsTongueTraitNames);

        // Check Winged has flying speed extracted
        $winged = Race::where('slug', 'tiefling-winged')->first();
        $this->assertEquals(30, $winged->fly_speed);
    }

    #[Test]
    public function it_sets_subrace_required_false_for_race_with_6_ability_points()
    {
        // Human has +1 to all six abilities = 6 ability points = complete race
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Human</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Str +1, Dex +1, Con +1, Int +1, Wis +1, Cha +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Humans are the most adaptable and ambitious people.
Source: Player's Handbook (2014) p. 29</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $human = Race::where('name', 'Human')->first();

        $this->assertNotNull($human);
        // 6 ability points >= 3, so subrace_required should be false
        $this->assertFalse($human->subrace_required, 'Race with 6 ability points should have subrace_required=false');
    }

    #[Test]
    public function it_sets_subrace_required_false_for_race_with_3_ability_points()
    {
        // Dragonborn has +2 Str, +1 Cha = 3 ability points = complete race
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Str +2, Cha +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $dragonborn = Race::where('name', 'Dragonborn')->first();

        $this->assertNotNull($dragonborn);
        // 3 ability points >= 3, so subrace_required should be false
        $this->assertFalse($dragonborn->subrace_required, 'Race with 3 ability points should have subrace_required=false');
    }

    #[Test]
    public function it_sets_subrace_required_true_for_base_race_with_only_2_ability_points()
    {
        // A subrace imports, its base race gets only the first bonus (Con +2 = 2 points)
        // Base race should have subrace_required=true because 2 < 3
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dwarf, Hill</name>
    <size>M</size>
    <speed>25</speed>
    <ability>Con +2, Wis +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>As a hill dwarf.
Source: Player's Handbook (2014) p. 20</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Base Dwarf race gets only the first bonus (Con +2 = 2 points)
        // 2 ability points < 3, so subrace_required should be true
        $dwarf = Race::where('name', 'Dwarf')
            ->whereNull('parent_race_id')
            ->first();

        $this->assertNotNull($dwarf);
        $this->assertTrue($dwarf->subrace_required, 'Base race with 2 ability points should have subrace_required=true');

        // Subraces should always have subrace_required=false (no nested subraces in D&D 5e)
        $hill = Race::where('name', 'Hill')
            ->whereNotNull('parent_race_id')
            ->first();

        $this->assertNotNull($hill);
        $this->assertFalse($hill->subrace_required, 'Subraces should always have subrace_required=false');
    }

    #[Test]
    public function it_sets_subrace_required_false_for_races_with_3_plus_fixed_ability_points()
    {
        // These races have 3+ fixed ability points and should have subrace_required=false
        $races = [
            'Tiefling' => 'Cha +2, Int +1',  // 3 points
            'Half-Orc' => 'Str +2, Con +1',  // 3 points
        ];

        foreach ($races as $name => $ability) {
            $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>{$name}</name>
    <size>M</size>
    <speed>30</speed>
    <ability>{$ability}</ability>
    <trait category="description">
      <name>Description</name>
      <text>{$name} race description.
Source: Player's Handbook (2014)</text>
    </trait>
  </race>
</compendium>
XML;

            $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
            file_put_contents($tmpFile, $xml);

            $this->importer->importFromFile($tmpFile);

            unlink($tmpFile);
        }

        foreach (['Tiefling', 'Half-Orc'] as $name) {
            $race = Race::where('name', $name)->first();
            $this->assertNotNull($race, "{$name} should exist");
            $this->assertFalse(
                $race->subrace_required,
                "{$name} with 3 fixed ability points should have subrace_required=false"
            );
        }
    }

    #[Test]
    public function it_sets_subrace_required_true_for_race_with_only_2_fixed_ability_points()
    {
        // Half-Elf without choice trait has only 2 fixed points (Cha +2)
        // Since 2 < 3, subrace_required should be true
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Half-Elf</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Cha +2</ability>
    <trait category="description">
      <name>Description</name>
      <text>Half-Elf race description.
Source: Player's Handbook (2014)</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $halfElf = Race::where('name', 'Half-Elf')->first();
        $this->assertNotNull($halfElf);
        // Half-Elf has only 2 fixed ability points < 3, so subrace_required should be true
        $this->assertTrue(
            $halfElf->subrace_required,
            'Half-Elf with only 2 fixed ability points should have subrace_required=true'
        );
    }

    #[Test]
    public function it_sets_subrace_required_false_when_ability_choices_give_3_plus_points()
    {
        // Half-Elf has Cha +2 (fixed) + "Two other ability scores increase by 1" (choice)
        // Total = 2 + 2 = 4 points >= 3, so subrace_required should be false
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Half-Elf</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Cha +2</ability>
    <trait>
      <name>Ability Score Increase</name>
      <text>Your Charisma score increases by 2, and two other ability scores of your choice increase by 1.</text>
    </trait>
    <trait category="description">
      <name>Description</name>
      <text>Half-elves combine human and elven traits.
Source: Player's Handbook (2014)</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $halfElf = Race::where('name', 'Half-Elf')->first();

        $this->assertNotNull($halfElf);
        // 2 fixed + 2 from choices = 4 points >= 3
        $this->assertFalse(
            $halfElf->subrace_required,
            'Half-Elf with 2 fixed + 2 choice ability points should have subrace_required=false'
        );
    }

    #[Test]
    public function it_sets_subrace_required_true_for_race_with_no_ability_bonuses()
    {
        // A race with no ability bonuses should require subraces
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>CustomRace</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="description">
      <name>Description</name>
      <text>A custom race with no ability bonuses.
Source: Homebrew</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $customRace = Race::where('name', 'CustomRace')->first();

        $this->assertNotNull($customRace);
        // 0 ability points < 3, so subrace_required should be true
        $this->assertTrue(
            $customRace->subrace_required,
            'Race with 0 ability points should have subrace_required=true'
        );
    }

    #[Test]
    public function it_sets_subrace_required_false_for_race_with_exactly_3_ability_points()
    {
        // Boundary test: exactly 3 points should result in subrace_required=false
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>BoundaryRace</name>
    <size>M</size>
    <speed>30</speed>
    <ability>Str +2, Con +1</ability>
    <trait category="description">
      <name>Description</name>
      <text>A race with exactly 3 ability points.
Source: Test</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'BoundaryRace')->first();

        $this->assertNotNull($race);
        // Exactly 3 points >= 3, so subrace_required should be false
        $this->assertFalse(
            $race->subrace_required,
            'Race with exactly 3 ability points should have subrace_required=false'
        );
    }

    #[Test]
    public function it_imports_bonus_feat_modifier_from_feat_trait()
    {
        // Variant Human grants a bonus feat
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Human, Variant</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Ability Score Increases</name>
      <text>Two different ability scores of your choice increase by 1.</text>
    </trait>
    <trait>
      <name>Feat</name>
      <text>You gain one feat of your choice.</text>
    </trait>
    <trait category="description">
      <name>Description</name>
      <text>Variant humans are versatile and adaptable.
Source: Player's Handbook (2014) p. 31</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        // Should create 2 races: base "Human" + subrace "Variant"
        $subrace = Race::where('name', 'Variant')
            ->whereNotNull('parent_race_id')
            ->first();

        $this->assertNotNull($subrace, 'Variant subrace should exist');

        // Check for bonus_feat modifier on the subrace
        $bonusFeatModifier = $subrace->modifiers()
            ->where('modifier_category', 'bonus_feat')
            ->first();

        $this->assertNotNull($bonusFeatModifier, 'Variant Human should have a bonus_feat modifier');
        $this->assertEquals(1, $bonusFeatModifier->value, 'Bonus feat value should be 1');
    }

    #[Test]
    public function it_imports_level_scaling_rolls_as_progression_table_with_entries()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Dragonborn</name>
    <size>M</size>
    <speed>30</speed>
    <trait category="species">
      <name>Breath Weapon</name>
      <text>You can exhale destructive energy. The damage increases as you gain levels.</text>
      <roll description="Damage" level="1">2d6</roll>
      <roll description="Damage" level="6">3d6</roll>
      <roll description="Damage" level="11">4d6</roll>
      <roll description="Damage" level="16">5d6</roll>
      <roll description="Saving Throw">8+%3+%8</roll>
    </trait>
    <trait category="description">
      <name>Description</name>
      <text>Born of dragons.
Source: Player's Handbook (2014) p. 32</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $race = Race::where('name', 'Dragonborn')->first();
        $breathWeaponTrait = $race->traits->where('name', 'Breath Weapon')->first();

        $this->assertNotNull($breathWeaponTrait, 'Breath Weapon trait should exist');

        // Should have 2 data tables: one PROGRESSION for "Damage", one RANDOM for "Saving Throw"
        $dataTables = $breathWeaponTrait->dataTables;
        $this->assertCount(2, $dataTables, 'Should have 2 data tables (Damage progression + Saving Throw)');

        // Check the Damage progression table
        $damageTable = $dataTables->where('table_name', 'Damage')->first();
        $this->assertNotNull($damageTable, 'Damage table should exist');
        $this->assertEquals(\App\Enums\DataTableType::PROGRESSION, $damageTable->table_type);

        // Check the level entries
        $entries = $damageTable->entries()->orderBy('level')->get();
        $this->assertCount(4, $entries, 'Should have 4 level entries for Damage');

        $this->assertEquals(1, $entries[0]->level);
        $this->assertEquals('2d6', $entries[0]->result_text);

        $this->assertEquals(6, $entries[1]->level);
        $this->assertEquals('3d6', $entries[1]->result_text);

        $this->assertEquals(11, $entries[2]->level);
        $this->assertEquals('4d6', $entries[2]->result_text);

        $this->assertEquals(16, $entries[3]->level);
        $this->assertEquals('5d6', $entries[3]->result_text);

        // Check the Saving Throw table (no level = RANDOM type)
        $savingThrowTable = $dataTables->where('table_name', 'Saving Throw')->first();
        $this->assertNotNull($savingThrowTable, 'Saving Throw table should exist');
        $this->assertEquals(\App\Enums\DataTableType::RANDOM, $savingThrowTable->table_type);
        $this->assertEquals('8+%3+%8', $savingThrowTable->dice_type);
    }

    #[Test]
    public function it_imports_bonus_feat_modifier_from_custom_lineage()
    {
        // Custom Lineage grants a bonus feat with qualifying requirements
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Custom Lineage</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Ability Score Increase</name>
      <text>One ability score of your choice increases by 2.</text>
    </trait>
    <trait>
      <name>Feat</name>
      <text>You gain one feat of your choice for which you qualify.</text>
    </trait>
    <trait category="description">
      <name>Description</name>
      <text>Instead of choosing one of the game's races for your character at 1st level, you can use the following traits to represent your character's lineage.
Source: Tasha's Cauldron of Everything p. 8</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $customLineage = Race::where('name', 'Custom Lineage')->first();

        $this->assertNotNull($customLineage, 'Custom Lineage race should exist');

        // Check for bonus_feat modifier
        $bonusFeatModifier = $customLineage->modifiers()
            ->where('modifier_category', 'bonus_feat')
            ->first();

        $this->assertNotNull($bonusFeatModifier, 'Custom Lineage should have a bonus_feat modifier');
        $this->assertEquals(1, $bonusFeatModifier->value, 'Bonus feat value should be 1');
    }

    #[Test]
    public function it_imports_unarmored_ac_from_lizardfolk()
    {
        // Lizardfolk have natural armor: AC = 13 + DEX
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Lizardfolk</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Natural Armor</name>
      <text>You have tough, scaly skin. When you aren't wearing armor, your AC is 13 + your Dexterity modifier. You can use your natural armor to determine your AC if the armor you wear would leave you with a lower AC. A shield's benefits apply as normal while you use your natural armor.</text>
    </trait>
    <trait category="description">
      <name>Description</name>
      <text>Source: Volo's Guide to Monsters p. 111</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $lizardfolk = Race::where('name', 'Lizardfolk')->first();
        $this->assertNotNull($lizardfolk, 'Lizardfolk race should exist');

        // Check for ac_unarmored modifier
        $acModifier = $lizardfolk->modifiers()
            ->where('modifier_category', 'ac_unarmored')
            ->first();

        $this->assertNotNull($acModifier, 'Lizardfolk should have ac_unarmored modifier');
        $this->assertEquals('13', $acModifier->value);
        $this->assertNotNull($acModifier->ability_score_id);
        $this->assertEquals('DEX', $acModifier->abilityScore->code);
        $this->assertStringContainsString('allows_shield: true', $acModifier->condition);
        $this->assertStringContainsString('replaces_armor: false', $acModifier->condition);
    }

    #[Test]
    public function it_imports_unarmored_ac_from_tortle()
    {
        // Tortle have shell: AC = 17 flat, can't wear armor
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Tortle</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Natural Armor</name>
      <text>Your shell provides you a base AC of 17 (your Dexterity modifier doesn't affect this number). You can't wear light, medium, or heavy armor, but if you are using a shield, you can apply the shield's bonus as normal.</text>
    </trait>
    <trait category="description">
      <name>Description</name>
      <text>Source: The Tortle Package p. 4</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $tortle = Race::where('name', 'Tortle')->first();
        $this->assertNotNull($tortle, 'Tortle race should exist');

        // Check for ac_unarmored modifier
        $acModifier = $tortle->modifiers()
            ->where('modifier_category', 'ac_unarmored')
            ->first();

        $this->assertNotNull($acModifier, 'Tortle should have ac_unarmored modifier');
        $this->assertEquals('17', $acModifier->value);
        $this->assertNull($acModifier->ability_score_id, 'Tortle should have no ability score for flat AC');
        $this->assertStringContainsString('allows_shield: true', $acModifier->condition);
        $this->assertStringContainsString('replaces_armor: true', $acModifier->condition);
    }

    #[Test]
    public function it_imports_unarmored_ac_with_con_modifier()
    {
        // Loxodon have natural armor: AC = 12 + CON
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5" auto_indent="NO">
  <race>
    <name>Loxodon</name>
    <size>M</size>
    <speed>30</speed>
    <trait>
      <name>Natural Armor</name>
      <text>You have thick, leathery skin. When you aren't wearing armor, your AC is 12 + your Constitution modifier. You can use your natural armor to determine your AC if the armor you wear would leave you with a lower AC. A shield's benefits apply as normal while you use your natural armor.</text>
    </trait>
    <trait category="description">
      <name>Description</name>
      <text>Source: Guildmasters' Guide to Ravnica p. 18</text>
    </trait>
  </race>
</compendium>
XML;

        $tmpFile = tempnam(sys_get_temp_dir(), 'race_test_');
        file_put_contents($tmpFile, $xml);

        $this->importer->importFromFile($tmpFile);

        unlink($tmpFile);

        $loxodon = Race::where('name', 'Loxodon')->first();
        $this->assertNotNull($loxodon, 'Loxodon race should exist');

        // Check for ac_unarmored modifier
        $acModifier = $loxodon->modifiers()
            ->where('modifier_category', 'ac_unarmored')
            ->first();

        $this->assertNotNull($acModifier, 'Loxodon should have ac_unarmored modifier');
        $this->assertEquals('12', $acModifier->value);
        $this->assertNotNull($acModifier->ability_score_id);
        $this->assertEquals('CON', $acModifier->abilityScore->code);
    }
}

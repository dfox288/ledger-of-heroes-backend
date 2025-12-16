<?php

namespace Tests\Feature\Importers;

use App\Models\AbilityScore;
use App\Models\CharacterClass;
use App\Services\Importers\ClassImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for Issue #726: spell_preparation_method in subclass imports.
 *
 * Verifies that the ClassImporter correctly sets spell_preparation_method
 * for both base classes and subclasses during import.
 */
#[Group('importers')]
class ClassImporterSpellPreparationMethodTest extends TestCase
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
    public function it_sets_spell_preparation_method_for_base_class(): void
    {
        // Import Cleric with 'prepared' method (from parser detection)
        $clericData = [
            'name' => 'Cleric',
            'hit_die' => 8,
            'spellcasting_ability' => 'Wisdom',
            'spell_preparation_method' => 'prepared',
            'spell_progression' => [
                [
                    'level' => 1,
                    'cantrips_known' => 3,
                    'spell_slots_1st' => 2,
                    'spell_slots_2nd' => 0,
                    'spell_slots_3rd' => 0,
                    'spell_slots_4th' => 0,
                    'spell_slots_5th' => 0,
                    'spell_slots_6th' => 0,
                    'spell_slots_7th' => 0,
                    'spell_slots_8th' => 0,
                    'spell_slots_9th' => 0,
                ],
            ],
            'traits' => [[
                'name' => 'Cleric',
                'description' => 'Divine spellcaster',
                'sources' => [['code' => 'PHB', 'page' => 56]],
            ]],
            'features' => [],
            'proficiencies' => [],
            'counters' => [],
        ];

        $cleric = $this->importer->import($clericData);

        $this->assertEquals('prepared', $cleric->spell_preparation_method);
        $this->assertEquals('prepared', $cleric->getRawOriginal('spell_preparation_method'));
    }

    #[Test]
    public function it_inherits_spell_preparation_method_for_regular_subclass(): void
    {
        // Create Cleric base class
        $wisdom = AbilityScore::where('code', 'WIS')->first();
        $cleric = CharacterClass::factory()->create([
            'slug' => 'phb:cleric-base',
            'name' => 'Cleric',
            'spell_preparation_method' => 'prepared',
            'spellcasting_ability_id' => $wisdom->id,
            'parent_class_id' => null,
        ]);

        // Import Life Domain subclass (no own spell progression)
        $subclassData = [
            'name' => 'Life Domain',
            'features' => [[
                'name' => 'Divine Domain: Life Domain',
                'description' => 'The Life domain focuses on healing.',
                'level' => 1,
                'is_optional' => false,
                'sources' => [['code' => 'PHB', 'page' => 60]],
                'sort_order' => 0,
            ]],
            'counters' => [],
            // No spell_progression - inherits from parent
        ];

        $lifeDomain = $this->importer->importSubclass($cleric, $subclassData);

        // Should inherit 'prepared' from Cleric parent
        $this->assertEquals('prepared', $lifeDomain->getRawOriginal('spell_preparation_method'));
        $this->assertEquals($cleric->id, $lifeDomain->parent_class_id);
    }

    #[Test]
    public function it_sets_known_method_for_subclass_with_own_spell_progression(): void
    {
        // Create Fighter base class (non-caster)
        $fighter = CharacterClass::factory()->create([
            'slug' => 'phb:fighter-base',
            'name' => 'Fighter',
            'spell_preparation_method' => null,
            'spellcasting_ability_id' => null,
            'parent_class_id' => null,
        ]);

        $intelligence = AbilityScore::where('code', 'INT')->first();

        // Import Eldritch Knight subclass (has own spell progression)
        $subclassData = [
            'name' => 'Eldritch Knight',
            'spellcasting_ability' => 'Intelligence',
            'spell_progression' => [
                [
                    'level' => 3,
                    'cantrips_known' => 2,
                    'spell_slots_1st' => 2,
                    'spell_slots_2nd' => 0,
                    'spell_slots_3rd' => 0,
                    'spell_slots_4th' => 0,
                    'spell_slots_5th' => 0,
                    'spell_slots_6th' => 0,
                    'spell_slots_7th' => 0,
                    'spell_slots_8th' => 0,
                    'spell_slots_9th' => 0,
                    'spells_known' => 3,
                ],
                [
                    'level' => 4,
                    'cantrips_known' => 2,
                    'spell_slots_1st' => 3,
                    'spell_slots_2nd' => 0,
                    'spell_slots_3rd' => 0,
                    'spell_slots_4th' => 0,
                    'spell_slots_5th' => 0,
                    'spell_slots_6th' => 0,
                    'spell_slots_7th' => 0,
                    'spell_slots_8th' => 0,
                    'spell_slots_9th' => 0,
                    'spells_known' => 4,
                ],
            ],
            'features' => [[
                'name' => 'Martial Archetype: Eldritch Knight',
                'description' => 'The archetypal Eldritch Knight combines martial prowess with magic.',
                'level' => 3,
                'is_optional' => false,
                'sources' => [['code' => 'PHB', 'page' => 74]],
                'sort_order' => 0,
            ]],
            'counters' => [],
        ];

        $eldritchKnight = $this->importer->importSubclass($fighter, $subclassData);

        // Subclasses with their own spell progression are 'known' casters
        $this->assertEquals('known', $eldritchKnight->getRawOriginal('spell_preparation_method'));
        $this->assertNotNull($eldritchKnight->spellcasting_ability_id);
    }

    #[Test]
    public function it_updates_existing_subclass_with_null_preparation_method(): void
    {
        // Create Wizard base class
        $intelligence = AbilityScore::where('code', 'INT')->first();
        $wizard = CharacterClass::factory()->create([
            'slug' => 'phb:wizard-base',
            'name' => 'Wizard',
            'spell_preparation_method' => 'spellbook',
            'spellcasting_ability_id' => $intelligence->id,
            'parent_class_id' => null,
        ]);

        // Create existing Evoker subclass with null preparation method (legacy data)
        $evoker = CharacterClass::factory()->create([
            'slug' => 'phb:wizard-school-of-evocation',
            'name' => 'School of Evocation',
            'parent_class_id' => $wizard->id,
            'spellcasting_ability_id' => $intelligence->id,
            'spell_preparation_method' => null, // Legacy: not set
        ]);

        // Import the same subclass again (merge scenario)
        $subclassData = [
            'name' => 'School of Evocation',
            'features' => [[
                'name' => 'Arcane Tradition: School of Evocation',
                'description' => 'You focus your study on evocation magic.',
                'level' => 2,
                'is_optional' => false,
                'sources' => [['code' => 'PHB', 'page' => 117]],
                'sort_order' => 0,
            ]],
            'counters' => [],
        ];

        $updatedEvoker = $this->importer->importSubclass($wizard, $subclassData);

        // Should update to 'spellbook' from parent
        $this->assertEquals($evoker->id, $updatedEvoker->id); // Same record
        $this->assertEquals('spellbook', $updatedEvoker->getRawOriginal('spell_preparation_method'));
    }

    #[Test]
    public function it_preserves_existing_preparation_method_on_merge(): void
    {
        // Create Bard base class
        $charisma = AbilityScore::where('code', 'CHA')->first();
        $bard = CharacterClass::factory()->create([
            'slug' => 'phb:bard-base',
            'name' => 'Bard',
            'spell_preparation_method' => 'known',
            'spellcasting_ability_id' => $charisma->id,
            'parent_class_id' => null,
        ]);

        // Create existing subclass that already has preparation method set
        $collegeOfLore = CharacterClass::factory()->create([
            'slug' => 'phb:bard-college-of-lore',
            'name' => 'College of Lore',
            'parent_class_id' => $bard->id,
            'spellcasting_ability_id' => $charisma->id,
            'spell_preparation_method' => 'known', // Already set correctly
        ]);

        // Import again (merge scenario) - should not overwrite
        $subclassData = [
            'name' => 'College of Lore',
            'features' => [[
                'name' => 'Bard College: College of Lore',
                'description' => 'Lore bards know something about most things.',
                'level' => 3,
                'is_optional' => false,
                'sources' => [['code' => 'PHB', 'page' => 54]],
                'sort_order' => 0,
            ]],
            'counters' => [],
        ];

        $updatedCollege = $this->importer->importSubclass($bard, $subclassData);

        // Should preserve existing 'known' value
        $this->assertEquals($collegeOfLore->id, $updatedCollege->id);
        $this->assertEquals('known', $updatedCollege->getRawOriginal('spell_preparation_method'));
    }

    #[Test]
    public function it_sets_null_for_non_spellcasting_subclass(): void
    {
        // Create Fighter base class (non-caster)
        $fighter = CharacterClass::factory()->create([
            'slug' => 'phb:fighter-base2',
            'name' => 'Fighter',
            'spell_preparation_method' => null,
            'spellcasting_ability_id' => null,
            'parent_class_id' => null,
        ]);

        // Import Battle Master subclass (no spellcasting)
        $subclassData = [
            'name' => 'Battle Master',
            'features' => [[
                'name' => 'Martial Archetype: Battle Master',
                'description' => 'Those who emulate the archetypal Battle Master employ martial techniques.',
                'level' => 3,
                'is_optional' => false,
                'sources' => [['code' => 'PHB', 'page' => 73]],
                'sort_order' => 0,
            ]],
            'counters' => [],
            // No spell_progression, no spellcasting_ability
        ];

        $battleMaster = $this->importer->importSubclass($fighter, $subclassData);

        // Non-caster subclass should have null
        $this->assertNull($battleMaster->getRawOriginal('spell_preparation_method'));
        $this->assertNull($battleMaster->spellcasting_ability_id);
    }
}

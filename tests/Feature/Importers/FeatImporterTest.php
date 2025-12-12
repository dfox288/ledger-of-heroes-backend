<?php

namespace Tests\Feature\Importers;

use App\Models\Feat;
use App\Services\Importers\FeatImporter;
use App\Services\Parsers\FeatXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class FeatImporterTest extends TestCase
{
    use RefreshDatabase;

    private FeatImporter $importer;

    protected $seed = true; // Seed ability_scores for modifiers tests

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new FeatImporter;
    }

    #[Test]
    public function it_imports_a_basic_feat()
    {
        $featData = [
            'name' => 'Alert',
            'prerequisites' => null,
            'description' => 'Always on the lookout for danger.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '165'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        $this->assertInstanceOf(Feat::class, $feat);
        $this->assertEquals('Alert', $feat->name);
        $this->assertNull($feat->prerequisites_text);
        $this->assertStringContainsString('Always on the lookout', $feat->description);

        // Check sources
        $this->assertEquals(1, $feat->sources()->count());
        $entitySource = $feat->sources()->first();
        $this->assertEquals('PHB', $entitySource->source->code);
        $this->assertEquals('165', $entitySource->pages);
    }

    #[Test]
    public function it_imports_feat_with_prerequisites()
    {
        $featData = [
            'name' => 'Defensive Duelist',
            'prerequisites' => 'Dexterity 13 or higher',
            'description' => 'When wielding a finesse weapon...',
            'sources' => [
                ['code' => 'PHB', 'pages' => '165'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        $this->assertEquals('Defensive Duelist', $feat->name);
        $this->assertEquals('Dexterity 13 or higher', $feat->prerequisites_text);
    }

    #[Test]
    public function it_imports_feat_with_modifiers()
    {
        $featData = [
            'name' => 'Actor',
            'prerequisites' => null,
            'description' => 'Skilled at mimicry and dramatics.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '165'],
            ],
            'modifiers' => [
                [
                    'category' => 'ability_score',
                    'value' => 1,
                    'ability_code' => 'CHA',
                ],
            ],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        $this->assertEquals('Actor', $feat->name);
        $this->assertCount(1, $feat->modifiers);

        $modifier = $feat->modifiers->first();
        $this->assertEquals('ability_score', $modifier->category);
        $this->assertEquals(1, $modifier->value);
        $this->assertNotNull($modifier->ability_score_id);
        $this->assertEquals('CHA', $modifier->abilityScore->code);
    }

    #[Test]
    public function it_imports_feat_with_bonus_modifiers()
    {
        $featData = [
            'name' => 'Alert',
            'prerequisites' => null,
            'description' => 'Always on the lookout for danger.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '165'],
            ],
            'modifiers' => [
                [
                    'category' => 'initiative',
                    'value' => 5,
                ],
            ],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        $this->assertCount(1, $feat->modifiers);
        $modifier = $feat->modifiers->first();
        $this->assertEquals('initiative', $modifier->category);
        $this->assertEquals(5, $modifier->value);
    }

    #[Test]
    public function it_imports_feat_with_specific_proficiencies()
    {
        $featData = [
            'name' => 'Heavily Armored',
            'prerequisites' => 'Proficiency with medium armor',
            'description' => 'You have trained to master heavy armor.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '167'],
            ],
            'modifiers' => [],
            'proficiencies' => [
                [
                    'description' => 'heavy armor',
                    'is_choice' => false,
                    'quantity' => null,
                ],
            ],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        $this->assertCount(1, $feat->proficiencies);
        $proficiency = $feat->proficiencies->first();
        $this->assertStringContainsString('heavy armor', strtolower($proficiency->proficiency_name));
    }

    #[Test]
    public function it_imports_feat_with_choice_proficiencies()
    {
        // Note: FeatImporter expects 'description' field which it transforms into 'name'
        // For unrestricted proficiency choices, we need to bypass the normal flow
        // by creating the feat first, then manually calling importEntityProficiencies

        $feat = \App\Models\Feat::create([
            'name' => 'Weapon Master',
            'slug' => 'weapon-master',
            'description' => 'You have practiced extensively with weapons.',
            'prerequisites_text' => null,
        ]);

        // Call the trait method directly with properly formatted data
        $importer = new \App\Services\Importers\FeatImporter;
        $reflection = new \ReflectionClass($importer);
        $method = $reflection->getMethod('importEntityProficiencies');
        $method->setAccessible(true);

        $proficienciesData = [
            [
                'type' => 'weapon',
                'name' => null, // Unrestricted choice
                'is_choice' => true,
                'quantity' => 4,
                'grants' => true,
            ],
        ];

        $method->invoke($importer, $feat, $proficienciesData);
        $feat->refresh();

        // Choice proficiencies go to entity_choices table
        $this->assertCount(1, $feat->proficiencyChoices);
        $choice = $feat->proficiencyChoices->first();
        $this->assertEquals('proficiency', $choice->choice_type);
        $this->assertEquals(4, $choice->quantity);
    }

    #[Test]
    public function it_imports_feat_with_conditions()
    {
        $featData = [
            'name' => 'Actor',
            'prerequisites' => null,
            'description' => 'Skilled at mimicry and dramatics.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '165'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [
                [
                    'effect_type' => 'advantage',
                    'description' => 'Charisma (Deception) and Charisma (Performance) checks when trying to pass yourself off as a different person',
                ],
            ],
        ];

        $feat = $this->importer->import($featData);

        $this->assertCount(1, $feat->conditions);
        $condition = $feat->conditions->first();
        $this->assertEquals('advantage', $condition->effect_type);
        $this->assertStringContainsString('Deception', $condition->description);
    }

    #[Test]
    public function it_updates_existing_feat_on_reimport()
    {
        $featData = [
            'name' => 'Alert',
            'prerequisites' => null,
            'description' => 'Original description.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '165'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
        ];

        $this->importer->import($featData);
        $this->assertEquals(1, Feat::count());

        // Re-import with updated description
        $featData['description'] = 'Updated description.';
        $feat = $this->importer->import($featData);

        $this->assertEquals(1, Feat::count());
        $this->assertStringContainsString('Updated description', $feat->fresh()->description);
    }

    #[Test]
    public function it_imports_from_xml_file()
    {
        $xml = file_get_contents(base_path('import-files/feats-phb.xml'));
        $parser = new FeatXmlParser;
        $featsData = $parser->parse($xml);

        $this->assertNotEmpty($featsData);

        // Import first feat
        $feat = $this->importer->import($featsData[0]);

        $this->assertInstanceOf(Feat::class, $feat);
        $this->assertNotEmpty($feat->name);
        $this->assertNotEmpty($feat->description);
    }

    #[Test]
    public function it_imports_feat_with_specific_spell()
    {
        // Create test spells
        $mistyStep = \App\Models\Spell::factory()->create([
            'name' => 'Misty Step',
            'slug' => 'misty-step',
            'level' => 2,
        ]);

        $charisma = \App\Models\AbilityScore::where('code', 'CHA')->first();

        $featData = [
            'name' => 'Fey Touched (Charisma)',
            'prerequisites' => null,
            'description' => 'Your exposure to the Feywild has changed you. You learn the misty step spell and one 1st-level spell.',
            'sources' => [
                ['code' => 'TCE', 'pages' => '79'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'spells' => [
                [
                    'spell_name' => 'Misty Step',
                    'pivot_data' => [
                        'ability_score_id' => $charisma->id,
                        'usage_limit' => 'long_rest',
                        'is_cantrip' => false,
                    ],
                ],
            ],
        ];

        $feat = $this->importer->import($featData);

        $this->assertCount(1, $feat->spells);
        $featSpell = $feat->spells->first();
        $this->assertEquals($mistyStep->id, $featSpell->id);
        $this->assertEquals($charisma->id, $featSpell->pivot->ability_score_id);
        $this->assertEquals('long_rest', $featSpell->pivot->usage_limit);
        $this->assertFalse((bool) $featSpell->pivot->is_cantrip);
    }

    #[Test]
    public function it_clears_spells_on_reimport()
    {
        // Create test spells
        $spell1 = \App\Models\Spell::factory()->create(['name' => 'Spell One', 'slug' => 'spell-one']);
        $spell2 = \App\Models\Spell::factory()->create(['name' => 'Spell Two', 'slug' => 'spell-two']);

        $featData = [
            'name' => 'Magic Feat',
            'prerequisites' => null,
            'description' => 'A magical feat.',
            'sources' => [],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'spells' => [
                ['spell_name' => 'Spell One', 'pivot_data' => []],
            ],
        ];

        // First import
        $feat = $this->importer->import($featData);
        $this->assertCount(1, $feat->spells);
        $this->assertEquals($spell1->id, $feat->spells->first()->id);

        // Second import with different spell
        $featData['spells'] = [
            ['spell_name' => 'Spell Two', 'pivot_data' => []],
        ];
        $feat = $this->importer->import($featData);
        $feat->load('spells'); // Reload to get fresh data

        $this->assertCount(1, $feat->spells);
        $this->assertEquals($spell2->id, $feat->spells->first()->id);
    }

    #[Test]
    public function it_imports_feat_with_language_choices()
    {
        $featData = [
            'name' => 'Linguist',
            'prerequisites' => null,
            'description' => 'You have studied languages and codes.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '167'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'languages' => [
                [
                    'language_id' => null,
                    'is_choice' => true,
                    'quantity' => 3,
                ],
            ],
        ];

        $feat = $this->importer->import($featData);

        // Language choices go to entity_choices table
        // Note: ImportsLanguages creates one EntityChoice per slot (quantity=3 → 3 records with quantity=1 each)
        $this->assertCount(3, $feat->languageChoices);
        $feat->languageChoices->each(function ($choice) {
            $this->assertEquals('language', $choice->choice_type);
            $this->assertEquals(1, $choice->quantity);
        });
    }

    #[Test]
    public function it_clears_languages_on_reimport()
    {
        $featData = [
            'name' => 'Linguist',
            'prerequisites' => null,
            'description' => 'You have studied languages and codes.',
            'sources' => [],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'languages' => [
                [
                    'language_id' => null,
                    'is_choice' => true,
                    'quantity' => 3,
                ],
            ],
        ];

        // First import (quantity=3 creates 3 separate choice records)
        $feat = $this->importer->import($featData);
        $this->assertCount(3, $feat->languageChoices);

        // Second import with different quantity (should clear old and create 2 new)
        $featData['languages'] = [
            [
                'language_id' => null,
                'is_choice' => true,
                'quantity' => 2,
            ],
        ];
        $feat = $this->importer->import($featData);
        $feat->refresh();

        $this->assertCount(2, $feat->languageChoices);
    }

    #[Test]
    public function it_imports_feat_with_damage_resistances()
    {
        // Get damage types that exist in the database (seeded or from import)
        $cold = \App\Models\DamageType::whereRaw('LOWER(name) = ?', ['cold'])->first();
        $poison = \App\Models\DamageType::whereRaw('LOWER(name) = ?', ['poison'])->first();

        // Skip test if damage types not available
        if (! $cold || ! $poison) {
            $this->markTestSkipped('Damage types not available in test database');
        }

        $featData = [
            'name' => 'Infernal Constitution',
            'prerequisites' => 'Tiefling',
            'description' => 'You have resistance to cold and poison damage.',
            'sources' => [
                ['code' => 'XGE', 'pages' => '75'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'languages' => [],
            'resistances' => [
                ['damage_type' => 'cold', 'condition' => null],
                ['damage_type' => 'poison', 'condition' => null],
            ],
        ];

        $feat = $this->importer->import($featData);

        // Check that resistances were imported as modifiers
        $resistanceModifiers = $feat->modifiers->where('modifier_category', 'damage_resistance');
        $this->assertCount(2, $resistanceModifiers, 'Should have 2 damage resistance modifiers');

        // Verify the damage types
        $damageTypeIds = $resistanceModifiers->pluck('damage_type_id')->sort()->values();
        $this->assertContains($cold->id, $damageTypeIds);
        $this->assertContains($poison->id, $damageTypeIds);
    }

    #[Test]
    public function it_imports_feat_with_conditional_damage_resistance()
    {
        $featData = [
            'name' => 'Dungeon Delver',
            'prerequisites' => null,
            'description' => 'You have resistance to the damage dealt by traps.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '166'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'languages' => [],
            'resistances' => [
                ['damage_type' => 'all', 'condition' => 'the damage dealt by traps'],
            ],
        ];

        $feat = $this->importer->import($featData);

        // Check that resistance was imported as modifier
        $resistanceModifiers = $feat->modifiers->where('modifier_category', 'damage_resistance');
        $this->assertCount(1, $resistanceModifiers, 'Should have 1 damage resistance modifier');

        $resistance = $resistanceModifiers->first();
        $this->assertNull($resistance->damage_type_id, 'All damage types should have null damage_type_id');
        $this->assertEquals('the damage dealt by traps', $resistance->condition);
    }

    #[Test]
    public function it_imports_feat_with_movement_cost_modifiers()
    {
        $featData = [
            'name' => 'Athlete (Dexterity)',
            'prerequisites' => null,
            'description' => 'You have undergone extensive physical training.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '165'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'movement_modifiers' => [
                [
                    'type' => 'movement_cost',
                    'activity' => 'climbing',
                    'cost' => 'normal',
                    'condition' => null,
                ],
                [
                    'type' => 'movement_cost',
                    'activity' => 'standing_from_prone',
                    'cost' => 5,
                    'condition' => null,
                ],
            ],
        ];

        $feat = $this->importer->import($featData);

        // Check that movement cost modifiers were imported
        $climbingMod = $feat->modifiers->where('modifier_category', 'movement_cost_climbing')->first();
        $standingMod = $feat->modifiers->where('modifier_category', 'movement_cost_standing_from_prone')->first();

        $this->assertNotNull($climbingMod, 'Should have climbing movement cost modifier');
        $this->assertEquals('normal', $climbingMod->value);

        $this->assertNotNull($standingMod, 'Should have standing from prone movement cost modifier');
        $this->assertEquals('5', $standingMod->value);
    }

    #[Test]
    public function it_imports_feat_with_speed_bonus_modifiers()
    {
        $featData = [
            'name' => 'Mobile',
            'prerequisites' => null,
            'description' => 'You are exceptionally speedy and agile.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '168'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'movement_modifiers' => [
                [
                    'type' => 'speed_bonus',
                    'value' => 10,
                    'movement_type' => 'walk',
                ],
            ],
        ];

        $feat = $this->importer->import($featData);

        // Check that speed bonus modifier was imported
        $speedMod = $feat->modifiers->where('modifier_category', 'speed_bonus_walk')->first();

        $this->assertNotNull($speedMod, 'Should have speed bonus modifier');
        $this->assertEquals('10', $speedMod->value);
    }

    #[Test]
    public function it_imports_feat_with_mixed_movement_modifiers()
    {
        // Mobile feat has both speed bonus and movement cost modifiers
        $featData = [
            'name' => 'Mobile',
            'prerequisites' => null,
            'description' => 'You are exceptionally speedy and agile.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '168'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'movement_modifiers' => [
                [
                    'type' => 'speed_bonus',
                    'value' => 10,
                    'movement_type' => 'walk',
                ],
                [
                    'type' => 'movement_cost',
                    'activity' => 'difficult_terrain',
                    'cost' => 'normal',
                    'condition' => 'When you use the Dash action',
                ],
            ],
        ];

        $feat = $this->importer->import($featData);

        // Check that both modifier types were imported
        $speedMod = $feat->modifiers->where('modifier_category', 'speed_bonus_walk')->first();
        $terrainMod = $feat->modifiers->where('modifier_category', 'movement_cost_difficult_terrain')->first();

        $this->assertNotNull($speedMod, 'Should have speed bonus modifier');
        $this->assertEquals('10', $speedMod->value);

        $this->assertNotNull($terrainMod, 'Should have difficult terrain movement cost modifier');
        $this->assertEquals('normal', $terrainMod->value);
        $this->assertEquals('When you use the Dash action', $terrainMod->condition);
    }

    #[Test]
    public function it_imports_feat_with_unarmored_ac()
    {
        // Dragon Hide feat grants natural armor
        $featData = [
            'name' => 'Dragon Hide',
            'prerequisites' => 'Dragonborn',
            'description' => 'While you aren\'t wearing armor, you can calculate your AC as 13 + your Dexterity modifier. You can use a shield and still gain this benefit.',
            'sources' => [
                ['code' => 'XGE', 'pages' => '74'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'unarmored_ac' => [
                'base_ac' => 13,
                'ability_code' => 'DEX',
                'allows_shield' => true,
                'replaces_armor' => false,
            ],
        ];

        $feat = $this->importer->import($featData);

        // Check that ac_unarmored modifier was created
        $acModifier = $feat->modifiers->where('modifier_category', 'ac_unarmored')->first();

        $this->assertNotNull($acModifier, 'Should have ac_unarmored modifier');
        $this->assertEquals('13', $acModifier->value);
        $this->assertNotNull($acModifier->ability_score_id);
        $this->assertEquals('DEX', $acModifier->abilityScore->code);
        $this->assertStringContainsString('allows_shield: true', $acModifier->condition);
        $this->assertStringContainsString('replaces_armor: false', $acModifier->condition);
    }

    #[Test]
    public function it_imports_feat_with_unarmored_ac_no_ability()
    {
        // Hypothetical feat with flat AC (like Tortle's shell)
        $featData = [
            'name' => 'Shell Defense',
            'prerequisites' => null,
            'description' => 'Your shell provides you a base AC of 17. You can\'t wear light, medium, or heavy armor.',
            'sources' => [
                ['code' => 'TEST', 'pages' => '1'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'unarmored_ac' => [
                'base_ac' => 17,
                'ability_code' => null,
                'allows_shield' => true,
                'replaces_armor' => true,
            ],
        ];

        $feat = $this->importer->import($featData);

        $acModifier = $feat->modifiers->where('modifier_category', 'ac_unarmored')->first();

        $this->assertNotNull($acModifier, 'Should have ac_unarmored modifier');
        $this->assertEquals('17', $acModifier->value);
        $this->assertNull($acModifier->ability_score_id, 'Should have no ability score for flat AC');
        $this->assertStringContainsString('replaces_armor: true', $acModifier->condition);
    }

    #[Test]
    public function it_does_not_create_modifier_when_no_unarmored_ac()
    {
        $featData = [
            'name' => 'Alert',
            'prerequisites' => null,
            'description' => 'Always on the lookout for danger.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '165'],
            ],
            'modifiers' => [],
            'proficiencies' => [],
            'conditions' => [],
            'unarmored_ac' => null,
        ];

        $feat = $this->importer->import($featData);

        $acModifier = $feat->modifiers->where('modifier_category', 'ac_unarmored')->first();
        $this->assertNull($acModifier, 'Should not have ac_unarmored modifier');
    }
}

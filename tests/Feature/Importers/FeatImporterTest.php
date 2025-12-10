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
        $this->assertFalse($proficiency->is_choice);
    }

    #[Test]
    public function it_imports_feat_with_choice_proficiencies()
    {
        $featData = [
            'name' => 'Weapon Master',
            'prerequisites' => null,
            'description' => 'You have practiced extensively with weapons.',
            'sources' => [
                ['code' => 'PHB', 'pages' => '170'],
            ],
            'modifiers' => [],
            'proficiencies' => [
                [
                    'description' => 'weapons',
                    'is_choice' => true,
                    'quantity' => 4,
                ],
            ],
            'conditions' => [],
        ];

        $feat = $this->importer->import($featData);

        $this->assertCount(1, $feat->proficiencies);
        $proficiency = $feat->proficiencies->first();
        $this->assertTrue($proficiency->is_choice);
        $this->assertEquals(4, $proficiency->quantity);
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

        $this->assertCount(1, $feat->languages);
        $language = $feat->languages->first();
        $this->assertNull($language->language_id);
        $this->assertTrue($language->is_choice);
        $this->assertEquals(3, $language->quantity);
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

        // First import
        $feat = $this->importer->import($featData);
        $this->assertCount(1, $feat->languages);
        $this->assertEquals(3, $feat->languages->first()->quantity);

        // Second import with different quantity
        $featData['languages'] = [
            [
                'language_id' => null,
                'is_choice' => true,
                'quantity' => 2,
            ],
        ];
        $feat = $this->importer->import($featData);
        $feat->refresh();

        $this->assertCount(1, $feat->languages);
        $this->assertEquals(2, $feat->languages->first()->quantity);
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
}

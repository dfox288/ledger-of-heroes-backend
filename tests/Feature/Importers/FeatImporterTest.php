<?php

namespace Tests\Feature\Importers;

use App\Models\Feat;
use App\Services\Importers\FeatImporter;
use App\Services\Parsers\FeatXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatImporterTest extends TestCase
{
    use RefreshDatabase;

    private FeatImporter $importer;

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
}

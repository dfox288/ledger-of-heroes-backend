<?php

namespace Tests\Unit\Services;

use App\Models\Spell;
use App\Models\SpellSchool;
use App\Models\SourceBook;
use App\Services\Importers\SpellImporter;
use App\Services\Parsers\SpellXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpellImporterTest extends TestCase
{
    use RefreshDatabase;

    private SpellImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new SpellImporter(new SpellXmlParser());
    }

    public function test_imports_spell_from_parsed_data(): void
    {
        $data = [
            'name' => 'Test Spell',
            'level' => 1,
            'school_code' => 'A',
            'is_ritual' => false,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'duration' => 'Instantaneous',
            'has_verbal_component' => true,
            'has_somatic_component' => false,
            'has_material_component' => false,
            'material_description' => null,
            'material_cost_gp' => null,
            'material_consumed' => false,
            'description' => 'Test description',
            'source_code' => 'PHB',
            'source_page' => 100,
            'classes' => [],
        ];

        $spell = $this->importer->importFromParsedData($data);

        $this->assertInstanceOf(Spell::class, $spell);
        $this->assertEquals('Test Spell', $spell->name);
        $this->assertEquals('test-spell', $spell->slug);
        $this->assertEquals(1, $spell->level);
        $this->assertTrue($spell->has_verbal_component);
        $this->assertFalse($spell->has_somatic_component);
    }

    public function test_creates_class_spell_associations(): void
    {
        $data = [
            'name' => 'Fireball',
            'level' => 3,
            'school_code' => 'EV',
            'is_ritual' => false,
            'casting_time' => '1 action',
            'range' => '150 feet',
            'duration' => 'Instantaneous',
            'has_verbal_component' => true,
            'has_somatic_component' => true,
            'has_material_component' => true,
            'material_description' => 'a tiny ball of bat guano and sulfur',
            'material_cost_gp' => null,
            'material_consumed' => false,
            'description' => 'A bright streak...',
            'source_code' => 'PHB',
            'source_page' => 241,
            'classes' => [
                ['class_name' => 'Wizard', 'subclass_name' => null],
                ['class_name' => 'Sorcerer', 'subclass_name' => null],
            ],
        ];

        $spell = $this->importer->importFromParsedData($data);

        $this->assertCount(2, $spell->classes);
        $classNames = $spell->classes->pluck('class_name')->toArray();
        $this->assertContains('Wizard', $classNames);
        $this->assertContains('Sorcerer', $classNames);
    }

    public function test_updates_existing_spell_instead_of_duplicating(): void
    {
        $spell = Spell::factory()->create(['name' => 'Existing Spell']);
        $initialId = $spell->id;

        $data = [
            'name' => 'Existing Spell',
            'level' => 2,
            'school_code' => 'C',
            'is_ritual' => false,
            'casting_time' => '1 action',
            'range' => '60 feet',
            'duration' => '1 minute',
            'has_verbal_component' => true,
            'has_somatic_component' => true,
            'has_material_component' => false,
            'material_description' => null,
            'material_cost_gp' => null,
            'material_consumed' => false,
            'description' => 'Updated description',
            'source_code' => 'PHB',
            'source_page' => 200,
            'classes' => [],
        ];

        $updatedSpell = $this->importer->importFromParsedData($data);

        $this->assertEquals($initialId, $updatedSpell->id);
        $this->assertEquals('Updated description', $updatedSpell->description);
        $this->assertEquals(1, Spell::count());
    }

    public function test_handles_subclass_associations(): void
    {
        $data = [
            'name' => 'Shield',
            'level' => 1,
            'school_code' => 'A',
            'is_ritual' => false,
            'casting_time' => '1 reaction',
            'range' => 'Self',
            'duration' => '1 round',
            'has_verbal_component' => true,
            'has_somatic_component' => true,
            'has_material_component' => false,
            'material_description' => null,
            'material_cost_gp' => null,
            'material_consumed' => false,
            'description' => 'An invisible barrier...',
            'source_code' => 'PHB',
            'source_page' => 275,
            'classes' => [
                ['class_name' => 'Fighter', 'subclass_name' => 'Eldritch Knight'],
                ['class_name' => 'Wizard', 'subclass_name' => null],
            ],
        ];

        $spell = $this->importer->importFromParsedData($data);

        $this->assertCount(2, $spell->classes);
        $this->assertEquals('Fighter', $spell->classes[0]->class_name);
        $this->assertEquals('Eldritch Knight', $spell->classes[0]->subclass_name);
        $this->assertEquals('Wizard', $spell->classes[1]->class_name);
        $this->assertNull($spell->classes[1]->subclass_name);
    }

    public function test_replaces_class_associations_on_update(): void
    {
        // Create initial spell with one class
        $spell = Spell::factory()->create(['name' => 'Test Spell']);
        $spell->classes()->create([
            'class_name' => 'OldClass',
            'subclass_name' => null,
        ]);

        $this->assertCount(1, $spell->classes);

        // Import with new classes
        $data = [
            'name' => 'Test Spell',
            'level' => 1,
            'school_code' => 'A',
            'is_ritual' => false,
            'casting_time' => '1 action',
            'range' => 'Touch',
            'duration' => 'Instantaneous',
            'has_verbal_component' => true,
            'has_somatic_component' => false,
            'has_material_component' => false,
            'material_description' => null,
            'material_cost_gp' => null,
            'material_consumed' => false,
            'description' => 'Test description',
            'source_code' => 'PHB',
            'source_page' => 100,
            'classes' => [
                ['class_name' => 'NewClass1', 'subclass_name' => null],
                ['class_name' => 'NewClass2', 'subclass_name' => null],
            ],
        ];

        $updatedSpell = $this->importer->importFromParsedData($data);

        $this->assertCount(2, $updatedSpell->classes);
        $this->assertEquals('NewClass1', $updatedSpell->classes[0]->class_name);
        $this->assertEquals('NewClass2', $updatedSpell->classes[1]->class_name);
    }
}

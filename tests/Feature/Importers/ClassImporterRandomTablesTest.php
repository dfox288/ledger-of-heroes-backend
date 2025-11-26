<?php

namespace Tests\Feature\Importers;

use App\Models\CharacterClass;
use App\Models\ClassFeature;
use App\Models\RandomTable;
use App\Services\Importers\ClassImporter;
use App\Services\Parsers\ClassXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class ClassImporterRandomTablesTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private ClassImporter $importer;

    private ClassXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new ClassImporter;
        $this->parser = new ClassXmlParser;
    }

    #[Test]
    public function it_creates_random_tables_from_roll_elements()
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <compendium>
            <class>
                <name>Rogue</name>
                <hd>8</hd>
                <autolevel level="1">
                    <feature>
                        <name>Sneak Attack</name>
                        <text>Beginning at 1st level, you know how to strike subtly and exploit a foe's distraction.</text>
                        <roll description="Extra Damage" level="1">1d6</roll>
                        <roll description="Extra Damage" level="3">2d6</roll>
                        <roll description="Extra Damage" level="5">3d6</roll>
                    </feature>
                </autolevel>
            </class>
        </compendium>
        XML;

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        // Verify class was created
        $this->assertDatabaseHas('classes', ['name' => 'Rogue']);
        $class = CharacterClass::where('name', 'Rogue')->first();

        // Verify feature was created
        $feature = ClassFeature::where('class_id', $class->id)
            ->where('feature_name', 'Sneak Attack')
            ->first();
        $this->assertNotNull($feature);

        // Verify random table was created
        $this->assertDatabaseHas('random_tables', [
            'reference_type' => ClassFeature::class,
            'reference_id' => $feature->id,
            'table_name' => 'Extra Damage',
            'dice_type' => 'd6',
        ]);

        $table = RandomTable::where('reference_type', ClassFeature::class)
            ->where('reference_id', $feature->id)
            ->first();

        // Verify table entries were created
        $this->assertEquals(3, $table->entries()->count());

        $entries = $table->entries()->orderBy('sort_order')->get();
        $this->assertEquals(1, $entries[0]->roll_min);
        $this->assertEquals('1d6', $entries[0]->result_text);
        $this->assertEquals(3, $entries[1]->roll_min);
        $this->assertEquals('2d6', $entries[1]->result_text);
        $this->assertEquals(5, $entries[2]->roll_min);
        $this->assertEquals('3d6', $entries[2]->result_text);
    }

    #[Test]
    public function it_handles_features_with_no_roll_elements()
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <compendium>
            <class>
                <name>Rogue</name>
                <hd>8</hd>
                <autolevel level="1">
                    <feature>
                        <name>Thieves' Cant</name>
                        <text>During your rogue training you learned thieves' cant...</text>
                    </feature>
                </autolevel>
            </class>
        </compendium>
        XML;

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Rogue')->first();
        $feature = ClassFeature::where('class_id', $class->id)
            ->where('feature_name', "Thieves' Cant")
            ->first();

        // Should have no random tables
        $this->assertEquals(0, $feature->randomTables()->count());
    }

    #[Test]
    public function it_groups_rolls_by_description()
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <compendium>
            <class>
                <name>Barbarian</name>
                <hd>12</hd>
                <autolevel level="1">
                    <feature>
                        <name>Wild Magic</name>
                        <text>The magical energy roiling inside you sometimes erupts...</text>
                        <roll description="Necrotic Damage">1d12</roll>
                        <roll description="Temporary Hit Points">1d12</roll>
                        <roll description="Force Damage">1d6</roll>
                        <roll description="Radiant Damage">1d6</roll>
                    </feature>
                </autolevel>
            </class>
        </compendium>
        XML;

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Barbarian')->first();
        $feature = ClassFeature::where('class_id', $class->id)
            ->where('feature_name', 'Wild Magic')
            ->first();

        // Should create 4 separate tables (one per unique description)
        $this->assertEquals(4, $feature->randomTables()->count());

        $necroticTable = $feature->randomTables()
            ->where('table_name', 'Necrotic Damage')
            ->first();
        $this->assertNotNull($necroticTable);
        $this->assertEquals('d12', $necroticTable->dice_type);
        $this->assertEquals(1, $necroticTable->entries()->count());
    }

    #[Test]
    public function it_handles_rolls_without_level_attribute()
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <compendium>
            <class>
                <name>Wizard</name>
                <hd>6</hd>
                <autolevel level="1">
                    <feature>
                        <name>Arcane Recovery</name>
                        <text>You can recover some spell slots...</text>
                        <roll description="Spell Slots">2d6</roll>
                    </feature>
                </autolevel>
            </class>
        </compendium>
        XML;

        $classes = $this->parser->parse($xml);
        $this->importer->import($classes[0]);

        $class = CharacterClass::where('name', 'Wizard')->first();
        $feature = ClassFeature::where('class_id', $class->id)
            ->where('feature_name', 'Arcane Recovery')
            ->first();

        $table = $feature->randomTables()->first();
        $this->assertNotNull($table);

        $entry = $table->entries()->first();
        // Should default roll_min/roll_max to 1 when no level attribute
        $this->assertEquals(1, $entry->roll_min);
        $this->assertEquals(1, $entry->roll_max);
    }
}

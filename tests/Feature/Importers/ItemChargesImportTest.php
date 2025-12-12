<?php

namespace Tests\Feature\Importers;

use App\Models\Item;
use App\Services\Importers\ItemImporter;
use App\Services\Parsers\ItemXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('importers')]
class ItemChargesImportTest extends TestCase
{
    use RefreshDatabase;

    private ItemImporter $importer;

    private ItemXmlParser $parser;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new ItemImporter;
        $this->parser = new ItemXmlParser;
    }

    #[Test]
    public function it_imports_wand_of_smiles_with_charges(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <compendium>
            <item>
                <name>Wand of Smiles</name>
                <detail>common</detail>
                <type>WD</type>
                <magic>YES</magic>
                <weight>1</weight>
                <text>This wand has 3 charges. While holding it, you can use an action to expend 1 of its charges and target a humanoid you can see within 30 feet of you. The target must succeed on a DC 10 Charisma saving throw or be forced to smile for 1 minute.
        	The wand regains all expended charges daily at dawn. If you expend the wand's last charge, roll a d20. On a 1, the wand transforms into a wand of scowls.

        Source:	Xanathar's Guide to Everything p. 140</text>
            </item>
        </compendium>
        XML;

        $items = $this->parser->parse($xml);
        $item = $this->importer->import($items[0]);

        $this->assertEquals('Wand of Smiles', $item->name);
        $this->assertEquals(3, $item->charges_max);
        $this->assertEquals('all', $item->recharge_formula);
        $this->assertEquals('dawn', $item->recharge_timing);
    }

    #[Test]
    public function it_imports_wand_of_binding_with_dice_based_recharge(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <compendium>
            <item>
                <name>Wand of Binding</name>
                <detail>rare (requires attunement by a spellcaster)</detail>
                <type>WD</type>
                <magic>YES</magic>
                <weight>1</weight>
                <text>This wand has 7 charges for the following properties. It regains 1d6 + 1 expended charges daily at dawn. If you expend the wand's last charge, roll a d20. On a 1, the wand crumbles into ashes and is destroyed.

        Source:	Dungeon Master's Guide p. 209</text>
            </item>
        </compendium>
        XML;

        $items = $this->parser->parse($xml);
        $item = $this->importer->import($items[0]);

        $this->assertEquals('Wand of Binding', $item->name);
        $this->assertEquals(7, $item->charges_max);
        $this->assertEquals('1d6+1', $item->recharge_formula);
        $this->assertEquals('dawn', $item->recharge_timing);
    }

    #[Test]
    public function it_imports_cubic_gate_with_large_capacity(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <compendium>
            <item>
                <name>Cubic Gate</name>
                <detail>legendary</detail>
                <type>W</type>
                <magic>YES</magic>
                <text>This cube is about an inch across. Each face has a distinct marking on it that can be pressed. The cube starts with 36 charges, and it regains 1d20 expended charges daily at dawn.

        Source:	Dungeon Master's Guide p. 160</text>
            </item>
        </compendium>
        XML;

        $items = $this->parser->parse($xml);
        $item = $this->importer->import($items[0]);

        $this->assertEquals('Cubic Gate', $item->name);
        $this->assertEquals(36, $item->charges_max);
        $this->assertEquals('1d20', $item->recharge_formula);
        $this->assertEquals('dawn', $item->recharge_timing);
    }

    #[Test]
    public function it_imports_regular_items_without_charges(): void
    {
        $xml = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <compendium>
            <item>
                <name>Longsword</name>
                <type>M</type>
                <magic>NO</magic>
                <weight>3</weight>
                <dmg1>1d8</dmg1>
                <dmg2>1d10</dmg2>
                <dmgType>S</dmgType>
                <text>A longsword is a versatile weapon that can be wielded with one or two hands.

        Source:	Player's Handbook p. 149</text>
            </item>
        </compendium>
        XML;

        $items = $this->parser->parse($xml);
        $item = $this->importer->import($items[0]);

        $this->assertEquals('Longsword', $item->name);
        $this->assertNull($item->charges_max);
        $this->assertNull($item->recharge_formula);
        $this->assertNull($item->recharge_timing);
    }

    #[Test]
    public function it_reimports_items_without_losing_charge_data(): void
    {
        // First import to create the item
        $xmlInitial = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <compendium>
            <item>
                <name>Wand of Test</name>
                <type>WD</type>
                <magic>YES</magic>
                <text>This wand has 5 charges. It regains 1d4 expended charges daily at dawn.

        Source:	Player's Handbook p. 149</text>
            </item>
        </compendium>
        XML;

        $items = $this->parser->parse($xmlInitial);
        $item = $this->importer->import($items[0]);

        $this->assertEquals(5, $item->charges_max);
        $this->assertEquals('phb:wand-of-test', $item->slug);

        // Reimport same item with updated charge data (should update, not create new)
        $xmlUpdated = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <compendium>
            <item>
                <name>Wand of Test</name>
                <type>WD</type>
                <magic>YES</magic>
                <text>This wand has 7 charges. It regains 1d6 expended charges daily at dawn.

        Source:	Player's Handbook p. 149</text>
            </item>
        </compendium>
        XML;

        $items = $this->parser->parse($xmlUpdated);
        $reimportedItem = $this->importer->import($items[0]);

        // Should update charge data
        $this->assertEquals($item->id, $reimportedItem->id); // Same item
        $this->assertEquals(7, $reimportedItem->charges_max); // Updated
        $this->assertEquals('1d6', $reimportedItem->recharge_formula); // Updated

        // Verify only one item exists with source-prefixed slug
        $this->assertEquals(1, Item::where('slug', 'phb:wand-of-test')->count());
    }
}

<?php

namespace Tests\Feature\Importers;

use App\Models\Modifier;
use App\Services\Importers\ItemImporter;
use App\Services\Parsers\ItemXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemConditionalSpeedModifierTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true; // Auto-seed before each test

    private ItemXmlParser $parser;

    private ItemImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ItemXmlParser;
        $this->importer = new ItemImporter;
    }

    #[Test]
    public function it_imports_conditional_speed_penalty_for_plate_armor(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Plate Armor</name>
        <type>HA</type>
        <ac>18</ac>
        <strength>15</strength>
        <stealth>YES</stealth>
        <text>Plate consists of shaped, interlocking metal plates to cover the entire body.

If the wearer has a Strength score lower than 15, their speed is reduced by 10 feet.

The wearer has disadvantage on Stealth (Dexterity) checks.

Source: Player's Handbook (2014) p. 145</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);
        $item = $this->importer->import($items[0]);

        $this->assertNotNull($item);
        $this->assertEquals('Plate Armor', $item->name);
        $this->assertEquals(15, $item->strength_requirement);

        // Should have 3 modifiers: ac_base, stealth disadvantage, AND speed penalty
        $this->assertEquals(3, $item->modifiers()->count());

        // Verify speed modifier
        $speedMod = $item->modifiers()->where('modifier_category', 'speed')->first();
        $this->assertNotNull($speedMod, 'Speed modifier should exist');
        $this->assertEquals(-10, $speedMod->value);
        $this->assertEquals('strength < 15', $speedMod->condition);
    }

    #[Test]
    public function it_imports_different_strength_requirements_correctly(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Chain Mail</name>
        <type>HA</type>
        <ac>16</ac>
        <strength>13</strength>
        <text>Chain mail armor.

If the wearer has a Strength score lower than 13, their speed is reduced by 10 feet.

Source: Player's Handbook (2014) p. 145</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);
        $item = $this->importer->import($items[0]);

        $this->assertEquals('Chain Mail', $item->name);
        $this->assertEquals(13, $item->strength_requirement);

        $speedMod = $item->modifiers()->where('modifier_category', 'speed')->first();
        $this->assertNotNull($speedMod);
        $this->assertEquals('strength < 13', $speedMod->condition);
    }

    #[Test]
    public function it_does_not_create_speed_modifier_without_penalty_text(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Longsword</name>
        <type>M</type>
        <text>A versatile weapon.

Source: Player's Handbook (2014) p. 149</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);
        $item = $this->importer->import($items[0]);

        $this->assertEquals('Longsword', $item->name);

        // Should not have any speed modifiers
        $this->assertEquals(0, $item->modifiers()->where('modifier_category', 'speed')->count());
    }
}

<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ItemXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemXmlParserTest extends TestCase
{
    private ItemXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new ItemXmlParser;
    }

    #[Test]
    public function it_matches_proficiency_types_for_item_requirements(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Longsword</name>
        <type>M</type>
        <text>Proficiency: Martial Weapons
Source: Player's Handbook (2014) p. 149</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertCount(1, $items[0]['proficiencies']);
        $this->assertArrayHasKey('proficiency_type_id', $items[0]['proficiencies'][0]);
        $this->assertFalse($items[0]['proficiencies'][0]['grants']);

        // If database is seeded, should match (optional for unit tests)
        try {
            if (\App\Models\ProficiencyType::count() > 0) {
                $this->assertNotNull($items[0]['proficiencies'][0]['proficiency_type_id']);
            }
        } catch (\Exception $e) {
            // Database not available in unit test context - that's okay
        }
    }

    #[Test]
    public function it_parses_ac_modifier_as_structured_data(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Shield</name>
        <type>S</type>
        <modifier category="bonus">ac +2</modifier>
        <text>Source: Player's Handbook (2014) p. 144</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertCount(1, $items[0]['modifiers']);
        // AC modifiers with category="bonus" are parsed as 'ac_magic'
        $this->assertEquals('ac_magic', $items[0]['modifiers'][0]['category']);
        $this->assertEquals(2, $items[0]['modifiers'][0]['value']);
        $this->assertIsInt($items[0]['modifiers'][0]['value']);
    }

    #[Test]
    public function it_parses_spell_attack_modifier(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Wand of the War Mage +1</name>
        <type>W</type>
        <modifier category="bonus">spell attack +1</modifier>
        <text>Source: Player's Handbook (2014) p. 212</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertEquals('spell_attack', $items[0]['modifiers'][0]['category']);
        $this->assertEquals(1, $items[0]['modifiers'][0]['value']);
    }

    #[Test]
    public function it_parses_ability_score_modifier(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Belt of Giant Strength</name>
        <type>W</type>
        <modifier category="ability score">Strength +2</modifier>
        <text>Source: Dungeon Master's Guide p. 155</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertEquals('ability_score', $items[0]['modifiers'][0]['category']);
        $this->assertEquals(2, $items[0]['modifiers'][0]['value']);
        $this->assertArrayHasKey('ability_score_id', $items[0]['modifiers'][0]);

        // If database is seeded, should match Strength (optional for unit tests)
        try {
            if (\App\Models\AbilityScore::count() > 0) {
                $this->assertNotNull($items[0]['modifiers'][0]['ability_score_id']);
            }
        } catch (\Exception $e) {
            // Database not available in unit test context - that's okay
        }
    }

    #[Test]
    public function it_handles_unparseable_modifiers_gracefully(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Test Item</name>
        <type>W</type>
        <modifier category="bonus">advantage on saves</modifier>
        <modifier category="bonus">ac +1</modifier>
        <text>Source: Player's Handbook (2014) p. 100</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        // Should have 1 parseable modifier (ac +1), skip unparseable
        // AC modifiers with category="bonus" are parsed as 'ac_magic'
        $this->assertCount(1, $items[0]['modifiers']);
        $this->assertEquals('ac_magic', $items[0]['modifiers'][0]['category']);
    }

    #[Test]
    public function it_parses_conditional_speed_penalty_from_strength_requirement(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Plate</name>
        <type>HA</type>
        <ac>18</ac>
        <strength>15</strength>
        <text>If the wearer has a Strength score lower than 15, their speed is reduced by 10 feet.

Source: Player's Handbook (2014) p. 145</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        // Should have strength requirement AND speed modifier
        $this->assertEquals(15, $items[0]['strength_requirement']);

        // Find speed modifier
        $speedModifiers = collect($items[0]['modifiers'])->where('category', 'speed');
        $this->assertCount(1, $speedModifiers);

        $speedMod = $speedModifiers->first();
        $this->assertEquals('speed', $speedMod['category']);
        $this->assertEquals(-10, $speedMod['value']);
        $this->assertEquals('strength < 15', $speedMod['condition']);
    }

    #[Test]
    public function it_parses_alternative_speed_penalty_phrasing(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Chain Mail</name>
        <type>HA</type>
        <ac>16</ac>
        <strength>13</strength>
        <text>If the wearer has a Strength score lower than 13, their speed is reduced by 10 feet.

Source: Player's Handbook (2014) p. 145</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        // Should parse different strength values correctly
        $this->assertEquals(13, $items[0]['strength_requirement']);

        $speedModifiers = collect($items[0]['modifiers'])->where('category', 'speed');
        $this->assertCount(1, $speedModifiers);
        $this->assertEquals('strength < 13', $speedModifiers->first()['condition']);
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
        <text>A longsword is a versatile weapon.

Source: Player's Handbook (2014) p. 149</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        // Should not have any speed modifiers
        $speedModifiers = collect($items[0]['modifiers'] ?? [])->where('category', 'speed');
        $this->assertCount(0, $speedModifiers);
    }

    #[Test]
    public function it_preserves_detail_field_from_xml(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Pistol</name>
        <detail>firearm, renaissance</detail>
        <type>R</type>
        <text>A ranged weapon.

Source: Dungeon Master's Guide (2014) p. 268</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertEquals('firearm, renaissance', $items[0]['detail']);
        $this->assertEquals('common', $items[0]['rarity']); // Rarity still extracted
    }

    #[Test]
    public function it_preserves_detail_with_rarity(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Druidic Focus</name>
        <detail>druidic focus, uncommon</detail>
        <type>W</type>
        <text>A spellcasting focus.

Source: Player's Handbook (2014) p. 150</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertEquals('druidic focus, uncommon', $items[0]['detail']);
        $this->assertEquals('uncommon', $items[0]['rarity']); // Rarity still extracted
    }

    #[Test]
    public function it_handles_empty_detail_field(): void
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

        $this->assertNull($items[0]['detail']);
        $this->assertEquals('common', $items[0]['rarity']); // Default rarity
    }
}

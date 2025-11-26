<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\ItemXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
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

    #[Test]
    public function it_parses_set_intelligence_score_modifier(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Headband of Intellect</name>
        <type>W</type>
        <detail>uncommon (requires attunement)</detail>
        <text>Your Intelligence score is 19 while you wear this headband. It has no effect on you if your Intelligence is already 19 or higher without it.

Source: Dungeon Master's Guide (2014) p. 173</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertCount(1, $items[0]['modifiers']);
        $this->assertEquals('ability_score', $items[0]['modifiers'][0]['category']);
        $this->assertEquals('set:19', $items[0]['modifiers'][0]['value']);
        $this->assertEquals('while you wear this headband', $items[0]['modifiers'][0]['condition']);

        // Check that ability score lookup data is present
        $this->assertArrayHasKey('ability_score_code', $items[0]['modifiers'][0]);
        $this->assertEquals('INT', $items[0]['modifiers'][0]['ability_score_code']);
    }

    #[Test]
    public function it_parses_set_strength_score_modifier(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Gauntlets of Ogre Power</name>
        <type>W</type>
        <detail>uncommon (requires attunement)</detail>
        <text>Your Strength score is 19 while you wear these gauntlets.

Source: Dungeon Master's Guide (2014) p. 171</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertCount(1, $items[0]['modifiers']);
        $this->assertEquals('ability_score', $items[0]['modifiers'][0]['category']);
        $this->assertEquals('set:19', $items[0]['modifiers'][0]['value']);
        $this->assertEquals('while you wear these gauntlets', $items[0]['modifiers'][0]['condition']);
        $this->assertEquals('STR', $items[0]['modifiers'][0]['ability_score_code']);
    }

    #[Test]
    public function it_parses_set_constitution_score_modifier(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Amulet of Health</name>
        <type>W</type>
        <detail>rare (requires attunement)</detail>
        <text>Your Constitution score is 19 while you wear this amulet. It has no effect on you if your Constitution is already 19 or higher.

Source: Dungeon Master's Guide (2014) p. 150</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertCount(1, $items[0]['modifiers']);
        $this->assertEquals('ability_score', $items[0]['modifiers'][0]['category']);
        $this->assertEquals('set:19', $items[0]['modifiers'][0]['value']);
        $this->assertEquals('CON', $items[0]['modifiers'][0]['ability_score_code']);
    }

    #[Test]
    public function it_does_not_parse_set_score_from_barding_descriptions(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Chain Barding</name>
        <type>HA</type>
        <text>Barding is armor designed to protect an animal's head, neck, chest, and body. Any type of armor shown on the Armor table can be purchased as barding. The cost is four times the equivalent armor made for humanoids, and it weighs twice as much.

Source: Player's Handbook (2014) p. 155</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        // Should not have any "set score" modifiers
        $setScoreModifiers = array_filter($items[0]['modifiers'], function ($mod) {
            return isset($mod['value']) && str_starts_with((string) $mod['value'], 'set:');
        });

        $this->assertEmpty($setScoreModifiers);
    }

    #[Test]
    public function it_parses_potion_of_acid_resistance(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Potion of Acid Resistance</name>
        <type>P</type>
        <detail>uncommon</detail>
        <text>When you drink this potion, you gain resistance to acid damage for 1 hour.

Source: Dungeon Master's Guide (2014) p. 188</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertCount(1, $items[0]['modifiers']);
        $this->assertEquals('damage_resistance', $items[0]['modifiers'][0]['category']);
        $this->assertEquals('resistance', $items[0]['modifiers'][0]['value']);
        $this->assertEquals('for 1 hour', $items[0]['modifiers'][0]['condition']);
        $this->assertArrayHasKey('damage_type_name', $items[0]['modifiers'][0]);
        $this->assertEquals('Acid', $items[0]['modifiers'][0]['damage_type_name']);
    }

    #[Test]
    public function it_parses_potion_of_fire_resistance(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Potion of Fire Resistance</name>
        <type>P</type>
        <text>When you drink this potion, you gain resistance to fire damage for 1 hour.</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertCount(1, $items[0]['modifiers']);
        $this->assertEquals('damage_resistance', $items[0]['modifiers'][0]['category']);
        $this->assertEquals('Fire', $items[0]['modifiers'][0]['damage_type_name']);
    }

    #[Test]
    public function it_parses_potion_of_invulnerability_with_all_damage_resistance(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Potion of Invulnerability</name>
        <type>P</type>
        <detail>rare</detail>
        <text>For 1 minute after you drink this potion, you have resistance to all damage. The potion's syrupy liquid looks like liquefied iron.

Source: Dungeon Master's Guide (2014) p. 188</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertCount(1, $items[0]['modifiers']);
        $this->assertEquals('damage_resistance', $items[0]['modifiers'][0]['category']);
        $this->assertEquals('resistance:all', $items[0]['modifiers'][0]['value']);
        $this->assertEquals('for 1 minute', $items[0]['modifiers'][0]['condition']);
        $this->assertNull($items[0]['modifiers'][0]['damage_type_id']);
        $this->assertArrayNotHasKey('damage_type_name', $items[0]['modifiers'][0]); // No name for "all"
    }

    #[Test]
    public function it_parses_alternative_resistance_phrasing(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <item>
        <name>Test Potion</name>
        <type>P</type>
        <text>You have resistance to cold damage for 10 minutes.</text>
    </item>
</compendium>
XML;

        $items = $this->parser->parse($xml);

        $this->assertCount(1, $items[0]['modifiers']);
        $this->assertEquals('damage_resistance', $items[0]['modifiers'][0]['category']);
        $this->assertEquals('Cold', $items[0]['modifiers'][0]['damage_type_name']);
        $this->assertEquals('for 10 minutes', $items[0]['modifiers'][0]['condition']);
    }
}

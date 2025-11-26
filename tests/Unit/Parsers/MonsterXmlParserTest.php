<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\MonsterXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class MonsterXmlParserTest extends TestCase
{
    protected MonsterXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MonsterXmlParser;
    }

    #[Test]
    public function it_parses_armor_class_from_simple_format()
    {
        $result = $this->invokeMethod($this->parser, 'parseArmorClass', ['17']);
        $this->assertEquals(17, $result);
    }

    #[Test]
    public function it_parses_armor_class_with_type()
    {
        $result = $this->invokeMethod($this->parser, 'parseArmorClass', ['17 (natural armor)']);
        $this->assertEquals(17, $result);
    }

    #[Test]
    public function it_extracts_armor_type()
    {
        $result = $this->invokeMethod($this->parser, 'extractArmorType', ['17 (natural armor)']);
        $this->assertEquals('natural armor', $result);
    }

    #[Test]
    public function it_returns_null_armor_type_when_not_specified()
    {
        $result = $this->invokeMethod($this->parser, 'extractArmorType', ['17']);
        $this->assertNull($result);
    }

    #[Test]
    public function it_parses_hit_points_average()
    {
        $result = $this->invokeMethod($this->parser, 'parseHitPoints', ['135 (18d10+36)']);
        $this->assertEquals(135, $result);
    }

    #[Test]
    public function it_parses_zero_hit_points()
    {
        $result = $this->invokeMethod($this->parser, 'parseHitPoints', ['0']);
        $this->assertEquals(0, $result);
    }

    #[Test]
    public function it_extracts_hit_dice()
    {
        $result = $this->invokeMethod($this->parser, 'extractHitDice', ['135 (18d10+36)']);
        $this->assertEquals('18d10+36', $result);
    }

    #[Test]
    public function it_extracts_hit_dice_without_modifier()
    {
        $result = $this->invokeMethod($this->parser, 'extractHitDice', ['19 (3d10+3)']);
        $this->assertEquals('3d10+3', $result);
    }

    #[Test]
    public function it_returns_empty_string_when_no_hit_dice()
    {
        $result = $this->invokeMethod($this->parser, 'extractHitDice', ['0']);
        $this->assertEquals('', $result);
    }

    #[Test]
    public function it_parses_single_speed_type()
    {
        $result = $this->invokeMethod($this->parser, 'parseSpeed', ['walk 30 ft.']);

        $this->assertEquals([
            'speed_walk' => 30,
            'speed_fly' => null,
            'speed_swim' => null,
            'speed_burrow' => null,
            'speed_climb' => null,
            'can_hover' => false,
        ], $result);
    }

    #[Test]
    public function it_parses_multiple_speed_types()
    {
        $result = $this->invokeMethod($this->parser, 'parseSpeed', ['walk 20 ft., fly 50 ft., swim 30 ft.']);

        $this->assertEquals([
            'speed_walk' => 20,
            'speed_fly' => 50,
            'speed_swim' => 30,
            'speed_burrow' => null,
            'speed_climb' => null,
            'can_hover' => false,
        ], $result);
    }

    #[Test]
    public function it_detects_hover_ability()
    {
        $result = $this->invokeMethod($this->parser, 'parseSpeed', ['walk 0 ft., fly 60 ft. (hover)']);

        $this->assertEquals([
            'speed_walk' => 0,
            'speed_fly' => 60,
            'speed_swim' => null,
            'speed_burrow' => null,
            'speed_climb' => null,
            'can_hover' => true,
        ], $result);
    }

    #[Test]
    public function it_parses_all_speed_types()
    {
        $result = $this->invokeMethod($this->parser, 'parseSpeed', ['walk 40 ft., fly 80 ft., swim 40 ft., burrow 20 ft., climb 30 ft.']);

        $this->assertEquals([
            'speed_walk' => 40,
            'speed_fly' => 80,
            'speed_swim' => 40,
            'speed_burrow' => 20,
            'speed_climb' => 30,
            'can_hover' => false,
        ], $result);
    }

    #[Test]
    public function it_parses_saving_throws()
    {
        $result = $this->invokeMethod($this->parser, 'parseSavingThrows', ['Dex +7, Con +16, Wis +9']);

        $this->assertEquals([
            ['ability' => 'DEX', 'bonus' => 7],
            ['ability' => 'CON', 'bonus' => 16],
            ['ability' => 'WIS', 'bonus' => 9],
        ], $result);
    }

    #[Test]
    public function it_parses_negative_saving_throw_bonuses()
    {
        $result = $this->invokeMethod($this->parser, 'parseSavingThrows', ['Str -2, Dex +1']);

        $this->assertEquals([
            ['ability' => 'STR', 'bonus' => -2],
            ['ability' => 'DEX', 'bonus' => 1],
        ], $result);
    }

    #[Test]
    public function it_returns_empty_array_for_no_saving_throws()
    {
        $result = $this->invokeMethod($this->parser, 'parseSavingThrows', ['']);
        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_parses_skills()
    {
        $result = $this->invokeMethod($this->parser, 'parseSkills', ['Perception +16, Stealth +7']);

        $this->assertEquals([
            ['skill' => 'Perception', 'bonus' => 16],
            ['skill' => 'Stealth', 'bonus' => 7],
        ], $result);
    }

    #[Test]
    public function it_parses_multi_word_skills()
    {
        $result = $this->invokeMethod($this->parser, 'parseSkills', ['Animal Handling +5, Sleight of Hand +8']);

        $this->assertEquals([
            ['skill' => 'Animal Handling', 'bonus' => 5],
            ['skill' => 'Sleight of Hand', 'bonus' => 8],
        ], $result);
    }

    #[Test]
    public function it_returns_empty_array_for_no_skills()
    {
        $result = $this->invokeMethod($this->parser, 'parseSkills', ['']);
        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_calculates_xp_for_fractional_cr()
    {
        $this->assertEquals(10, $this->invokeMethod($this->parser, 'calculateXP', ['0']));
        $this->assertEquals(25, $this->invokeMethod($this->parser, 'calculateXP', ['1/8']));
        $this->assertEquals(50, $this->invokeMethod($this->parser, 'calculateXP', ['1/4']));
        $this->assertEquals(100, $this->invokeMethod($this->parser, 'calculateXP', ['1/2']));
    }

    #[Test]
    public function it_calculates_xp_for_integer_cr()
    {
        $this->assertEquals(200, $this->invokeMethod($this->parser, 'calculateXP', ['1']));
        $this->assertEquals(450, $this->invokeMethod($this->parser, 'calculateXP', ['2']));
        $this->assertEquals(5900, $this->invokeMethod($this->parser, 'calculateXP', ['10']));
        $this->assertEquals(62000, $this->invokeMethod($this->parser, 'calculateXP', ['24']));
    }

    #[Test]
    public function it_calculates_xp_for_high_cr()
    {
        $this->assertEquals(155000, $this->invokeMethod($this->parser, 'calculateXP', ['30']));
    }

    #[Test]
    public function it_returns_zero_xp_for_unknown_cr()
    {
        $this->assertEquals(0, $this->invokeMethod($this->parser, 'calculateXP', ['']));
        $this->assertEquals(0, $this->invokeMethod($this->parser, 'calculateXP', ['99']));
    }

    #[Test]
    public function it_parses_attack_data_as_json()
    {
        $xml = new \SimpleXMLElement('<action><attack>Bludgeoning Damage|+9|2d6+5</attack></action>');
        $result = $this->invokeMethod($this->parser, 'parseAttackData', [$xml->attack]);

        $this->assertEquals('["Bludgeoning Damage|+9|2d6+5"]', $result);
    }

    #[Test]
    public function it_parses_multiple_attack_data_elements()
    {
        $xml = new \SimpleXMLElement('<action><attack>Slashing Damage||1d8+3</attack><attack>Necrotic Damage||1d8</attack></action>');
        $result = $this->invokeMethod($this->parser, 'parseAttackData', [$xml->attack]);

        $decoded = json_decode($result, true);
        $this->assertCount(2, $decoded);
        $this->assertEquals('Slashing Damage||1d8+3', $decoded[0]);
        $this->assertEquals('Necrotic Damage||1d8', $decoded[1]);
    }

    #[Test]
    public function it_returns_null_for_no_attack_data()
    {
        $xml = new \SimpleXMLElement('<action></action>');
        $result = $this->invokeMethod($this->parser, 'parseAttackData', [$xml->attack]);

        $this->assertNull($result);
    }

    #[Test]
    public function it_parses_traits()
    {
        $xml = new \SimpleXMLElement('
            <monster>
                <trait>
                    <name>Amphibious</name>
                    <text>The aboleth can breathe air and water.</text>
                </trait>
                <trait>
                    <name>Legendary Resistance</name>
                    <recharge>3/DAY</recharge>
                    <text>If the dragon fails a saving throw...</text>
                </trait>
            </monster>
        ');

        $result = $this->invokeMethod($this->parser, 'parseTraits', [$xml->trait]);

        $this->assertCount(2, $result);
        $this->assertEquals('Amphibious', $result[0]['name']);
        $this->assertEquals('The aboleth can breathe air and water.', $result[0]['description']);
        $this->assertNull($result[0]['attack_data']);
        $this->assertNull($result[0]['recharge']);
        $this->assertEquals(0, $result[0]['sort_order']);

        $this->assertEquals('Legendary Resistance', $result[1]['name']);
        $this->assertEquals('3/DAY', $result[1]['recharge']);
        $this->assertEquals(1, $result[1]['sort_order']);
    }

    #[Test]
    public function it_parses_traits_with_attack_data()
    {
        $xml = new \SimpleXMLElement('
            <monster>
                <trait>
                    <name>Incorporeal Movement</name>
                    <text>The avatar can move through other creatures...</text>
                    <attack>Force Damage||1d10</attack>
                </trait>
            </monster>
        ');

        $result = $this->invokeMethod($this->parser, 'parseTraits', [$xml->trait]);

        $this->assertCount(1, $result);
        $this->assertEquals('Incorporeal Movement', $result[0]['name']);
        $this->assertEquals('["Force Damage||1d10"]', $result[0]['attack_data']);
    }

    #[Test]
    public function it_parses_actions()
    {
        $xml = new \SimpleXMLElement('
            <monster>
                <action>
                    <name>Multiattack</name>
                    <text>The aboleth makes three tentacle attacks.</text>
                </action>
                <action>
                    <name>Tentacle</name>
                    <text>Melee Weapon Attack: +9 to hit...</text>
                    <attack>Bludgeoning Damage|+9|2d6+5</attack>
                </action>
            </monster>
        ');

        $result = $this->invokeMethod($this->parser, 'parseActions', [$xml->action]);

        $this->assertCount(2, $result);
        $this->assertEquals('action', $result[0]['action_type']);
        $this->assertEquals('Multiattack', $result[0]['name']);
        $this->assertNull($result[0]['attack_data']);
        $this->assertNull($result[0]['recharge']);
        $this->assertEquals(0, $result[0]['sort_order']);

        $this->assertEquals('Tentacle', $result[1]['name']);
        $this->assertEquals('["Bludgeoning Damage|+9|2d6+5"]', $result[1]['attack_data']);
        $this->assertEquals(1, $result[1]['sort_order']);
    }

    #[Test]
    public function it_parses_actions_with_custom_action_type()
    {
        $xml = new \SimpleXMLElement('
            <monster>
                <reaction>
                    <name>Parry</name>
                    <text>The monster adds 2 to its AC...</text>
                </reaction>
            </monster>
        ');

        $result = $this->invokeMethod($this->parser, 'parseActions', [$xml->reaction, 'reaction']);

        $this->assertCount(1, $result);
        $this->assertEquals('reaction', $result[0]['action_type']);
        $this->assertEquals('Parry', $result[0]['name']);
    }

    #[Test]
    public function it_parses_legendary_actions()
    {
        $xml = new \SimpleXMLElement('
            <monster>
                <legendary>
                    <name>Legendary Actions (3/Turn)</name>
                    <recharge>3/TURN</recharge>
                    <text>The aboleth can take 3 legendary actions...</text>
                </legendary>
                <legendary>
                    <name>Psychic Drain</name>
                    <text>One creature charmed by the aboleth...</text>
                    <attack>Psychic Damage||3d6</attack>
                </legendary>
            </monster>
        ');

        $result = $this->invokeMethod($this->parser, 'parseLegendary', [$xml->legendary]);

        $this->assertCount(2, $result);
        $this->assertEquals('Legendary Actions (3/Turn)', $result[0]['name']);
        $this->assertEquals('3/TURN', $result[0]['recharge']);
        $this->assertNull($result[0]['category']);
        $this->assertEquals(0, $result[0]['sort_order']);

        $this->assertEquals('Psychic Drain', $result[1]['name']);
        $this->assertEquals('["Psychic Damage||3d6"]', $result[1]['attack_data']);
        $this->assertEquals(1, $result[1]['sort_order']);
    }

    #[Test]
    public function it_parses_legendary_actions_with_lair_category()
    {
        $xml = new \SimpleXMLElement('
            <monster>
                <legendary category="lair">
                    <name>Lair Actions</name>
                    <text>When fighting inside its lair...</text>
                </legendary>
            </monster>
        ');

        $result = $this->invokeMethod($this->parser, 'parseLegendary', [$xml->legendary]);

        $this->assertCount(1, $result);
        $this->assertEquals('Lair Actions', $result[0]['name']);
        $this->assertEquals('lair', $result[0]['category']);
    }

    #[Test]
    public function it_parses_description()
    {
        $xml = new \SimpleXMLElement('<description>Before the coming of the gods...</description>');
        $result = $this->invokeMethod($this->parser, 'parseDescription', [$xml]);

        $this->assertEquals('Before the coming of the gods...', $result);
    }

    #[Test]
    public function it_returns_null_for_empty_description()
    {
        $xml = new \SimpleXMLElement('<monster></monster>');
        $result = $this->invokeMethod($this->parser, 'parseDescription', [$xml->description]);

        $this->assertNull($result);
    }

    #[Test]
    public function it_parses_complete_monster_from_xml_file()
    {
        // Create a temporary XML file for testing
        $xmlContent = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <monster>
        <name>Test Monster</name>
        <size>L</size>
        <type>aberration</type>
        <alignment>Lawful Evil</alignment>
        <ac>17 (natural armor)</ac>
        <hp>135 (18d10+36)</hp>
        <speed>walk 10 ft., swim 40 ft.</speed>
        <str>21</str>
        <dex>9</dex>
        <con>15</con>
        <int>18</int>
        <wis>15</wis>
        <cha>18</cha>
        <save>Con +6, Int +8, Wis +6</save>
        <skill>History +12, Perception +10</skill>
        <passive>20</passive>
        <languages>Deep Speech, telepathy 120 ft.</languages>
        <cr>10</cr>
        <vulnerable/>
        <resist/>
        <immune/>
        <conditionImmune/>
        <senses>darkvision 120 ft.</senses>
        <trait>
            <name>Amphibious</name>
            <text>The monster can breathe air and water.</text>
        </trait>
        <action>
            <name>Tentacle</name>
            <text>Melee Weapon Attack: +9 to hit...</text>
            <attack>Bludgeoning Damage|+9|2d6+5</attack>
        </action>
        <description>Test description</description>
        <environment>underdark</environment>
    </monster>
</compendium>
XML;

        $tempFile = tempnam(sys_get_temp_dir(), 'monster_test_');
        file_put_contents($tempFile, $xmlContent);

        try {
            $result = $this->parser->parse($tempFile);

            $this->assertIsArray($result);
            $this->assertCount(1, $result);

            $monster = $result[0];

            // Basic info
            $this->assertEquals('Test Monster', $monster['name']);
            $this->assertEquals('L', $monster['size']);
            $this->assertEquals('aberration', $monster['type']);
            $this->assertEquals('Lawful Evil', $monster['alignment']);

            // Combat stats
            $this->assertEquals(17, $monster['armor_class']);
            $this->assertEquals('natural armor', $monster['armor_type']);
            $this->assertEquals(135, $monster['hit_points']);
            $this->assertEquals('18d10+36', $monster['hit_dice']);

            // Speeds
            $this->assertEquals(10, $monster['speed_walk']);
            $this->assertNull($monster['speed_fly']);
            $this->assertEquals(40, $monster['speed_swim']);
            $this->assertNull($monster['speed_burrow']);
            $this->assertNull($monster['speed_climb']);
            $this->assertFalse($monster['can_hover']);

            // Ability scores
            $this->assertEquals(21, $monster['strength']);
            $this->assertEquals(9, $monster['dexterity']);
            $this->assertEquals(15, $monster['constitution']);
            $this->assertEquals(18, $monster['intelligence']);
            $this->assertEquals(15, $monster['wisdom']);
            $this->assertEquals(18, $monster['charisma']);

            // Challenge
            $this->assertEquals('10', $monster['challenge_rating']);
            $this->assertEquals(5900, $monster['experience_points']);

            // Saving throws
            $this->assertCount(3, $monster['saving_throws']);
            $this->assertEquals(['ability' => 'CON', 'bonus' => 6], $monster['saving_throws'][0]);

            // Skills
            $this->assertCount(2, $monster['skills']);
            $this->assertEquals(['skill' => 'History', 'bonus' => 12], $monster['skills'][0]);

            // Other attributes
            $this->assertNull($monster['damage_vulnerabilities']);
            $this->assertNull($monster['damage_resistances']);
            $this->assertNull($monster['damage_immunities']);
            $this->assertNull($monster['condition_immunities']);
            $this->assertEquals('darkvision 120 ft.', $monster['senses']);
            $this->assertEquals('Deep Speech, telepathy 120 ft.', $monster['languages']);

            // Description
            $this->assertEquals('Test description', $monster['description']);
            $this->assertEquals('underdark', $monster['environment']);

            // Traits
            $this->assertCount(1, $monster['traits']);
            $this->assertEquals('Amphibious', $monster['traits'][0]['name']);

            // Actions
            $this->assertCount(1, $monster['actions']);
            $this->assertEquals('Tentacle', $monster['actions'][0]['name']);

            // Reactions and legendary should be empty
            $this->assertEmpty($monster['reactions']);
            $this->assertEmpty($monster['legendary']);

            // Spellcasting
            $this->assertNull($monster['slots']);
            $this->assertNull($monster['spells']);
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Helper method to invoke protected/private methods for testing.
     */
    protected function invokeMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}

<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\RaceXmlParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for parsing natural weapons (claws, fangs, bite) from trait text.
 */
#[Group('unit-pure')]
class ParsesNaturalWeaponsTest extends TestCase
{
    private RaceXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RaceXmlParser;
    }

    #[Test]
    public function it_parses_simple_natural_weapon_damage()
    {
        // Aarakocra: "deal 1d4 slashing damage"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Aarakocra</name>
        <size>M</size>
        <speed>25</speed>
        <trait category="species">
            <name>Talons</name>
            <text>You are proficient with your unarmed strikes, which deal 1d4 slashing damage on a hit.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('natural_weapons', $races[0]);
        $this->assertCount(1, $races[0]['natural_weapons']);

        $weapon = $races[0]['natural_weapons'][0];
        $this->assertEquals('Talons', $weapon['name']);
        $this->assertEquals('1d4', $weapon['damage_dice']);
        $this->assertEquals('slashing', $weapon['damage_type']);
    }

    #[Test]
    public function it_parses_natural_weapon_with_ability_modifier()
    {
        // Tabaxi: "deal slashing damage equal to 1d4 + your Strength modifier"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Tabaxi</name>
        <size>M</size>
        <speed>30</speed>
        <trait category="species">
            <name>Cat's Claws</name>
            <text>Because of your claws, you have a climbing speed of 20 feet. In addition, your claws are natural weapons, which you can use to make unarmed strikes. If you hit with them, you deal slashing damage equal to 1d4 + your Strength modifier, instead of the bludgeoning damage normal for an unarmed strike.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('natural_weapons', $races[0]);
        $this->assertCount(1, $races[0]['natural_weapons']);

        $weapon = $races[0]['natural_weapons'][0];
        $this->assertEquals("Cat's Claws", $weapon['name']);
        $this->assertEquals('1d4', $weapon['damage_dice']);
        $this->assertEquals('slashing', $weapon['damage_type']);
        $this->assertEquals('STR', $weapon['ability']);
    }

    #[Test]
    public function it_parses_bite_attack_with_piercing_damage()
    {
        // Gnoll: "piercing damage equal to 1d4 + your Strength modifier"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Gnoll</name>
        <size>M</size>
        <speed>30</speed>
        <trait category="species">
            <name>Bite</name>
            <text>Your fanged maw is a natural weapon, which you can use to make unarmed strikes. If you hit with it, you deal piercing damage equal to 1d4 + your Strength modifier, instead of the bludgeoning damage normal for an unarmed strike.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('natural_weapons', $races[0]);
        $this->assertCount(1, $races[0]['natural_weapons']);

        $weapon = $races[0]['natural_weapons'][0];
        $this->assertEquals('Bite', $weapon['name']);
        $this->assertEquals('1d4', $weapon['damage_dice']);
        $this->assertEquals('piercing', $weapon['damage_type']);
        $this->assertEquals('STR', $weapon['ability']);
    }

    #[Test]
    public function it_parses_natural_weapon_with_constitution_modifier()
    {
        // Dhampir: "add your Constitution modifier"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Dhampir</name>
        <size>M</size>
        <speed>35</speed>
        <trait category="species">
            <name>Vampiric Bite</name>
            <text>Your fanged bite is a natural weapon, which counts as a simple melee weapon with which you are proficient. You add your Constitution modifier, instead of your Strength modifier, to the attack and damage rolls when you attack with this bite. It deals 1d4 piercing damage on a hit.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('natural_weapons', $races[0]);
        $this->assertCount(1, $races[0]['natural_weapons']);

        $weapon = $races[0]['natural_weapons'][0];
        $this->assertEquals('Vampiric Bite', $weapon['name']);
        $this->assertEquals('1d4', $weapon['damage_dice']);
        $this->assertEquals('piercing', $weapon['damage_type']);
        $this->assertEquals('CON', $weapon['ability']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_natural_weapons()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Human</name>
        <size>M</size>
        <speed>30</speed>
        <trait category="species">
            <name>Versatile</name>
            <text>You gain proficiency in one skill of your choice.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('natural_weapons', $races[0]);
        $this->assertEmpty($races[0]['natural_weapons']);
    }

    #[Test]
    public function it_parses_larger_damage_dice()
    {
        // Some races have 1d6 natural weapons
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Longtooth Shifter</name>
        <size>M</size>
        <speed>30</speed>
        <trait category="species">
            <name>Shifting Feature</name>
            <text>While shifted, you can use your elongated fangs to make an unarmed strike as a bonus action. If you hit with your fangs, you can deal piercing damage equal to 1d6 + your Strength modifier.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertArrayHasKey('natural_weapons', $races[0]);
        $this->assertCount(1, $races[0]['natural_weapons']);

        $weapon = $races[0]['natural_weapons'][0];
        $this->assertEquals('1d6', $weapon['damage_dice']);
        $this->assertEquals('piercing', $weapon['damage_type']);
        $this->assertEquals('STR', $weapon['ability']);
    }
}

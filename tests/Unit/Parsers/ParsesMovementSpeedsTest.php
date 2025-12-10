<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\RaceXmlParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for parsing alternative movement speeds (fly, swim, climb) from trait text.
 */
#[Group('unit-pure')]
class ParsesMovementSpeedsTest extends TestCase
{
    private RaceXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new RaceXmlParser;
    }

    #[Test]
    public function it_parses_flying_speed_from_trait_text()
    {
        // Aarakocra: "You have a flying speed of 50 feet."
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Aarakocra</name>
        <size>M</size>
        <speed>25</speed>
        <trait category="species">
            <name>Flight</name>
            <text>You have a flying speed of 50 feet. To use this speed, you can't be wearing medium or heavy armor.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertEquals(25, $races[0]['speed']);
        $this->assertEquals(50, $races[0]['fly_speed']);
        $this->assertNull($races[0]['swim_speed']);
        $this->assertNull($races[0]['climb_speed']);
    }

    #[Test]
    public function it_parses_swimming_speed_from_trait_text()
    {
        // Triton: "You have a swimming speed of 30 feet."
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Triton</name>
        <size>M</size>
        <speed>30</speed>
        <trait category="species">
            <name>Swim Speed</name>
            <text>You have a swimming speed of 30 feet.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertEquals(30, $races[0]['speed']);
        $this->assertNull($races[0]['fly_speed']);
        $this->assertEquals(30, $races[0]['swim_speed']);
        $this->assertNull($races[0]['climb_speed']);
    }

    #[Test]
    public function it_parses_climbing_speed_from_trait_text()
    {
        // Tabaxi: "you have a climbing speed of 20 feet"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Tabaxi</name>
        <size>M</size>
        <speed>30</speed>
        <trait category="species">
            <name>Cat's Claws</name>
            <text>Because of your claws, you have a climbing speed of 20 feet.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertEquals(30, $races[0]['speed']);
        $this->assertNull($races[0]['fly_speed']);
        $this->assertNull($races[0]['swim_speed']);
        $this->assertEquals(20, $races[0]['climb_speed']);
    }

    #[Test]
    public function it_parses_swim_speed_shorthand()
    {
        // Bullywug: "base swimming speed of 40 feet"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Bullywug</name>
        <size>M</size>
        <speed>20</speed>
        <trait category="species">
            <name>Swim speed</name>
            <text>You have a base swimming speed of 40 feet.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertEquals(40, $races[0]['swim_speed']);
    }

    #[Test]
    public function it_parses_speed_equal_to_walking_speed()
    {
        // Fairy: "flying speed equal to your walking speed"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Fairy</name>
        <size>S</size>
        <speed>30</speed>
        <trait category="species">
            <name>Flight</name>
            <text>Because of your wings, you have a flying speed equal to your walking speed.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertEquals(30, $races[0]['speed']);
        // When equal to walking speed, should use walking speed value
        $this->assertEquals(30, $races[0]['fly_speed']);
    }

    #[Test]
    public function it_parses_climbing_speed_equal_to_walking_speed()
    {
        // Dhampir: "climbing speed equal to your walking speed"
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <race>
        <name>Dhampir</name>
        <size>M</size>
        <speed>35</speed>
        <trait category="species">
            <name>Spider Climb</name>
            <text>You have a climbing speed equal to your walking speed.</text>
        </trait>
    </race>
</compendium>
XML;

        $races = $this->parser->parse($xml);

        $this->assertCount(1, $races);
        $this->assertEquals(35, $races[0]['speed']);
        $this->assertEquals(35, $races[0]['climb_speed']);
    }

    #[Test]
    public function it_returns_null_for_missing_alternative_speeds()
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
        $this->assertEquals(30, $races[0]['speed']);
        $this->assertNull($races[0]['fly_speed']);
        $this->assertNull($races[0]['swim_speed']);
        $this->assertNull($races[0]['climb_speed']);
    }
}

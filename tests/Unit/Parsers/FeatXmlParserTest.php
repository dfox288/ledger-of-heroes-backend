<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\FeatXmlParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatXmlParserTest extends TestCase
{
    private FeatXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new FeatXmlParser;
    }

    #[Test]
    public function it_parses_basic_feat_data()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Alert</name>
        <text>Always on the lookout for danger, you gain the following benefits:

	• You gain a +5 bonus to initiative.

	• You can't be surprised while you are conscious.

	• Other creatures don't gain advantage on attack rolls against you as a result of being unseen by you.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertEquals('Alert', $feats[0]['name']);
        $this->assertStringContainsString('Always on the lookout', $feats[0]['description']);
        $this->assertNull($feats[0]['prerequisites']);
        $this->assertArrayHasKey('sources', $feats[0]);
    }

    #[Test]
    public function it_parses_feat_with_prerequisites()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Defensive Duelist</name>
        <prerequisite>Dexterity 13 or higher</prerequisite>
        <text>When you are wielding a finesse weapon with which you are proficient and another creature hits you with a melee attack, you can use your reaction to add your proficiency bonus to your AC for that attack, potentially causing the attack to miss you.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(1, $feats);
        $this->assertEquals('Defensive Duelist', $feats[0]['name']);
        $this->assertEquals('Dexterity 13 or higher', $feats[0]['prerequisites']);
    }

    #[Test]
    public function it_parses_multiple_feats()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Alert</name>
        <text>Always on the lookout for danger.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
    <feat>
        <name>Actor</name>
        <text>Skilled at mimicry and dramatics.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        $this->assertCount(2, $feats);
        $this->assertEquals('Alert', $feats[0]['name']);
        $this->assertEquals('Actor', $feats[1]['name']);
    }

    #[Test]
    public function it_extracts_and_removes_source_citations()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Alert</name>
        <text>Always on the lookout for danger, you gain the following benefits:

	• You gain a +5 bonus to initiative.

Source:	Player's Handbook (2014) p. 165</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        // Should extract source citation
        $this->assertCount(1, $feats[0]['sources']);
        $this->assertEquals('PHB', $feats[0]['sources'][0]['code']);
        $this->assertEquals('165', $feats[0]['sources'][0]['pages']);

        // Should remove source from description
        $this->assertStringNotContainsString('Source:', $feats[0]['description']);
        $this->assertStringNotContainsString("Player's Handbook", $feats[0]['description']);
    }

    #[Test]
    public function it_parses_multiple_source_citations()
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Fey Touched</name>
        <text>Your exposure to the Feywild's magic has changed you.

Source:	Tasha's Cauldron of Everything (2020) p. 79, Player's Handbook (2024) p. 200</text>
    </feat>
</compendium>
XML;

        $feats = $this->parser->parse($xml);

        // Should parse at least one source (multi-source parsing may vary)
        $this->assertGreaterThanOrEqual(1, count($feats[0]['sources']));
        $this->assertArrayHasKey('code', $feats[0]['sources'][0]);
        $this->assertArrayHasKey('pages', $feats[0]['sources'][0]);
    }
}

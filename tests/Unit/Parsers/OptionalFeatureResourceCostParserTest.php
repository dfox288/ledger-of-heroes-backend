<?php

namespace Tests\Unit\Parsers;

use App\Enums\ResourceType;
use App\Services\Parsers\OptionalFeatureXmlParser;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit-pure')]
class OptionalFeatureResourceCostParserTest extends TestCase
{
    private OptionalFeatureXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new OptionalFeatureXmlParser;
    }

    #[Test]
    public function it_parses_superiority_die_cost_from_maneuver_description(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Maneuver: Commander's Strike</name>
        <text>When you take the Attack action on your turn, you can forgo one of your attacks and use a bonus action to direct one of your companions to strike. When you do so, choose a friendly creature who can see or hear you and expend one superiority die. That creature can immediately use its reaction to make one weapon attack, adding the superiority die to the attack's damage roll.

Source:	Player's Handbook (2014) p. 74</text>
    </feat>
</compendium>
XML;

        $features = $this->parser->parse($xml);

        $this->assertCount(1, $features);
        $this->assertEquals("Commander's Strike", $features[0]['name']);
        $this->assertEquals(ResourceType::SUPERIORITY_DIE, $features[0]['resource_type']);
        $this->assertEquals(1, $features[0]['resource_cost']);
        $this->assertNull($features[0]['cost_formula']);
    }

    #[Test]
    public function it_parses_variable_sorcery_point_cost_for_twinned_spell(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Metamagic: Twinned Spell</name>
        <level>0</level>
        <time/>
        <range/>
        <components/>
        <duration/>
        <classes>Metamagic Options</classes>
        <text>When you cast a spell that targets only one creature and doesn't have a range of self, you can spend a number of sorcery points equal to the spell's level to target a second creature in range with the same spell (1 sorcery point if the spell is a cantrip).

Source:	Player's Handbook (2014) p. 102</text>
    </spell>
</compendium>
XML;

        $features = $this->parser->parse($xml);

        $this->assertCount(1, $features);
        $this->assertEquals('Twinned Spell', $features[0]['name']);
        $this->assertEquals(ResourceType::SORCERY_POINTS, $features[0]['resource_type']);
        $this->assertNull($features[0]['resource_cost']);
        $this->assertEquals('spell_level', $features[0]['cost_formula']);
    }

    #[Test]
    public function it_parses_fixed_sorcery_point_cost_from_components(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Metamagic: Quickened Spell</name>
        <level>0</level>
        <time/>
        <range/>
        <components>V, S, M (2 sorcery points)</components>
        <duration/>
        <classes>Metamagic Options</classes>
        <text>When you cast a spell that has a casting time of 1 action, you can spend 2 sorcery points to change the casting time to 1 bonus action for this casting.

Source:	Player's Handbook (2014) p. 102</text>
    </spell>
</compendium>
XML;

        $features = $this->parser->parse($xml);

        $this->assertCount(1, $features);
        $this->assertEquals('Quickened Spell', $features[0]['name']);
        $this->assertEquals(ResourceType::SORCERY_POINTS, $features[0]['resource_type']);
        $this->assertEquals(2, $features[0]['resource_cost']);
        $this->assertNull($features[0]['cost_formula']);
    }

    #[Test]
    public function it_parses_ki_point_cost_from_components(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <spell>
        <name>Elemental Discipline: Fist of Four Thunders</name>
        <level>0</level>
        <time>1 action</time>
        <range>Self (15-foot cube)</range>
        <components>V, S, M (2 ki points)</components>
        <duration>Instantaneous</duration>
        <classes>Monk (Way of the Four Elements)</classes>
        <text>You can spend 2 ki points to cast thunderwave.

Source:	Player's Handbook (2014) p. 81</text>
    </spell>
</compendium>
XML;

        $features = $this->parser->parse($xml);

        $this->assertCount(1, $features);
        $this->assertEquals('Fist of Four Thunders', $features[0]['name']);
        $this->assertEquals(ResourceType::KI_POINTS, $features[0]['resource_type']);
        $this->assertEquals(2, $features[0]['resource_cost']);
        $this->assertNull($features[0]['cost_formula']);
    }

    #[Test]
    public function it_returns_null_for_passive_features_without_resource_cost(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Fighting Style: Defense</name>
        <text>While you are wearing armor, you gain a +1 bonus to AC.

Source:	Player's Handbook (2014) p. 72</text>
    </feat>
</compendium>
XML;

        $features = $this->parser->parse($xml);

        $this->assertCount(1, $features);
        $this->assertEquals('Defense', $features[0]['name']);
        $this->assertNull($features[0]['resource_type']);
        $this->assertNull($features[0]['resource_cost']);
        $this->assertNull($features[0]['cost_formula']);
    }

    #[Test]
    public function it_parses_expend_a_superiority_die_variation(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<compendium version="5">
    <feat>
        <name>Maneuver: Riposte</name>
        <text>When a creature misses you with a melee attack, you can use your reaction and expend a superiority die to make a melee weapon attack against the creature.

Source:	Player's Handbook (2014) p. 74</text>
    </feat>
</compendium>
XML;

        $features = $this->parser->parse($xml);

        $this->assertCount(1, $features);
        $this->assertEquals('Riposte', $features[0]['name']);
        $this->assertEquals(ResourceType::SUPERIORITY_DIE, $features[0]['resource_type']);
        $this->assertEquals(1, $features[0]['resource_cost']);
    }
}

<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\BeastStrategy;
use App\Services\Parsers\MonsterXmlParser;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class BeastStrategyTest extends TestCase
{
    private BeastStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new BeastStrategy;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_beast_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'beast']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Beast']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_apply_to_non_beast_type(): void
    {
        $this->assertFalse($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'fiend']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'elemental']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_keen_senses(): void
    {
        $traits = [
            [
                'name' => 'Keen Smell',
                'description' => 'The bear has advantage on Wisdom (Perception) checks that rely on smell.',
            ],
        ];

        $monsterData = [
            'name' => 'Brown Bear',
            'type' => 'beast',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('keen_senses', $tags);
        $this->assertArrayHasKey('keen_senses_count', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_pack_tactics(): void
    {
        $traits = [
            [
                'name' => 'Pack Tactics',
                'description' => 'The wolf has advantage on attack rolls against a creature if at least one ally is within 5 feet.',
            ],
        ];

        $monsterData = [
            'name' => 'Wolf',
            'type' => 'beast',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('pack_tactics', $tags);
        $this->assertArrayHasKey('pack_tactics_count', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_charge_mechanics(): void
    {
        $traits = [
            [
                'name' => 'Pounce',
                'description' => 'If the lion moves at least 20 feet straight toward a creature and then hits it with a claw attack, the target must succeed on a DC 13 Strength saving throw or be knocked prone.',
            ],
        ];

        $monsterData = [
            'name' => 'Lion',
            'type' => 'beast',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('charge', $tags);
        $this->assertArrayHasKey('charge_count', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_special_movement(): void
    {
        $traits = [
            [
                'name' => 'Spider Climb',
                'description' => 'The spider can climb difficult surfaces, including upside down on ceilings.',
            ],
            [
                'name' => 'Web Walker',
                'description' => 'The spider ignores movement restrictions caused by webbing.',
            ],
        ];

        $monsterData = [
            'name' => 'Giant Spider',
            'type' => 'beast',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('special_movement', $tags);
        $this->assertArrayHasKey('special_movement_count', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_beast_metrics(): void
    {
        $monsterData = [
            'name' => 'Wolf',
            'type' => 'beast',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('beasts_enhanced', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['beasts_enhanced']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_with_real_xml_fixture(): void
    {
        $parser = new MonsterXmlParser;
        $monsters = $parser->parse(base_path('tests/Fixtures/xml/monsters/test-beasts.xml'));

        $this->assertCount(4, $monsters);

        // Wolf - keen senses + pack tactics
        $this->assertEquals('Wolf', $monsters[0]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[0]));

        // Brown Bear - keen senses only
        $this->assertEquals('Brown Bear', $monsters[1]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[1]));

        // Lion - keen senses + pack tactics + pounce
        $this->assertEquals('Lion', $monsters[2]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[2]));

        // Giant Spider - special movement
        $this->assertEquals('Giant Spider', $monsters[3]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[3]));
    }
}

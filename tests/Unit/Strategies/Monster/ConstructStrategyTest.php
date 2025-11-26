<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\ConstructStrategy;
use App\Services\Parsers\MonsterXmlParser;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class ConstructStrategyTest extends TestCase
{
    private ConstructStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ConstructStrategy;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_construct_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'construct']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Construct']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_apply_to_non_construct_type(): void
    {
        $this->assertFalse($this->strategy->appliesTo(['type' => 'fiend']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'celestial']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_poison_immunity(): void
    {
        $monsterData = [
            'name' => 'Animated Armor',
            'type' => 'construct',
            'damage_immunities' => 'poison, psychic',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('poison_immune', $tags);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_condition_immunities(): void
    {
        $monsterData = [
            'name' => 'Iron Golem',
            'type' => 'construct',
            'condition_immunities' => 'charmed, exhaustion, frightened, paralyzed, petrified, poisoned',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('condition_immune_count', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['condition_immune_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_constructed_nature_trait(): void
    {
        $traits = [
            [
                'name' => 'Constructed Nature',
                'description' => "A golem doesn't require air, food, drink, or sleep.",
            ],
        ];

        $monsterData = [
            'name' => 'Stone Golem',
            'type' => 'construct',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('constructed_nature_count', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['constructed_nature_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_construct_enhancement_metrics(): void
    {
        $monsterData = [
            'name' => 'Animated Armor',
            'type' => 'construct',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('constructs_enhanced', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['constructs_enhanced']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_with_real_xml_fixture(): void
    {
        $parser = new MonsterXmlParser;
        $monsters = $parser->parse(base_path('tests/Fixtures/xml/monsters/test-constructs.xml'));

        $this->assertCount(2, $monsters);

        // Animated Armor
        $this->assertEquals('Animated Armor', $monsters[0]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[0]));
        $this->assertStringContainsString('poison', strtolower($monsters[0]['damage_immunities']));

        // Iron Golem
        $this->assertEquals('Iron Golem', $monsters[1]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[1]));
    }
}

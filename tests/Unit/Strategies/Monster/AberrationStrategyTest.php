<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\AberrationStrategy;
use App\Services\Parsers\MonsterXmlParser;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class AberrationStrategyTest extends TestCase
{
    private AberrationStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new AberrationStrategy;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_aberration_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'aberration']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Aberration']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_apply_to_non_aberration_type(): void
    {
        $this->assertFalse($this->strategy->appliesTo(['type' => 'fiend']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'elemental']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_telepathy(): void
    {
        $monsterData = [
            'name' => 'Mind Flayer',
            'type' => 'aberration',
            'languages' => 'Deep Speech, Undercommon, telepathy 120 ft.',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('telepathy', $tags);
        $this->assertArrayHasKey('telepaths', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_psychic_damage_in_actions(): void
    {
        $actions = [
            [
                'name' => 'Mind Blast',
                'description' => 'The mind flayer emits psychic energy. Each creature must succeed on a DC 15 Intelligence saving throw or take 22 (4d8 + 4) psychic damage.',
                'action_type' => 'action',
                'attack_data' => [],
                'recharge' => '5-6',
                'sort_order' => 0,
            ],
        ];

        $monsterData = ['type' => 'aberration'];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);
        $this->strategy->enhanceActions($actions, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('psychic_damage', $tags);
        $this->assertArrayHasKey('psychic_attackers', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_mind_control_in_traits(): void
    {
        $traits = [
            [
                'name' => 'Dominate',
                'description' => 'The creature can dominate the minds of others.',
            ],
        ];

        $monsterData = ['type' => 'aberration'];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('mind_control', $tags);
        $this->assertArrayHasKey('mind_controllers', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_mind_control_in_actions(): void
    {
        $actions = [
            [
                'name' => 'Enslave',
                'description' => 'The aboleth targets one creature. The target must succeed on a DC 14 Wisdom saving throw or be magically charmed by the aboleth.',
                'action_type' => 'action',
                'attack_data' => [],
                'recharge' => null,
                'sort_order' => 0,
            ],
        ];

        $monsterData = ['type' => 'aberration'];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);
        $this->strategy->enhanceActions($actions, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('mind_control', $tags);
        $this->assertArrayHasKey('mind_controllers', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_antimagic_abilities(): void
    {
        $traits = [
            [
                'name' => 'Antimagic Cone',
                'description' => 'The beholder creates an area of antimagic in a 150-foot cone.',
            ],
        ];

        $monsterData = ['type' => 'aberration'];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('antimagic', $tags);
        $this->assertArrayHasKey('antimagic_users', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_aberration_metrics(): void
    {
        $monsterData = ['type' => 'aberration'];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);
        $this->strategy->enhanceActions([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('aberrations_enhanced', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['aberrations_enhanced']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_with_real_xml_fixture(): void
    {
        $parser = new MonsterXmlParser;
        $xmlContent = file_get_contents(base_path('tests/Fixtures/xml/monsters/test-aberrations.xml'));
        $monsters = $parser->parse($xmlContent);

        $this->assertCount(3, $monsters);

        // Mind Flayer
        $this->assertEquals('Mind Flayer', $monsters[0]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[0]));
        $this->assertStringContainsString('telepathy', strtolower($monsters[0]['languages']));

        // Beholder
        $this->assertEquals('Beholder', $monsters[1]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[1]));

        // Aboleth
        $this->assertEquals('Aboleth', $monsters[2]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[2]));
        $this->assertStringContainsString('telepathy', strtolower($monsters[2]['languages']));
    }
}

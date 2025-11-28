<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\ShapechangerStrategy;
use App\Services\Parsers\MonsterXmlParser;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class ShapechangerStrategyTest extends TestCase
{
    private ShapechangerStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ShapechangerStrategy;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_shapechanger_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'humanoid (shapechanger)']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'monstrosity (shapechanger)']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'aberration (shapechanger)']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_apply_to_non_shapechanger_type(): void
    {
        $this->assertFalse($this->strategy->appliesTo(['type' => 'humanoid']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'elemental']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_lycanthrope_subtype(): void
    {
        $traits = [
            [
                'name' => 'Shapechanger',
                'description' => 'The werewolf can polymorph into a wolf-humanoid hybrid.',
            ],
        ];

        $monsterData = [
            'name' => 'Werewolf',
            'type' => 'humanoid (human, shapechanger)',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('lycanthrope', $tags);
        $this->assertArrayHasKey('lycanthropes', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_doppelganger_subtype(): void
    {
        $traits = [
            [
                'name' => 'Shapechanger',
                'description' => 'The doppelganger can polymorph into a humanoid.',
            ],
        ];

        $monsterData = [
            'name' => 'Doppelganger',
            'type' => 'monstrosity (shapechanger)',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('doppelganger', $tags);
        $this->assertArrayHasKey('doppelgangers', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_mimic_subtype(): void
    {
        $traits = [
            [
                'name' => 'Adhesive',
                'description' => 'The mimic adheres to anything that touches it.',
            ],
            [
                'name' => 'False Appearance',
                'description' => 'While motionless, indistinguishable from an ordinary object.',
            ],
        ];

        $monsterData = [
            'name' => 'Mimic',
            'type' => 'monstrosity (shapechanger)',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('mimic', $tags);
        $this->assertArrayHasKey('mimics', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_shapechanger_metrics(): void
    {
        $monsterData = [
            'name' => 'Werewolf',
            'type' => 'humanoid (shapechanger)',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('shapechangers_enhanced', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['shapechangers_enhanced']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_with_real_xml_fixture(): void
    {
        $parser = new MonsterXmlParser;
        $xmlContent = file_get_contents(base_path('tests/Fixtures/xml/monsters/test-shapechangers.xml'));
        $monsters = $parser->parse($xmlContent);

        $this->assertCount(3, $monsters);

        // Werewolf
        $this->assertEquals('Werewolf', $monsters[0]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[0]));
        $this->assertStringContainsString('shapechanger', strtolower($monsters[0]['type']));

        // Doppelganger
        $this->assertEquals('Doppelganger', $monsters[1]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[1]));

        // Mimic
        $this->assertEquals('Mimic', $monsters[2]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[2]));
    }
}

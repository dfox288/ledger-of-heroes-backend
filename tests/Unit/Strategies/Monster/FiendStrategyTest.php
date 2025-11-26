<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\FiendStrategy;
use App\Services\Parsers\MonsterXmlParser;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class FiendStrategyTest extends TestCase
{
    private FiendStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new FiendStrategy;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_fiend_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'fiend (demon)']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'fiend (devil)']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'fiend (yugoloth)']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Fiend']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_apply_to_non_fiend_type(): void
    {
        $this->assertFalse($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'undead']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'celestial']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_fire_immunity(): void
    {
        $monsterData = [
            'name' => 'Balor',
            'type' => 'fiend (demon)',
            'damage_immunities' => 'fire, poison',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('fire_immune_count', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['fire_immune_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_poison_immunity(): void
    {
        $monsterData = [
            'name' => 'Pit Fiend',
            'type' => 'fiend (devil)',
            'damage_immunities' => 'fire, poison',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('poison_immune_count', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['poison_immune_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_magic_resistance_trait(): void
    {
        $traits = [
            [
                'name' => 'Magic Resistance',
                'description' => 'The balor has advantage on saving throws against spells and other magical effects.',
            ],
        ];

        $monsterData = [
            'name' => 'Balor',
            'type' => 'fiend (demon)',
            'damage_immunities' => 'fire, poison',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits($traits, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('magic_resistance_count', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['magic_resistance_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_fiend_enhancement_metrics(): void
    {
        $monsterData = [
            'name' => 'Balor',
            'type' => 'fiend (demon)',
            'damage_immunities' => 'fire, poison',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('fiends_enhanced', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['fiends_enhanced']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_with_real_xml_fixture(): void
    {
        $parser = new MonsterXmlParser;
        $monsters = $parser->parse(base_path('tests/Fixtures/xml/monsters/test-fiends.xml'));

        $this->assertCount(3, $monsters);

        // Balor (demon)
        $this->assertEquals('Balor', $monsters[0]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[0]));
        $this->assertStringContainsString('fire', strtolower($monsters[0]['damage_immunities']));

        // Pit Fiend (devil)
        $this->assertEquals('Pit Fiend', $monsters[1]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[1]));

        // Arcanaloth (yugoloth)
        $this->assertEquals('Arcanaloth', $monsters[2]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[2]));
    }
}

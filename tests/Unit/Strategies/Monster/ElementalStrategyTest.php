<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\ElementalStrategy;
use App\Services\Parsers\MonsterXmlParser;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class ElementalStrategyTest extends TestCase
{
    private ElementalStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ElementalStrategy;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_elemental_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'elemental']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Elemental']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_apply_to_non_elemental_type(): void
    {
        $this->assertFalse($this->strategy->appliesTo(['type' => 'fiend']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'aberration']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_fire_elemental_subtype(): void
    {
        $monsterData = [
            'name' => 'Fire Elemental',
            'type' => 'elemental',
            'languages' => 'Ignan',
            'damage_immunities' => 'fire, poison',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('fire_elemental', $tags);
        $this->assertArrayHasKey('fire_elementals', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_water_elemental_subtype(): void
    {
        $monsterData = [
            'name' => 'Water Elemental',
            'type' => 'elemental',
            'languages' => 'Aquan',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('water_elemental', $tags);
        $this->assertArrayHasKey('water_elementals', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_earth_elemental_subtype(): void
    {
        $monsterData = [
            'name' => 'Earth Elemental',
            'type' => 'elemental',
            'languages' => 'Terran',
            'damage_immunities' => 'poison',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('earth_elemental', $tags);
        $this->assertArrayHasKey('earth_elementals', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_air_elemental_subtype(): void
    {
        $monsterData = [
            'name' => 'Air Elemental',
            'type' => 'elemental',
            'languages' => 'Auran',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('air_elemental', $tags);
        $this->assertArrayHasKey('air_elementals', $metadata['metrics']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_poison_immunity(): void
    {
        $monsterData = [
            'name' => 'Fire Elemental',
            'type' => 'elemental',
            'damage_immunities' => 'fire, poison',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];
        $this->assertContains('poison_immune', $tags);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_elemental_metrics(): void
    {
        $monsterData = [
            'name' => 'Fire Elemental',
            'type' => 'elemental',
            'languages' => 'Ignan',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceTraits([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('elementals_enhanced', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['elementals_enhanced']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_with_real_xml_fixture(): void
    {
        $parser = new MonsterXmlParser;
        $monsters = $parser->parse(base_path('tests/Fixtures/xml/monsters/test-elementals.xml'));

        $this->assertCount(4, $monsters);

        // Fire Elemental
        $this->assertEquals('Fire Elemental', $monsters[0]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[0]));
        $this->assertStringContainsString('Ignan', $monsters[0]['languages']);

        // Water Elemental
        $this->assertEquals('Water Elemental', $monsters[1]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[1]));
        $this->assertStringContainsString('Aquan', $monsters[1]['languages']);

        // Earth Elemental
        $this->assertEquals('Earth Elemental', $monsters[2]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[2]));
        $this->assertStringContainsString('Terran', $monsters[2]['languages']);

        // Air Elemental
        $this->assertEquals('Air Elemental', $monsters[3]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[3]));
        $this->assertStringContainsString('Auran', $monsters[3]['languages']);
    }
}

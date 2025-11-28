<?php

namespace Tests\Unit\Strategies\Monster;

use App\Services\Importers\Strategies\Monster\CelestialStrategy;
use App\Services\Parsers\MonsterXmlParser;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class CelestialStrategyTest extends TestCase
{
    private CelestialStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new CelestialStrategy;
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_to_celestial_type(): void
    {
        $this->assertTrue($this->strategy->appliesTo(['type' => 'celestial']));
        $this->assertTrue($this->strategy->appliesTo(['type' => 'Celestial']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_apply_to_non_celestial_type(): void
    {
        $this->assertFalse($this->strategy->appliesTo(['type' => 'fiend']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'dragon']));
        $this->assertFalse($this->strategy->appliesTo(['type' => 'undead']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_radiant_damage_in_actions(): void
    {
        $actions = [
            [
                'name' => 'Mace',
                'description' => 'Melee Weapon Attack: +8 to hit. Hit: 7 (1d6 + 4) bludgeoning damage plus 18 (4d8) radiant damage.',
                'action_type' => 'action',
                'attack_data' => [],
                'recharge' => null,
                'sort_order' => 0,
            ],
        ];

        $monsterData = ['type' => 'celestial'];

        $this->strategy->reset();
        $this->strategy->enhanceActions($actions, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('radiant_attackers', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['radiant_attackers']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_healing_abilities(): void
    {
        $actions = [
            [
                'name' => 'Healing Touch (3/Day)',
                'description' => 'The deva touches another creature. The target magically regains 20 (4d8 + 2) hit points.',
                'action_type' => 'action',
                'attack_data' => [],
                'recharge' => null,
                'sort_order' => 0,
            ],
        ];

        $monsterData = ['type' => 'celestial'];

        $this->strategy->reset();
        $this->strategy->enhanceActions($actions, $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('healers_count', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['healers_count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tracks_celestial_enhancement_metrics(): void
    {
        $monsterData = ['type' => 'celestial'];

        $this->strategy->reset();
        $this->strategy->enhanceActions([], $monsterData);

        $metadata = $this->strategy->extractMetadata($monsterData);
        $this->assertArrayHasKey('celestials_enhanced', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['celestials_enhanced']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_integrates_with_real_xml_fixture(): void
    {
        $parser = new MonsterXmlParser;
        $xmlContent = file_get_contents(base_path('tests/Fixtures/xml/monsters/test-celestials.xml'));
        $monsters = $parser->parse($xmlContent);

        $this->assertCount(2, $monsters);

        // Deva
        $this->assertEquals('Deva', $monsters[0]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[0]));
        $this->assertNotEmpty($monsters[0]['actions']);

        // Solar
        $this->assertEquals('Solar', $monsters[1]['name']);
        $this->assertTrue($this->strategy->appliesTo($monsters[1]));
    }
}

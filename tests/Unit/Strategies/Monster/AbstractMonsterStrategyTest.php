<?php

namespace Tests\Unit\Strategies\Monster;

use Tests\TestCase;

class AbstractMonsterStrategyTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_extracts_action_cost_from_legendary_name(): void
    {
        $strategy = new class extends \App\Services\Importers\Strategies\Monster\AbstractMonsterStrategy
        {
            public function appliesTo(array $monsterData): bool
            {
                return true;
            }

            public function test_extract_cost(string $name): int
            {
                return $this->extractActionCost($name);
            }
        };

        $this->assertEquals(1, $strategy->test_extract_cost('Detect'));
        $this->assertEquals(2, $strategy->test_extract_cost('Wing Attack (Costs 2 Actions)'));
        $this->assertEquals(3, $strategy->test_extract_cost('Psychic Drain (Costs 3 Actions)'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_lair_actions_from_category(): void
    {
        $strategy = new class extends \App\Services\Importers\Strategies\Monster\AbstractMonsterStrategy
        {
            public function appliesTo(array $monsterData): bool
            {
                return true;
            }
        };

        $legendary = [
            ['name' => 'Detect', 'description' => '...', 'category' => null],
            ['name' => 'Lair Actions', 'description' => '...', 'category' => 'lair'],
        ];

        $enhanced = $strategy->enhanceLegendaryActions($legendary, []);

        $this->assertEquals(1, $enhanced[0]['action_cost']);
        $this->assertFalse($enhanced[0]['is_lair_action']);

        $this->assertEquals(1, $enhanced[1]['action_cost']);
        $this->assertTrue($enhanced[1]['is_lair_action']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_damage_immunity(): void
    {
        $strategy = new \App\Services\Importers\Strategies\Monster\DefaultStrategy;
        $monsterData = [
            'damage_immunities' => 'fire, cold',
        ];

        $this->assertTrue($this->callProtectedMethod($strategy, 'hasDamageImmunity', [$monsterData, 'fire']));
        $this->assertTrue($this->callProtectedMethod($strategy, 'hasDamageImmunity', [$monsterData, 'cold']));
        $this->assertFalse($this->callProtectedMethod($strategy, 'hasDamageImmunity', [$monsterData, 'poison']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_damage_resistance(): void
    {
        $strategy = new \App\Services\Importers\Strategies\Monster\DefaultStrategy;
        $monsterData = [
            'damage_resistances' => 'bludgeoning, piercing',
        ];

        $this->assertTrue($this->callProtectedMethod($strategy, 'hasDamageResistance', [$monsterData, 'bludgeoning']));
        $this->assertFalse($this->callProtectedMethod($strategy, 'hasDamageResistance', [$monsterData, 'fire']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_condition_immunity(): void
    {
        $strategy = new \App\Services\Importers\Strategies\Monster\DefaultStrategy;
        $monsterData = [
            'condition_immunities' => 'charmed, frightened, poisoned',
        ];

        $this->assertTrue($this->callProtectedMethod($strategy, 'hasConditionImmunity', [$monsterData, 'charmed']));
        $this->assertTrue($this->callProtectedMethod($strategy, 'hasConditionImmunity', [$monsterData, 'frightened']));
        $this->assertFalse($this->callProtectedMethod($strategy, 'hasConditionImmunity', [$monsterData, 'paralyzed']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_finds_trait_containing_keyword(): void
    {
        $strategy = new \App\Services\Importers\Strategies\Monster\DefaultStrategy;
        $traits = [
            ['name' => 'Magic Resistance', 'description' => 'The creature has advantage on saving throws against spells'],
            ['name' => 'Pack Tactics', 'description' => 'The creature has advantage on attacks'],
        ];

        $this->assertTrue($this->callProtectedMethod($strategy, 'hasTraitContaining', [$traits, 'magic resistance']));
        $this->assertTrue($this->callProtectedMethod($strategy, 'hasTraitContaining', [$traits, 'MAGIC RESISTANCE']));
        $this->assertFalse($this->callProtectedMethod($strategy, 'hasTraitContaining', [$traits, 'regeneration']));
    }

    /**
     * Helper to call protected methods via reflection.
     */
    private function callProtectedMethod(object $object, string $method, array $args): mixed
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}

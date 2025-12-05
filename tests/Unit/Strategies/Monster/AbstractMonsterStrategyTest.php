<?php

namespace Tests\Unit\Strategies\Monster;

use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_conditional_tags_with_trait_checks(): void
    {
        $strategy = new \App\Services\Importers\Strategies\Monster\DefaultStrategy;
        $strategy->reset();

        $traits = [
            ['name' => 'Keen Smell', 'description' => 'Advantage on Perception checks'],
            ['name' => 'Pack Tactics', 'description' => 'Advantage when ally is near'],
        ];

        $monsterData = ['name' => 'Wolf', 'type' => 'beast'];

        $this->callProtectedMethod($strategy, 'applyConditionalTags', [
            'beast',
            [
                'trait:keen smell' => 'keen_senses',
                'trait:pack tactics' => 'pack_tactics',
                'trait:charge' => 'charge', // Not present - should not be added
            ],
            $traits,
            $monsterData,
        ]);

        $metadata = $strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];

        $this->assertContains('beast', $tags);
        $this->assertContains('keen_senses', $tags);
        $this->assertContains('pack_tactics', $tags);
        $this->assertNotContains('charge', $tags);
        $this->assertEquals(1, $metadata['metrics']['keen_senses_count']);
        $this->assertEquals(1, $metadata['metrics']['pack_tactics_count']);
        $this->assertEquals(1, $metadata['metrics']['beasts_enhanced']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_conditional_tags_with_immunity_checks(): void
    {
        $strategy = new \App\Services\Importers\Strategies\Monster\DefaultStrategy;
        $strategy->reset();

        $monsterData = [
            'name' => 'Balor',
            'type' => 'fiend',
            'damage_immunities' => 'fire, poison',
        ];

        $this->callProtectedMethod($strategy, 'applyConditionalTags', [
            'fiend',
            [
                'immunity:fire' => 'fire_immune',
                'immunity:poison' => 'poison_immune',
                'immunity:cold' => 'cold_immune', // Not present
            ],
            [],
            $monsterData,
        ]);

        $metadata = $strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];

        $this->assertContains('fiend', $tags);
        $this->assertContains('fire_immune', $tags);
        $this->assertContains('poison_immune', $tags);
        $this->assertNotContains('cold_immune', $tags);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_conditional_tags_with_resistance_checks(): void
    {
        $strategy = new \App\Services\Importers\Strategies\Monster\DefaultStrategy;
        $strategy->reset();

        $monsterData = [
            'name' => 'Gargoyle',
            'type' => 'elemental',
            'damage_resistances' => 'bludgeoning, piercing, slashing',
        ];

        $this->callProtectedMethod($strategy, 'applyConditionalTags', [
            'elemental',
            [
                'resistance:bludgeoning' => 'physical_resistant',
            ],
            [],
            $monsterData,
        ]);

        $metadata = $strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];

        $this->assertContains('physical_resistant', $tags);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_conditional_tags_with_condition_immunity_checks(): void
    {
        $strategy = new \App\Services\Importers\Strategies\Monster\DefaultStrategy;
        $strategy->reset();

        $monsterData = [
            'name' => 'Iron Golem',
            'type' => 'construct',
            'condition_immunities' => 'charmed, exhaustion, frightened',
        ];

        $this->callProtectedMethod($strategy, 'applyConditionalTags', [
            'construct',
            [
                'condition:charmed' => 'charm_immune',
                'condition:paralyzed' => 'paralysis_immune', // Not present
            ],
            [],
            $monsterData,
        ]);

        $metadata = $strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];

        $this->assertContains('charm_immune', $tags);
        $this->assertNotContains('paralysis_immune', $tags);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_applies_conditional_tags_with_custom_metric_names(): void
    {
        $strategy = new \App\Services\Importers\Strategies\Monster\DefaultStrategy;
        $strategy->reset();

        $traits = [
            ['name' => 'Magic Resistance', 'description' => 'Advantage on saves against spells'],
        ];

        $monsterData = ['name' => 'Balor', 'type' => 'fiend'];

        $this->callProtectedMethod($strategy, 'applyConditionalTags', [
            'fiend',
            [
                'trait:magic resistance' => ['tag' => 'magic_resistance', 'metric' => 'magic_resistant_fiends'],
            ],
            $traits,
            $monsterData,
        ]);

        $metadata = $strategy->extractMetadata($monsterData);

        $this->assertContains('magic_resistance', $metadata['metrics']['tags_applied']);
        $this->assertEquals(1, $metadata['metrics']['magic_resistant_fiends']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deduplicates_tags_when_multiple_conditions_match_same_tag(): void
    {
        $strategy = new \App\Services\Importers\Strategies\Monster\DefaultStrategy;
        $strategy->reset();

        $traits = [
            ['name' => 'Keen Smell', 'description' => 'Advantage on Perception'],
            ['name' => 'Keen Sight', 'description' => 'Advantage on Perception'],
            ['name' => 'Keen Hearing', 'description' => 'Advantage on Perception'],
        ];

        $monsterData = ['name' => 'Eagle', 'type' => 'beast'];

        $this->callProtectedMethod($strategy, 'applyConditionalTags', [
            'beast',
            [
                'trait:keen smell' => 'keen_senses',
                'trait:keen sight' => 'keen_senses',
                'trait:keen hearing' => 'keen_senses',
            ],
            $traits,
            $monsterData,
        ]);

        $metadata = $strategy->extractMetadata($monsterData);
        $tags = $metadata['metrics']['tags_applied'] ?? [];

        // Tag should only appear once even though all 3 traits matched
        $this->assertEquals(1, count(array_filter($tags, fn ($t) => $t === 'keen_senses')));
        // But metric should be incremented for each match
        $this->assertEquals(3, $metadata['metrics']['keen_senses_count']);
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

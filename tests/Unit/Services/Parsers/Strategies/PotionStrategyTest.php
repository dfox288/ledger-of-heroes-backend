<?php

namespace Tests\Unit\Services\Parsers\Strategies;

use App\Services\Parsers\Strategies\PotionStrategy;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

class PotionStrategyTest extends TestCase
{
    private PotionStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new PotionStrategy;
    }

    #[Test]
    public function it_applies_to_potion_items()
    {
        $baseData = ['type_code' => 'P', 'name' => 'Potion of Healing'];
        $xml = new SimpleXMLElement('<item><name>Potion of Healing</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_to_non_potion_items()
    {
        $baseData = ['type_code' => 'W', 'name' => 'Wand of Fireballs'];
        $xml = new SimpleXMLElement('<item><name>Wand</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_extracts_duration_in_hours()
    {
        $baseData = [
            'name' => 'Potion of Fire Resistance',
            'description' => 'When you drink this potion, you gain resistance to fire damage for 1 hour.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('1 hour', $metadata['metrics']['duration']);
    }

    #[Test]
    public function it_extracts_duration_in_minutes()
    {
        $baseData = [
            'name' => 'Potion of Invisibility',
            'description' => 'You become invisible for 10 minutes.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('10 minutes', $metadata['metrics']['duration']);
    }

    #[Test]
    public function it_categorizes_healing_potions()
    {
        $baseData = [
            'name' => 'Potion of Healing',
            'description' => 'You regain 2d4 + 2 hit points when you drink this potion.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('healing', $metadata['metrics']['effect_category']);
        $this->assertEquals(1, $metadata['metrics']['effect_healing']);
    }

    #[Test]
    public function it_categorizes_resistance_potions()
    {
        $baseData = [
            'name' => 'Potion of Fire Resistance',
            'description' => 'You gain resistance to fire damage for 1 hour.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('resistance', $metadata['metrics']['effect_category']);
        $this->assertEquals(1, $metadata['metrics']['effect_resistance']);
    }

    #[Test]
    public function it_categorizes_buff_potions()
    {
        $testCases = [
            [
                'name' => 'Potion of Heroism',
                'description' => 'For 1 hour, you gain 10 temporary hit points.',
            ],
            [
                'name' => 'Potion of Giant Strength',
                'description' => 'Your Strength score becomes 21 for 1 hour.',
            ],
        ];

        foreach ($testCases as $testCase) {
            $this->strategy->reset();
            $xml = new SimpleXMLElement('<item><name>Test</name></item>');

            $this->strategy->enhanceModifiers([], $testCase, $xml);

            $metadata = $this->strategy->extractMetadata();

            $this->assertEquals('buff', $metadata['metrics']['effect_category'], "Failed for: {$testCase['name']}");
        }
    }

    #[Test]
    public function it_categorizes_utility_potions()
    {
        $testCases = [
            'Potion of Invisibility',
            'Potion of Diminution',
            'Potion of Gaseous Form',
            'Potion of Water Breathing',
            'Potion of Climbing',
        ];

        foreach ($testCases as $name) {
            $this->strategy->reset();
            $baseData = ['name' => $name, 'description' => 'Some effect description.'];
            $xml = new SimpleXMLElement('<item><name>Test</name></item>');

            $this->strategy->enhanceModifiers([], $baseData, $xml);

            $metadata = $this->strategy->extractMetadata();

            $this->assertEquals('utility', $metadata['metrics']['effect_category'], "Failed for: {$name}");
        }
    }

    #[Test]
    public function it_categorizes_debuff_potions()
    {
        $baseData = [
            'name' => 'Potion of Poison',
            'description' => 'You become poisoned for 1 hour.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('debuff', $metadata['metrics']['effect_category']);
        $this->assertEquals(1, $metadata['metrics']['effect_debuff']);
    }

    #[Test]
    public function it_detects_resistance_from_modifiers()
    {
        $modifiers = [
            [
                'category' => 'damage_resistance',
                'value' => 'resistance',
                'damage_type_name' => 'Fire',
            ],
        ];

        $baseData = [
            'name' => 'Potion of Fire Resistance',
            'description' => 'Grants fire resistance.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers($modifiers, $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('resistance', $metadata['metrics']['effect_category']);
    }
}

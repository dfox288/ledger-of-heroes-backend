<?php

namespace Tests\Unit\Services\Parsers\Strategies;

use App\Services\Parsers\Strategies\PotionStrategy;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

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

    #[Test]
    public function it_handles_missing_type_code()
    {
        $baseData = ['name' => 'Some Item'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_handles_empty_type_code()
    {
        $baseData = ['type_code' => '', 'name' => 'Some Item'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_extracts_plural_hours_duration()
    {
        $baseData = [
            'name' => 'Potion of Fire Resistance',
            'description' => 'When you drink this potion, you gain resistance to fire damage for 8 hours.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('8 hours', $metadata['metrics']['duration']);
    }

    #[Test]
    public function it_extracts_singular_minute_duration()
    {
        $baseData = [
            'name' => 'Potion of Speed',
            'description' => 'You become hastened for 1 minute.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('1 minute', $metadata['metrics']['duration']);
    }

    #[Test]
    public function it_handles_no_duration_in_description()
    {
        $baseData = [
            'name' => 'Potion of Healing',
            'description' => 'You regain 2d4 + 2 hit points when you drink this potion.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertArrayNotHasKey('duration', $metadata['metrics']);
    }

    #[Test]
    public function it_categorizes_healing_by_name_containing_healing()
    {
        $baseData = [
            'name' => 'Potion of Greater Healing',
            'description' => 'This potion restores vitality.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('healing', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_categorizes_healing_by_name_containing_health()
    {
        $baseData = [
            'name' => 'Potion of Health',
            'description' => 'This potion makes you healthier.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('healing', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_categorizes_buff_by_advantage_in_description()
    {
        $baseData = [
            'name' => 'Potion of Clairvoyance',
            'description' => 'You gain advantage on Wisdom (Perception) checks for 1 hour.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('buff', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_categorizes_buff_by_stat_increase_pattern()
    {
        $baseData = [
            'name' => 'Potion of Mind Reading',
            'description' => 'Your Intelligence score is increased by 2 for 1 hour.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('buff', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_categorizes_buff_by_invulnerability_in_name()
    {
        $baseData = [
            'name' => 'Potion of Invulnerability',
            'description' => 'You gain special protection for 1 minute.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('buff', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_categorizes_debuff_by_poisoned_in_description()
    {
        $baseData = [
            'name' => 'Tainted Elixir',
            'description' => 'You become poisoned for 1 hour after drinking this.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('debuff', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_categorizes_debuff_by_disadvantage_in_description()
    {
        $baseData = [
            'name' => 'Potion of Weakness',
            'description' => 'You suffer disadvantage when making checks for 1 hour.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('debuff', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_categorizes_utility_by_growth_in_name()
    {
        $baseData = [
            'name' => 'Potion of Growth',
            'description' => 'You grow to twice your size.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('utility', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_categorizes_utility_by_flying_in_name()
    {
        $baseData = [
            'name' => 'Potion of Flying',
            'description' => 'You gain a flying speed for 1 hour.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('utility', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_returns_null_category_for_uncategorizable_potion()
    {
        $baseData = [
            'name' => 'Potion of Strange Effects',
            'description' => 'Something weird happens.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertArrayNotHasKey('effect_category', $metadata['metrics']);
    }

    #[Test]
    public function it_handles_missing_name_field()
    {
        $baseData = [
            'description' => 'You regain 2d4 + 2 hit points for 1 hour.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $modifiers = $this->strategy->enhanceModifiers([], $baseData, $xml);

        $this->assertIsArray($modifiers);
        $this->assertEquals([], $modifiers);
    }

    #[Test]
    public function it_handles_missing_description_field()
    {
        $baseData = [
            'name' => 'Potion of Healing',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $modifiers = $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertIsArray($modifiers);
        $this->assertEquals('healing', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_returns_modifiers_unchanged()
    {
        $inputModifiers = [
            [
                'category' => 'damage_resistance',
                'value' => 'resistance',
                'damage_type_name' => 'Fire',
            ],
        ];

        $baseData = [
            'name' => 'Potion of Fire Resistance',
            'description' => 'Grants fire resistance for 1 hour.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $outputModifiers = $this->strategy->enhanceModifiers($inputModifiers, $baseData, $xml);

        $this->assertEquals($inputModifiers, $outputModifiers);
    }

    #[Test]
    public function it_handles_empty_name_and_description()
    {
        $baseData = [
            'name' => '',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $modifiers = $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertIsArray($modifiers);
        $this->assertEquals([], $modifiers);
        $this->assertArrayNotHasKey('duration', $metadata['metrics']);
        $this->assertArrayNotHasKey('effect_category', $metadata['metrics']);
    }

    #[Test]
    public function it_extracts_duration_case_insensitively()
    {
        $baseData = [
            'name' => 'Potion Test',
            'description' => 'This effect lasts FOR 3 HOURS.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('3 hours', $metadata['metrics']['duration']);
    }

    #[Test]
    public function it_increments_effect_counter_for_each_category()
    {
        $baseData = [
            'name' => 'Potion of Healing',
            'description' => 'You regain 2d4 + 2 hit points.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(1, $metadata['metrics']['effect_healing']);
    }

    #[Test]
    public function it_prioritizes_healing_over_other_categories()
    {
        // A potion that could be both healing and buff should be categorized as healing
        $baseData = [
            'name' => 'Potion of Healing and Strength',
            'description' => 'You regain 2d4 + 2 hit points and your Strength increases.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('healing', $metadata['metrics']['effect_category']);
    }

    #[Test]
    public function it_prioritizes_resistance_from_modifiers_over_name()
    {
        $modifiers = [
            [
                'category' => 'damage_resistance',
                'value' => 'resistance',
            ],
        ];

        $baseData = [
            'name' => 'Potion of Strength',
            'description' => 'Your Strength score increases.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers($modifiers, $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('resistance', $metadata['metrics']['effect_category']);
    }
}

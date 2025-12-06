<?php

namespace Tests\Unit\Services\Parsers\Strategies;

use App\Services\Parsers\Strategies\LegendaryStrategy;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class LegendaryStrategyTest extends TestCase
{
    private LegendaryStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new LegendaryStrategy;
    }

    #[Test]
    public function it_applies_to_legendary_items()
    {
        $baseData = ['rarity' => 'legendary', 'name' => 'Vorpal Sword'];
        $xml = new SimpleXMLElement('<item><name>Vorpal Sword</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_applies_to_artifact_items()
    {
        $baseData = ['rarity' => 'artifact', 'name' => 'Eye of Vecna'];
        $xml = new SimpleXMLElement('<item><name>Eye of Vecna</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_to_non_legendary_items()
    {
        $baseData = ['rarity' => 'rare', 'name' => 'Flame Tongue'];
        $xml = new SimpleXMLElement('<item><name>Flame Tongue</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_detects_sentient_items()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This sword is sentient and has an Intelligence score of 17.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['is_sentient']);
        $this->assertEquals(1, $metadata['metrics']['sentient_items']);
    }

    #[Test]
    public function it_extracts_alignment_from_sentient_items()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This weapon is sentient and chaotic evil.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('chaotic evil', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_tracks_artifact_vs_legendary_metrics()
    {
        // Test legendary item
        $legendary = ['rarity' => 'legendary', 'description' => ''];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $legendary, $xml);
        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(1, $metadata['metrics']['legendary_items']);
        $this->assertArrayNotHasKey('artifacts', $metadata['metrics']);

        // Test artifact
        $artifact = ['rarity' => 'artifact', 'description' => ''];

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $artifact, $xml);
        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(1, $metadata['metrics']['artifacts']);
        $this->assertArrayNotHasKey('legendary_items', $metadata['metrics']);
    }

    #[Test]
    public function it_detects_destruction_methods_in_artifacts()
    {
        $baseData = [
            'rarity' => 'artifact',
            'description' => 'The artifact can only be destroyed by casting it into the fires of Mount Doom.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['has_destruction_method']);
    }

    #[Test]
    public function it_extracts_personality_traits()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This sentient sword is arrogant and cruel, seeking only power.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertContains('arrogant', $metadata['metrics']['personality_traits']);
        $this->assertContains('cruel', $metadata['metrics']['personality_traits']);
    }

    #[Test]
    public function it_applies_to_legendary_case_insensitive()
    {
        $baseData = ['rarity' => 'LEGENDARY', 'name' => 'Test Item'];
        $xml = new SimpleXMLElement('<item><name>Test Item</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_applies_to_artifact_case_insensitive()
    {
        $baseData = ['rarity' => 'ARTIFACT', 'name' => 'Test Item'];
        $xml = new SimpleXMLElement('<item><name>Test Item</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_when_rarity_missing()
    {
        $baseData = ['name' => 'Test Item'];
        $xml = new SimpleXMLElement('<item><name>Test Item</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_to_common_items()
    {
        $baseData = ['rarity' => 'common', 'name' => 'Test Item'];
        $xml = new SimpleXMLElement('<item><name>Test Item</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_to_uncommon_items()
    {
        $baseData = ['rarity' => 'uncommon', 'name' => 'Test Item'];
        $xml = new SimpleXMLElement('<item><name>Test Item</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_to_very_rare_items()
    {
        $baseData = ['rarity' => 'very rare', 'name' => 'Test Item'];
        $xml = new SimpleXMLElement('<item><name>Test Item</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_handles_non_sentient_legendary_items()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This is a powerful magical sword with a +3 bonus.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertArrayNotHasKey('is_sentient', $metadata['metrics']);
        $this->assertArrayNotHasKey('sentient_items', $metadata['metrics']);
        $this->assertEquals(1, $metadata['metrics']['legendary_items']);
    }

    #[Test]
    public function it_detects_sentience_via_intelligence_score()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'The blade has an Intelligence score of 14.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['is_sentient']);
    }

    #[Test]
    public function it_detects_sentience_via_wisdom_score()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'The item possesses a Wisdom score of 12.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['is_sentient']);
    }

    #[Test]
    public function it_detects_sentience_via_charisma_score()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This relic has a Charisma score of 18.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['is_sentient']);
    }

    #[Test]
    public function it_detects_sentience_via_telepathy()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'The sword communicates via telepathy.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['is_sentient']);
    }

    #[Test]
    public function it_detects_sentience_via_speaks()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'The weapon speaks in a deep, resonant voice.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['is_sentient']);
    }

    #[Test]
    public function it_detects_sentience_via_communicates()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'The artifact communicates with its bearer.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['is_sentient']);
    }

    #[Test]
    public function it_extracts_alignment_from_detail_field()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This weapon is sentient.',
            'detail' => 'The sword is lawful good and seeks to destroy evil.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('lawful good', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_extracts_lawful_neutral_alignment()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This sentient item is lawful neutral in nature.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('lawful neutral', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_extracts_lawful_evil_alignment()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'The sentient blade is lawful evil.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('lawful evil', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_extracts_neutral_good_alignment()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This sentient weapon is neutral good.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('neutral good', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_extracts_true_neutral_alignment()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'The sentient artifact is true neutral.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('true neutral', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_extracts_neutral_evil_alignment()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This sentient blade is neutral evil.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('neutral evil', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_extracts_chaotic_good_alignment()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'The sentient weapon is chaotic good.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('chaotic good', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_extracts_chaotic_neutral_alignment()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This sentient sword is chaotic neutral.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('chaotic neutral', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_extracts_unaligned_alignment()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'The sentient artifact is unaligned.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('unaligned', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_extracts_first_alignment_when_multiple_present()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'The sentient blade was once lawful good but became chaotic evil.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('lawful good', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_handles_sentient_item_without_alignment()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This weapon is sentient but its alignment is unclear.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['is_sentient']);
        $this->assertArrayNotHasKey('alignment', $metadata['metrics']);
    }

    #[Test]
    public function it_handles_empty_description()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceModifiers([], $baseData, $xml);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_missing_description_field()
    {
        $baseData = ['rarity' => 'legendary'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceModifiers([], $baseData, $xml);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_missing_detail_field()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This is sentient and lawful good.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('lawful good', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_handles_artifact_without_destruction_method()
    {
        $baseData = [
            'rarity' => 'artifact',
            'description' => 'A powerful ancient relic with immense power.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(1, $metadata['metrics']['artifacts']);
        $this->assertArrayNotHasKey('has_destruction_method', $metadata['metrics']);
    }

    #[Test]
    public function it_detects_destruction_method_case_insensitive()
    {
        $baseData = [
            'rarity' => 'artifact',
            'description' => 'To DESTROY this artifact, cast it into the abyss.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['has_destruction_method']);
    }

    #[Test]
    public function it_extracts_multiple_personality_traits()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This sentient weapon is arrogant, cruel, greedy, and cunning.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertCount(4, $metadata['metrics']['personality_traits']);
        $this->assertContains('arrogant', $metadata['metrics']['personality_traits']);
        $this->assertContains('cruel', $metadata['metrics']['personality_traits']);
        $this->assertContains('greedy', $metadata['metrics']['personality_traits']);
        $this->assertContains('cunning', $metadata['metrics']['personality_traits']);
    }

    #[Test]
    public function it_extracts_all_possible_personality_traits()
    {
        $traits = [
            'arrogant', 'kind', 'cruel', 'benevolent', 'malevolent',
            'proud', 'humble', 'greedy', 'generous', 'wrathful',
            'patient', 'impulsive', 'cunning', 'straightforward',
        ];

        foreach ($traits as $trait) {
            $baseData = [
                'rarity' => 'legendary',
                'description' => "This sentient blade is $trait.",
            ];
            $xml = new SimpleXMLElement('<item><name>Test</name></item>');

            $this->strategy->reset();
            $this->strategy->enhanceModifiers([], $baseData, $xml);

            $metadata = $this->strategy->extractMetadata();

            $this->assertContains($trait, $metadata['metrics']['personality_traits'], "Failed to extract trait: $trait");
        }
    }

    #[Test]
    public function it_handles_sentient_item_without_personality_traits()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This weapon is sentient but has no discernible personality.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['is_sentient']);
        $this->assertArrayNotHasKey('personality_traits', $metadata['metrics']);
    }

    #[Test]
    public function it_returns_modifiers_unchanged()
    {
        $modifiers = [
            ['type' => 'bonus', 'value' => '+3'],
            ['type' => 'damage', 'value' => '1d6'],
        ];

        $baseData = [
            'rarity' => 'legendary',
            'description' => 'A powerful sword.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceModifiers($modifiers, $baseData, $xml);

        $this->assertEquals($modifiers, $result);
    }

    #[Test]
    public function it_handles_sentience_detection_case_insensitive()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This blade is SENTIENT and possesses great power.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertTrue($metadata['metrics']['is_sentient']);
    }

    #[Test]
    public function it_handles_alignment_detection_case_insensitive()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This sentient weapon is LAWFUL GOOD.',
            'detail' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('lawful good', $metadata['metrics']['alignment']);
    }

    #[Test]
    public function it_handles_personality_traits_case_insensitive()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This sentient sword is ARROGANT and CRUEL.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertContains('arrogant', $metadata['metrics']['personality_traits']);
        $this->assertContains('cruel', $metadata['metrics']['personality_traits']);
    }

    #[Test]
    public function it_combines_description_and_detail_for_alignment()
    {
        $baseData = [
            'rarity' => 'legendary',
            'description' => 'This weapon is sentient.',
            'detail' => 'It has a chaotic good alignment.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('chaotic good', $metadata['metrics']['alignment']);
    }
}

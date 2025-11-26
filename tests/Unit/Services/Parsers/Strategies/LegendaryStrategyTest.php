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
}

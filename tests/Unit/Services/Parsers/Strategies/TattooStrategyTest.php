<?php

namespace Tests\Unit\Services\Parsers\Strategies;

use App\Services\Parsers\Strategies\TattooStrategy;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

class TattooStrategyTest extends TestCase
{
    private TattooStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new TattooStrategy;
    }

    #[Test]
    public function it_applies_to_wondrous_items_with_tattoo_in_name()
    {
        $baseData = ['type_code' => 'W', 'name' => 'Absorbing Tattoo'];
        $xml = new SimpleXMLElement('<item><name>Absorbing Tattoo</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_to_non_tattoo_wondrous_items()
    {
        $baseData = ['type_code' => 'W', 'name' => 'Bag of Holding'];
        $xml = new SimpleXMLElement('<item><name>Bag of Holding</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_to_non_wondrous_items()
    {
        $baseData = ['type_code' => 'ST', 'name' => 'Staff Tattoo']; // Hypothetical
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_extracts_tattoo_type_from_name()
    {
        $baseData = [
            'name' => 'Absorbing Tattoo',
            'description' => 'This tattoo features designs that emphasize one color.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('absorbing', $metadata['metrics']['tattoo_type']);
    }

    #[Test]
    public function it_detects_action_activation()
    {
        $baseData = [
            'name' => 'Illuminator\'s Tattoo',
            'description' => 'As an action, you can shed bright light in a 30-foot radius.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertArrayHasKey('activation_methods', $metadata['metrics']);
        $this->assertContains('action', $metadata['metrics']['activation_methods']);
    }

    #[Test]
    public function it_tracks_metrics_for_tattoo_types()
    {
        $baseData = [
            'name' => 'Masquerade Tattoo',
            'description' => 'This tattoo allows you to change your appearance.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(1, $metadata['metrics']['type_masquerade']);
    }
}

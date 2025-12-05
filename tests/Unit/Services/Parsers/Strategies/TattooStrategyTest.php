<?php

namespace Tests\Unit\Services\Parsers\Strategies;

use App\Services\Parsers\Strategies\TattooStrategy;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

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

    #[Test]
    public function it_detects_bonus_action_activation()
    {
        $baseData = [
            'name' => 'Blood Fury Tattoo',
            'description' => 'You can use a bonus action to activate this tattoo.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertContains('bonus_action', $metadata['metrics']['activation_methods']);
    }

    #[Test]
    public function it_detects_reaction_activation()
    {
        $baseData = [
            'name' => 'Guardian Tattoo',
            'description' => 'As a reaction, you can grant yourself a bonus to AC.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertContains('reaction', $metadata['metrics']['activation_methods']);
    }

    #[Test]
    public function it_detects_passive_activation_with_while_keyword()
    {
        $baseData = [
            'name' => 'Barrier Tattoo',
            'description' => 'While you are not wearing armor, this tattoo grants you an AC of 12 + Dex.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertContains('passive', $metadata['metrics']['activation_methods']);
    }

    #[Test]
    public function it_detects_passive_activation_with_whenever_keyword()
    {
        $baseData = [
            'name' => 'Test Tattoo',
            'description' => 'Whenever you are damaged, you gain temporary hit points.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertContains('passive', $metadata['metrics']['activation_methods']);
    }

    #[Test]
    public function it_returns_empty_activation_methods_when_none_found()
    {
        $baseData = [
            'name' => 'Simple Tattoo',
            'description' => 'This tattoo is purely decorative.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEmpty($metadata['metrics']['activation_methods']);
    }

    #[Test]
    public function it_extracts_body_location_arm()
    {
        $baseData = [
            'name' => 'Absorbing Tattoo',
            'description' => 'This tattoo is applied to your arm and absorbs damage.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('arm', $metadata['metrics']['body_location']);
    }

    #[Test]
    public function it_extracts_body_location_chest()
    {
        $baseData = [
            'name' => 'Guardian Tattoo',
            'description' => 'Applied to your chest, this tattoo protects you.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('chest', $metadata['metrics']['body_location']);
    }

    #[Test]
    public function it_extracts_body_location_back()
    {
        $baseData = [
            'name' => 'Test Tattoo',
            'description' => 'This tattoo spans across your back.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('back', $metadata['metrics']['body_location']);
    }

    #[Test]
    public function it_handles_missing_body_location()
    {
        $baseData = [
            'name' => 'Generic Tattoo',
            'description' => 'This tattoo grants you special powers.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertArrayNotHasKey('body_location', $metadata['metrics']);
    }

    #[Test]
    public function it_handles_empty_description()
    {
        $baseData = [
            'name' => 'Test Tattoo',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEmpty($metadata['metrics']['activation_methods']);
        $this->assertArrayNotHasKey('body_location', $metadata['metrics']);
    }

    #[Test]
    public function it_handles_missing_description()
    {
        $baseData = ['name' => 'Test Tattoo'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEmpty($metadata['metrics']['activation_methods']);
    }

    #[Test]
    public function it_handles_missing_name()
    {
        $baseData = ['description' => 'This tattoo is magical.'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertArrayNotHasKey('tattoo_type', $metadata['metrics']);
    }

    #[Test]
    public function it_extracts_multi_word_tattoo_type()
    {
        $baseData = [
            'name' => 'Blood Fury Tattoo',
            'description' => 'This tattoo enhances your rage.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('blood_fury', $metadata['metrics']['tattoo_type']);
        $this->assertEquals(1, $metadata['metrics']['type_blood_fury']);
    }

    #[Test]
    public function it_extracts_tattoo_type_with_apostrophe()
    {
        $baseData = [
            'name' => 'Illuminator\'s Tattoo',
            'description' => 'This tattoo sheds light.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('illuminator\'s', $metadata['metrics']['tattoo_type']);
    }

    #[Test]
    public function it_handles_case_insensitive_tattoo_in_name()
    {
        $baseData = ['type_code' => 'W', 'name' => 'ABSORBING TATTOO'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_handles_tattoo_lowercase_in_name()
    {
        $baseData = ['type_code' => 'W', 'name' => 'absorbing tattoo'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_detects_multiple_activation_methods()
    {
        $baseData = [
            'name' => 'Versatile Tattoo',
            'description' => 'You can use an action or bonus action to activate this tattoo. As a reaction, you can also deflect attacks.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertCount(3, $metadata['metrics']['activation_methods']);
        $this->assertContains('action', $metadata['metrics']['activation_methods']);
        $this->assertContains('bonus_action', $metadata['metrics']['activation_methods']);
        $this->assertContains('reaction', $metadata['metrics']['activation_methods']);
    }

    #[Test]
    public function it_returns_unmodified_modifiers()
    {
        $modifiers = [['category' => 'ac_magic', 'value' => 2]];
        $baseData = [
            'name' => 'Barrier Tattoo',
            'description' => 'This tattoo grants you an AC bonus.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceModifiers($modifiers, $baseData, $xml);

        $this->assertEquals($modifiers, $result);
    }

    #[Test]
    public function it_detects_use_action_phrasing()
    {
        $baseData = [
            'name' => 'Test Tattoo',
            'description' => 'You can use action to invoke its power.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertContains('action', $metadata['metrics']['activation_methods']);
    }

    #[Test]
    public function it_detects_take_action_phrasing()
    {
        $baseData = [
            'name' => 'Test Tattoo',
            'description' => 'You must take action to activate this tattoo.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertContains('action', $metadata['metrics']['activation_methods']);
    }
}

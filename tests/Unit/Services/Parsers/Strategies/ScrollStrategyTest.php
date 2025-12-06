<?php

namespace Tests\Unit\Services\Parsers\Strategies;

use App\Services\Parsers\Strategies\ScrollStrategy;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]

class ScrollStrategyTest extends TestCase
{
    private ScrollStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new ScrollStrategy;
    }

    #[Test]
    public function it_applies_to_scroll_items()
    {
        $baseData = ['type_code' => 'SC', 'name' => 'Spell Scroll'];
        $xml = new SimpleXMLElement('<item><name>Spell Scroll</name></item>');

        $this->assertTrue($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_does_not_apply_to_non_scroll_items()
    {
        $baseData = ['type_code' => 'ST', 'name' => 'Staff of Fire'];
        $xml = new SimpleXMLElement('<item><name>Staff of Fire</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_extracts_spell_level_from_spell_scroll_name()
    {
        $baseData = [
            'name' => 'Spell Scroll (3rd Level)',
            'description' => 'A scroll containing a 3rd-level spell.',
        ];
        $xml = new SimpleXMLElement('<item><name>Spell Scroll (3rd Level)</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertArrayHasKey('spell_level', $relationships);
        $this->assertEquals(3, $relationships['spell_level']);

        $metadata = $this->strategy->extractMetadata();
        $this->assertEquals(3, $metadata['metrics']['spell_level']);
    }

    #[Test]
    public function it_extracts_spell_level_from_cantrip_scroll()
    {
        $baseData = [
            'name' => 'Spell Scroll (Cantrip)',
            'description' => 'A scroll containing a cantrip.',
        ];
        $xml = new SimpleXMLElement('<item><name>Spell Scroll (Cantrip)</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertArrayHasKey('spell_level', $relationships);
        $this->assertEquals(0, $relationships['spell_level']);

        $metadata = $this->strategy->extractMetadata();
        $this->assertEquals(0, $metadata['metrics']['spell_level']);
    }

    #[Test]
    public function it_handles_various_spell_level_formats()
    {
        $testCases = [
            ['Spell Scroll (1st Level)', 1],
            ['Spell Scroll (2nd Level)', 2],
            ['Spell Scroll (3rd Level)', 3],
            ['Spell Scroll (4th Level)', 4],
            ['Spell Scroll (9th Level)', 9],
        ];

        foreach ($testCases as [$name, $expectedLevel]) {
            $this->strategy->reset();
            $baseData = ['name' => $name, 'description' => ''];
            $xml = new SimpleXMLElement("<item><name>{$name}</name></item>");

            $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

            $this->assertEquals($expectedLevel, $relationships['spell_level'], "Failed for: {$name}");
        }
    }

    #[Test]
    public function it_distinguishes_protection_scrolls_from_spell_scrolls()
    {
        $baseData = [
            'name' => 'Scroll of Protection from Aberrations',
            'description' => 'Using an action to read the scroll encloses you in an invisible barrier. For 5 minutes...',
        ];
        $xml = new SimpleXMLElement('<item><name>Scroll of Protection from Aberrations</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        // Protection scrolls should NOT have spell_level
        $this->assertArrayNotHasKey('spell_level', $metadata['metrics']);

        // Should increment protection_scrolls counter
        $this->assertEquals(1, $metadata['metrics']['protection_scrolls']);
    }

    #[Test]
    public function it_tracks_spell_scroll_vs_protection_scroll_metrics()
    {
        // Test spell scroll
        $spellScroll = [
            'name' => 'Spell Scroll (5th Level)',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $spellScroll, $xml);
        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(1, $metadata['metrics']['spell_scrolls']);
        $this->assertArrayNotHasKey('protection_scrolls', $metadata['metrics']);

        // Test protection scroll
        $protectionScroll = [
            'name' => 'Scroll of Protection from Undead',
            'description' => 'For 10 minutes...',
        ];

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $protectionScroll, $xml);
        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(1, $metadata['metrics']['protection_scrolls']);
        $this->assertArrayNotHasKey('spell_scrolls', $metadata['metrics']);
    }

    #[Test]
    public function it_extracts_duration_from_protection_scroll_descriptions()
    {
        $baseData = [
            'name' => 'Scroll of Protection from Dragons',
            'description' => 'Using an action to read the scroll encloses you in an invisible barrier. For 5 minutes, creatures of the chosen type cannot willingly enter or affect anything within the barrier.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('5 minutes', $metadata['metrics']['protection_duration']);
    }

    #[Test]
    public function it_warns_when_spell_level_cannot_be_extracted()
    {
        $baseData = [
            'name' => 'Spell Scroll (Unknown)',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceRelationships($baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertCount(1, $metadata['warnings']);
        $this->assertStringContainsString('Could not extract spell level', $metadata['warnings'][0]);
    }

    #[Test]
    public function it_returns_empty_relationships_for_protection_scrolls()
    {
        $baseData = [
            'name' => 'Scroll of Protection from Fiends',
            'description' => 'For 5 minutes...',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        // Protection scrolls should not add spell_level
        $this->assertEmpty($relationships);
    }

    #[Test]
    public function it_handles_missing_type_code()
    {
        $baseData = ['name' => 'Some Item'];
        $xml = new SimpleXMLElement('<item><name>Some Item</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_handles_empty_type_code()
    {
        $baseData = ['type_code' => '', 'name' => 'Some Item'];
        $xml = new SimpleXMLElement('<item><name>Some Item</name></item>');

        $this->assertFalse($this->strategy->appliesTo($baseData, $xml));
    }

    #[Test]
    public function it_handles_missing_name_in_enhance_modifiers()
    {
        $baseData = ['description' => 'Some description'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceModifiers([], $baseData, $xml);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_missing_description_in_enhance_modifiers()
    {
        $baseData = ['name' => 'Scroll of Protection from Dragons'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(1, $metadata['metrics']['protection_scrolls']);
        $this->assertArrayNotHasKey('protection_duration', $metadata['metrics']);
    }

    #[Test]
    public function it_handles_hour_duration_in_protection_scrolls()
    {
        $baseData = [
            'name' => 'Scroll of Protection from Magic',
            'description' => 'For 1 hour, you are protected.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('1 hour', $metadata['metrics']['protection_duration']);
    }

    #[Test]
    public function it_handles_plural_hours_duration_in_protection_scrolls()
    {
        $baseData = [
            'name' => 'Scroll of Protection from Magic',
            'description' => 'For 8 hours, you are protected.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('8 hours', $metadata['metrics']['protection_duration']);
    }

    #[Test]
    public function it_handles_plural_minutes_duration_in_protection_scrolls()
    {
        $baseData = [
            'name' => 'Scroll of Protection from Fiends',
            'description' => 'For 10 minutes, creatures cannot enter.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals('10 minutes', $metadata['metrics']['protection_duration']);
    }

    #[Test]
    public function it_handles_missing_name_in_enhance_relationships()
    {
        $baseData = ['description' => 'Some description'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertEmpty($relationships);
    }

    #[Test]
    public function it_handles_empty_name_in_enhance_relationships()
    {
        $baseData = ['name' => '', 'description' => ''];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertEmpty($relationships);
    }

    #[Test]
    public function it_handles_case_insensitive_protection_scroll_detection()
    {
        $baseData = [
            'name' => 'SCROLL OF PROTECTION FROM UNDEAD',
            'description' => 'For 5 minutes...',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertEmpty($relationships);
    }

    #[Test]
    public function it_handles_case_insensitive_spell_scroll_detection()
    {
        $baseData = [
            'name' => 'SPELL SCROLL (3RD LEVEL)',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertEquals(3, $relationships['spell_level']);
    }

    #[Test]
    public function it_handles_case_insensitive_cantrip_scroll_detection()
    {
        $baseData = [
            'name' => 'SPELL SCROLL (CANTRIP)',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertEquals(0, $relationships['spell_level']);
    }

    #[Test]
    public function it_does_not_match_spell_scroll_with_excessive_whitespace()
    {
        $baseData = [
            'name' => 'Spell Scroll (  3rd  Level  )',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        // Excessive whitespace in parentheses doesn't match the regex pattern
        $this->assertEmpty($relationships);
        $metadata = $this->strategy->extractMetadata();
        $this->assertCount(1, $metadata['warnings']);
    }

    #[Test]
    public function it_does_not_match_cantrip_scroll_with_excessive_whitespace()
    {
        $baseData = [
            'name' => 'Spell Scroll (  Cantrip  )',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        // Excessive whitespace in parentheses doesn't match the regex pattern
        $this->assertEmpty($relationships);
        $metadata = $this->strategy->extractMetadata();
        $this->assertCount(1, $metadata['warnings']);
    }

    #[Test]
    public function it_returns_empty_array_for_unrecognized_spell_scroll_format()
    {
        $baseData = [
            'name' => 'Spell Scroll of Fireball',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $relationships = $this->strategy->enhanceRelationships($baseData, $xml);

        $this->assertEmpty($relationships);
        $metadata = $this->strategy->extractMetadata();
        $this->assertCount(1, $metadata['warnings']);
    }

    #[Test]
    public function it_accumulates_spell_scroll_metrics_across_multiple_calls()
    {
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], ['name' => 'Spell Scroll (1st Level)'], $xml);
        $this->strategy->enhanceModifiers([], ['name' => 'Spell Scroll (2nd Level)'], $xml);
        $this->strategy->enhanceModifiers([], ['name' => 'Spell Scroll (3rd Level)'], $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(3, $metadata['metrics']['spell_scrolls']);
    }

    #[Test]
    public function it_accumulates_protection_scroll_metrics_across_multiple_calls()
    {
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], ['name' => 'Scroll of Protection from Aberrations'], $xml);
        $this->strategy->enhanceModifiers([], ['name' => 'Scroll of Protection from Undead'], $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(2, $metadata['metrics']['protection_scrolls']);
    }

    #[Test]
    public function it_preserves_passed_modifiers_in_enhance_modifiers()
    {
        $existingModifiers = [
            ['type' => 'bonus', 'value' => '+1'],
        ];
        $baseData = ['name' => 'Spell Scroll (1st Level)'];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $result = $this->strategy->enhanceModifiers($existingModifiers, $baseData, $xml);

        $this->assertEquals($existingModifiers, $result);
    }

    #[Test]
    public function it_handles_protection_in_middle_of_name()
    {
        $baseData = [
            'name' => 'Ancient Protection Scroll',
            'description' => 'For 5 minutes...',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEquals(1, $metadata['metrics']['protection_scrolls']);
    }

    #[Test]
    public function it_handles_duration_with_case_variations()
    {
        $baseData = [
            'name' => 'Scroll of Protection',
            'description' => 'For 5 Minutes, you are protected.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        // The regex preserves the original case from the description
        $this->assertEquals('5 Minutes', $metadata['metrics']['protection_duration']);
    }

    #[Test]
    public function it_does_not_extract_duration_from_spell_scrolls()
    {
        $baseData = [
            'name' => 'Spell Scroll (3rd Level)',
            'description' => 'For 10 minutes, the spell lasts.',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceModifiers([], $baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertArrayNotHasKey('protection_duration', $metadata['metrics']);
    }

    #[Test]
    public function it_does_not_warn_for_non_spell_scroll_items()
    {
        $baseData = [
            'name' => 'Random Scroll',
            'description' => '',
        ];
        $xml = new SimpleXMLElement('<item><name>Test</name></item>');

        $this->strategy->reset();
        $this->strategy->enhanceRelationships($baseData, $xml);

        $metadata = $this->strategy->extractMetadata();

        $this->assertEmpty($metadata['warnings']);
    }
}

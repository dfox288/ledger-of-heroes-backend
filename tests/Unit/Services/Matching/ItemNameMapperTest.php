<?php

namespace Tests\Unit\Services\Matching;

use App\Services\Matching\ItemNameMapper;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemNameMapperTest extends TestCase
{
    private ItemNameMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new ItemNameMapper;
    }

    #[Test]
    public function it_maps_currency_abbreviations_to_canonical_names()
    {
        $this->assertSame('Gold (gp)', $this->mapper->map('gp'));
        $this->assertSame('Silver (sp)', $this->mapper->map('sp'));
        $this->assertSame('Copper (cp)', $this->mapper->map('cp'));
        $this->assertSame('Electrum (ep)', $this->mapper->map('ep'));
        $this->assertSame('Platinum (pp)', $this->mapper->map('pp'));
    }

    #[Test]
    public function it_maps_currency_abbreviations_case_insensitively()
    {
        $this->assertSame('Gold (gp)', $this->mapper->map('GP'));
        $this->assertSame('Gold (gp)', $this->mapper->map('Gp'));
        $this->assertSame('Silver (sp)', $this->mapper->map('SP'));
        $this->assertSame('Platinum (pp)', $this->mapper->map('PP'));
    }

    #[Test]
    public function it_maps_common_item_variants()
    {
        $this->assertSame('Pouch', $this->mapper->map('belt pouch'));
        $this->assertSame('Pouch', $this->mapper->map('purse'));
        $this->assertSame('Holy Symbol', $this->mapper->map('holy symbol'));
        $this->assertSame('Prayer Book', $this->mapper->map('prayer book'));
        $this->assertSame('Prayer Wheel', $this->mapper->map('prayer wheel'));
    }

    #[Test]
    public function it_maps_writing_implements()
    {
        $this->assertSame('Ink Pen', $this->mapper->map('quill'));
        $this->assertSame('Ink (1-ounce bottle)', $this->mapper->map('bottle of black ink'));
    }

    #[Test]
    public function it_maps_rope_variants()
    {
        $this->assertSame('Silk Rope (50 feet)', $this->mapper->map('feet of silk rope'));
        $this->assertSame('Silk Rope (50 feet)', $this->mapper->map('silk rope'));
    }

    #[Test]
    public function it_returns_original_name_when_no_mapping_exists()
    {
        $this->assertSame('Longsword', $this->mapper->map('Longsword'));
        $this->assertSame('Healing Potion', $this->mapper->map('Healing Potion'));
        $this->assertSame('Bag of Holding', $this->mapper->map('Bag of Holding'));
        $this->assertSame('Unknown Item', $this->mapper->map('Unknown Item'));
    }

    #[Test]
    public function it_trims_whitespace_before_mapping()
    {
        $this->assertSame('Gold (gp)', $this->mapper->map('  gp  '));
        $this->assertSame('Pouch', $this->mapper->map('  belt pouch  '));
        $this->assertSame('Holy Symbol', $this->mapper->map("\t holy symbol \n"));
    }

    #[Test]
    public function it_handles_empty_strings()
    {
        // Empty string returns empty string
        $this->assertSame('', $this->mapper->map(''));

        // Whitespace-only string returns empty after trim (no mapping found)
        // Note: trim('   ') = '', and no mapping exists for '', so returns original ''
        // However, the mapper returns the ORIGINAL string when no mapping found
        // So it returns '   ' (the original input)
        $this->assertSame('   ', $this->mapper->map('   '));
    }

    #[Test]
    public function it_preserves_original_case_when_no_mapping_exists()
    {
        $this->assertSame('MyCustomItem', $this->mapper->map('MyCustomItem'));
        $this->assertSame('UPPERCASE', $this->mapper->map('UPPERCASE'));
        $this->assertSame('MiXeD CaSe', $this->mapper->map('MiXeD CaSe'));
    }

    #[Test]
    public function it_handles_special_characters_in_unmapped_names()
    {
        $this->assertSame('Item+1', $this->mapper->map('Item+1'));
        $this->assertSame('Item (Variant)', $this->mapper->map('Item (Variant)'));
        $this->assertSame('Item-Name', $this->mapper->map('Item-Name'));
        $this->assertSame("Item's Name", $this->mapper->map("Item's Name"));
    }

    #[Test]
    public function has_mapping_returns_true_for_existing_mappings()
    {
        $this->assertTrue($this->mapper->hasMapping('gp'));
        $this->assertTrue($this->mapper->hasMapping('belt pouch'));
        $this->assertTrue($this->mapper->hasMapping('quill'));
        $this->assertTrue($this->mapper->hasMapping('silk rope'));
    }

    #[Test]
    public function has_mapping_returns_false_for_non_existing_mappings()
    {
        $this->assertFalse($this->mapper->hasMapping('Longsword'));
        $this->assertFalse($this->mapper->hasMapping('Unknown'));
        $this->assertFalse($this->mapper->hasMapping(''));
    }

    #[Test]
    public function has_mapping_is_case_insensitive()
    {
        $this->assertTrue($this->mapper->hasMapping('GP'));
        $this->assertTrue($this->mapper->hasMapping('Gp'));
        $this->assertTrue($this->mapper->hasMapping('BELT POUCH'));
        $this->assertTrue($this->mapper->hasMapping('Belt Pouch'));
    }

    #[Test]
    public function has_mapping_trims_whitespace()
    {
        $this->assertTrue($this->mapper->hasMapping('  gp  '));
        $this->assertTrue($this->mapper->hasMapping("\t belt pouch \n"));
    }

    #[Test]
    public function get_all_mappings_returns_array()
    {
        $mappings = $this->mapper->getAllMappings();

        $this->assertIsArray($mappings);
        $this->assertNotEmpty($mappings);
    }

    #[Test]
    public function get_all_mappings_contains_expected_keys()
    {
        $mappings = $this->mapper->getAllMappings();

        $this->assertArrayHasKey('gp', $mappings);
        $this->assertArrayHasKey('sp', $mappings);
        $this->assertArrayHasKey('belt pouch', $mappings);
        $this->assertArrayHasKey('quill', $mappings);
        $this->assertArrayHasKey('silk rope', $mappings);
    }

    #[Test]
    public function get_all_mappings_has_correct_values()
    {
        $mappings = $this->mapper->getAllMappings();

        $this->assertSame('Gold (gp)', $mappings['gp']);
        $this->assertSame('Silver (sp)', $mappings['sp']);
        $this->assertSame('Pouch', $mappings['belt pouch']);
        $this->assertSame('Ink Pen', $mappings['quill']);
    }

    #[Test]
    public function mapping_is_idempotent()
    {
        // Mapping the same value multiple times gives the same result
        $this->assertSame('Gold (gp)', $this->mapper->map('gp'));
        $this->assertSame('Gold (gp)', $this->mapper->map('gp'));
        $this->assertSame('Gold (gp)', $this->mapper->map('gp'));
    }

    #[Test]
    public function mapping_does_not_chain()
    {
        // Mapping 'gp' gives 'Gold (gp)'
        // Mapping 'Gold (gp)' should return 'Gold (gp)' (no further mapping)
        $mapped = $this->mapper->map('gp');
        $this->assertSame('Gold (gp)', $mapped);

        $remapped = $this->mapper->map($mapped);
        $this->assertSame('Gold (gp)', $remapped);
    }

    #[Test]
    public function it_handles_unicode_characters_in_unmapped_names()
    {
        $this->assertSame('Élven Sword', $this->mapper->map('Élven Sword'));
        $this->assertSame('Mañana Potion', $this->mapper->map('Mañana Potion'));
        $this->assertSame('Dragon™', $this->mapper->map('Dragon™'));
    }

    #[Test]
    public function it_handles_numeric_strings()
    {
        $this->assertSame('123', $this->mapper->map('123'));
        $this->assertSame('Item 42', $this->mapper->map('Item 42'));
    }

    #[Test]
    public function all_mappings_use_lowercase_keys()
    {
        $mappings = $this->mapper->getAllMappings();

        foreach (array_keys($mappings) as $key) {
            $this->assertSame(strtolower($key), $key, "Mapping key '{$key}' should be lowercase");
        }
    }
}

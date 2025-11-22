<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\Concerns\ParsesItemSavingThrows;
use Tests\TestCase;

class ItemSavingThrowsParserTest extends TestCase
{
    use ParsesItemSavingThrows;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_dc_10_charisma_save()
    {
        $description = 'The target must succeed on a DC 10 Charisma saving throw or be forced to smile for 1 minute.';
        $result = $this->parseItemSavingThrow($description);

        $this->assertIsArray($result);
        $this->assertSame('CHA', $result['ability_code']);
        $this->assertSame('negates', $result['save_effect']);
        $this->assertTrue($result['is_initial_save']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_dc_15_dexterity_save()
    {
        $description = 'Each creature must make a DC 15 Dexterity saving throw or take 5d4 fire damage.';
        $result = $this->parseItemSavingThrow($description);

        $this->assertIsArray($result);
        $this->assertSame('DEX', $result['ability_code']);
        $this->assertSame('half_damage', $result['save_effect']); // "or take damage" = half on save
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_dc_15_dexterity_save_with_half_damage_explicit()
    {
        $description = 'DC 15 Dexterity saving throw, taking 5d4 fire damage on a failed save, or half as much damage on a successful one.';
        $result = $this->parseItemSavingThrow($description);

        $this->assertIsArray($result);
        $this->assertSame('DEX', $result['ability_code']);
        $this->assertSame('half_damage', $result['save_effect']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_dc_17_charisma_save()
    {
        $description = 'You must make a DC 17 Charisma saving throw.';
        $result = $this->parseItemSavingThrow($description);

        $this->assertIsArray($result);
        $this->assertSame('CHA', $result['ability_code']);
        $this->assertSame('negates', $result['save_effect']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_dc_10_constitution_save()
    {
        $description = 'A creature subjected to this poison must make a DC 10 Constitution saving throw.';
        $result = $this->parseItemSavingThrow($description);

        $this->assertIsArray($result);
        $this->assertSame('CON', $result['ability_code']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_wisdom_saving_throw()
    {
        $description = 'The creature must make a DC 15 Wisdom saving throw or be frightened.';
        $result = $this->parseItemSavingThrow($description);

        $this->assertIsArray($result);
        $this->assertSame('WIS', $result['ability_code']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_strength_saving_throw()
    {
        $description = 'Make a DC 12 Strength saving throw or be pushed back.';
        $result = $this->parseItemSavingThrow($description);

        $this->assertIsArray($result);
        $this->assertSame('STR', $result['ability_code']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_parses_intelligence_saving_throw()
    {
        $description = 'Succeed on a DC 14 Intelligence saving throw.';
        $result = $this->parseItemSavingThrow($description);

        $this->assertIsArray($result);
        $this->assertSame('INT', $result['ability_code']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_for_items_without_saves()
    {
        $description = 'This wand has 3 charges and can cast spells.';
        $result = $this->parseItemSavingThrow($description);

        $this->assertNull($result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_case_insensitive_ability_names()
    {
        $description = 'DC 10 charisma SAVING THROW';
        $result = $this->parseItemSavingThrow($description);

        $this->assertIsArray($result);
        $this->assertSame('CHA', $result['ability_code']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_detects_half_damage_from_various_phrasings()
    {
        $texts = [
            'DC 15 Dexterity saving throw, taking 5d4 damage on a failed save, or half as much on a successful one',
            'DC 15 DEX save or take 5d4 damage, half on success',
            'DC 15 Dexterity saving throw, taking damage on failure or half damage on success',
        ];

        foreach ($texts as $text) {
            $result = $this->parseItemSavingThrow($text);
            $this->assertSame('half_damage', $result['save_effect'], "Failed for: {$text}");
        }
    }
}

<?php

namespace Tests\Unit\Enums;

use App\Enums\DataTableType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DataTableTypeTest extends TestCase
{
    #[Test]
    public function it_has_correct_values(): void
    {
        $this->assertSame('random', DataTableType::RANDOM->value);
        $this->assertSame('damage', DataTableType::DAMAGE->value);
        $this->assertSame('modifier', DataTableType::MODIFIER->value);
        $this->assertSame('lookup', DataTableType::LOOKUP->value);
        $this->assertSame('progression', DataTableType::PROGRESSION->value);
    }

    #[Test]
    public function it_has_correct_labels(): void
    {
        $this->assertSame('Random Table', DataTableType::RANDOM->label());
        $this->assertSame('Damage Dice', DataTableType::DAMAGE->label());
        $this->assertSame('Modifier', DataTableType::MODIFIER->label());
        $this->assertSame('Lookup Table', DataTableType::LOOKUP->label());
        $this->assertSame('Progression', DataTableType::PROGRESSION->label());
    }

    #[Test]
    public function it_has_correct_descriptions(): void
    {
        $this->assertSame(
            'Rollable tables with discrete outcomes (e.g., Personality Trait d8)',
            DataTableType::RANDOM->description()
        );
        $this->assertSame(
            'Damage dice for features/spells (e.g., Necrotic Damage d12)',
            DataTableType::DAMAGE->description()
        );
        $this->assertSame(
            'Size/weight modifiers (e.g., Size Modifier 2d4)',
            DataTableType::MODIFIER->description()
        );
        $this->assertSame(
            'Reference tables without dice (e.g., Musical Instrument)',
            DataTableType::LOOKUP->description()
        );
        $this->assertSame(
            'Level-based progressions (e.g., Bard Spells Known)',
            DataTableType::PROGRESSION->description()
        );
    }

    #[Test]
    public function it_correctly_identifies_types_with_dice(): void
    {
        $this->assertTrue(DataTableType::RANDOM->hasDice());
        $this->assertTrue(DataTableType::DAMAGE->hasDice());
        $this->assertTrue(DataTableType::MODIFIER->hasDice());
    }

    #[Test]
    public function it_correctly_identifies_types_without_dice(): void
    {
        $this->assertFalse(DataTableType::LOOKUP->hasDice());
        $this->assertFalse(DataTableType::PROGRESSION->hasDice());
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(DataTableType::RANDOM, DataTableType::from('random'));
        $this->assertSame(DataTableType::DAMAGE, DataTableType::from('damage'));
        $this->assertSame(DataTableType::MODIFIER, DataTableType::from('modifier'));
        $this->assertSame(DataTableType::LOOKUP, DataTableType::from('lookup'));
        $this->assertSame(DataTableType::PROGRESSION, DataTableType::from('progression'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(DataTableType::tryFrom('invalid'));
        $this->assertNull(DataTableType::tryFrom('unknown'));
    }
}

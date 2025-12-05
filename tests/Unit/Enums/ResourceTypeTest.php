<?php

namespace Tests\Unit\Enums;

use App\Enums\ResourceType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceTypeTest extends TestCase
{
    #[Test]
    public function it_has_correct_values(): void
    {
        $this->assertSame('ki_points', ResourceType::KI_POINTS->value);
        $this->assertSame('sorcery_points', ResourceType::SORCERY_POINTS->value);
        $this->assertSame('superiority_die', ResourceType::SUPERIORITY_DIE->value);
        $this->assertSame('charges', ResourceType::CHARGES->value);
        $this->assertSame('spell_slot', ResourceType::SPELL_SLOT->value);
    }

    #[Test]
    public function it_has_correct_labels(): void
    {
        $this->assertSame('Ki Points', ResourceType::KI_POINTS->label());
        $this->assertSame('Sorcery Points', ResourceType::SORCERY_POINTS->label());
        $this->assertSame('Superiority Die', ResourceType::SUPERIORITY_DIE->label());
        $this->assertSame('Charges', ResourceType::CHARGES->label());
        $this->assertSame('Spell Slot', ResourceType::SPELL_SLOT->label());
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(ResourceType::KI_POINTS, ResourceType::from('ki_points'));
        $this->assertSame(ResourceType::SORCERY_POINTS, ResourceType::from('sorcery_points'));
        $this->assertSame(ResourceType::SUPERIORITY_DIE, ResourceType::from('superiority_die'));
        $this->assertSame(ResourceType::CHARGES, ResourceType::from('charges'));
        $this->assertSame(ResourceType::SPELL_SLOT, ResourceType::from('spell_slot'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(ResourceType::tryFrom('invalid'));
        $this->assertNull(ResourceType::tryFrom('mana_points'));
    }
}

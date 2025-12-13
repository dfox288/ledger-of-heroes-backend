<?php

namespace Tests\Unit\Enums;

use App\Enums\ToolProficiencyCategory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ToolProficiencyCategoryTest extends TestCase
{
    #[Test]
    public function it_has_correct_database_values(): void
    {
        // These values must match the proficiency_types.subcategory column
        $this->assertSame('artisan', ToolProficiencyCategory::ARTISAN->value);
        $this->assertSame('musical_instrument', ToolProficiencyCategory::MUSICAL_INSTRUMENT->value);
        $this->assertSame('gaming', ToolProficiencyCategory::GAMING->value);
    }

    #[Test]
    public function it_returns_human_readable_labels(): void
    {
        $this->assertSame("Artisan's Tools", ToolProficiencyCategory::ARTISAN->label());
        $this->assertSame('Musical Instrument', ToolProficiencyCategory::MUSICAL_INSTRUMENT->label());
        $this->assertSame('Gaming Set', ToolProficiencyCategory::GAMING->label());
    }

    #[Test]
    public function it_can_be_created_from_database_value(): void
    {
        $this->assertSame(ToolProficiencyCategory::ARTISAN, ToolProficiencyCategory::from('artisan'));
        $this->assertSame(ToolProficiencyCategory::MUSICAL_INSTRUMENT, ToolProficiencyCategory::from('musical_instrument'));
        $this->assertSame(ToolProficiencyCategory::GAMING, ToolProficiencyCategory::from('gaming'));
    }
}

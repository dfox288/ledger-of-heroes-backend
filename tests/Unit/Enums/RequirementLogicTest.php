<?php

namespace Tests\Unit\Enums;

use App\Enums\RequirementLogic;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class RequirementLogicTest extends TestCase
{
    #[Test]
    public function it_has_correct_database_values(): void
    {
        // These values are stored in entity_proficiencies.proficiency_subcategory
        // for multiclass requirements
        $this->assertSame('OR', RequirementLogic::OR->value);
        $this->assertSame('AND', RequirementLogic::AND->value);
    }

    #[Test]
    public function it_returns_human_readable_labels(): void
    {
        $this->assertSame('Any One', RequirementLogic::OR->label());
        $this->assertSame('All Required', RequirementLogic::AND->label());
    }

    #[Test]
    public function it_can_be_created_from_database_value(): void
    {
        $this->assertSame(RequirementLogic::OR, RequirementLogic::from('OR'));
        $this->assertSame(RequirementLogic::AND, RequirementLogic::from('AND'));
    }
}

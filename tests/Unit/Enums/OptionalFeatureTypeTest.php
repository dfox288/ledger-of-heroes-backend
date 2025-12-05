<?php

namespace Tests\Unit\Enums;

use App\Enums\OptionalFeatureType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OptionalFeatureTypeTest extends TestCase
{
    #[Test]
    public function it_has_correct_values(): void
    {
        $this->assertSame('eldritch_invocation', OptionalFeatureType::ELDRITCH_INVOCATION->value);
        $this->assertSame('elemental_discipline', OptionalFeatureType::ELEMENTAL_DISCIPLINE->value);
        $this->assertSame('maneuver', OptionalFeatureType::MANEUVER->value);
        $this->assertSame('metamagic', OptionalFeatureType::METAMAGIC->value);
        $this->assertSame('fighting_style', OptionalFeatureType::FIGHTING_STYLE->value);
        $this->assertSame('artificer_infusion', OptionalFeatureType::ARTIFICER_INFUSION->value);
        $this->assertSame('rune', OptionalFeatureType::RUNE->value);
        $this->assertSame('arcane_shot', OptionalFeatureType::ARCANE_SHOT->value);
    }

    #[Test]
    public function it_has_correct_labels(): void
    {
        $this->assertSame('Eldritch Invocation', OptionalFeatureType::ELDRITCH_INVOCATION->label());
        $this->assertSame('Elemental Discipline', OptionalFeatureType::ELEMENTAL_DISCIPLINE->label());
        $this->assertSame('Maneuver', OptionalFeatureType::MANEUVER->label());
        $this->assertSame('Metamagic', OptionalFeatureType::METAMAGIC->label());
        $this->assertSame('Fighting Style', OptionalFeatureType::FIGHTING_STYLE->label());
        $this->assertSame('Artificer Infusion', OptionalFeatureType::ARTIFICER_INFUSION->label());
        $this->assertSame('Rune', OptionalFeatureType::RUNE->label());
        $this->assertSame('Arcane Shot', OptionalFeatureType::ARCANE_SHOT->label());
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(OptionalFeatureType::ELDRITCH_INVOCATION, OptionalFeatureType::from('eldritch_invocation'));
        $this->assertSame(OptionalFeatureType::MANEUVER, OptionalFeatureType::from('maneuver'));
        $this->assertSame(OptionalFeatureType::FIGHTING_STYLE, OptionalFeatureType::from('fighting_style'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(OptionalFeatureType::tryFrom('invalid'));
        $this->assertNull(OptionalFeatureType::tryFrom('unknown_feature'));
    }

    #[Test]
    public function default_class_name_returns_correct_values(): void
    {
        $this->assertSame('Warlock', OptionalFeatureType::ELDRITCH_INVOCATION->defaultClassName());
        $this->assertSame('Monk', OptionalFeatureType::ELEMENTAL_DISCIPLINE->defaultClassName());
        $this->assertSame('Fighter', OptionalFeatureType::MANEUVER->defaultClassName());
        $this->assertSame('Sorcerer', OptionalFeatureType::METAMAGIC->defaultClassName());
        $this->assertSame('Artificer', OptionalFeatureType::ARTIFICER_INFUSION->defaultClassName());
        $this->assertSame('Fighter', OptionalFeatureType::RUNE->defaultClassName());
        $this->assertSame('Fighter', OptionalFeatureType::ARCANE_SHOT->defaultClassName());
    }

    #[Test]
    public function default_class_name_returns_null_for_fighting_style(): void
    {
        $this->assertNull(OptionalFeatureType::FIGHTING_STYLE->defaultClassName());
    }

    #[Test]
    public function default_subclass_name_returns_correct_values(): void
    {
        $this->assertSame('Way of the Four Elements', OptionalFeatureType::ELEMENTAL_DISCIPLINE->defaultSubclassName());
        $this->assertSame('Battle Master', OptionalFeatureType::MANEUVER->defaultSubclassName());
        $this->assertSame('Rune Knight', OptionalFeatureType::RUNE->defaultSubclassName());
        $this->assertSame('Arcane Archer', OptionalFeatureType::ARCANE_SHOT->defaultSubclassName());
    }

    #[Test]
    public function default_subclass_name_returns_null_for_types_without_default_subclass(): void
    {
        $this->assertNull(OptionalFeatureType::ELDRITCH_INVOCATION->defaultSubclassName());
        $this->assertNull(OptionalFeatureType::METAMAGIC->defaultSubclassName());
        $this->assertNull(OptionalFeatureType::FIGHTING_STYLE->defaultSubclassName());
        $this->assertNull(OptionalFeatureType::ARTIFICER_INFUSION->defaultSubclassName());
    }

    #[Test]
    public function fighter_subclasses_have_distinct_optional_feature_types(): void
    {
        // Verify that multiple Fighter archetypes are properly distinguished
        $fighterTypes = [
            OptionalFeatureType::MANEUVER,
            OptionalFeatureType::RUNE,
            OptionalFeatureType::ARCANE_SHOT,
        ];

        $subclassNames = array_map(fn ($type) => $type->defaultSubclassName(), $fighterTypes);
        $uniqueSubclasses = array_filter($subclassNames);

        $this->assertCount(3, $uniqueSubclasses);
        $this->assertContains('Battle Master', $uniqueSubclasses);
        $this->assertContains('Rune Knight', $uniqueSubclasses);
        $this->assertContains('Arcane Archer', $uniqueSubclasses);
    }
}

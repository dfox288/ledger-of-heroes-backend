<?php

namespace Tests\Unit\Enums;

use App\Enums\NoteCategory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NoteCategoryTest extends TestCase
{
    #[Test]
    public function it_has_correct_values(): void
    {
        $this->assertSame('personality_trait', NoteCategory::PersonalityTrait->value);
        $this->assertSame('ideal', NoteCategory::Ideal->value);
        $this->assertSame('bond', NoteCategory::Bond->value);
        $this->assertSame('flaw', NoteCategory::Flaw->value);
        $this->assertSame('backstory', NoteCategory::Backstory->value);
        $this->assertSame('custom', NoteCategory::Custom->value);
    }

    #[Test]
    public function it_has_correct_labels(): void
    {
        $this->assertSame('Personality Trait', NoteCategory::PersonalityTrait->label());
        $this->assertSame('Ideal', NoteCategory::Ideal->label());
        $this->assertSame('Bond', NoteCategory::Bond->label());
        $this->assertSame('Flaw', NoteCategory::Flaw->label());
        $this->assertSame('Backstory', NoteCategory::Backstory->label());
        $this->assertSame('Custom Note', NoteCategory::Custom->label());
    }

    #[Test]
    public function it_correctly_identifies_categories_requiring_title(): void
    {
        $this->assertTrue(NoteCategory::Custom->requiresTitle());
        $this->assertTrue(NoteCategory::Backstory->requiresTitle());
    }

    #[Test]
    public function it_correctly_identifies_categories_not_requiring_title(): void
    {
        $this->assertFalse(NoteCategory::PersonalityTrait->requiresTitle());
        $this->assertFalse(NoteCategory::Ideal->requiresTitle());
        $this->assertFalse(NoteCategory::Bond->requiresTitle());
        $this->assertFalse(NoteCategory::Flaw->requiresTitle());
    }

    #[Test]
    public function character_sheet_categories_returns_correct_categories(): void
    {
        $categories = NoteCategory::characterSheetCategories();

        $this->assertCount(5, $categories);
        $this->assertContains(NoteCategory::PersonalityTrait, $categories);
        $this->assertContains(NoteCategory::Ideal, $categories);
        $this->assertContains(NoteCategory::Bond, $categories);
        $this->assertContains(NoteCategory::Flaw, $categories);
        $this->assertContains(NoteCategory::Backstory, $categories);
    }

    #[Test]
    public function character_sheet_categories_does_not_include_custom(): void
    {
        $categories = NoteCategory::characterSheetCategories();

        $this->assertNotContains(NoteCategory::Custom, $categories);
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(NoteCategory::PersonalityTrait, NoteCategory::from('personality_trait'));
        $this->assertSame(NoteCategory::Ideal, NoteCategory::from('ideal'));
        $this->assertSame(NoteCategory::Bond, NoteCategory::from('bond'));
        $this->assertSame(NoteCategory::Flaw, NoteCategory::from('flaw'));
        $this->assertSame(NoteCategory::Backstory, NoteCategory::from('backstory'));
        $this->assertSame(NoteCategory::Custom, NoteCategory::from('custom'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(NoteCategory::tryFrom('invalid'));
        $this->assertNull(NoteCategory::tryFrom('unknown'));
    }
}

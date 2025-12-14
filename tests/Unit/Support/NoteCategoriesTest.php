<?php

namespace Tests\Unit\Support;

use App\Support\NoteCategories;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoteCategoriesTest extends TestCase
{
    // =====================
    // Constants Tests
    // =====================

    #[Test]
    public function it_has_correct_constant_values(): void
    {
        $this->assertSame('personality_trait', NoteCategories::PERSONALITY_TRAIT);
        $this->assertSame('ideal', NoteCategories::IDEAL);
        $this->assertSame('bond', NoteCategories::BOND);
        $this->assertSame('flaw', NoteCategories::FLAW);
        $this->assertSame('backstory', NoteCategories::BACKSTORY);
        $this->assertSame('custom', NoteCategories::CUSTOM);
    }

    #[Test]
    public function defaults_contains_all_six_categories(): void
    {
        $this->assertCount(6, NoteCategories::DEFAULTS);
        $this->assertContains(NoteCategories::PERSONALITY_TRAIT, NoteCategories::DEFAULTS);
        $this->assertContains(NoteCategories::IDEAL, NoteCategories::DEFAULTS);
        $this->assertContains(NoteCategories::BOND, NoteCategories::DEFAULTS);
        $this->assertContains(NoteCategories::FLAW, NoteCategories::DEFAULTS);
        $this->assertContains(NoteCategories::BACKSTORY, NoteCategories::DEFAULTS);
        $this->assertContains(NoteCategories::CUSTOM, NoteCategories::DEFAULTS);
    }

    // =====================
    // requiresTitle Tests
    // =====================

    #[Test]
    public function backstory_requires_title(): void
    {
        $this->assertTrue(NoteCategories::requiresTitle(NoteCategories::BACKSTORY));
    }

    #[Test]
    public function standard_categories_do_not_require_title(): void
    {
        $this->assertFalse(NoteCategories::requiresTitle(NoteCategories::PERSONALITY_TRAIT));
        $this->assertFalse(NoteCategories::requiresTitle(NoteCategories::IDEAL));
        $this->assertFalse(NoteCategories::requiresTitle(NoteCategories::BOND));
        $this->assertFalse(NoteCategories::requiresTitle(NoteCategories::FLAW));
    }

    #[Test]
    public function custom_category_does_not_require_title(): void
    {
        $this->assertFalse(NoteCategories::requiresTitle(NoteCategories::CUSTOM));
    }

    #[Test]
    public function user_created_categories_do_not_require_title(): void
    {
        $this->assertFalse(NoteCategories::requiresTitle('session_notes'));
        $this->assertFalse(NoteCategories::requiresTitle('npcs'));
        $this->assertFalse(NoteCategories::requiresTitle('quests'));
        $this->assertFalse(NoteCategories::requiresTitle('My Custom Category'));
    }

    // =====================
    // label Tests
    // =====================

    #[Test]
    public function label_returns_correct_labels_for_default_categories(): void
    {
        $this->assertSame('Personality Trait', NoteCategories::label(NoteCategories::PERSONALITY_TRAIT));
        $this->assertSame('Ideal', NoteCategories::label(NoteCategories::IDEAL));
        $this->assertSame('Bond', NoteCategories::label(NoteCategories::BOND));
        $this->assertSame('Flaw', NoteCategories::label(NoteCategories::FLAW));
        $this->assertSame('Backstory', NoteCategories::label(NoteCategories::BACKSTORY));
        $this->assertSame('Custom Note', NoteCategories::label(NoteCategories::CUSTOM));
    }

    #[Test]
    public function label_converts_snake_case_to_title_case_for_unknown_categories(): void
    {
        $this->assertSame('Session Notes', NoteCategories::label('session_notes'));
        $this->assertSame('Important Npcs', NoteCategories::label('important_npcs'));
        $this->assertSame('Quest Log', NoteCategories::label('quest_log'));
    }

    #[Test]
    public function label_title_cases_plain_strings(): void
    {
        $this->assertSame('Quests', NoteCategories::label('quests'));
        $this->assertSame('Npcs', NoteCategories::label('npcs'));
    }

    // =====================
    // isDefault Tests
    // =====================

    #[Test]
    public function is_default_returns_true_for_default_categories(): void
    {
        $this->assertTrue(NoteCategories::isDefault(NoteCategories::PERSONALITY_TRAIT));
        $this->assertTrue(NoteCategories::isDefault(NoteCategories::IDEAL));
        $this->assertTrue(NoteCategories::isDefault(NoteCategories::BOND));
        $this->assertTrue(NoteCategories::isDefault(NoteCategories::FLAW));
        $this->assertTrue(NoteCategories::isDefault(NoteCategories::BACKSTORY));
        $this->assertTrue(NoteCategories::isDefault(NoteCategories::CUSTOM));
    }

    #[Test]
    public function is_default_returns_false_for_user_created_categories(): void
    {
        $this->assertFalse(NoteCategories::isDefault('session_notes'));
        $this->assertFalse(NoteCategories::isDefault('npcs'));
        $this->assertFalse(NoteCategories::isDefault('My Custom Category'));
    }
}

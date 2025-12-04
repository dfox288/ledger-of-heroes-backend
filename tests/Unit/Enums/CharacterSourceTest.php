<?php

namespace Tests\Unit\Enums;

use App\Enums\CharacterSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CharacterSourceTest extends TestCase
{
    #[Test]
    public function it_has_correct_values(): void
    {
        $this->assertSame('race', CharacterSource::RACE->value);
        $this->assertSame('background', CharacterSource::BACKGROUND->value);
        $this->assertSame('class', CharacterSource::CHARACTER_CLASS->value);
        $this->assertSame('feat', CharacterSource::FEAT->value);
        $this->assertSame('item', CharacterSource::ITEM->value);
        $this->assertSame('other', CharacterSource::OTHER->value);
    }

    #[Test]
    public function it_has_correct_labels(): void
    {
        $this->assertSame('Race', CharacterSource::RACE->label());
        $this->assertSame('Background', CharacterSource::BACKGROUND->label());
        $this->assertSame('Class', CharacterSource::CHARACTER_CLASS->label());
        $this->assertSame('Feat', CharacterSource::FEAT->label());
        $this->assertSame('Item', CharacterSource::ITEM->label());
        $this->assertSame('Other', CharacterSource::OTHER->label());
    }

    #[Test]
    public function it_can_be_created_from_string(): void
    {
        $this->assertSame(CharacterSource::RACE, CharacterSource::from('race'));
        $this->assertSame(CharacterSource::CHARACTER_CLASS, CharacterSource::from('class'));
    }

    #[Test]
    public function it_returns_null_for_invalid_string_with_try_from(): void
    {
        $this->assertNull(CharacterSource::tryFrom('invalid'));
    }

    #[Test]
    public function for_languages_returns_correct_sources(): void
    {
        $sources = CharacterSource::forLanguages();

        $this->assertCount(3, $sources);
        $this->assertContains(CharacterSource::RACE, $sources);
        $this->assertContains(CharacterSource::BACKGROUND, $sources);
        $this->assertContains(CharacterSource::FEAT, $sources);
    }

    #[Test]
    public function for_proficiencies_returns_correct_sources(): void
    {
        $sources = CharacterSource::forProficiencies();

        $this->assertCount(3, $sources);
        $this->assertContains(CharacterSource::CHARACTER_CLASS, $sources);
        $this->assertContains(CharacterSource::RACE, $sources);
        $this->assertContains(CharacterSource::BACKGROUND, $sources);
    }

    #[Test]
    public function for_spells_returns_correct_sources(): void
    {
        $sources = CharacterSource::forSpells();

        $this->assertCount(5, $sources);
        $this->assertContains(CharacterSource::CHARACTER_CLASS, $sources);
        $this->assertContains(CharacterSource::RACE, $sources);
        $this->assertContains(CharacterSource::FEAT, $sources);
        $this->assertContains(CharacterSource::ITEM, $sources);
        $this->assertContains(CharacterSource::OTHER, $sources);
    }

    #[Test]
    public function for_features_returns_correct_sources(): void
    {
        $sources = CharacterSource::forFeatures();

        $this->assertCount(3, $sources);
        $this->assertContains(CharacterSource::CHARACTER_CLASS, $sources);
        $this->assertContains(CharacterSource::RACE, $sources);
        $this->assertContains(CharacterSource::BACKGROUND, $sources);
    }

    #[Test]
    public function validation_rule_generates_correct_string(): void
    {
        $rule = CharacterSource::validationRule(CharacterSource::forLanguages());

        $this->assertSame('in:race,background,feat', $rule);
    }

    #[Test]
    public function validation_rule_works_with_custom_array(): void
    {
        $rule = CharacterSource::validationRule([CharacterSource::CHARACTER_CLASS, CharacterSource::FEAT]);

        $this->assertSame('in:class,feat', $rule);
    }
}

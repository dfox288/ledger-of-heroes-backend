<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Services\Parsers\Concerns\ParsesSkillAdvantages;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ParsesSkillAdvantagesTest extends TestCase
{
    use ParsesSkillAdvantages;

    #[Test]
    public function it_parses_single_skill_advantage()
    {
        $text = 'You have advantage on Intelligence (History) checks.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(1, $result);
        $this->assertEquals('skill_advantage', $result[0]['modifier_category']);
        $this->assertEquals('History', $result[0]['skill_name']);
        $this->assertEquals('advantage', $result[0]['value']);
        $this->assertNull($result[0]['condition']);
    }

    #[Test]
    public function it_parses_dual_skill_advantages()
    {
        $text = 'You have advantage on Charisma (Deception) and Charisma (Performance) checks when trying to pass yourself off as a different person.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(2, $result);

        $this->assertEquals('Deception', $result[0]['skill_name']);
        $this->assertEquals('skill_advantage', $result[0]['modifier_category']);

        $this->assertEquals('Performance', $result[1]['skill_name']);
        $this->assertEquals('skill_advantage', $result[1]['modifier_category']);
    }

    #[Test]
    public function it_captures_conditional_text_after_when()
    {
        $text = 'You have advantage on Wisdom (Perception) checks when you are underground.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Perception', $result[0]['skill_name']);
        $this->assertEquals('you are underground', $result[0]['condition']);
    }

    #[Test]
    public function it_captures_conditional_text_after_while()
    {
        $text = 'You have advantage on Dexterity (Stealth) checks while in dim light or darkness.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Stealth', $result[0]['skill_name']);
        $this->assertEquals('in dim light or darkness', $result[0]['condition']);
    }

    #[Test]
    public function it_captures_conditional_text_after_related_to()
    {
        // Stonecunning pattern from Dwarf racial trait
        $text = 'You have advantage on Intelligence (History) checks related to the origin of stonework.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(1, $result);
        $this->assertEquals('History', $result[0]['skill_name']);
        $this->assertEquals('the origin of stonework', $result[0]['condition']);
    }

    #[Test]
    public function it_captures_conditional_text_after_made_to()
    {
        // Dungeon Delver pattern
        $text = 'You have advantage on Wisdom (Perception) and Intelligence (Investigation) checks made to detect secret doors.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Perception', $result[0]['skill_name']);
        $this->assertEquals('detect secret doors', $result[0]['condition']);
        $this->assertEquals('Investigation', $result[1]['skill_name']);
        $this->assertEquals('detect secret doors', $result[1]['condition']);
    }

    #[Test]
    public function it_returns_empty_array_for_non_matching_text()
    {
        $text = 'You have proficiency in the Perception skill.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_ignores_proficiency_grants()
    {
        $text = 'You gain proficiency in the Athletics skill.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_ignores_saving_throw_advantages()
    {
        // These should be handled by condition parsing, not skill advantage parsing
        $text = 'You have advantage on saving throws against being frightened.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_handles_singular_check_at_end_of_sentence()
    {
        // Singular "check" without trailing text
        $text = 'You have advantage on Intelligence (Investigation) check.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Investigation', $result[0]['skill_name']);
        $this->assertNull($result[0]['condition']);
    }

    #[Test]
    public function it_is_case_insensitive_for_advantage_keyword()
    {
        $text = 'You have ADVANTAGE on Wisdom (Insight) checks.';

        $result = $this->parseSkillAdvantages($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Insight', $result[0]['skill_name']);
    }
}

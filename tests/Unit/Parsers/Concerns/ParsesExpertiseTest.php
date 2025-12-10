<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Services\Parsers\Concerns\ParsesExpertise;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ParsesExpertiseTest extends TestCase
{
    use ParsesExpertise;

    #[Test]
    public function it_parses_double_proficiency_bonus_pattern()
    {
        // Dwarf - Stonecunning
        $text = 'Whenever you make an Intelligence (History) check related to the origin of stonework, you are considered proficient in the History skill and add double your proficiency bonus to the check, instead of your normal proficiency bonus.';

        $result = $this->parseExpertise($text);

        $this->assertCount(1, $result);
        $this->assertEquals('expertise', $result[0]['modifier_category']);
        $this->assertEquals('History', $result[0]['skill_name']);
        $this->assertTrue($result[0]['grants_proficiency']);
        $this->assertEquals('related to the origin of stonework', $result[0]['condition']);
    }

    #[Test]
    public function it_parses_twice_proficiency_bonus_pattern()
    {
        // Rock Gnome - Artificer's Lore
        $text = 'Whenever you make an Intelligence (History) check related to magic items, alchemical objects, or technological devices, you can add twice your proficiency bonus, instead of any proficiency bonus you normally apply.';

        $result = $this->parseExpertise($text);

        $this->assertCount(1, $result);
        $this->assertEquals('expertise', $result[0]['modifier_category']);
        $this->assertEquals('History', $result[0]['skill_name']);
        $this->assertFalse($result[0]['grants_proficiency']);
        $this->assertEquals('related to magic items, alchemical objects, or technological devices', $result[0]['condition']);
    }

    #[Test]
    public function it_parses_expertise_without_condition()
    {
        // Generic expertise pattern (like from a feat)
        $text = 'You have expertise in the Stealth skill. Your proficiency bonus is doubled for any ability check you make that uses Stealth.';

        $result = $this->parseExpertise($text);

        $this->assertCount(1, $result);
        $this->assertEquals('expertise', $result[0]['modifier_category']);
        $this->assertEquals('Stealth', $result[0]['skill_name']);
        $this->assertNull($result[0]['condition']);
    }

    #[Test]
    public function it_parses_proficiency_is_doubled_pattern()
    {
        $text = 'Your proficiency bonus is doubled for any ability check you make that uses the Perception skill.';

        $result = $this->parseExpertise($text);

        $this->assertCount(1, $result);
        $this->assertEquals('expertise', $result[0]['modifier_category']);
        $this->assertEquals('Perception', $result[0]['skill_name']);
        $this->assertFalse($result[0]['grants_proficiency']);
        $this->assertNull($result[0]['condition']);
    }

    #[Test]
    public function it_handles_multiple_expertise_mentions()
    {
        // Unlikely but possible: text mentioning multiple skills with double proficiency
        $text = 'You add double your proficiency bonus to Intelligence (Investigation) checks. Additionally, you add double your proficiency bonus to Wisdom (Perception) checks.';

        $result = $this->parseExpertise($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Investigation', $result[0]['skill_name']);
        $this->assertEquals('Perception', $result[1]['skill_name']);
    }

    #[Test]
    public function it_returns_empty_array_for_non_matching_text()
    {
        $text = 'You have proficiency in the Stealth skill.';

        $result = $this->parseExpertise($text);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_ignores_advantage_patterns()
    {
        // Should not match advantage patterns (handled by ParsesSkillAdvantages)
        $text = 'You have advantage on Wisdom (Perception) checks.';

        $result = $this->parseExpertise($text);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_ignores_saving_throw_patterns()
    {
        // Should not match saving throw proficiency doubling
        $text = 'Your proficiency bonus is doubled for saving throws against poison.';

        $result = $this->parseExpertise($text);

        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_detects_grants_proficiency_from_considered_proficient()
    {
        $text = 'When making such a check, you are considered proficient in the Arcana skill and add double your proficiency bonus.';

        $result = $this->parseExpertise($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Arcana', $result[0]['skill_name']);
        $this->assertTrue($result[0]['grants_proficiency']);
    }

    #[Test]
    public function it_parses_tool_expertise_pattern()
    {
        // Artificer's Lore style for tools
        $text = 'Whenever you make an ability check with thieves\' tools, you can add double your proficiency bonus to the check.';

        $result = $this->parseExpertise($text);

        $this->assertCount(1, $result);
        $this->assertEquals('expertise', $result[0]['modifier_category']);
        $this->assertEquals("thieves' tools", $result[0]['tool_name']);
        $this->assertNull($result[0]['skill_name']);
    }

    #[Test]
    public function it_is_case_insensitive()
    {
        $text = 'You add DOUBLE YOUR PROFICIENCY BONUS to Intelligence (Arcana) checks.';

        $result = $this->parseExpertise($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Arcana', $result[0]['skill_name']);
    }

    #[Test]
    public function it_extracts_ability_score_from_check_pattern()
    {
        $text = 'Whenever you make an Intelligence (History) check related to stonework, you add double your proficiency bonus.';

        $result = $this->parseExpertise($text);

        $this->assertCount(1, $result);
        $this->assertEquals('History', $result[0]['skill_name']);
        $this->assertEquals('Intelligence', $result[0]['ability_score_name']);
    }
}

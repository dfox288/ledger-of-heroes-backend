<?php

namespace Tests\Unit\Parsers;

use App\Enums\ResetTiming;
use App\Services\Parsers\Concerns\ParsesRestTiming;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ParsesRestTimingTest extends TestCase
{
    use ParsesRestTiming;

    // =========================================================================
    // Short Rest Detection
    // =========================================================================

    #[Test]
    public function it_detects_short_or_long_rest_as_short_rest(): void
    {
        // Per D&D rules: if something resets on "short or long rest", it resets on BOTH
        // We track as short_rest since it's the more frequent reset
        $texts = [
            'You regain the use of this feature when you finish a short or long rest.',
            'Once you use this feature, you can\'t use it again until you finish a short or long rest.',
            'You must finish a short or long rest before you can use this ability again.',
            'short or long rest',
        ];

        foreach ($texts as $text) {
            $result = $this->parseResetTiming($text);
            $this->assertEquals(
                ResetTiming::SHORT_REST,
                $result,
                "Failed for: $text"
            );
        }
    }

    #[Test]
    public function it_detects_short_rest_without_long(): void
    {
        $texts = [
            'You regain the use of this ability when you finish a short rest.',
            'Once you use this feature, you must finish a short rest before using it again.',
            'when you complete a short rest',
        ];

        foreach ($texts as $text) {
            $result = $this->parseResetTiming($text);
            $this->assertEquals(
                ResetTiming::SHORT_REST,
                $result,
                "Failed for: $text"
            );
        }
    }

    // =========================================================================
    // Long Rest Detection
    // =========================================================================

    #[Test]
    public function it_detects_finish_a_long_rest(): void
    {
        $texts = [
            'You regain all uses when you finish a long rest.',
            'Once you use this trait, you can\'t do so again until you finish a long rest.',
            'you must finish a long rest to use it again',
        ];

        foreach ($texts as $text) {
            $result = $this->parseResetTiming($text);
            $this->assertEquals(
                ResetTiming::LONG_REST,
                $result,
                "Failed for: $text"
            );
        }
    }

    #[Test]
    public function it_detects_between_long_rests(): void
    {
        $texts = [
            'You can use this feature twice between long rests.',
            'This ability can be used once between long rests.',
        ];

        foreach ($texts as $text) {
            $result = $this->parseResetTiming($text);
            $this->assertEquals(
                ResetTiming::LONG_REST,
                $result,
                "Failed for: $text"
            );
        }
    }

    #[Test]
    public function it_detects_once_per_long_rest(): void
    {
        $texts = [
            'You can use this ability once per long rest.',
            'This feature can be activated once per long rest.',
        ];

        foreach ($texts as $text) {
            $result = $this->parseResetTiming($text);
            $this->assertEquals(
                ResetTiming::LONG_REST,
                $result,
                "Failed for: $text"
            );
        }
    }

    // =========================================================================
    // Dawn Detection
    // =========================================================================

    #[Test]
    public function it_detects_at_dawn(): void
    {
        $texts = [
            'This property regains its use at dawn.',
            'You regain all expended uses at dawn.',
            'daily at dawn',
        ];

        foreach ($texts as $text) {
            $result = $this->parseResetTiming($text);
            $this->assertEquals(
                ResetTiming::DAWN,
                $result,
                "Failed for: $text"
            );
        }
    }

    #[Test]
    public function it_detects_next_dawn(): void
    {
        $texts = [
            'You can\'t use this feature again until the next dawn.',
            'Once used, this ability can\'t be used again until next dawn.',
        ];

        foreach ($texts as $text) {
            $result = $this->parseResetTiming($text);
            $this->assertEquals(
                ResetTiming::DAWN,
                $result,
                "Failed for: $text"
            );
        }
    }

    // =========================================================================
    // No Match
    // =========================================================================

    #[Test]
    public function it_returns_null_when_no_reset_timing_found(): void
    {
        $texts = [
            'You gain proficiency with light armor.',
            'This feature grants you darkvision.',
            'Your speed increases by 10 feet.',
            '',
        ];

        foreach ($texts as $text) {
            $result = $this->parseResetTiming($text);
            $this->assertNull(
                $result,
                "Should be null for: $text"
            );
        }
    }

    // =========================================================================
    // Real D&D Feature Examples
    // =========================================================================

    #[Test]
    public function it_parses_second_wind_description(): void
    {
        // Fighter feature - resets on short or long rest
        $text = 'You have a limited well of stamina that you can draw on to protect yourself from harm. On your turn, you can use a bonus action to regain hit points equal to 1d10 + your fighter level. Once you use this feature, you must finish a short or long rest before you can use it again.';

        $result = $this->parseResetTiming($text);

        $this->assertEquals(ResetTiming::SHORT_REST, $result);
    }

    #[Test]
    public function it_parses_action_surge_description(): void
    {
        // Fighter feature - resets on short or long rest
        $text = 'Starting at 2nd level, you can push yourself beyond your normal limits for a moment. On your turn, you can take one additional action. Once you use this feature, you must finish a short or long rest before you can use it again.';

        $result = $this->parseResetTiming($text);

        $this->assertEquals(ResetTiming::SHORT_REST, $result);
    }

    #[Test]
    public function it_parses_indomitable_description(): void
    {
        // Fighter feature - resets on long rest only
        $text = 'Beginning at 9th level, you can reroll a saving throw that you fail. If you do so, you must use the new roll, and you can\'t use this feature again until you finish a long rest.';

        $result = $this->parseResetTiming($text);

        $this->assertEquals(ResetTiming::LONG_REST, $result);
    }

    #[Test]
    public function it_parses_lucky_feat_description(): void
    {
        // Feat - resets on long rest
        $text = 'You have 3 luck points. Whenever you make an attack roll, an ability check, or a saving throw, you can spend one luck point to roll an additional d20. You regain your expended luck points when you finish a long rest.';

        $result = $this->parseResetTiming($text);

        $this->assertEquals(ResetTiming::LONG_REST, $result);
    }

    #[Test]
    public function it_parses_relentless_endurance_description(): void
    {
        // Half-Orc racial trait - resets on long rest
        $text = 'When you are reduced to 0 hit points but not killed outright, you can drop to 1 hit point instead. You can\'t use this feature again until you finish a long rest.';

        $result = $this->parseResetTiming($text);

        $this->assertEquals(ResetTiming::LONG_REST, $result);
    }

    #[Test]
    public function it_parses_breath_weapon_description(): void
    {
        // Dragonborn racial trait - may vary
        $text = 'You can use your action to exhale destructive energy. Your draconic ancestry determines the size, shape, and damage type of the exhalation. After you use your breath weapon, you can\'t use it again until you complete a short or long rest.';

        $result = $this->parseResetTiming($text);

        $this->assertEquals(ResetTiming::SHORT_REST, $result);
    }

    // =========================================================================
    // Priority Tests (short or long > long only)
    // =========================================================================

    #[Test]
    public function short_or_long_rest_takes_priority_over_long_rest_alone(): void
    {
        // Edge case: text mentions both patterns
        $text = 'You can use this feature. You finish a short or long rest to regain it. Alternatively, it resets when you finish a long rest.';

        $result = $this->parseResetTiming($text);

        // "short or long rest" should win because it appears first and is more permissive
        $this->assertEquals(ResetTiming::SHORT_REST, $result);
    }
}

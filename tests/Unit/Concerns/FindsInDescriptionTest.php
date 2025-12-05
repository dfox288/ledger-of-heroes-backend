<?php

namespace Tests\Unit\Concerns;

use App\Services\Parsers\Concerns\FindsInDescription;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class FindsInDescriptionTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // Create anonymous class using the trait
        $this->subject = new class
        {
            use FindsInDescription;

            // Expose protected methods for testing
            public function test_find_first_keyword(string $text, array $keywords): ?string
            {
                return $this->findFirstKeyword($text, $keywords);
            }

            public function test_find_all_keywords(string $text, array $keywords): array
            {
                return $this->findAllKeywords($text, $keywords);
            }

            public function test_has_any_keyword(string $text, array $keywords): bool
            {
                return $this->hasAnyKeyword($text, $keywords);
            }
        };
    }

    #[Test]
    public function it_finds_first_matching_keyword(): void
    {
        $text = 'The sword is lawful good and very powerful.';
        $keywords = ['chaotic evil', 'lawful good', 'neutral'];

        $result = $this->subject->test_find_first_keyword($text, $keywords);

        $this->assertEquals('lawful good', $result);
    }

    #[Test]
    public function it_returns_null_when_no_keyword_matches(): void
    {
        $text = 'A simple mundane item.';
        $keywords = ['magical', 'sentient', 'legendary'];

        $result = $this->subject->test_find_first_keyword($text, $keywords);

        $this->assertNull($result);
    }

    #[Test]
    public function it_is_case_insensitive(): void
    {
        $text = 'The item has TELEPATHY abilities.';
        $keywords = ['telepathy', 'sentient'];

        $result = $this->subject->test_find_first_keyword($text, $keywords);

        $this->assertEquals('telepathy', $result);
    }

    #[Test]
    public function it_finds_all_matching_keywords(): void
    {
        $text = 'The item is arrogant, cruel, and cunning.';
        $keywords = ['arrogant', 'kind', 'cruel', 'cunning', 'humble'];

        $result = $this->subject->test_find_all_keywords($text, $keywords);

        $this->assertCount(3, $result);
        $this->assertContains('arrogant', $result);
        $this->assertContains('cruel', $result);
        $this->assertContains('cunning', $result);
    }

    #[Test]
    public function it_returns_empty_array_when_no_keywords_match(): void
    {
        $text = 'A plain item with no special traits.';
        $keywords = ['magical', 'sentient'];

        $result = $this->subject->test_find_all_keywords($text, $keywords);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_checks_if_any_keyword_exists(): void
    {
        $text = 'This sentient sword speaks to its wielder.';
        $keywords = ['sentient', 'telepathy', 'speaks'];

        $this->assertTrue($this->subject->test_has_any_keyword($text, $keywords));
    }

    #[Test]
    public function it_returns_false_when_no_keyword_exists(): void
    {
        $text = 'A normal longsword.';
        $keywords = ['sentient', 'telepathy', 'magical'];

        $this->assertFalse($this->subject->test_has_any_keyword($text, $keywords));
    }

    #[Test]
    public function it_handles_empty_text(): void
    {
        $keywords = ['magical', 'sentient'];

        $this->assertNull($this->subject->test_find_first_keyword('', $keywords));
        $this->assertEmpty($this->subject->test_find_all_keywords('', $keywords));
        $this->assertFalse($this->subject->test_has_any_keyword('', $keywords));
    }

    #[Test]
    public function it_handles_empty_keywords_array(): void
    {
        $text = 'Some text to search.';

        $this->assertNull($this->subject->test_find_first_keyword($text, []));
        $this->assertEmpty($this->subject->test_find_all_keywords($text, []));
        $this->assertFalse($this->subject->test_has_any_keyword($text, []));
    }
}

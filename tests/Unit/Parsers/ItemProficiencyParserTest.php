<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\Concerns\ParsesItemProficiencies;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemProficiencyParserTest extends TestCase
{
    private object $parser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create anonymous class that uses the trait
        $this->parser = new class
        {
            use ParsesItemProficiencies;

            public function test_parse_proficiencies_from_text(string $text): array
            {
                return $this->parseProficienciesFromText($text);
            }
        };
    }

    #[Test]
    public function it_parses_proficiency_with_two_weapons()
    {
        $text = 'While wearing these bracers, you have proficiency with the longbow and shortbow, and you gain a +2 bonus to damage rolls.';

        $result = $this->parser->test_parse_proficiencies_from_text($text);

        $this->assertCount(2, $result);

        // Check for longbow proficiency
        $longbow = collect($result)->firstWhere('proficiency_name', 'longbow');
        $this->assertNotNull($longbow);
        $this->assertEquals('weapon', $longbow['proficiency_type']);

        // Check for shortbow proficiency
        $shortbow = collect($result)->firstWhere('proficiency_name', 'shortbow');
        $this->assertNotNull($shortbow);
        $this->assertEquals('weapon', $shortbow['proficiency_type']);
    }

    #[Test]
    public function it_parses_proficiency_with_single_weapon()
    {
        $text = 'You have proficiency with the rapier while wearing this item.';

        $result = $this->parser->test_parse_proficiencies_from_text($text);

        $this->assertCount(1, $result);
        $this->assertEquals('rapier', $result[0]['proficiency_name']);
        $this->assertEquals('weapon', $result[0]['proficiency_type']);
    }

    #[Test]
    public function it_parses_proficiency_with_three_weapons()
    {
        $text = 'You have proficiency with the longsword, shortsword, and greatsword.';

        $result = $this->parser->test_parse_proficiencies_from_text($text);

        $this->assertCount(3, $result);

        $names = collect($result)->pluck('proficiency_name')->toArray();
        $this->assertContains('longsword', $names);
        $this->assertContains('shortsword', $names);
        $this->assertContains('greatsword', $names);
    }

    #[Test]
    public function it_does_not_parse_proficiency_when_pattern_not_found()
    {
        $text = 'This is a magic item with no proficiency grants.';

        $result = $this->parser->test_parse_proficiencies_from_text($text);

        $this->assertEmpty($result);
    }
}

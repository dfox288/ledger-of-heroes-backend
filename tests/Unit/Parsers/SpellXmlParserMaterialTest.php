<?php

namespace Tests\Unit\Parsers;

use App\Services\Parsers\SpellXmlParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tests for material component parsing in SpellXmlParser.
 */
#[Group('unit-pure')]
class SpellXmlParserMaterialTest extends TestCase
{
    private SpellXmlParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SpellXmlParser;
    }

    #[Test]
    #[DataProvider('materialCostProvider')]
    public function it_parses_material_cost_gp(?string $materialComponents, ?int $expected): void
    {
        $result = $this->invokeParseMaterialCostGp($materialComponents);

        $this->assertSame($expected, $result);
    }

    public static function materialCostProvider(): array
    {
        return [
            // "worth at least X gp" pattern
            ['gold dust worth at least 25 gp, which the spell consumes', 25],
            ['ruby dust worth 50 gp, which the spell consumes', 50],
            ['an agate worth at least 1,000 gp, which the spell consumes', 1000],
            ['a diamond worth at least 50 gp', 50],

            // "X gp worth of" pattern
            ['10 gp worth of charcoal, incense, and herbs', 10],

            // No cost specified
            ['a tiny ball of bat guano and sulfur', null],
            ['a pinch of soot and salt', null],

            // Edge cases
            [null, null],
            ['', null],
        ];
    }

    #[Test]
    #[DataProvider('materialConsumedProvider')]
    public function it_parses_material_consumed(?string $materialComponents, bool $expected): void
    {
        $result = $this->invokeParseMaterialConsumed($materialComponents);

        $this->assertSame($expected, $result);
    }

    public static function materialConsumedProvider(): array
    {
        return [
            // Consumed patterns
            ['gold dust worth at least 25 gp, which the spell consumes', true],
            ['a gem worth 100 gp that is consumed by the spell', true],
            ['charcoal, incense, and herbs that the spell consumes', true],

            // Not consumed
            ['a diamond worth at least 50 gp', false],
            ['a tiny ball of bat guano and sulfur', false],

            // Edge cases
            [null, false],
            ['', false],
        ];
    }

    /**
     * Invoke the private parseMaterialCostGp method via reflection.
     */
    private function invokeParseMaterialCostGp(?string $materialComponents): ?int
    {
        $method = new ReflectionMethod(SpellXmlParser::class, 'parseMaterialCostGp');
        $method->setAccessible(true);

        return $method->invoke($this->parser, $materialComponents);
    }

    /**
     * Invoke the private parseMaterialConsumed method via reflection.
     */
    private function invokeParseMaterialConsumed(?string $materialComponents): bool
    {
        $method = new ReflectionMethod(SpellXmlParser::class, 'parseMaterialConsumed');
        $method->setAccessible(true);

        return $method->invoke($this->parser, $materialComponents);
    }
}

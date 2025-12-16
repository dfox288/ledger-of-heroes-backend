<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Enums\ResetTiming;
use App\Services\Parsers\Concerns\ParsesRestTiming;
use App\Services\Parsers\Concerns\ParsesTraits;
use App\Services\Parsers\Concerns\ParsesUsageLimits;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use Tests\TestCase;

/**
 * Tests for usage limit parsing in racial traits.
 *
 * These tests verify that ParsesTraits correctly extracts max_uses and resets_on
 * from trait descriptions for features like Breath Weapon, Relentless Endurance, etc.
 */
#[\PHPUnit\Framework\Attributes\Group('unit-pure')]
class ParsesTraitsUsageLimitsTest extends TestCase
{
    use ParsesRestTiming;
    use ParsesTraits;
    use ParsesUsageLimits;

    #[Test]
    public function it_parses_breath_weapon_usage_limits(): void
    {
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Breath Weapon</name>
                <text>You can use your action to exhale destructive energy. After you use your breath weapon, you can't use it again until you complete a short or long rest.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertCount(1, $traits);
        $this->assertEquals('Breath Weapon', $traits[0]['name']);
        $this->assertEquals(1, $traits[0]['max_uses']);
        $this->assertEquals(ResetTiming::SHORT_REST, $traits[0]['resets_on']);
    }

    #[Test]
    public function it_parses_relentless_endurance_usage_limits(): void
    {
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Relentless Endurance</name>
                <text>When you are reduced to 0 hit points but not killed outright, you can drop to 1 hit point instead. You can't use this feature again until you finish a long rest.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertEquals(1, $traits[0]['max_uses']);
        $this->assertEquals(ResetTiming::LONG_REST, $traits[0]['resets_on']);
    }

    #[Test]
    public function it_parses_fey_step_usage_limits(): void
    {
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Fey Step</name>
                <text>You can cast the misty step spell once using this trait. You regain the ability to do so when you finish a short or long rest.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertEquals(1, $traits[0]['max_uses']);
        $this->assertEquals(ResetTiming::SHORT_REST, $traits[0]['resets_on']);
    }

    #[Test]
    public function it_parses_hidden_step_usage_limits(): void
    {
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Hidden Step</name>
                <text>As a bonus action, you can magically turn invisible until the start of your next turn. Once you use this trait, you can't use it again until you finish a short or long rest.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertEquals(1, $traits[0]['max_uses']);
        $this->assertEquals(ResetTiming::SHORT_REST, $traits[0]['resets_on']);
    }

    #[Test]
    public function it_parses_firbolg_magic_usage_limits(): void
    {
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Firbolg Magic</name>
                <text>You can cast detect magic and disguise self with this trait. Once you cast either spell, you can't cast it again with this trait until you finish a short or long rest.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertEquals(1, $traits[0]['max_uses']);
        $this->assertEquals(ResetTiming::SHORT_REST, $traits[0]['resets_on']);
    }

    #[Test]
    public function it_returns_null_for_traits_without_usage_limits(): void
    {
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Darkvision</name>
                <text>You can see in dim light within 60 feet of you as if it were bright light.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertNull($traits[0]['max_uses']);
        $this->assertNull($traits[0]['resets_on']);
    }

    #[Test]
    public function it_parses_savage_attacks_without_usage_limits(): void
    {
        // This trait doesn't have limited uses - it's always active
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Savage Attacks</name>
                <text>When you score a critical hit with a melee weapon attack, you can roll one of the weapon's damage dice one additional time and add it to the extra damage of the critical hit.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertNull($traits[0]['max_uses']);
        $this->assertNull($traits[0]['resets_on']);
    }

    #[Test]
    public function it_parses_healing_hands_usage_limits(): void
    {
        // Aasimar trait - uses equal to proficiency bonus, long rest reset
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Healing Hands</name>
                <text>As an action, you can touch a creature and roll a number of d4s equal to your proficiency bonus. The creature regains a number of hit points equal to the total rolled. Once you use this trait, you can't use it again until you finish a long rest.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        // This still has 1 use per rest, not proficiency uses
        $this->assertEquals(1, $traits[0]['max_uses']);
        $this->assertEquals(ResetTiming::LONG_REST, $traits[0]['resets_on']);
    }

    #[Test]
    public function it_parses_cannot_variant_usage_limits(): void
    {
        // Breath Weapon uses "cannot" instead of "can't"
        $xml = <<<'XML'
        <root>
            <trait>
                <name>Breath Weapon</name>
                <text>You can use your action to exhale destructive energy. After you use your breath weapon, you cannot use it again until you complete a short or long rest.</text>
            </trait>
        </root>
        XML;

        $element = new SimpleXMLElement($xml);
        $traits = $this->parseTraitElements($element);

        $this->assertEquals(1, $traits[0]['max_uses']);
        $this->assertEquals(ResetTiming::SHORT_REST, $traits[0]['resets_on']);
    }

    // Mock parseRollElements for test isolation
    protected function parseRollElements(\SimpleXMLElement $element): array
    {
        $rolls = [];
        foreach ($element->roll as $rollElement) {
            $rolls[] = [
                'description' => isset($rollElement['description']) ? (string) $rollElement['description'] : null,
                'formula' => (string) $rollElement,
                'level' => isset($rollElement['level']) ? (int) $rollElement['level'] : null,
            ];
        }

        return $rolls;
    }
}

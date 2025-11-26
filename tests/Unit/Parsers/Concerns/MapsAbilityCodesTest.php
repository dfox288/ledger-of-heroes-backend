<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Services\Parsers\Concerns\MapsAbilityCodes;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class MapsAbilityCodesTest extends TestCase
{
    use MapsAbilityCodes;

    #[Test]
    public function it_maps_full_ability_names_to_codes()
    {
        $this->assertEquals('STR', $this->mapAbilityNameToCode('Strength'));
        $this->assertEquals('DEX', $this->mapAbilityNameToCode('Dexterity'));
        $this->assertEquals('CON', $this->mapAbilityNameToCode('Constitution'));
        $this->assertEquals('INT', $this->mapAbilityNameToCode('Intelligence'));
        $this->assertEquals('WIS', $this->mapAbilityNameToCode('Wisdom'));
        $this->assertEquals('CHA', $this->mapAbilityNameToCode('Charisma'));
    }

    #[Test]
    public function it_is_case_insensitive()
    {
        $this->assertEquals('STR', $this->mapAbilityNameToCode('strength'));
        $this->assertEquals('DEX', $this->mapAbilityNameToCode('DEXTERITY'));
        $this->assertEquals('WIS', $this->mapAbilityNameToCode('WiSdOm'));
    }

    #[Test]
    public function it_handles_abbreviated_input()
    {
        $this->assertEquals('STR', $this->mapAbilityNameToCode('str'));
        $this->assertEquals('DEX', $this->mapAbilityNameToCode('dex'));
    }

    #[Test]
    public function it_falls_back_to_first_three_letters_for_unknown()
    {
        $this->assertEquals('UNK', $this->mapAbilityNameToCode('Unknown'));
        $this->assertEquals('XYZ', $this->mapAbilityNameToCode('xyz'));
    }
}

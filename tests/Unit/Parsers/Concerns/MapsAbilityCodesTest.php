<?php

namespace Tests\Unit\Parsers\Concerns;

use App\Services\Parsers\Concerns\MapsAbilityCodes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class MapsAbilityCodesTest extends TestCase
{
    use MapsAbilityCodes;

    public static function abilityMappingProvider(): array
    {
        return [
            // Full ability names
            'Strength full name' => ['Strength', 'STR'],
            'Dexterity full name' => ['Dexterity', 'DEX'],
            'Constitution full name' => ['Constitution', 'CON'],
            'Intelligence full name' => ['Intelligence', 'INT'],
            'Wisdom full name' => ['Wisdom', 'WIS'],
            'Charisma full name' => ['Charisma', 'CHA'],

            // Case insensitive
            'lowercase strength' => ['strength', 'STR'],
            'uppercase DEXTERITY' => ['DEXTERITY', 'DEX'],
            'mixed case WiSdOm' => ['WiSdOm', 'WIS'],

            // Abbreviated input
            'abbreviated str' => ['str', 'STR'],
            'abbreviated dex' => ['dex', 'DEX'],

            // Fallback for unknown
            'unknown ability' => ['Unknown', 'UNK'],
            'xyz fallback' => ['xyz', 'XYZ'],
        ];
    }

    #[Test]
    #[DataProvider('abilityMappingProvider')]
    public function it_maps_ability_names_to_codes(string $input, string $expected)
    {
        $this->assertEquals($expected, $this->mapAbilityNameToCode($input));
    }
}

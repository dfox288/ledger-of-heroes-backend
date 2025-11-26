<?php

namespace Tests\Unit\Models;

use App\Models\Spell;
use Tests\TestCase;

#[\PHPUnit\Framework\Attributes\Group('unit-db')]
class SpellTest extends TestCase
{
    public function test_spell_belongs_to_spell_school(): void
    {
        $spell = new Spell;
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $spell->spellSchool());
    }

    public function test_spell_has_many_sources(): void
    {
        $spell = new Spell;
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class, $spell->sources());
    }

    public function test_spell_does_not_use_timestamps(): void
    {
        $spell = new Spell;
        $this->assertFalse($spell->timestamps);
    }

    // Computed Accessor Tests

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('castingTimeTypeProvider')]
    public function casting_time_type_is_computed_from_casting_time(string $castingTime, string $expectedType): void
    {
        $spell = new Spell(['casting_time' => $castingTime]);

        $this->assertSame($expectedType, $spell->casting_time_type);
    }

    public static function castingTimeTypeProvider(): array
    {
        return [
            'action' => ['1 action', 'action'],
            'bonus action' => ['1 bonus action', 'bonus_action'],
            'reaction' => ['1 reaction', 'reaction'],
            'reaction with trigger' => ['1 reaction, which you take when you see a creature within 60 feet', 'reaction'],
            '1 minute' => ['1 minute', 'minute'],
            '10 minutes' => ['10 minutes', 'minute'],
            '1 hour' => ['1 hour', 'hour'],
            '8 hours' => ['8 hours', 'hour'],
            '24 hours' => ['24 hours', 'hour'],
            'special' => ['Special', 'special'],
            'null casting time' => ['', 'unknown'],
        ];
    }
}

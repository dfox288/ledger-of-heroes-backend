<?php

namespace App\DTOs;

class SpellSlotResult
{
    public function __construct(
        public readonly ?array $standardSlots,
        public readonly ?PactSlotInfo $pactSlots,
    ) {}

    public static function empty(): self
    {
        return new self(standardSlots: null, pactSlots: null);
    }
}

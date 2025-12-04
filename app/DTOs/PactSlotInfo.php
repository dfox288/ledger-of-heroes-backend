<?php

namespace App\DTOs;

class PactSlotInfo
{
    public function __construct(
        public readonly int $count,
        public readonly int $level,
    ) {}
}

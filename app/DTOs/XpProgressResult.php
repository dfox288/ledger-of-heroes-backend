<?php

namespace App\DTOs;

/**
 * Data Transfer Object for character XP progress.
 */
class XpProgressResult
{
    public function __construct(
        public readonly int $experiencePoints,
        public readonly int $level,
        public readonly ?int $nextLevelXp,
        public readonly int $xpToNextLevel,
        public readonly float $xpProgressPercent,
        public readonly bool $isMaxLevel,
        public readonly ?bool $leveledUp = null,
    ) {}
}

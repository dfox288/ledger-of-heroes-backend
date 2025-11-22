<?php

namespace App\Services\Importers\Strategies\Monster;

class DefaultStrategy extends AbstractMonsterStrategy
{
    public function appliesTo(array $monsterData): bool
    {
        return true; // Always applicable as fallback
    }

    // Uses base implementations (no enhancements)
}

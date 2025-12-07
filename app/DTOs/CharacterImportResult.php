<?php

namespace App\DTOs;

use App\Models\Character;

/**
 * Result of a character import operation.
 */
readonly class CharacterImportResult
{
    /**
     * @param  array<string>  $warnings
     */
    public function __construct(
        public Character $character,
        public array $warnings = [],
    ) {}

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }
}

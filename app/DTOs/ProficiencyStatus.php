<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ProficiencyStatus
{
    /**
     * @param  bool  $hasProficiency  Whether character has proficiency
     * @param  array<string>  $penalties  Active penalties if not proficient
     * @param  string|null  $source  What granted proficiency (class/race/background name)
     */
    public function __construct(
        public bool $hasProficiency,
        public array $penalties = [],
        public ?string $source = null,
    ) {}

    public function toArray(): array
    {
        return [
            'has_proficiency' => $this->hasProficiency,
            'penalties' => $this->penalties,
            'source' => $this->source,
        ];
    }
}

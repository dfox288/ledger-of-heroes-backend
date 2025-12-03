<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class PrerequisiteResult
{
    /**
     * @param  bool  $met  Whether all prerequisites are met
     * @param  array<array{type: string, requirement: string, current: string|int|null}>  $unmet  Unmet prerequisites
     * @param  array<string>  $warnings  Warnings for prerequisites that couldn't be validated
     */
    public function __construct(
        public bool $met,
        public array $unmet = [],
        public array $warnings = [],
    ) {}
}

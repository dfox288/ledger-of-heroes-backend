<?php

namespace App\DTOs;

class RequirementCheck
{
    public function __construct(
        public readonly bool $met,
        public readonly string $className,
        public readonly array $failedRequirements = [],
    ) {}
}

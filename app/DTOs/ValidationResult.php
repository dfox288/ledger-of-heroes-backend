<?php

namespace App\DTOs;

class ValidationResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly array $errors = [],
    ) {}

    public static function success(): self
    {
        return new self(passed: true);
    }

    public static function failure(array $errors): self
    {
        return new self(passed: false, errors: $errors);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

/**
 * Result of validating a switch operation.
 */
class ValidationResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly ?string $pattern = null,
    ) {}

    public static function pass(): self
    {
        return new self(passed: true);
    }

    public static function fail(array $errors, ?string $pattern = null): self
    {
        return new self(
            passed: false,
            errors: $errors,
            pattern: $pattern,
        );
    }

    public static function passWithWarnings(array $warnings): self
    {
        return new self(
            passed: true,
            warnings: $warnings,
        );
    }

    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'pattern' => $this->pattern,
        ];
    }
}

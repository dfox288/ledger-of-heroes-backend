<?php

declare(strict_types=1);

namespace App\Services\LevelUpFlowTesting;

/**
 * Result of a single level-up step.
 */
class LevelUpStepResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly int $level,
        public readonly string $classSlug,
        public readonly int $hpGained = 0,
        public readonly array $featuresGained = [],
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly ?string $pattern = null,
        public readonly ?array $beforeSnapshot = null,
        public readonly ?array $afterSnapshot = null,
    ) {}

    /**
     * Create a successful step result.
     */
    public static function success(
        int $level,
        string $classSlug,
        int $hpGained = 0,
        array $featuresGained = [],
        array $warnings = [],
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
    ): self {
        return new self(
            passed: true,
            level: $level,
            classSlug: $classSlug,
            hpGained: $hpGained,
            featuresGained: $featuresGained,
            warnings: $warnings,
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
        );
    }

    /**
     * Create a failed step result.
     */
    public static function failure(
        int $level,
        string $classSlug,
        array $errors,
        ?string $pattern = null,
        array $warnings = [],
        ?array $beforeSnapshot = null,
        ?array $afterSnapshot = null,
    ): self {
        return new self(
            passed: false,
            level: $level,
            classSlug: $classSlug,
            errors: $errors,
            warnings: $warnings,
            pattern: $pattern,
            beforeSnapshot: $beforeSnapshot,
            afterSnapshot: $afterSnapshot,
        );
    }

    /**
     * Convert to array for reporting.
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'class_slug' => $this->classSlug,
            'passed' => $this->passed,
            'hp_gained' => $this->hpGained,
            'features_gained' => $this->featuresGained,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'pattern' => $this->pattern,
        ];
    }
}

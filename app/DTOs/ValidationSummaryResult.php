<?php

namespace App\DTOs;

use Illuminate\Support\Collection;

/**
 * Data Transfer Object for bulk character validation summary.
 */
class ValidationSummaryResult
{
    public function __construct(
        public readonly int $total,
        public readonly int $valid,
        public readonly int $invalid,
        public readonly Collection $characters,
    ) {}

    /**
     * Create from service response array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            total: $data['total'],
            valid: $data['valid'],
            invalid: $data['invalid'],
            characters: $data['characters'] instanceof Collection
                ? $data['characters']
                : collect($data['characters']),
        );
    }
}

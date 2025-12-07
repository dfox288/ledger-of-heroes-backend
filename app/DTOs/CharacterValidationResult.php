<?php

namespace App\DTOs;

/**
 * Data Transfer Object for character reference validation results.
 *
 * Tracks dangling references (slugs that don't resolve to entities)
 * for character data integrity validation.
 */
class CharacterValidationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly array $danglingReferences,
        public readonly int $totalReferences,
        public readonly int $validReferences,
        public readonly int $danglingCount,
    ) {}

    public static function fromDanglingReferences(array $dangling, int $total): self
    {
        $danglingCount = self::countDanglingReferences($dangling);

        return new self(
            valid: empty($dangling),
            danglingReferences: $dangling,
            totalReferences: $total,
            validReferences: $total - $danglingCount,
            danglingCount: $danglingCount,
        );
    }

    private static function countDanglingReferences(array $dangling): int
    {
        $count = 0;
        foreach ($dangling as $value) {
            if (is_array($value)) {
                $count += count($value);
            } else {
                $count++;
            }
        }

        return $count;
    }
}

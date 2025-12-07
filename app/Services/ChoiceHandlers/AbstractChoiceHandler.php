<?php

namespace App\Services\ChoiceHandlers;

use App\Exceptions\InvalidChoiceException;

abstract class AbstractChoiceHandler implements ChoiceTypeHandler
{
    /**
     * Separator used in choice IDs.
     * Using pipe to avoid conflicts with colons in slugs (e.g., 'phb:wizard').
     */
    protected const CHOICE_ID_SEPARATOR = '|';

    /**
     * Number of segments expected in a valid choice ID.
     */
    protected const CHOICE_ID_SEGMENTS = 5;

    /**
     * Generate a deterministic choice ID.
     * Format: {type}|{source}|{sourceSlug}|{level}|{group}
     */
    protected function generateChoiceId(
        string $type,
        string $source,
        string $sourceSlug,
        int $level,
        string $group
    ): string {
        return implode(self::CHOICE_ID_SEPARATOR, [$type, $source, $sourceSlug, $level, $group]);
    }

    /**
     * Parse a choice ID into its components.
     *
     * @return array{type: string, source: string, sourceSlug: string, level: int, group: string}
     *
     * @throws InvalidChoiceException If the choice ID format is invalid
     */
    protected function parseChoiceId(string $choiceId): array
    {
        $parts = explode(self::CHOICE_ID_SEPARATOR, $choiceId);

        if (count($parts) !== self::CHOICE_ID_SEGMENTS) {
            throw new InvalidChoiceException($choiceId, 'Malformed choice ID: expected '.self::CHOICE_ID_SEGMENTS.' segments');
        }

        return [
            'type' => $parts[0],
            'source' => $parts[1],
            'sourceSlug' => $parts[2],
            'level' => (int) $parts[3],
            'group' => $parts[4],
        ];
    }
}

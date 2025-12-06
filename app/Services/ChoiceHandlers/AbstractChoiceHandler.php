<?php

namespace App\Services\ChoiceHandlers;

abstract class AbstractChoiceHandler implements ChoiceTypeHandler
{
    /**
     * Generate a deterministic choice ID.
     * Format: {type}:{source}:{sourceId}:{level}:{group}
     */
    protected function generateChoiceId(
        string $type,
        string $source,
        int $sourceId,
        int $level,
        string $group
    ): string {
        return implode(':', [$type, $source, $sourceId, $level, $group]);
    }

    /**
     * Parse a choice ID into its components.
     *
     * @return array{type: string, source: string, sourceId: int, level: int, group: string}
     */
    protected function parseChoiceId(string $choiceId): array
    {
        $parts = explode(':', $choiceId);

        return [
            'type' => $parts[0] ?? '',
            'source' => $parts[1] ?? '',
            'sourceId' => (int) ($parts[2] ?? 0),
            'level' => (int) ($parts[3] ?? 0),
            'group' => $parts[4] ?? '',
        ];
    }
}

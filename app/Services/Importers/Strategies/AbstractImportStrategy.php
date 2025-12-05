<?php

namespace App\Services\Importers\Strategies;

use App\Services\Concerns\TracksMetricsAndWarnings;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for all import strategies.
 *
 * Provides common functionality for warnings, metrics, and lifecycle hooks.
 * Concrete strategies implement appliesTo() and enhance().
 */
abstract class AbstractImportStrategy
{
    use TracksMetricsAndWarnings;

    /**
     * Determine if this strategy applies to the given data.
     */
    abstract public function appliesTo(array $data): bool;

    /**
     * Enhance entity data with strategy-specific logic.
     */
    abstract public function enhance(array $data): array;

    /**
     * Post-creation hook for additional relationship syncing.
     */
    public function afterCreate(Model $entity, array $data): void
    {
        // Default: no-op
    }

    /**
     * Extract metadata for logging and statistics.
     */
    public function extractMetadata(array $data): array
    {
        return [
            'warnings' => $this->warnings,
            'metrics' => $this->metrics,
        ];
    }
}

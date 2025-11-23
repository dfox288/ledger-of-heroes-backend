<?php

namespace App\Services\Importers;

/**
 * Merge strategy for multi-file imports.
 *
 * Used when importing classes from multiple sources (PHB + XGE + TCE).
 */
enum MergeMode: string
{
    /**
     * Create new entity (fail if exists).
     */
    case CREATE = 'create';

    /**
     * Merge with existing entity (add subclasses, skip duplicates).
     */
    case MERGE = 'merge';

    /**
     * Skip import if entity already exists.
     */
    case SKIP_IF_EXISTS = 'skip';
}

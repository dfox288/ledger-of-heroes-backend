<?php

namespace App\Services\Importers;

use App\Events\ModelImported;
use App\Services\Importers\Concerns\GeneratesSlugs;
use App\Services\Importers\Concerns\ImportsProficiencies;
use App\Services\Importers\Concerns\ImportsRandomTables;
use App\Services\Importers\Concerns\ImportsSources;
use App\Services\Importers\Concerns\ImportsTraits;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Base class for all entity importers.
 *
 * Provides:
 * - Transaction management
 * - Common concerns (sources, traits, proficiencies, etc.)
 * - Template method pattern for import flow
 *
 * Subclasses must implement: importEntity(array $data)
 */
abstract class BaseImporter
{
    use GeneratesSlugs;
    use ImportsProficiencies;
    use ImportsRandomTables;
    use ImportsSources;
    use ImportsTraits;

    /**
     * Import an entity from parsed data.
     *
     * Wraps the import in a database transaction.
     *
     * @param  array  $data  Parsed entity data
     * @return Model The imported entity
     */
    public function import(array $data): Model
    {
        $entity = DB::transaction(function () use ($data) {
            return $this->importEntity($data);
        });

        // Dispatch event to clear validation caches
        event(new ModelImported($entity));

        return $entity;
    }

    /**
     * Import the specific entity type.
     *
     * Must be implemented by each importer.
     *
     * @param  array  $data  Parsed entity data
     * @return Model The imported entity
     */
    abstract protected function importEntity(array $data): Model;
}

<?php

namespace App\Services\Importers;

use App\Events\ModelImported;
use App\Exceptions\Import\FileNotFoundException;
use App\Services\Importers\Concerns\GeneratesSlugs;
use App\Services\Importers\Concerns\ImportsDataTables;
use App\Services\Importers\Concerns\ImportsProficiencies;
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
    use ImportsDataTables;
    use ImportsProficiencies;
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

    /**
     * Get the parser instance for this importer.
     *
     * Must be implemented by each importer to return their specific parser.
     *
     * @return object The XML parser instance
     */
    abstract public function getParser(): object;

    /**
     * Import entities from an XML file.
     *
     * Standard implementation for all importers:
     * 1. Validates file exists
     * 2. Reads XML content
     * 3. Parses with importer-specific parser
     * 4. Imports each entity
     *
     * @param  string  $filePath  Path to XML file
     * @return int Number of entities imported
     *
     * @throws FileNotFoundException If file doesn't exist
     */
    public function importFromFile(string $filePath): int
    {
        if (! file_exists($filePath)) {
            throw new FileNotFoundException($filePath);
        }

        $xmlContent = file_get_contents($filePath);
        $parser = $this->getParser();
        $entities = $parser->parse($xmlContent);

        $count = 0;
        foreach ($entities as $entityData) {
            $this->import($entityData);
            $count++;
        }

        return $count;
    }
}

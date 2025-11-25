<?php

namespace App\Services\Importers;

use App\Models\Source;
use App\Services\Parsers\SourceXmlParser;
use Illuminate\Database\Eloquent\Model;

/**
 * Importer for D&D sourcebook data.
 *
 * Sources are the simplest entity to import:
 * - One source per XML file
 * - No relationships to manage
 * - Uses code as unique key for upsert
 *
 * Must be imported FIRST before any other entities,
 * as all other entities reference sources.
 */
class SourceImporter extends BaseImporter
{
    protected function importEntity(array $data): Model
    {
        // Upsert source using code as unique key
        return Source::updateOrCreate(
            ['code' => $data['code']],
            [
                'name' => $data['name'],
                'publisher' => $data['publisher'],
                'publication_year' => $data['publication_year'],
                'url' => $data['url'],
                'author' => $data['author'],
                'artist' => $data['artist'],
                'website' => $data['website'],
                'category' => $data['category'],
                'description' => $data['description'],
            ]
        );
    }

    protected function getParser(): object
    {
        return new SourceXmlParser;
    }
}

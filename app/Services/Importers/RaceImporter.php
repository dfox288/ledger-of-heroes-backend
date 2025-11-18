<?php

namespace App\Services\Importers;

use App\Models\Race;
use App\Models\Size;
use App\Models\Source;
use App\Services\Parsers\RaceXmlParser;

class RaceImporter
{
    private array $createdBaseRaces = [];

    public function import(array $raceData): Race
    {
        // Lookup size by code
        $size = Size::where('code', $raceData['size_code'])->firstOrFail();

        // Lookup source by code
        $source = Source::where('code', $raceData['source_code'])->firstOrFail();

        // If this is a subrace, ensure base race exists first
        $parentRaceId = null;
        if (!empty($raceData['base_race_name'])) {
            $baseRace = $this->getOrCreateBaseRace(
                $raceData['base_race_name'],
                $raceData['size_code'],
                $raceData['speed'],
                $raceData['source_code'],
                $raceData['source_pages']
            );
            $parentRaceId = $baseRace->id;
        }

        // Create or update race
        $race = Race::updateOrCreate(
            [
                'name' => $raceData['name'],
                'parent_race_id' => $parentRaceId,
            ],
            [
                'size_id' => $size->id,
                'speed' => $raceData['speed'],
                'description' => $raceData['description'],
                'source_id' => $source->id,
                'source_pages' => $raceData['source_pages'],
            ]
        );

        return $race;
    }

    public function importFromFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $xmlContent = file_get_contents($filePath);
        $parser = new RaceXmlParser();
        $races = $parser->parse($xmlContent);

        // Reset tracking for base races
        $this->createdBaseRaces = [];

        $count = 0;
        foreach ($races as $raceData) {
            // If this is a subrace, check if we need to count the base race
            if (!empty($raceData['base_race_name'])) {
                $baseRaceName = $raceData['base_race_name'];

                // Only count the base race once per import
                if (!isset($this->createdBaseRaces[$baseRaceName])) {
                    $this->createdBaseRaces[$baseRaceName] = true;
                    $count++; // Count base race creation
                }
            }

            $this->import($raceData);
            $count++;
        }

        return $count;
    }

    private function getOrCreateBaseRace(
        string $baseRaceName,
        string $sizeCode,
        int $speed,
        string $sourceCode,
        string $sourcePages
    ): Race {
        // Check if base race already exists
        $existing = Race::where('name', $baseRaceName)
                        ->whereNull('parent_race_id')
                        ->first();

        if ($existing) {
            return $existing;
        }

        // Create base race with minimal data
        $size = Size::where('code', $sizeCode)->firstOrFail();
        $source = Source::where('code', $sourceCode)->firstOrFail();

        return Race::create([
            'name' => $baseRaceName,
            'size_id' => $size->id,
            'speed' => $speed,
            'description' => "Base {$baseRaceName} race. See subraces for details.",
            'source_id' => $source->id,
            'source_pages' => $sourcePages,
            'parent_race_id' => null,
        ]);
    }
}

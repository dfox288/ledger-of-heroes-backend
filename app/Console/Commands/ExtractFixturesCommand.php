<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExtractFixturesCommand extends Command
{
    protected $signature = 'fixtures:extract
                            {entity : Entity type to extract (spells, monsters, classes, races, items, feats, backgrounds, optionalfeatures, all)}
                            {--output=tests/fixtures : Output directory}
                            {--analyze-tests : Analyze test files for referenced entities}
                            {--limit=100 : Maximum entities per type}';

    protected $description = 'Extract fixture data from database for test seeding';

    public function handle(): int
    {
        $entity = $this->argument('entity');
        $output = $this->option('output');

        if (! File::isDirectory(base_path($output))) {
            File::makeDirectory(base_path($output), 0755, true);
        }

        $entities = $entity === 'all'
            ? ['spells', 'monsters', 'classes', 'races', 'items', 'feats', 'backgrounds', 'optionalfeatures']
            : [$entity];

        foreach ($entities as $entityType) {
            $this->extractEntity($entityType, $output);
        }

        return self::SUCCESS;
    }

    protected function extractEntity(string $entity, string $output): void
    {
        $this->info("Extracting {$entity}...");

        $extractor = $this->getExtractor($entity);

        if (! $extractor) {
            $this->error("Unknown entity type: {$entity}");

            return;
        }

        $data = $extractor();
        $path = base_path("{$output}/entities/{$entity}.json");

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info('  Extracted '.count($data)." {$entity} to {$path}");
    }

    protected function getExtractor(string $entity): ?\Closure
    {
        return match ($entity) {
            'spells' => fn () => $this->extractSpells(),
            'monsters' => fn () => $this->extractMonsters(),
            'classes' => fn () => $this->extractClasses(),
            'races' => fn () => $this->extractRaces(),
            'items' => fn () => $this->extractItems(),
            'feats' => fn () => $this->extractFeats(),
            'backgrounds' => fn () => $this->extractBackgrounds(),
            'optionalfeatures' => fn () => $this->extractOptionalFeatures(),
            default => null,
        };
    }

    // Placeholder extractors - will be implemented in subsequent tasks
    protected function extractSpells(): array
    {
        return [];
    }

    protected function extractMonsters(): array
    {
        return [];
    }

    protected function extractClasses(): array
    {
        return [];
    }

    protected function extractRaces(): array
    {
        return [];
    }

    protected function extractItems(): array
    {
        return [];
    }

    protected function extractFeats(): array
    {
        return [];
    }

    protected function extractBackgrounds(): array
    {
        return [];
    }

    protected function extractOptionalFeatures(): array
    {
        return [];
    }
}

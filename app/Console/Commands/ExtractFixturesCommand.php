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
        $limit = (int) $this->option('limit');

        // Get coverage-based selection:
        // 1. One spell per level (0-9)
        // 2. One spell per school
        // 3. Concentration and ritual variants
        // 4. Additional random spells up to limit

        $spellIds = collect();

        // One per level
        foreach (range(0, 9) as $level) {
            $spell = \App\Models\Spell::where('level', $level)->first();
            if ($spell) {
                $spellIds->push($spell->id);
            }
        }

        // One per school
        \App\Models\SpellSchool::all()->each(function ($school) use ($spellIds) {
            $spell = \App\Models\Spell::where('spell_school_id', $school->id)
                ->whereNotIn('id', $spellIds->toArray())
                ->first();
            if ($spell) {
                $spellIds->push($spell->id);
            }
        });

        // Concentration spells
        $concentration = \App\Models\Spell::where('needs_concentration', true)
            ->whereNotIn('id', $spellIds->toArray())
            ->take(3)
            ->pluck('id');
        $spellIds = $spellIds->merge($concentration);

        // Ritual spells
        $ritual = \App\Models\Spell::where('is_ritual', true)
            ->whereNotIn('id', $spellIds->toArray())
            ->take(3)
            ->pluck('id');
        $spellIds = $spellIds->merge($ritual);

        // Fill remaining with random spells
        $remaining = $limit - $spellIds->count();
        if ($remaining > 0) {
            $additional = \App\Models\Spell::whereNotIn('id', $spellIds->toArray())
                ->inRandomOrder()
                ->take($remaining)
                ->pluck('id');
            $spellIds = $spellIds->merge($additional);
        }

        // Load full models with relationships
        $spells = \App\Models\Spell::whereIn('id', $spellIds->unique())
            ->with(['spellSchool', 'classes', 'sources.source', 'effects.damageType'])
            ->get();

        return $spells->map(fn ($spell) => $this->formatSpell($spell))->toArray();
    }

    protected function formatSpell(\App\Models\Spell $spell): array
    {
        return [
            'name' => $spell->name,
            'slug' => $spell->slug,
            'level' => $spell->level,
            'school' => $spell->spellSchool->code,
            'casting_time' => $spell->casting_time,
            'range' => $spell->range,
            'components' => $spell->components,
            'material_components' => $spell->material_components,
            'duration' => $spell->duration,
            'needs_concentration' => $spell->needs_concentration,
            'is_ritual' => $spell->is_ritual,
            'description' => $spell->description,
            'higher_levels' => $spell->higher_levels,
            'classes' => $spell->classes->pluck('slug')->toArray(),
            'damage_types' => $spell->effects
                ->filter(fn ($e) => $e->damageType)
                ->pluck('damageType.code')
                ->unique()
                ->values()
                ->toArray(),
            'sources' => $spell->sources->map(function ($entitySource) {
                return [
                    'code' => $entitySource->source->code,
                    'pages' => $entitySource->pages,
                ];
            })->toArray(),
        ];
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

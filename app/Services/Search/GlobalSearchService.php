<?php

namespace App\Services\Search;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Race;
use App\Models\Spell;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GlobalSearchService
{
    protected array $searchableModels = [
        'spell' => Spell::class,
        'item' => Item::class,
        'race' => Race::class,
        'class' => CharacterClass::class,
        'background' => Background::class,
        'feat' => Feat::class,
    ];

    protected array $pluralMap = [
        'spell' => 'spells',
        'item' => 'items',
        'race' => 'races',
        'class' => 'classes',
        'background' => 'backgrounds',
        'feat' => 'feats',
    ];

    /**
     * Search across multiple model types
     *
     * @param  string  $query  Search term
     * @param  array|null  $types  Entity types to search (null = all)
     * @param  int  $limit  Results per type
     * @return array<string, Collection>
     */
    public function search(string $query, ?array $types = null, int $limit = 20): array
    {
        $types = $types ?? array_keys($this->searchableModels);
        $results = [];

        foreach ($types as $type) {
            if (! isset($this->searchableModels[$type])) {
                continue;
            }

            $modelClass = $this->searchableModels[$type];

            try {
                $pluralKey = $this->pluralMap[$type] ?? $type.'s';
                $results[$pluralKey] = $modelClass::search($query)
                    ->take($limit)
                    ->get();
            } catch (\Exception $e) {
                // Log and continue with other types
                Log::warning("Global search failed for {$type}", [
                    'error' => $e->getMessage(),
                    'query' => $query,
                ]);

                $pluralKey = $this->pluralMap[$type] ?? $type.'s';
                $results[$pluralKey] = collect();
            }
        }

        return $results;
    }

    /**
     * Get available searchable types
     */
    public function getAvailableTypes(): array
    {
        return array_keys($this->searchableModels);
    }
}

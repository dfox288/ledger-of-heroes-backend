<?php

namespace App\Services;

use App\DTOs\ItemSearchDTO;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;

final class ItemSearchService
{
    public function buildScoutQuery(ItemSearchDTO $dto): \Laravel\Scout\Builder
    {
        $search = Item::search($dto->searchQuery);

        if (isset($dto->filters['item_type_id'])) {
            $search->where('item_type_id', $dto->filters['item_type_id']);
        }

        if (isset($dto->filters['rarity'])) {
            $search->where('rarity', $dto->filters['rarity']);
        }

        if (isset($dto->filters['is_magic'])) {
            $search->where('is_magic', (bool) $dto->filters['is_magic']);
        }

        if (isset($dto->filters['requires_attunement'])) {
            $search->where('requires_attunement', (bool) $dto->filters['requires_attunement']);
        }

        return $search;
    }

    public function buildDatabaseQuery(ItemSearchDTO $dto): Builder
    {
        $query = Item::with([
            'itemType',
            'damageType',
            'properties',
            'sources.source',
            'prerequisites.prerequisite',
        ]);

        $this->applyFilters($query, $dto);
        $this->applySorting($query, $dto);

        return $query;
    }

    private function applyFilters(Builder $query, ItemSearchDTO $dto): void
    {
        if (isset($dto->filters['search'])) {
            $query->where(function ($q) use ($dto) {
                $q->where('name', 'like', "%{$dto->filters['search']}%")
                    ->orWhere('description', 'like', "%{$dto->filters['search']}%");
            });
        }

        if (isset($dto->filters['item_type_id'])) {
            $query->where('item_type_id', $dto->filters['item_type_id']);
        }

        if (isset($dto->filters['rarity'])) {
            $query->where('rarity', $dto->filters['rarity']);
        }

        if (isset($dto->filters['is_magic'])) {
            $query->where('is_magic', (bool) $dto->filters['is_magic']);
        }

        if (isset($dto->filters['requires_attunement'])) {
            $query->where('requires_attunement', (bool) $dto->filters['requires_attunement']);
        }

        if (isset($dto->filters['min_strength'])) {
            $query->whereMinStrength((int) $dto->filters['min_strength']);
        }

        if (isset($dto->filters['has_prerequisites']) && (bool) $dto->filters['has_prerequisites']) {
            $query->hasPrerequisites();
        }
    }

    private function applySorting(Builder $query, ItemSearchDTO $dto): void
    {
        $query->orderBy($dto->sortBy, $dto->sortDirection);
    }
}

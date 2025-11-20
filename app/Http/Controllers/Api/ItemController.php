<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ItemIndexRequest;
use App\Http\Requests\ItemShowRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Support\Facades\Log;

class ItemController extends Controller
{
    /**
     * List all items
     *
     * Returns a paginated list of D&D 5e items including weapons, armor, and magic items.
     * Supports filtering by item type, rarity, magic properties, attunement requirements,
     * and prerequisites. All query parameters are validated automatically.
     */
    public function index(ItemIndexRequest $request)
    {
        $validated = $request->validated();
        $perPage = $validated['per_page'] ?? 15;

        // Handle Scout search if 'q' parameter is provided
        if ($request->filled('q')) {
            try {
                $items = $this->performScoutSearch($request, $validated, $perPage);
            } catch (\Exception $e) {
                Log::warning('Meilisearch search failed, falling back to MySQL', [
                    'query' => $validated['q'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $items = $this->performMysqlSearch($validated, $perPage);
            }
        } else {
            $items = $this->buildStandardQuery($validated)->paginate($perPage);
        }

        return ItemResource::collection($items);
    }

    /**
     * Perform Scout/Meilisearch search
     */
    protected function performScoutSearch(ItemIndexRequest $request, array $validated, int $perPage)
    {
        $search = Item::search($validated['q']);

        // Apply filters
        if (isset($validated['item_type_id'])) {
            $search->where('item_type_id', $validated['item_type_id']);
        }

        if (isset($validated['rarity'])) {
            $search->where('rarity', $validated['rarity']);
        }

        if (isset($validated['is_magic'])) {
            $search->where('is_magic', $request->boolean('is_magic'));
        }

        if (isset($validated['requires_attunement'])) {
            $search->where('requires_attunement', $request->boolean('requires_attunement'));
        }

        return $search->paginate($perPage);
    }

    /**
     * Perform MySQL FULLTEXT search fallback
     */
    protected function performMysqlSearch(array $validated, int $perPage)
    {
        $query = Item::with([
            'itemType',
            'damageType',
            'properties',
            'sources.source',
            'prerequisites.prerequisite',
        ]);

        // MySQL FULLTEXT search
        if (isset($validated['q'])) {
            $query->whereRaw(
                'MATCH(name, description) AGAINST(? IN NATURAL LANGUAGE MODE)',
                [$validated['q']]
            );
        }

        $this->applyItemFilters($query, $validated);

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        return $query->paginate($perPage);
    }

    /**
     * Build standard database query with filters
     */
    protected function buildStandardQuery(array $validated)
    {
        $query = Item::with([
            'itemType',
            'damageType',
            'properties',
            'sources.source',
            'prerequisites.prerequisite',
        ]);

        // Legacy search parameter using LIKE
        if (isset($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('name', 'like', "%{$validated['search']}%")
                    ->orWhere('description', 'like', "%{$validated['search']}%");
            });
        }

        $this->applyItemFilters($query, $validated);

        // Apply sorting
        $sortBy = $validated['sort_by'] ?? 'name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);

        return $query;
    }

    /**
     * Apply common item filters to query
     */
    protected function applyItemFilters($query, array $validated): void
    {
        if (isset($validated['item_type_id'])) {
            $query->where('item_type_id', $validated['item_type_id']);
        }

        if (isset($validated['rarity'])) {
            $query->where('rarity', $validated['rarity']);
        }

        if (isset($validated['is_magic'])) {
            $query->where('is_magic', (bool) $validated['is_magic']);
        }

        if (isset($validated['requires_attunement'])) {
            $query->where('requires_attunement', (bool) $validated['requires_attunement']);
        }

        if (isset($validated['min_strength'])) {
            $query->whereMinStrength((int) $validated['min_strength']);
        }

        if (isset($validated['has_prerequisites']) && (bool) $validated['has_prerequisites']) {
            $query->hasPrerequisites();
        }
    }

    /**
     * Get a single item
     *
     * Returns detailed information about a specific item including item type, damage type,
     * properties, abilities, random tables, modifiers, proficiencies, and prerequisites.
     * Supports selective relationship loading via the 'include' parameter.
     */
    public function show(Item $item, ItemShowRequest $request)
    {
        $validated = $request->validated();

        // Default relationships to load
        $relationships = [
            'itemType',
            'damageType',
            'properties',
            'abilities',
            'randomTables.entries',
            'sources.source',
            'proficiencies.proficiencyType',
            'modifiers.abilityScore',
            'modifiers.skill',
            'prerequisites.prerequisite',
        ];

        // If 'include' parameter provided, use it (note: this is for additional validation)
        // The actual loading is still done via the default relationships above
        // In a more advanced implementation, you might dynamically build the relationships array
        $item->load($relationships);

        return new ItemResource($item);
    }
}

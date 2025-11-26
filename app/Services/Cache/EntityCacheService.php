<?php

namespace App\Services\Cache;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Feat;
use App\Models\Item;
use App\Models\Monster;
use App\Models\OptionalFeature;
use App\Models\Race;
use App\Models\Spell;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class EntityCacheService
{
    public const TTL = 900; // 15 minutes

    /**
     * Get a spell by ID or slug
     */
    public function getSpell(int|string $id): ?Spell
    {
        $numericId = is_int($id) ? $id : $this->resolveId(Spell::class, $id);

        if (! $numericId) {
            return null;
        }

        $this->trackEntityId('spell', $numericId);
        $cacheKey = $this->cacheKey('spell', $numericId);

        return Cache::remember($cacheKey, self::TTL, function () use ($numericId) {
            return Spell::with($this->getRelationships('spell'))
                ->find($numericId);
        });
    }

    /**
     * Get an item by ID or slug
     */
    public function getItem(int|string $id): ?Item
    {
        $numericId = is_int($id) ? $id : $this->resolveId(Item::class, $id);

        if (! $numericId) {
            return null;
        }

        $this->trackEntityId('item', $numericId);
        $cacheKey = $this->cacheKey('item', $numericId);

        return Cache::remember($cacheKey, self::TTL, function () use ($numericId) {
            return Item::with($this->getRelationships('item'))
                ->find($numericId);
        });
    }

    /**
     * Get a monster by ID or slug
     */
    public function getMonster(int|string $id): ?Monster
    {
        $numericId = is_int($id) ? $id : $this->resolveId(Monster::class, $id);

        if (! $numericId) {
            return null;
        }

        $this->trackEntityId('monster', $numericId);
        $cacheKey = $this->cacheKey('monster', $numericId);

        return Cache::remember($cacheKey, self::TTL, function () use ($numericId) {
            return Monster::with($this->getRelationships('monster'))
                ->find($numericId);
        });
    }

    /**
     * Get a class by ID or slug
     */
    public function getClass(int|string $id): ?CharacterClass
    {
        $numericId = is_int($id) ? $id : $this->resolveId(CharacterClass::class, $id);

        if (! $numericId) {
            return null;
        }

        $this->trackEntityId('class', $numericId);
        $cacheKey = $this->cacheKey('class', $numericId);

        return Cache::remember($cacheKey, self::TTL, function () use ($numericId) {
            return CharacterClass::with($this->getRelationships('class'))
                ->find($numericId);
        });
    }

    /**
     * Get a race by ID or slug
     */
    public function getRace(int|string $id): ?Race
    {
        $numericId = is_int($id) ? $id : $this->resolveId(Race::class, $id);

        if (! $numericId) {
            return null;
        }

        $this->trackEntityId('race', $numericId);
        $cacheKey = $this->cacheKey('race', $numericId);

        return Cache::remember($cacheKey, self::TTL, function () use ($numericId) {
            return Race::with($this->getRelationships('race'))
                ->find($numericId);
        });
    }

    /**
     * Get a background by ID or slug
     */
    public function getBackground(int|string $id): ?Background
    {
        $numericId = is_int($id) ? $id : $this->resolveId(Background::class, $id);

        if (! $numericId) {
            return null;
        }

        $this->trackEntityId('background', $numericId);
        $cacheKey = $this->cacheKey('background', $numericId);

        return Cache::remember($cacheKey, self::TTL, function () use ($numericId) {
            return Background::with($this->getRelationships('background'))
                ->find($numericId);
        });
    }

    /**
     * Get a feat by ID or slug
     */
    public function getFeat(int|string $id): ?Feat
    {
        $numericId = is_int($id) ? $id : $this->resolveId(Feat::class, $id);

        if (! $numericId) {
            return null;
        }

        $this->trackEntityId('feat', $numericId);
        $cacheKey = $this->cacheKey('feat', $numericId);

        return Cache::remember($cacheKey, self::TTL, function () use ($numericId) {
            return Feat::with($this->getRelationships('feat'))
                ->find($numericId);
        });
    }

    /**
     * Get an optional feature by ID or slug
     */
    public function getOptionalFeature(int|string $id): ?OptionalFeature
    {
        $numericId = is_int($id) ? $id : $this->resolveId(OptionalFeature::class, $id);

        if (! $numericId) {
            return null;
        }

        $this->trackEntityId('optional_feature', $numericId);
        $cacheKey = $this->cacheKey('optional_feature', $numericId);

        return Cache::remember($cacheKey, self::TTL, function () use ($numericId) {
            return OptionalFeature::with($this->getRelationships('optional_feature'))
                ->find($numericId);
        });
    }

    /**
     * Invalidate a specific spell from cache
     */
    public function invalidateSpell(int $id): void
    {
        Cache::forget($this->cacheKey('spell', $id));
    }

    /**
     * Invalidate a specific item from cache
     */
    public function invalidateItem(int $id): void
    {
        Cache::forget($this->cacheKey('item', $id));
    }

    /**
     * Invalidate a specific monster from cache
     */
    public function invalidateMonster(int $id): void
    {
        Cache::forget($this->cacheKey('monster', $id));
    }

    /**
     * Invalidate a specific class from cache
     */
    public function invalidateClass(int $id): void
    {
        Cache::forget($this->cacheKey('class', $id));
    }

    /**
     * Invalidate a specific race from cache
     */
    public function invalidateRace(int $id): void
    {
        Cache::forget($this->cacheKey('race', $id));
    }

    /**
     * Invalidate a specific background from cache
     */
    public function invalidateBackground(int $id): void
    {
        Cache::forget($this->cacheKey('background', $id));
    }

    /**
     * Invalidate a specific feat from cache
     */
    public function invalidateFeat(int $id): void
    {
        Cache::forget($this->cacheKey('feat', $id));
    }

    /**
     * Invalidate a specific optional feature from cache
     */
    public function invalidateOptionalFeature(int $id): void
    {
        Cache::forget($this->cacheKey('optional_feature', $id));
    }

    /**
     * Invalidate all entities of a specific type
     */
    public function invalidateAll(string $entityType): void
    {
        // Track IDs for this entity type to enable selective deletion
        $idsKey = "entity:{$entityType}:ids";
        $ids = Cache::get($idsKey, []);

        // Delete all cached entities for this type
        foreach ($ids as $id) {
            Cache::forget($this->cacheKey($entityType, $id));
        }

        // Clear the ID tracking key
        Cache::forget($idsKey);
    }

    /**
     * Clear all entity caches
     */
    public function clearAll(): void
    {
        Cache::flush();
    }

    /**
     * Track an entity ID for selective invalidation
     */
    private function trackEntityId(string $entityType, int $id): void
    {
        $idsKey = "entity:{$entityType}:ids";
        $ids = Cache::get($idsKey, []);

        if (! in_array($id, $ids)) {
            $ids[] = $id;
            Cache::put($idsKey, $ids, self::TTL);
        }
    }

    /**
     * Generate cache key for an entity
     */
    private function cacheKey(string $type, int|string $id): string
    {
        return "entity:{$type}:{$id}";
    }

    /**
     * Resolve a slug to a numeric ID
     */
    private function resolveId(string $modelClass, int|string $id): ?int
    {
        if (is_int($id)) {
            return $id;
        }

        /** @var Model $model */
        $model = $modelClass::where('slug', $id)->first();

        return $model?->id;
    }

    /**
     * Get relationships to eager-load for each entity type
     */
    private function getRelationships(string $entityType): array
    {
        return match ($entityType) {
            'spell' => ['spellSchool', 'sources.source', 'effects', 'classes', 'tags'],
            'item' => ['itemType', 'sources.source', 'modifiers', 'tags'],
            'monster' => ['size', 'sources.source', 'traits', 'actions', 'legendaryActions'],
            'class' => ['sources.source', 'parentClass', 'subclasses', 'tags'],
            'race' => ['size', 'sources.source', 'parent', 'subraces', 'traits', 'tags'],
            'background' => ['sources.source', 'traits', 'proficiencies', 'languages', 'tags'],
            'feat' => ['sources.source', 'prerequisites', 'modifiers', 'tags'],
            'optional_feature' => ['classes', 'sources.source', 'tags', 'prerequisites', 'rolls'],
            default => [],
        };
    }
}

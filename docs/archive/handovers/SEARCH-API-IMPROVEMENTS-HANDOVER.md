# Search & API Improvements Handover

## Completed Items ✅

### 1. Update SEARCH.md Documentation (10 minutes)
**Status:** ✅ COMPLETE

- Updated entity counts (3,002 → 3,601 documents)
- Updated item count (2,107 → 2,156)
- Updated race count (115 → 67)
- Added monsters to searchable entities table
- Updated index stats (6 → 7 indexes, 15MB → 20MB)
- Added note about `monsters_index` naming convention

**Files Changed:**
- `docs/SEARCH.md`

---

### 2. Add Tag Filtering to Meilisearch Indexes (30 minutes)
**Status:** ✅ CODE COMPLETE - NEEDS RE-INDEXING

**Changes Made:**
1. Updated `Monster::toSearchableArray()` to include `tag_slugs`
2. Updated `Spell::toSearchableArray()` to include `tag_slugs`
3. Updated `Item::toSearchableArray()` to include `tag_slugs`
4. Added `tag_slugs` to filterable attributes in `MeilisearchIndexConfigurator`:
   - Spells index
   - Items index
   - Monsters index

**Files Changed:**
- `app/Models/Monster.php`
- `app/Models/Spell.php`
- `app/Models/Item.php`
- `app/Services/Search/MeilisearchIndexConfigurator.php`

**REQUIRED NEXT STEPS:**
```bash
# Reconfigure indexes with new filterable attributes
docker compose exec php php artisan search:configure-indexes

# Re-import entities to include tag data
docker compose exec php php artisan scout:import "App\\Models\\Monster"
docker compose exec php php artisan scout:import "App\\Models\\Spell"
docker compose exec php php artisan scout:import "App\\Models\\Item"
```

**Test After Re-indexing:**
```bash
# Should work after re-indexing
curl "http://localhost:8080/api/v1/monsters?filter=tag_slugs=undead"
curl "http://localhost:8080/api/v1/monsters?filter=tag_slugs=fire_immune"
```

---

### 3. Document monsters_index Naming
**Status:** ✅ COMPLETE

Added note to SEARCH.md explaining why monsters index is `monsters_index` not `monsters`.

---

## Remaining Items To Implement

### 4. Add Spell School Filter to MonsterIndexRequest (1 hour)
**Status:** ⏳ TODO

**Goal:** Filter monsters by the spell schools they can cast

**Example:**
```bash
GET /api/v1/monsters?spell_school=EV
# Returns monsters that cast evocation spells
```

**Implementation:**
1. Add `spell_school` parameter to `MonsterIndexRequest`:
   ```php
   'spell_school' => ['sometimes', 'string', 'max:20'],
   ```

2. Add logic to `MonsterSearchService` to join with entity_spells and filter by spell school

3. Add examples to `MonsterController` documentation

**Files to Edit:**
- `app/Http/Requests/MonsterIndexRequest.php`
- `app/Services/MonsterSearchService.php`
- `app/Http/Controllers/Api/MonsterController.php`

---

### 5. Implement Faceted Search Support (3-4 hours)
**Status:** ⏳ TODO

**Goal:** Return filter value counts alongside results

**Example Response:**
```json
{
  "data": [...],
  "facets": {
    "level": {"1": 93, "2": 71, "3": 58},
    "school_code": {"EV": 87, "EN": 65},
    "rarity": {"common": 120, "uncommon": 45, "rare": 23}
  }
}
```

**Implementation:**
1. Meilisearch supports facets via API - add to `MeilisearchIndexConfigurator`:
   ```php
   $index->updateSettings([
       'faceting' => [
           'maxValuesPerFacet' => 100
       ]
   ]);
   ```

2. Update search services to request facets:
   ```php
   $results = $index->search($query, [
       'facets' => ['level', 'school_code', 'rarity']
   ]);
   ```

3. Add `facets` parameter to IndexRequests (optional, defaults to common facets)

4. Return facets in API responses

**Files to Create/Edit:**
- Update all `*SearchService.php` files
- Update all `*IndexRequest.php` files
- Update controller docblocks with facet examples

---

### 6. Create Autocomplete Endpoints (2 hours)
**Status:** ⏳ TODO

**Goal:** Lightweight endpoints returning just `id` and `name` for typeahead

**Example:**
```bash
GET /api/v1/spells/autocomplete?q=fire
→ [{"id": 140, "name": "Fireball"}, {"id": 141, "name": "Fire Bolt"}]
```

**Implementation:**
1. Add `autocomplete()` method to each entity controller:
   ```php
   public function autocomplete(Request $request, SpellSearchService $service)
   {
       $query = $request->validate(['q' => 'required|string|min:2']);
       $results = Spell::search($query)->take(10)->get();
       return response()->json($results->map(fn($s) => [
           'id' => $s->id,
           'name' => $s->name,
           'slug' => $s->slug
       ]));
   }
   ```

2. Add routes in `routes/api.php`:
   ```php
   Route::get('spells/autocomplete', [SpellController::class, 'autocomplete']);
   ```

3. Add to OpenAPI docs

**Files to Edit:**
- All 7 entity controllers
- `routes/api.php`
- Update `docs/SEARCH.md` with autocomplete examples

---

### 8. Add Range Query Documentation (30 minutes)
**Status:** ⏳ TODO

**Goal:** Document Meilisearch filter syntax for range queries

**Add to Controller Docblocks:**
```php
/**
 * **Range Query Examples:**
 * - CR 10-15: `GET /api/v1/monsters?filter=challenge_rating >= 10 AND challenge_rating <= 15`
 * - Low-level spells: `GET /api/v1/spells?filter=level IN [0,1,2,3]`
 * - High HP monsters: `GET /api/v1/monsters?filter=hit_points_average > 100`
 * - Multiple ranges: `GET /api/v1/monsters?filter=challenge_rating >= 5 AND hit_points_average > 50`
 */
```

**Add to SEARCH.md:**
Section on "Advanced Meilisearch Filters" with comprehensive examples

**Files to Edit:**
- `app/Http/Controllers/Api/MonsterController.php`
- `app/Http/Controllers/Api/SpellController.php`
- `docs/SEARCH.md`

---

### 9. Add OR Operator Support for Multi-Value Filters (2 hours per entity)
**Status:** ⏳ TODO

**Goal:** Allow OR logic for filters like source, rarity, type

**Example:**
```bash
GET /api/v1/spells?sources=phb,xge&source_operator=OR
# Returns spells from PHB OR XGE (not just those in both)
```

**Implementation Pattern (already exists for Race/Monster spell filtering):**
```php
// In Request validation
'sources' => ['sometimes', 'string'],
'source_operator' => ['sometimes', 'in:AND,OR'],

// In Service
if (isset($dto->filters['sources'])) {
    $slugs = explode(',', $dto->filters['sources']);
    $operator = $dto->filters['source_operator'] ?? 'AND';

    if ($operator === 'OR') {
        $query->where(function($q) use ($slugs) {
            foreach ($slugs as $slug) {
                $q->orWhereHas('sources', fn($sq) =>
                    $sq->whereHas('source', fn($sourceQuery) =>
                        $sourceQuery->where('code', $slug)
                    )
                );
            }
        });
    } else {
        // AND logic (existing)
    }
}
```

**Entities to Update:**
- Spells (sources, classes)
- Items (sources, types)
- Monsters (sources, types) - partially done
- Classes (sources)
- Backgrounds (sources)
- Feats (sources)
- Races (sources) - partially done

---

## Test Commands

After completing re-indexing:

```bash
# Test tag filtering
curl "http://localhost:8080/api/v1/monsters?filter=tag_slugs=undead" | jq '.meta.total'
curl "http://localhost:8080/api/v1/monsters?filter=tag_slugs=construct" | jq '.meta.total'

# Test existing functionality still works
curl "http://localhost:8080/api/v1/spells?q=fireball" | jq '.meta.total'
curl "http://localhost:8080/api/v1/monsters?cr=5" | jq '.meta.total'
curl "http://localhost:8080/api/v1/items?rarity=rare" | jq '.meta.total'
```

---

## Summary

**Completed (2/9 items, ~40 minutes):**
1. ✅ Update SEARCH.md documentation
2. ✅ Add tag filtering (code complete, needs re-indexing)
3. ✅ Document monsters_index naming

**Ready to Implement (6 items, ~8-9 hours):**
4. ⏳ Spell school filter for monsters (1h)
5. ⏳ Faceted search support (3-4h)
6. ⏳ Autocomplete endpoints (2h)
8. ⏳ Range query documentation (30min)
9. ⏳ OR operator support (2h per entity, ~6 entities = 12h total, but can do incrementally)

**Not Implementing (per user):**
7. Query result caching
10. Search analytics
11. Synonyms
12. Custom ranking

---

## Priority Order

1. **First:** Re-index with tag support (5 minutes)
2. **Quick wins:** Range query documentation (30min)
3. **High value:** Faceted search (3-4h) - big UX improvement
4. **Med value:** Autocomplete endpoints (2h) - performance win
5. **Nice to have:** Spell school monster filter (1h)
6. **Incremental:** OR operator support (do 1-2 entities at a time)

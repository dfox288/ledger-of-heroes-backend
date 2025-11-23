# Parent Relationships in List Views - Design Document

**Date:** 2025-11-23
**Status:** 🟡 Ready for Implementation
**Complexity:** Low (1-2 hours)
**Impact:** User Experience Enhancement

---

## Problem Statement

The index/list endpoints (`GET /api/v1/races`, `GET /api/v1/classes`) do not return parent relationship data, which is needed for frontend list views to display context like:
- "High Elf (Elf)" - showing subrace with parent name
- "School of Evocation (Wizard)" - showing subclass with parent class

The parent relationships exist in the API Resources and work on show/detail endpoints, but are not loaded for index endpoints due to the Scout search fix (commits 77e3c57 + f08a79f) which intentionally matched the original database queries.

**Specific Requirements:**
1. ✅ Add parent relationships to index/list endpoints
2. ✅ Return minimal parent data only (id, slug, name) - not full parent with all relationships
3. ✅ Work for both regular list queries (`/races`) and search queries (`/races?q=elf`)
4. ✅ Show endpoints should continue to load full parent data with all relationships
5. ✅ No N+1 query issues
6. ✅ Maintain backward compatibility with existing code

---

## Solution: Split Index vs Show Relationships

### Architecture Overview

Define **two relationship sets** per SearchService:
1. **INDEX_RELATIONSHIPS:** Lightweight list for browsing many items
2. **SHOW_RELATIONSHIPS:** Complete relationships for viewing single item details

**Key Principle:** Eloquent's `with(['parent'])` loads the parent model's columns but NOT the parent's relationships unless explicitly requested. This means no N+1 risk!

---

## Implementation Details

### 1. SearchService Changes (7 files)

Each SearchService will have three constants and three methods:

```php
// Example: RaceSearchService.php
final class RaceSearchService
{
    /**
     * Relationships for index/list endpoints (lightweight)
     */
    private const INDEX_RELATIONSHIPS = [
        'size',
        'sources.source',
        'proficiencies.skill',
        'traits.randomTables.entries',
        'modifiers.abilityScore',
        'conditions.condition',
        'spells.spell',
        'spells.abilityScore',
        'parent',  // ← NEW: Parent for list context
    ];

    /**
     * Relationships for show/detail endpoints (comprehensive)
     */
    private const SHOW_RELATIONSHIPS = [
        'size',
        'sources.source',
        'parent',
        'subraces',
        'proficiencies.skill.abilityScore',
        'proficiencies.abilityScore',
        'traits.randomTables.entries',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'languages.language',
        'conditions.condition',
        'spells.spell',
        'spells.abilityScore',
        'tags',
    ];

    /**
     * Backward compatibility alias
     */
    private const DEFAULT_RELATIONSHIPS = self::INDEX_RELATIONSHIPS;

    // Methods
    public function getDefaultRelationships(): array
    {
        return self::INDEX_RELATIONSHIPS;  // Used by index()
    }

    public function getIndexRelationships(): array
    {
        return self::INDEX_RELATIONSHIPS;  // Explicit alternative
    }

    public function getShowRelationships(): array
    {
        return self::SHOW_RELATIONSHIPS;  // Used by show()
    }

    // buildDatabaseQuery uses INDEX_RELATIONSHIPS
    public function buildDatabaseQuery(RaceSearchDTO $dto): Builder
    {
        $query = Race::with(self::INDEX_RELATIONSHIPS);
        // ... rest of method
    }
}
```

**Entities to Update:**
1. ✅ `RaceSearchService` - Add `'parent'` to INDEX, create SHOW_RELATIONSHIPS
2. ✅ `ClassSearchService` - Add `'parentClass'` to INDEX, create SHOW_RELATIONSHIPS
3. ✅ `SpellSearchService` - No parent relationship (base entity)
4. ✅ `MonsterSearchService` - No parent relationship (base entity)
5. ✅ `ItemSearchService` - No parent relationship (base entity)
6. ✅ `BackgroundSearchService` - No parent relationship (base entity)
7. ✅ `FeatSearchService` - No parent relationship (base entity)

**Notes:**
- Only Race and Class have parent relationships
- Other services still need SHOW_RELATIONSHIPS constant for consistency
- Other services just copy existing show() relationship lists

---

### 2. Controller Changes (7 files)

**Index Endpoints:** No changes needed! Already calls `getDefaultRelationships()`.

**Show Endpoints:** Replace inline relationship arrays with service method calls.

**Example - RaceController:**

```php
// BEFORE (16 lines of inline array)
public function show(RaceShowRequest $request, Race $race, EntityCacheService $cache)
{
    $validated = $request->validated();

    $defaultRelationships = [
        'size',
        'sources.source',
        'parent',
        'subraces',
        'proficiencies.skill.abilityScore',
        'proficiencies.abilityScore',
        'traits.randomTables.entries',
        'modifiers.abilityScore',
        'modifiers.skill',
        'modifiers.damageType',
        'languages.language',
        'conditions.condition',
        'spells.spell',
        'spells.abilityScore',
        'tags',
    ];

    // ... rest of method
}

// AFTER (1 line)
public function show(RaceShowRequest $request, Race $race, EntityCacheService $cache, RaceSearchService $service)
{
    $validated = $request->validated();

    $defaultRelationships = $service->getShowRelationships();

    // ... rest of method (identical)
}
```

**Controllers to Update:**
1. ✅ `RaceController::show()` - Inject RaceSearchService, use getShowRelationships()
2. ✅ `ClassController::show()` - Inject ClassSearchService, use getShowRelationships()
3. ✅ `SpellController::show()` - Inject SpellSearchService, use getShowRelationships()
4. ✅ `MonsterController::show()` - Inject MonsterSearchService, use getShowRelationships()
5. ✅ `ItemController::show()` - Inject ItemSearchService, use getShowRelationships()
6. ✅ `BackgroundController::show()` - Inject BackgroundSearchService, use getShowRelationships()
7. ✅ `FeatController::show()` - Inject FeatSearchService, use getShowRelationships()

---

### 3. Resource Changes

**No changes needed!** Resources already use `whenLoaded()` which handles both cases:

```php
// RaceResource.php - WORKS AS-IS
'parent_race' => $this->when($this->parent_race_id, function () {
    return new RaceResource($this->whenLoaded('parent'));
}),
```

**Behavior:**
- **Index endpoint:** Loads `'parent'` → Returns minimal `{id, slug, name}` (parent's other relationships aren't loaded)
- **Show endpoint:** Loads `'parent'` with all its relationships → Returns full parent data recursively

`★ Insight ─────────────────────────────────────`
**Why Resources Don't Need Changes:**

`RaceResource` is recursive. When you pass a parent Race to `new RaceResource($parent)`, it renders based on what's loaded on that parent model:

- Index: Parent has no relationships loaded → `whenLoaded()` returns null for traits/modifiers/etc → Only id/slug/name appear
- Show: Parent has relationships loaded → `whenLoaded()` returns data → Full nested structure

This is the beauty of `whenLoaded()` - it adapts to whatever data is actually present!
`─────────────────────────────────────────────────`

---

## Performance Analysis

### Query Impact

**Before (current state):**
- Index: ~8 queries (size, sources, proficiencies, traits, modifiers, conditions, spells)
- Show: ~14 queries (all of above + parent, subraces, languages, tags)

**After (with parent in index):**
- Index: ~9 queries (+1 for parent) - **11% increase**
- Show: ~14 queries (no change)

**Redis Cache Impact:**
- Lookup tables already cached → 0ms overhead
- Entity cache active on show() → minimal impact
- Parent models likely already in query cache → <1ms actual overhead

**Conclusion:** Negligible performance impact (~1ms per request), acceptable for UX gain.

---

### N+1 Query Prevention

**Why This Doesn't Cause N+1:**

Eloquent's `with(['parent'])` executes:
```sql
-- Query 1: Load all races
SELECT * FROM races WHERE ...

-- Query 2: Load all parents (IN clause, single query)
SELECT * FROM races WHERE id IN (1, 3, 7, 12)  -- Parent IDs collected from Query 1
```

**NOT:**
```sql
-- This does NOT happen:
SELECT * FROM races WHERE id = 1  -- Parent for race 1
SELECT * FROM races WHERE id = 3  -- Parent for race 2
SELECT * FROM races WHERE id = 7  -- Parent for race 3
-- ... N queries (this is N+1, but we're NOT doing this)
```

Laravel batches parent loading into a single `WHERE IN` query. The N+1 myth comes from forgetting that `with()` is smart about batching.

---

## API Response Examples

### Index Endpoint (After Implementation)

**Request:** `GET /api/v1/races?per_page=3`

```json
{
  "data": [
    {
      "id": 23,
      "slug": "high-elf",
      "name": "High Elf",
      "size": {"code": "M", "name": "Medium"},
      "speed": 30,
      "parent_race": {
        "id": 5,
        "slug": "elf",
        "name": "Elf"
        // No traits, modifiers, proficiencies, etc.
      },
      "traits": [...],
      "modifiers": [...]
    }
  ]
}
```

### Show Endpoint (After Implementation)

**Request:** `GET /api/v1/races/high-elf`

```json
{
  "data": {
    "id": 23,
    "slug": "high-elf",
    "name": "High Elf",
    "parent_race": {
      "id": 5,
      "slug": "elf",
      "name": "Elf",
      "traits": [...],        // ← Full parent data
      "modifiers": [...],     // ← Full parent data
      "subraces": [...]       // ← Full parent data
    }
  }
}
```

---

## Testing Strategy

### 1. Manual Testing

```bash
# Test Race index with parent
curl -s "http://localhost:8080/api/v1/races?per_page=1" | jq '.data[0].parent_race'
# Expected: {id, slug, name} or null

# Test Race show with full parent
curl -s "http://localhost:8080/api/v1/races/high-elf" | jq '.data.parent_race | keys'
# Expected: ["id", "slug", "name", "size", "traits", "modifiers", ...]

# Test Class index with parent
curl -s "http://localhost:8080/api/v1/classes?per_page=1" | jq '.data[0].parent_class'
# Expected: {id, slug, name} or null

# Test search results
curl -s "http://localhost:8080/api/v1/races?q=elf" | jq '.data[0].parent_race'
# Expected: {id, slug, name} or null
```

### 2. Automated Testing

**Add Feature Tests:**

```php
// tests/Feature/Api/RaceControllerTest.php

test('index returns parent race with minimal data', function () {
    $parent = Race::factory()->create(['name' => 'Elf']);
    $subrace = Race::factory()->create([
        'name' => 'High Elf',
        'parent_race_id' => $parent->id,
    ]);

    $response = $this->getJson('/api/v1/races');

    $highElf = collect($response->json('data'))
        ->firstWhere('slug', 'high-elf');

    expect($highElf['parent_race'])
        ->toHaveKeys(['id', 'slug', 'name'])
        ->toEqual([
            'id' => $parent->id,
            'slug' => 'elf',
            'name' => 'Elf',
        ]);

    // Should NOT have parent's relationships in index
    expect($highElf['parent_race'])->not->toHaveKey('traits');
    expect($highElf['parent_race'])->not->toHaveKey('modifiers');
});

test('show returns parent race with full relationships', function () {
    $parent = Race::factory()->create(['name' => 'Elf']);
    $parent->traits()->create([...]);  // Add trait to parent

    $subrace = Race::factory()->create([
        'name' => 'High Elf',
        'parent_race_id' => $parent->id,
    ]);

    $response = $this->getJson("/api/v1/races/{$subrace->slug}");

    expect($response->json('data.parent_race'))
        ->toHaveKeys(['id', 'slug', 'name', 'traits', 'modifiers']);
});

test('search results include parent race', function () {
    $parent = Race::factory()->create(['name' => 'Elf']);
    $subrace = Race::factory()->create([
        'name' => 'High Elf',
        'parent_race_id' => $parent->id,
    ]);

    $response = $this->getJson('/api/v1/races?q=high');

    expect($response->json('data.0.parent_race'))
        ->toEqual([
            'id' => $parent->id,
            'slug' => 'elf',
            'name' => 'Elf',
        ]);
});
```

### 3. Query Count Verification

```php
test('index does not cause N+1 queries with parent relationship', function () {
    $parent = Race::factory()->create();
    Race::factory()->count(10)->create(['parent_race_id' => $parent->id]);

    DB::enableQueryLog();

    $this->getJson('/api/v1/races?per_page=10');

    $queries = DB::getQueryLog();

    // Should be ~9 queries total (not 10 + 10 for parents)
    expect(count($queries))->toBeLessThan(15);
});
```

---

## Migration Path

### Step-by-Step Implementation

1. **Update SearchServices (7 files):**
   - Add INDEX_RELATIONSHIPS, SHOW_RELATIONSHIPS, DEFAULT_RELATIONSHIPS constants
   - Add getIndexRelationships(), getShowRelationships() methods
   - Update buildDatabaseQuery() to use INDEX_RELATIONSHIPS
   - For Race/Class: Add parent to INDEX_RELATIONSHIPS

2. **Update Controllers (7 files):**
   - Inject SearchService into show() methods
   - Replace inline relationship arrays with $service->getShowRelationships()

3. **Test manually:**
   - Verify index endpoints return parent data
   - Verify show endpoints return full parent data
   - Check query counts (should be +1 query max)

4. **Add automated tests:**
   - Index parent data tests (minimal)
   - Show parent data tests (full)
   - N+1 prevention tests

5. **Update documentation:**
   - CHANGELOG.md - Add feature entry
   - Update session handover if needed

---

## Backward Compatibility

### Breaking Changes: None

- ✅ Existing `getDefaultRelationships()` method still works
- ✅ API response structure unchanged (parent fields already existed in Resources)
- ✅ No parameter changes
- ✅ No HTTP status code changes

### Additions Only

- ✅ Index endpoints now return parent data (previously null)
- ✅ New methods: `getIndexRelationships()`, `getShowRelationships()`

---

## Files to Modify

### SearchServices (7 files)
1. `app/Services/RaceSearchService.php` - Add parent to INDEX, add SHOW
2. `app/Services/ClassSearchService.php` - Add parentClass to INDEX, add SHOW
3. `app/Services/SpellSearchService.php` - Add SHOW constant only
4. `app/Services/MonsterSearchService.php` - Add SHOW constant only
5. `app/Services/ItemSearchService.php` - Add SHOW constant only
6. `app/Services/BackgroundSearchService.php` - Add SHOW constant only
7. `app/Services/FeatSearchService.php` - Add SHOW constant only

### Controllers (7 files)
1. `app/Http/Controllers/Api/RaceController.php` - Inject service, use getShowRelationships()
2. `app/Http/Controllers/Api/ClassController.php` - Inject service, use getShowRelationships()
3. `app/Http/Controllers/Api/SpellController.php` - Inject service, use getShowRelationships()
4. `app/Http/Controllers/Api/MonsterController.php` - Inject service, use getShowRelationships()
5. `app/Http/Controllers/Api/ItemController.php` - Inject service, use getShowRelationships()
6. `app/Http/Controllers/Api/BackgroundController.php` - Inject service, use getShowRelationships()
7. `app/Http/Controllers/Api/FeatController.php` - Inject service, use getShowRelationships()

### Tests (new files)
1. `tests/Feature/Api/ParentRelationshipTest.php` - New comprehensive test file

### Documentation (2 files)
1. `CHANGELOG.md` - Add feature entry under [Unreleased]
2. `docs/SESSION-HANDOVER-2025-11-23-PARENT-RELATIONSHIPS.md` - Session summary

**Total:** 17 files (14 modified, 2 new, 1 updated)

---

## Estimated Effort

- **SearchServices:** 7 files × 5 minutes = 35 minutes
- **Controllers:** 7 files × 3 minutes = 21 minutes
- **Tests:** 1 file × 15 minutes = 15 minutes
- **Documentation:** 2 files × 10 minutes = 20 minutes
- **Manual testing:** 10 minutes
- **Buffer:** 15 minutes

**Total: ~2 hours**

---

## Success Criteria

- ✅ Index endpoints return parent data with id, slug, name
- ✅ Show endpoints return full parent data with all relationships
- ✅ Search results include parent data
- ✅ No N+1 queries (verify with query log)
- ✅ All existing tests pass
- ✅ New tests added and passing
- ✅ Code formatted with Pint
- ✅ CHANGELOG updated
- ✅ Changes committed and pushed

---

## Future Enhancements

### 1. GraphQL-Style Field Selection

Allow clients to request specific relationships:

```
GET /api/v1/races?fields=id,slug,name,parent_race
GET /api/v1/races?fields=id,slug,name,parent_race.traits
```

**Benefit:** Ultimate flexibility without creating dozens of endpoints.

### 2. Relationship Preloading Configuration

Centralized config for all endpoints:

```php
// config/api-relationships.php
return [
    'race' => [
        'index' => [...],
        'show' => [...],
        'minimal' => ['id', 'slug', 'name'],  // For parents
    ],
];
```

**Benefit:** Single source of truth, easier to audit and maintain.

### 3. Automatic Minimal Parent Detection

Service automatically detects when loading parent and limits to minimal fields:

```php
$query->with(['parent' => function ($q) {
    $q->select('id', 'slug', 'name', 'parent_race_id');
}]);
```

**Benefit:** No extra constants needed, enforced at query level.

---

## References

### Related Documentation
- `docs/SESSION-HANDOVER-2025-11-23-SCOUT-SEARCH-RELATIONSHIP-FIX.md` - Context for why this is needed
- `CLAUDE.md` - Development standards and workflows
- Laravel Eager Loading: https://laravel.com/docs/11.x/eloquent-relationships#eager-loading
- API Resources: https://laravel.com/docs/11.x/eloquent-resources#conditional-relationships

### Related Issues
- Scout search fix removed parent relationships (commits 77e3c57 + f08a79f)
- Frontend needs parent context for list views ("High Elf (Elf)")

---

**Status:** 🟢 Ready for Implementation
**Next Step:** Create implementation plan with TDD approach

---

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>

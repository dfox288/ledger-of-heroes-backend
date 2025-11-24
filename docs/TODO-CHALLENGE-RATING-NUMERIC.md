# TODO: Convert Challenge Rating to Numeric

**Priority:** Medium
**Estimated Effort:** 2-3 hours
**Impact:** High - Enables numeric filtering/sorting on CR in Meilisearch

---

## Problem

Challenge Rating is currently stored as a `string` in the database:
- Database column: `string('challenge_rating', 10)`
- Values: `"0"`, `"1/8"`, `"1/4"`, `"1/2"`, `"1"`, `"2"`, ..., `"30"`
- Location: `database/migrations/2025_11_17_205907_create_monsters_table.php:46`

This prevents numeric comparison queries in Meilisearch:
```bash
# ❌ Does NOT work (0 results)
GET /api/v1/monsters?filter=challenge_rating >= 10

# ✅ Works but awkward (requires listing all values)
GET /api/v1/monsters?filter=challenge_rating IN [10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20]
```

---

## Proposed Solution

Add a **numeric** `challenge_rating_numeric` column alongside the existing string column.

### CR to Numeric Conversion Table

| String CR | Numeric Value | Notes |
|-----------|---------------|-------|
| `"0"` | `0.0` | CR 0 creatures |
| `"1/8"` | `0.125` | Very weak |
| `"1/4"` | `0.25` | Weak |
| `"1/3"` | `0.33` | (if exists) |
| `"1/2"` | `0.5` | Common low CR |
| `"1"` | `1.0` | Level 1 appropriate |
| `"2"` | `2.0` | ... |
| ... | ... | ... |
| `"30"` | `30.0` | Tarrasque-level |

---

## Implementation Steps

### 1. Create Migration (15 min)

```bash
php artisan make:migration add_challenge_rating_numeric_to_monsters
```

**Migration content:**
```php
public function up()
{
    Schema::table('monsters', function (Blueprint $table) {
        // Add new numeric column after string CR
        $table->decimal('challenge_rating_numeric', 5, 3)
              ->after('challenge_rating')
              ->nullable()
              ->index('monsters_cr_numeric_idx');
    });

    // Data migration: convert existing string CRs to numeric
    DB::statement("
        UPDATE monsters
        SET challenge_rating_numeric = CASE
            WHEN challenge_rating = '0' THEN 0.0
            WHEN challenge_rating = '1/8' THEN 0.125
            WHEN challenge_rating = '1/4' THEN 0.25
            WHEN challenge_rating = '1/3' THEN 0.33
            WHEN challenge_rating = '1/2' THEN 0.5
            ELSE CAST(challenge_rating AS DECIMAL(5,3))
        END
    ");

    // Make non-nullable after data migration
    Schema::table('monsters', function (Blueprint $table) {
        $table->decimal('challenge_rating_numeric', 5, 3)->nullable(false)->change();
    });
}
```

### 2. Update Monster Model (10 min)

**File:** `app/Models/Monster.php`

Add to fillable:
```php
protected $fillable = [
    // ... existing fields ...
    'challenge_rating',
    'challenge_rating_numeric', // NEW
    // ... rest ...
];
```

Add cast:
```php
protected $casts = [
    // ... existing casts ...
    'challenge_rating_numeric' => 'float',
];
```

### 3. Update toSearchableArray() (5 min)

**File:** `app/Models/Monster.php`

```php
public function toSearchableArray(): array
{
    return [
        // ... existing fields ...
        'challenge_rating' => $this->challenge_rating, // Keep for exact matches
        'challenge_rating_numeric' => $this->challenge_rating_numeric, // NEW - for range queries
        // ... rest ...
    ];
}
```

### 4. Update searchableOptions() (5 min)

**File:** `app/Models/Monster.php`

```php
'filterableAttributes' => [
    // ... existing ...
    'challenge_rating',         // Keep for exact matches like "1/2"
    'challenge_rating_numeric', // NEW - for numeric filtering
    // ... rest ...
],
'sortableAttributes' => [
    // ... existing ...
    'challenge_rating_numeric', // NEW - enables proper CR sorting
    // ... rest ...
],
```

### 5. Update MeilisearchIndexConfigurator (5 min)

**File:** `app/Services/Search/MeilisearchIndexConfigurator.php`

```php
public function configureMonstersIndex(): void
{
    // ... existing code ...

    $index->updateFilterableAttributes([
        // ... existing ...
        'challenge_rating',         // String - for exact matches
        'challenge_rating_numeric', // NEW - for range queries
        // ... rest ...
    ]);

    $index->updateSortableAttributes([
        // ... existing ...
        'challenge_rating_numeric', // NEW
        // ... rest ...
    ]);
}
```

### 6. Update MonsterImporter (30 min)

**File:** `app/Services/Importers/MonsterImporter.php`

Add helper method:
```php
/**
 * Convert string CR to numeric value for filtering/sorting
 */
private function convertChallengeRatingToNumeric(string $cr): float
{
    return match($cr) {
        '0' => 0.0,
        '1/8' => 0.125,
        '1/4' => 0.25,
        '1/3' => 0.33,
        '1/2' => 0.5,
        default => (float) $cr,
    };
}
```

Update data array:
```php
$monsterData = [
    // ... existing fields ...
    'challenge_rating' => $challengeRating,
    'challenge_rating_numeric' => $this->convertChallengeRatingToNumeric($challengeRating),
    // ... rest ...
];
```

### 7. Update API Resources (5 min)

**File:** `app/Http/Resources/MonsterResource.php`

```php
public function toArray(Request $request): array
{
    return [
        // ... existing ...
        'challenge_rating' => $this->challenge_rating,
        'challenge_rating_numeric' => $this->challenge_rating_numeric, // NEW
        // ... rest ...
    ];
}
```

### 8. Re-import & Re-index (30 min)

```bash
# Run migration
php artisan migrate

# Re-import all monsters (will populate new numeric column)
php artisan import:monsters import-files/bestiary-*.xml

# Reconfigure Meilisearch indexes
php artisan search:configure-indexes

# Re-import to Meilisearch with new attribute
php artisan scout:import "App\Models\Monster"
```

### 9. Update Tests (30 min)

Add tests for numeric CR filtering:

**File:** `tests/Feature/Api/MonsterSearchTest.php`

```php
#[Test]
public function it_filters_by_numeric_challenge_rating_range()
{
    // Test CR >= 10
    $response = $this->getJson('/api/v1/monsters?filter=challenge_rating_numeric >= 10');
    $response->assertStatus(200);

    $data = $response->json('data');
    foreach ($data as $monster) {
        $this->assertGreaterThanOrEqual(10, $monster['challenge_rating_numeric']);
    }
}

#[Test]
public function it_sorts_by_numeric_challenge_rating()
{
    $response = $this->getJson('/api/v1/monsters?sort=challenge_rating_numeric:asc&per_page=100');

    $crs = collect($response->json('data'))->pluck('challenge_rating_numeric');
    $this->assertEquals($crs->sort()->values()->toArray(), $crs->toArray());
}
```

### 10. Update Documentation (15 min)

Update controller docblock examples:

**File:** `app/Http/Controllers/Api/MonsterController.php`

```php
/**
 * **Challenge Rating Filtering:**
 * - By exact CR (string): `GET /api/v1/monsters?filter=challenge_rating = "1/2"`
 * - By numeric range: `GET /api/v1/monsters?filter=challenge_rating_numeric >= 10`
 * - By numeric range: `GET /api/v1/monsters?filter=challenge_rating_numeric >= 5 AND challenge_rating_numeric <= 10`
 * - Boss monsters: `GET /api/v1/monsters?filter=challenge_rating_numeric >= 15`
 * - Low-level: `GET /api/v1/monsters?filter=challenge_rating_numeric <= 3`
 * - Fractional CRs: `GET /api/v1/monsters?filter=challenge_rating_numeric < 1`
 */
```

---

## New Query Capabilities After Implementation

```bash
# Range queries (NOW POSSIBLE!)
GET /api/v1/monsters?filter=challenge_rating_numeric >= 10
GET /api/v1/monsters?filter=challenge_rating_numeric >= 5 AND challenge_rating_numeric <= 10

# Combined with speed (YOUR ORIGINAL QUERY WILL WORK!)
GET /api/v1/monsters?filter=speed_fly >= 60 AND challenge_rating_numeric >= 10

# Combined with abilities
GET /api/v1/monsters?filter=strength >= 20 AND challenge_rating_numeric <= 5

# Boss monsters with high CR
GET /api/v1/monsters?filter=challenge_rating_numeric >= 15 AND hit_points_average >= 200

# Sorting by CR (proper numeric order)
GET /api/v1/monsters?sort=challenge_rating_numeric:asc
```

---

## Benefits

1. **Numeric Range Queries** - `>=`, `<=`, `>`, `<` operators work properly
2. **Proper Sorting** - CR 2, 3, 10, 20 (not "10", "2", "20", "3" lexicographic)
3. **Combined Filters** - Easy to combine with speed, abilities, etc.
4. **API Consistency** - Matches user expectations for numeric fields
5. **Keep String CR** - Maintains exact string matching for fractional CRs

---

## Backwards Compatibility

✅ **Fully backwards compatible** - keeping string `challenge_rating` column:
- Existing queries with string CR continue to work
- API Resources expose both fields
- Meilisearch indexes both fields

---

## Testing Checklist

- [ ] Migration runs successfully
- [ ] All existing CRs converted correctly (check fractions)
- [ ] MonsterImporter populates both columns
- [ ] Meilisearch index includes new attribute
- [ ] Numeric range queries work (`>=`, `<=`)
- [ ] Sorting by CR numeric works correctly
- [ ] API Resources expose both fields
- [ ] All existing tests still pass
- [ ] New CR numeric tests pass

---

## Files to Modify

1. ✅ `database/migrations/YYYY_MM_DD_add_challenge_rating_numeric_to_monsters.php` (NEW)
2. ✅ `app/Models/Monster.php` (fillable, casts, toSearchableArray, searchableOptions)
3. ✅ `app/Services/Search/MeilisearchIndexConfigurator.php` (configureMonstersIndex)
4. ✅ `app/Services/Importers/MonsterImporter.php` (conversion logic)
5. ✅ `app/Http/Resources/MonsterResource.php` (expose new field)
6. ✅ `app/Http/Controllers/Api/MonsterController.php` (update docblock)
7. ✅ `tests/Feature/Api/MonsterSearchTest.php` (new tests)

---

## Estimated Timeline

| Task | Time |
|------|------|
| Create migration | 15 min |
| Update models | 15 min |
| Update importer | 30 min |
| Update configurator | 5 min |
| Update resources | 5 min |
| Re-import & reindex | 30 min |
| Write tests | 30 min |
| Update docs | 15 min |
| Testing & verification | 30 min |
| **Total** | **~2.5 hours** |

---

**Created:** 2025-11-24
**Related to:** Phase 1 Meilisearch improvements - enables proper numeric CR filtering

🤖 Generated with [Claude Code](https://claude.com/claude-code)

# Implementation Plan: Enhanced Meilisearch Filtering

**Date:** 2025-11-21
**Goal:** Add native Meilisearch filter expressions to API endpoints for advanced filtering capabilities
**Scope:** Phase 1 - Spells only (proof of concept)
**Estimated Time:** 3-4 hours with TDD

---

## Overview

Expose Meilisearch's native filter syntax directly through API endpoints, enabling advanced filtering like range queries, logical operators, and array matching without adding new dependencies.

**Key Benefits:**
- ✅ No new dependencies (use existing Meilisearch)
- ✅ Lightning fast (< 100ms with complex filters)
- ✅ Combine search + filtering in one request
- ✅ Auto-documented via Scramble

---

## 1. Scaffolding & Preparation

### 1.1 Confirm Environment
**Runner:** Sail (Docker containers running)

```bash
# Verify Meilisearch is running
docker compose ps meilisearch

# Verify indexes exist
curl -s "http://localhost:7700/indexes" -H "Authorization: Bearer masterKey" | jq '.results[].uid'
# Should show: spells, items, races, classes, backgrounds, feats
```

### 1.2 Branch Strategy
```bash
# Create feature branch
git checkout -b feature/meilisearch-advanced-filters

# Verify clean starting point
docker compose exec php php artisan test
# Should show: 778 tests passing
```

**Decision:** Work directly on main branch (per project conventions) or use feature branch?

---

## 2. Data Model Updates

### 2.1 Update Meilisearch Index Settings

**Goal:** Add more attributes to `filterableAttributes` for flexible filtering

**File:** Create new artisan command for index configuration

```bash
# Create command
docker compose exec php php artisan make:command ConfigureMeilisearchIndexes
```

**Implementation:**
```php
// app/Console/Commands/ConfigureMeilisearchIndexes.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Meilisearch\Client;

class ConfigureMeilisearchIndexes extends Command
{
    protected $signature = 'search:configure-indexes';
    protected $description = 'Configure Meilisearch indexes with optimal settings';

    public function handle(Client $client): int
    {
        $this->info('Configuring Meilisearch indexes...');

        // Spells Index
        $this->configureSpellsIndex($client);

        $this->info('✅ All indexes configured successfully');
        return Command::SUCCESS;
    }

    private function configureSpellsIndex(Client $client): void
    {
        $index = $client->index('spells');

        // Update filterable attributes
        $index->updateFilterableAttributes([
            'id',
            'level',
            'school_code',
            'school_name',
            'concentration',
            'ritual',
            'class_slugs',
            'source_codes',
        ]);

        // Update sortable attributes
        $index->updateSortableAttributes([
            'level',
            'name',
        ]);

        $this->info('  ✓ Spells index configured');
    }
}
```

**Test:**
```bash
# Run command
docker compose exec php php artisan search:configure-indexes

# Verify settings
curl -s "http://localhost:7700/indexes/spells/settings" \
  -H "Authorization: Bearer masterKey" | jq '.filterableAttributes'
```

**Commit:** `feat: add command to configure Meilisearch index settings`

---

## 3. Services & DTOs

### 3.1 Update SpellSearchDTO

**Goal:** Add `meilisearchFilter` property

**File:** `app/DTOs/SpellSearchDTO.php`

```php
// Add property
public readonly ?string $meilisearchFilter;

// Update constructor
public function __construct(
    public readonly ?string $searchQuery = null,
    public readonly ?string $meilisearchFilter = null, // NEW
    public readonly int $page = 1,
    public readonly int $perPage = 15,
    public readonly string $sortBy = 'name',
    public readonly string $sortDirection = 'asc',
    public readonly array $filters = [],
) {}

// Update fromRequest
public static function fromRequest($request): self
{
    return new self(
        searchQuery: $request->validated('q'),
        meilisearchFilter: $request->validated('filter'), // NEW
        page: (int) $request->validated('page', 1),
        perPage: (int) $request->validated('per_page', 15),
        sortBy: $request->validated('sort_by', 'name'),
        sortDirection: $request->validated('sort_direction', 'asc'),
        filters: $request->only(['level', 'school', 'concentration', 'ritual']),
    );
}
```

**Commit:** `feat: add meilisearchFilter to SpellSearchDTO`

### 3.2 Update SpellSearchService

**Goal:** Add method to query Meilisearch with custom filters

**File:** `app/Services/SpellSearchService.php`

```php
use Meilisearch\Client;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Search using Meilisearch with custom filter expressions
 */
public function searchWithMeilisearch(SpellSearchDTO $dto, Client $client): LengthAwarePaginator
{
    $searchParams = [
        'limit' => $dto->perPage,
        'offset' => ($dto->page - 1) * $dto->perPage,
    ];

    // Add filter if provided
    if ($dto->meilisearchFilter) {
        $searchParams['filter'] = $dto->meilisearchFilter;
    }

    // Add sort if needed
    if ($dto->sortBy && $dto->sortDirection) {
        $searchParams['sort'] = ["{$dto->sortBy}:{$dto->sortDirection}"];
    }

    // Execute search
    $results = $client->index('spells')->search($dto->searchQuery ?? '', $searchParams);

    // Hydrate Eloquent models to use with API Resources
    $spellIds = collect($results['hits'])->pluck('id');

    if ($spellIds->isEmpty()) {
        return new LengthAwarePaginator([], 0, $dto->perPage, $dto->page);
    }

    $spells = Spell::with(['spellSchool', 'sources.source', 'effects.damageType', 'classes'])
        ->findMany($spellIds);

    // Preserve Meilisearch result order
    $orderedSpells = $spellIds->map(function ($id) use ($spells) {
        return $spells->firstWhere('id', $id);
    })->filter();

    return new LengthAwarePaginator(
        $orderedSpells,
        $results['estimatedTotalHits'] ?? 0,
        $dto->perPage,
        $dto->page,
        ['path' => request()->url(), 'query' => request()->query()]
    );
}
```

**Commit:** `feat: add searchWithMeilisearch method to SpellSearchService`

---

## 4. Controllers & Requests

### 4.1 Update SpellIndexRequest

**Goal:** Add validation for `filter` parameter

**File:** `app/Http/Requests/SpellIndexRequest.php`

```php
protected function entityRules(): array
{
    return [
        // Search query (Scout/Meilisearch)
        'q' => ['sometimes', 'string', 'min:2', 'max:255'],

        // NEW: Meilisearch filter expression
        'filter' => ['sometimes', 'string', 'max:1000'],

        // Existing filters (for backwards compatibility)
        'level' => ['sometimes', 'integer', 'min:0', 'max:9'],
        'school' => ['sometimes', 'integer', 'exists:spell_schools,id'],
        'concentration' => ['sometimes', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
        'ritual' => ['sometimes', Rule::in([true, false, 1, 0, '1', '0', 'true', 'false'])],
    ];
}
```

**Commit:** `feat: add filter parameter validation to SpellIndexRequest`

### 4.2 Update SpellController

**Goal:** Use Meilisearch when filter parameter provided

**File:** `app/Http/Controllers/Api/SpellController.php`

```php
use Meilisearch\Client;

public function index(SpellIndexRequest $request, SpellSearchService $service, Client $meilisearch)
{
    $dto = SpellSearchDTO::fromRequest($request);

    // Use Meilisearch if search query OR filter provided
    if ($dto->searchQuery !== null || $dto->meilisearchFilter !== null) {
        try {
            $spells = $service->searchWithMeilisearch($dto, $meilisearch);
        } catch (\Meilisearch\Exceptions\ApiException $e) {
            // Invalid filter syntax
            return response()->json([
                'message' => 'Invalid filter syntax',
                'error' => $e->getMessage(),
            ], 422);
        }

        return SpellResource::collection($spells);
    }

    // Fallback to database query (existing logic)
    $spells = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);

    return SpellResource::collection($spells);
}
```

**Commit:** `feat: integrate Meilisearch filtering in SpellController`

---

## 5. Tests (TDD Approach)

### 5.1 Feature Tests for Filter Parameter

**File:** `tests/Feature/Api/SpellMeilisearchFilterTest.php`

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellMeilisearchFilterTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    #[Test]
    public function it_filters_spells_by_level_range()
    {
        // Import test spells with various levels
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml']);

        $response = $this->getJson('/api/v1/spells?filter=level >= 1 AND level <= 3');

        $response->assertOk();
        $response->assertJsonStructure(['data', 'links', 'meta']);

        foreach ($response->json('data') as $spell) {
            $this->assertGreaterThanOrEqual(1, $spell['level']);
            $this->assertLessThanOrEqual(3, $spell['level']);
        }
    }

    #[Test]
    public function it_filters_by_school_code()
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml']);

        $response = $this->getJson('/api/v1/spells?filter=school_code = EV');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertEquals('Evocation', $spell['spell_school']['name']);
        }
    }

    #[Test]
    public function it_combines_multiple_filters_with_and()
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml']);

        $response = $this->getJson('/api/v1/spells?filter=level <= 2 AND concentration = false');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertLessThanOrEqual(2, $spell['level']);
            $this->assertFalse($spell['needs_concentration']);
        }
    }

    #[Test]
    public function it_combines_multiple_filters_with_or()
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml']);

        $response = $this->getJson('/api/v1/spells?filter=school_code = EV OR school_code = C');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertContains($spell['spell_school']['name'], ['Evocation', 'Conjuration']);
        }
    }

    #[Test]
    public function it_filters_by_range_using_to_operator()
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml']);

        $response = $this->getJson('/api/v1/spells?filter=level 1 TO 3');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertGreaterThanOrEqual(1, $spell['level']);
            $this->assertLessThanOrEqual(3, $spell['level']);
        }
    }

    #[Test]
    public function it_combines_search_query_with_filter()
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml']);

        $response = $this->getJson('/api/v1/spells?q=fire&filter=level <= 3');

        $response->assertOk();

        foreach ($response->json('data') as $spell) {
            $this->assertLessThanOrEqual(3, $spell['level']);
            // Name or description should contain "fire"
            $nameOrDesc = strtolower($spell['name'] . ' ' . $spell['description']);
            $this->assertStringContainsString('fire', $nameOrDesc);
        }
    }

    #[Test]
    public function it_validates_filter_max_length()
    {
        $longFilter = str_repeat('level = 1 AND ', 100);

        $response = $this->getJson("/api/v1/spells?filter={$longFilter}");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('filter');
    }

    #[Test]
    public function it_returns_error_for_invalid_filter_syntax()
    {
        $response = $this->getJson('/api/v1/spells?filter=invalid syntax here @@##');

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'error']);
    }

    #[Test]
    public function it_paginates_filtered_results()
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml']);

        $response = $this->getJson('/api/v1/spells?filter=level <= 5&per_page=5&page=1');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
        $response->assertJsonStructure([
            'meta' => ['current_page', 'total', 'per_page'],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
    }

    #[Test]
    public function it_returns_empty_results_for_impossible_filter()
    {
        $this->artisan('import:spells', ['file' => 'import-files/spells-phb.xml']);

        $response = $this->getJson('/api/v1/spells?filter=level = 99');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
        $response->assertJsonPath('meta.total', 0);
    }
}
```

**Run tests (should FAIL initially):**
```bash
docker compose exec php php artisan test --filter=SpellMeilisearchFilterTest
```

**Commit:** `test: add feature tests for Meilisearch filter parameter`

---

## 6. Quality Gates & Verification

### 6.1 Run Full Test Suite
```bash
# All tests should pass
docker compose exec php php artisan test

# Should show: 788+ tests passing (10 new tests added)
```

### 6.2 Format Code
```bash
docker compose exec php ./vendor/bin/pint
```

### 6.3 Verify Scramble Documentation
```bash
# Generate OpenAPI spec
docker compose exec php php artisan scramble:export

# Check that filter parameter is documented
cat api.json | jq '.paths["/api/v1/spells"].get.parameters[] | select(.name == "filter")'

# Should show:
# {
#   "name": "filter",
#   "in": "query",
#   "required": false,
#   "schema": { "type": "string", "maxLength": 1000 }
# }
```

### 6.4 Manual Testing
```bash
# Test range filter
curl "http://localhost:8080/api/v1/spells?filter=level%201%20TO%203" | jq '.data[].level'

# Test logical operators
curl "http://localhost:8080/api/v1/spells?filter=school_code%20=%20EV%20AND%20level%20<=%202" | jq '.data[] | {name, level, school: .spell_school.name}'

# Test combined search + filter
curl "http://localhost:8080/api/v1/spells?q=fire&filter=level%20<=%203" | jq '.data[] | {name, level}'
```

**Commit:** `docs: update OpenAPI spec with filter parameter`

---

## 7. Rollout Strategy

### Phase 1: Spells Only ✅ (This Plan)
- Prove the concept
- Gather feedback
- Measure performance
- Validate Scramble integration

### Phase 2: Extend to Other Entities (Future)
**Only if Phase 1 succeeds:**

1. Items - Add filterable attributes: `rarity`, `requires_attunement`, `is_magic`
2. Races - Add filterable attributes: `size_id`, `speed`, `parent_race_id`
3. Classes - Add filterable attributes: `hit_die`, `spellcasting_ability_id`
4. Backgrounds - Add filterable attributes: `source_codes`
5. Feats - Add filterable attributes: `source_codes`

**Extract shared logic into trait if duplication emerges**

### Phase 3: Advanced Features (Optional)
- Faceting for aggregations (`GET /api/v1/spells?facets=school_name,level`)
- Filter builder helper (convert friendly params to Meilisearch syntax)
- Cached filter results for common queries

---

## 8. Observability & Monitoring

### 8.1 Add Logging
```php
// In SpellSearchService::searchWithMeilisearch()
use Illuminate\Support\Facades\Log;

$start = microtime(true);
$results = $client->index('spells')->search(...);
$duration = (microtime(true) - $start) * 1000;

Log::info('Meilisearch query executed', [
    'index' => 'spells',
    'query' => $dto->searchQuery,
    'filter' => $dto->meilisearchFilter,
    'duration_ms' => round($duration, 2),
    'total_hits' => $results['estimatedTotalHits'] ?? 0,
]);
```

### 8.2 Performance Benchmarks
```bash
# Run performance test
docker compose exec php php artisan tinker --execute="
\$start = microtime(true);
\$client = app(\Meilisearch\Client::class);
\$results = \$client->index('spells')->search('', [
    'filter' => 'level >= 1 AND level <= 3 AND school_code = EV',
    'limit' => 20
]);
\$duration = (microtime(true) - \$start) * 1000;
echo 'Duration: ' . round(\$duration, 2) . 'ms' . PHP_EOL;
echo 'Hits: ' . \$results['estimatedTotalHits'] . PHP_EOL;
"

# Target: < 50ms for complex filters
```

---

## 9. Documentation Updates

### 9.1 Update CLAUDE.md
Add section about Meilisearch filtering:

```markdown
## Advanced Filtering with Meilisearch

The API supports advanced filtering using Meilisearch's native filter syntax.

**Examples:**
- Range queries: `?filter=level >= 1 AND level <= 3`
- Logical operators: `?filter=school_code = EV OR school_code = C`
- Combined with search: `?q=fire&filter=level <= 3`

**Supported operators:**
- Comparison: `=`, `!=`, `>`, `<`, `>=`, `<=`, `TO` (range)
- Logical: `AND`, `OR`, `NOT`
- Arrays: `IN`, `NOT IN`

**Filterable attributes per entity:**
- Spells: `level`, `school_code`, `school_name`, `concentration`, `ritual`, `class_slugs`, `source_codes`
```

### 9.2 Create Filter Examples Document
**File:** `docs/MEILISEARCH-FILTERS.md`

```markdown
# Meilisearch Filter Examples

Comprehensive guide to filtering D&D compendium data.

[Include practical examples for common queries]
```

**Commit:** `docs: add Meilisearch filtering documentation`

---

## 10. Final Checklist

Before marking complete:

- [ ] Command `search:configure-indexes` created and tested
- [ ] SpellSearchDTO updated with `meilisearchFilter` property
- [ ] SpellSearchService has `searchWithMeilisearch()` method
- [ ] SpellIndexRequest validates `filter` parameter
- [ ] SpellController integrates Meilisearch filtering
- [ ] 10 feature tests added and passing
- [ ] Full test suite passes (788+ tests)
- [ ] Code formatted with Pint
- [ ] Scramble documents `filter` parameter in OpenAPI spec
- [ ] Manual testing confirms filters work
- [ ] Performance benchmarks meet targets (< 100ms)
- [ ] Documentation updated (CLAUDE.md + MEILISEARCH-FILTERS.md)
- [ ] Git commits are clean and descriptive

---

## Success Criteria

✅ **Functionality:**
- Users can filter spells using Meilisearch syntax
- Range queries work (`level 1 TO 3`, `level >= 1 AND level <= 3`)
- Logical operators work (`AND`, `OR`, `NOT`)
- Search + filter combination works

✅ **Performance:**
- Simple filters: < 50ms
- Complex filters: < 100ms p95
- No N+1 queries in result hydration

✅ **Quality:**
- 788+ tests passing (10 new)
- Pint formatting clean
- Scramble auto-documents filter parameter
- No regressions

✅ **Documentation:**
- Filter syntax documented
- Examples provided
- Scramble OpenAPI spec complete

---

**Estimated Total Time:** 3-4 hours with TDD
**Dependencies:** None (uses existing Meilisearch setup)
**Risk Level:** Low (additive feature, no breaking changes)

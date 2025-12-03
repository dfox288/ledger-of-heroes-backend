# Custom Exceptions Analysis & Recommendations

**Date:** 2025-11-21
**Status:** 📋 Recommendation Document
**Priority:** Medium (Quality of Life Improvement)

---

## Executive Summary

Currently, the codebase uses **generic exception handling** with `\InvalidArgumentException`, `\Exception`, and `abort()` calls. While functional, introducing **custom exceptions** would provide:

1. **Better error messages** - Domain-specific error context
2. **Consistent API responses** - Standardized error formats
3. **Easier debugging** - Clear exception types in logs
4. **Type safety** - Catch specific exceptions, not generic ones
5. **Automatic HTTP status codes** - Laravel exception handler integration

---

## Current Exception Usage Analysis

### 1. Controllers (API Layer)

**Current Pattern:**
```php
// SpellController.php:33-38
catch (\MeiliSearch\Exceptions\ApiException $e) {
    abort(response()->json([
        'message' => 'Invalid filter syntax',
        'error' => $e->getMessage(),
    ], 422));
}
```

**Issues:**
- ❌ Manual error response construction
- ❌ Inconsistent error formats across controllers
- ❌ Breaks Scramble type inference (fixed, but fragile)
- ❌ Mixes infrastructure exceptions (Meilisearch) with domain logic

---

### 2. Importers (Business Logic Layer)

**Current Pattern:**
```php
// BaseImporter.php:88
if (! file_exists($filePath)) {
    throw new \InvalidArgumentException("File not found: {$filePath}");
}

// RaceImporter.php:175
if (! file_exists($filePath)) {
    throw new \InvalidArgumentException("File not found: {$filePath}");
}
```

**Issues:**
- ❌ Generic exception type doesn't convey intent
- ❌ Duplicated validation logic
- ❌ No distinction between "file not found" vs "invalid XML" vs "missing required field"
- ❌ Console commands catch generic `\Exception`, can't handle specific failures

---

### 3. Parsers (Parsing Layer)

**Current Pattern:**
```php
// Multiple parsers: MatchesLanguages.php:24, MatchesProficiencyTypes.php:22, etc.
} catch (\Exception $e) {
    // Graceful fallback for unit tests without database
    $this->languagesCache = collect();
}
```

**Issues:**
- ❌ Catches **all exceptions** (including logic errors, syntax errors, etc.)
- ❌ Silent failures - database connection issues masked as "unit test mode"
- ❌ No distinction between "database unavailable" vs "corrupt data"

---

### 4. Lookup/Matching Services

**Current Pattern:**
```php
// CachesLookupTables.php:44
$result = $useFail ? $query->firstOrFail() : $query->first();

// Throws: \Illuminate\Database\Eloquent\ModelNotFoundException
```

**Issues:**
- ✅ **Actually good!** - Laravel's `ModelNotFoundException` is a custom exception
- ❌ But our code doesn't catch/transform it for API responses
- ❌ Results in generic 500 errors instead of meaningful 404s

---

### 5. Search Services

**Current Pattern:**
```php
// GlobalSearchService.php:59-65
} catch (\Exception $e) {
    Log::warning("Global search failed for {$type}", [
        'error' => $e->getMessage(),
        'type' => $type,
    ]);
}
```

**Issues:**
- ❌ Catches **all exceptions** - too broad
- ✅ **Good:** Logs and continues with other types
- ❌ Should catch specific exceptions (connection, timeout, syntax)

---

## Recommended Custom Exceptions

### Architecture Overview

```
app/Exceptions/
├── ApiException.php                    # Base for all API exceptions
├── Import/
│   ├── ImportException.php             # Base for import failures
│   ├── FileNotFoundException.php       # XML file not found
│   ├── InvalidXmlException.php         # Malformed XML
│   ├── MissingRequiredFieldException.php
│   └── DuplicateEntityException.php    # Unique constraint violations
├── Search/
│   ├── SearchException.php             # Base for search failures
│   ├── InvalidFilterSyntaxException.php # Meilisearch filter errors
│   └── SearchUnavailableException.php  # Meilisearch down
├── Lookup/
│   ├── LookupException.php             # Base for lookup failures
│   ├── EntityNotFoundException.php     # Generic "not found"
│   └── InvalidReferenceException.php   # FK constraint violations
└── Validation/
    ├── ValidationException.php          # Already exists in Laravel
    └── SchemaViolationException.php     # Data doesn't match expected structure
```

---

## Implementation Examples

### 1. Meilisearch Filter Exception

**Problem:** Currently catching `\MeiliSearch\Exceptions\ApiException`

**Solution:**
```php
// app/Exceptions/Search/InvalidFilterSyntaxException.php
namespace App\Exceptions\Search;

use App\Exceptions\ApiException;
use Throwable;

class InvalidFilterSyntaxException extends ApiException
{
    public function __construct(
        string $filter,
        string $meilisearchMessage,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            message: "Invalid filter syntax: {$meilisearchMessage}",
            code: 422,
            previous: $previous
        );

        $this->filter = $filter;
        $this->meilisearchMessage = $meilisearchMessage;
    }

    public function render($request)
    {
        return response()->json([
            'message' => 'Invalid filter syntax',
            'error' => $this->meilisearchMessage,
            'filter' => $this->filter,
            'documentation' => url('/docs/meilisearch-filters'),
        ], 422);
    }
}
```

**Usage:**
```php
// SpellController.php
public function index(SpellIndexRequest $request, SpellSearchService $service, Client $meilisearch)
{
    $dto = SpellSearchDTO::fromRequest($request);

    if ($dto->meilisearchFilter !== null) {
        $spells = $service->searchWithMeilisearch($dto, $meilisearch);
        // Exception thrown from service, caught by Laravel exception handler
    } elseif ($dto->searchQuery !== null) {
        $spells = $service->buildScoutQuery($dto)->paginate($dto->perPage);
    } else {
        $spells = $service->buildDatabaseQuery($dto)->paginate($dto->perPage);
    }

    return SpellResource::collection($spells);
}

// SpellSearchService.php
public function searchWithMeilisearch(SpellSearchDTO $dto, Client $client): LengthAwarePaginator
{
    try {
        $results = $client->index('spells')->search($dto->searchQuery ?? '', $searchParams);
    } catch (\MeiliSearch\Exceptions\ApiException $e) {
        throw new InvalidFilterSyntaxException(
            filter: $dto->meilisearchFilter,
            meilisearchMessage: $e->getMessage(),
            previous: $e
        );
    }

    // ... rest of method
}
```

**Benefits:**
- ✅ Clean controller (no catch block, single return statement)
- ✅ Preserves Scramble type inference
- ✅ Custom error response with documentation link
- ✅ Wraps infrastructure exception in domain exception

---

### 2. Import File Exception

**Problem:** Duplicate file validation with generic exception

**Solution:**
```php
// app/Exceptions/Import/FileNotFoundException.php
namespace App\Exceptions\Import;

class FileNotFoundException extends ImportException
{
    public function __construct(string $filePath)
    {
        parent::__construct(
            message: "Import file not found: {$filePath}",
            code: 404
        );

        $this->filePath = $filePath;
    }

    public function render($request)
    {
        // Console output
        if ($request instanceof \Illuminate\Console\Command) {
            return; // Let console handle it
        }

        // API response
        return response()->json([
            'message' => 'Import file not found',
            'file_path' => $this->filePath,
        ], 404);
    }
}
```

**Usage:**
```php
// BaseImporter.php (remove duplicate validation in subclasses)
protected function validateFile(string $filePath): void
{
    if (! file_exists($filePath)) {
        throw new FileNotFoundException($filePath);
    }

    if (! is_readable($filePath)) {
        throw new ImportException("File is not readable: {$filePath}", 403);
    }
}

public function importFromFile(string $filePath): void
{
    $this->validateFile($filePath);
    // ... rest of import logic
}
```

**Benefits:**
- ✅ Remove duplicate validation from all 6 importers
- ✅ Specific exception type for file issues
- ✅ Consistent error handling across all importers
- ✅ Single place to add file validation logic

---

### 3. Entity Not Found Exception

**Problem:** Laravel's `ModelNotFoundException` returns 500, not 404

**Solution:**
```php
// app/Exceptions/Lookup/EntityNotFoundException.php
namespace App\Exceptions\Lookup;

class EntityNotFoundException extends LookupException
{
    public function __construct(
        string $entityType,
        string $identifier,
        string $column = 'id'
    ) {
        parent::__construct(
            message: "{$entityType} not found with {$column}: {$identifier}",
            code: 404
        );

        $this->entityType = $entityType;
        $this->identifier = $identifier;
        $this->column = $column;
    }

    public function render($request)
    {
        return response()->json([
            'message' => "{$this->entityType} not found",
            'identifier' => $this->identifier,
            'search_column' => $this->column,
        ], 404);
    }
}
```

**Usage:**
```php
// CachesLookupTables.php
protected function cachedFind(string $model, string $column, mixed $value, bool $useFail = true): ?Model
{
    $normalizedValue = strtoupper((string) $value);

    if (! isset($this->lookupCache[$model][$column][$normalizedValue])) {
        try {
            $query = $model::where($column, $normalizedValue);
            $result = $useFail ? $query->firstOrFail() : $query->first();
            $this->lookupCache[$model][$column][$normalizedValue] = $result;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $entityType = class_basename($model);
            throw new EntityNotFoundException($entityType, $normalizedValue, $column);
        }
    }

    return $this->lookupCache[$model][$column][$normalizedValue];
}
```

**Benefits:**
- ✅ Proper 404 status code instead of 500
- ✅ Consistent error format
- ✅ Includes context (entity type, identifier, column)
- ✅ Easier debugging

---

### 4. Parser Exception for Database Issues

**Problem:** Catching generic `\Exception` masks real errors

**Solution:**
```php
// app/Exceptions/Parsing/DatabaseUnavailableException.php
namespace App\Exceptions\Parsing;

class DatabaseUnavailableException extends \RuntimeException
{
    public function __construct(string $context)
    {
        parent::__construct("Database unavailable during parsing: {$context}");
        $this->context = $context;
    }
}
```

**Usage:**
```php
// MatchesLanguages.php
protected function loadLanguagesCache(): Collection
{
    if ($this->languagesCache === null) {
        try {
            $this->languagesCache = Language::all()
                ->keyBy(fn ($language) => $language->slug);
        } catch (\Illuminate\Database\QueryException $e) {
            // Only catch DB-specific exceptions, not all exceptions
            throw new DatabaseUnavailableException('loading languages cache');
        }
    }

    return $this->languagesCache;
}

// Or for unit test compatibility:
protected function loadLanguagesCacheWithFallback(): Collection
{
    try {
        return $this->loadLanguagesCache();
    } catch (DatabaseUnavailableException $e) {
        // Graceful fallback for unit tests
        return collect();
    }
}
```

**Benefits:**
- ✅ Only catches database errors, not logic/syntax errors
- ✅ Explicit about when fallback is appropriate
- ✅ Clearer unit test behavior

---

## Priority Recommendations

### High Priority (Immediate Value)

1. **InvalidFilterSyntaxException**
   - **Where:** SpellController, future controllers with Meilisearch filtering
   - **Impact:** Cleaner controllers, better error messages, preserves Scramble inference
   - **Effort:** 30 minutes

2. **FileNotFoundException**
   - **Where:** All 6 importers
   - **Impact:** Remove duplicate validation, consistent error handling
   - **Effort:** 1 hour

3. **EntityNotFoundException**
   - **Where:** CachesLookupTables trait
   - **Impact:** Proper 404 responses, better debugging
   - **Effort:** 45 minutes

### Medium Priority (Nice to Have)

4. **InvalidXmlException**
   - **Where:** All parsers
   - **Impact:** Distinguish "file not found" from "corrupt XML" from "missing fields"
   - **Effort:** 2 hours

5. **SearchUnavailableException**
   - **Where:** GlobalSearchService, all search services
   - **Impact:** Graceful degradation when Meilisearch is down
   - **Effort:** 1 hour

### Low Priority (Future Enhancement)

6. **DuplicateEntityException**
   - **Where:** Importers (reimport detection)
   - **Impact:** Better handling of reimports, unique constraint violations
   - **Effort:** 1.5 hours

7. **SchemaViolationException**
   - **Where:** Parsers, importers
   - **Impact:** Validate XML structure matches expectations
   - **Effort:** 2-3 hours

---

## Implementation Strategy

### Phase 1: Foundation (1-2 hours)

1. Create base exception classes:
   ```
   app/Exceptions/
   ├── ApiException.php              # Base for API-facing exceptions
   ├── DomainException.php           # Base for business logic exceptions
   └── Handler.php (update)          # Laravel exception handler
   ```

2. Update Laravel's exception handler to render custom exceptions:
   ```php
   // app/Exceptions/Handler.php
   public function register(): void
   {
       $this->renderable(function (ApiException $e, Request $request) {
           return $e->render($request);
       });
   }
   ```

### Phase 2: High-Priority Exceptions (2-3 hours)

1. Implement `InvalidFilterSyntaxException`
2. Implement `FileNotFoundException`
3. Implement `EntityNotFoundException`
4. Write tests for each exception
5. Update affected code

### Phase 3: Medium-Priority Exceptions (3-4 hours)

1. Implement remaining search/import exceptions
2. Update all importers and search services
3. Write comprehensive tests
4. Update documentation

### Phase 4: Testing & Documentation (1-2 hours)

1. Add exception handling tests
2. Update `CLAUDE.md` with exception usage guidelines
3. Document custom exceptions in API docs
4. Add examples to `docs/EXCEPTIONS.md`

---

## Testing Strategy

### Exception Tests

```php
// tests/Unit/Exceptions/InvalidFilterSyntaxExceptionTest.php
class InvalidFilterSyntaxExceptionTest extends TestCase
{
    #[Test]
    public function it_renders_proper_json_response()
    {
        $exception = new InvalidFilterSyntaxException(
            filter: 'invalid_field = value',
            meilisearchMessage: 'Attribute `invalid_field` is not filterable'
        );

        $request = Request::create('/api/v1/spells', 'GET');
        $response = $exception->render($request);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertJsonStructure([
            'message',
            'error',
            'filter',
            'documentation',
        ], $response->getData(true));
    }
}
```

### Integration Tests

```php
// tests/Feature/Api/SpellMeilisearchFilterExceptionTest.php
class SpellMeilisearchFilterExceptionTest extends TestCase
{
    #[Test]
    public function it_returns_proper_error_for_invalid_filter()
    {
        $response = $this->getJson('/api/v1/spells?filter=invalid_field = value');

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'message',
            'error',
            'filter',
            'documentation',
        ]);
        $response->assertJson([
            'message' => 'Invalid filter syntax',
            'filter' => 'invalid_field = value',
        ]);
    }
}
```

---

## Backwards Compatibility

### No Breaking Changes

- ✅ Existing exception handling still works
- ✅ New exceptions are additions, not replacements
- ✅ Gradual migration - can implement one at a time
- ✅ Tests continue to pass during transition

### Migration Path

1. **Add new exceptions** alongside existing code
2. **Update one controller/service at a time**
3. **Run tests after each change**
4. **Update documentation incrementally**
5. **Remove old exception handling gradually**

---

## Alternative Approaches Considered

### 1. Keep Current Approach
**Pros:** No work required
**Cons:** Poor error messages, inconsistent handling, harder debugging
**Verdict:** ❌ Not recommended - quality issues accumulate

### 2. Use Only Laravel's Built-in Exceptions
**Pros:** No custom code
**Cons:** Limited context, generic messages, can't customize responses
**Verdict:** ❌ Not recommended - insufficient for complex domain

### 3. Exception Facades/Helpers
**Pros:** Simpler syntax (`Exceptions::notFound()`)
**Cons:** Less type-safe, harder to test, non-standard Laravel pattern
**Verdict:** ❌ Not recommended - breaks Laravel conventions

### 4. Custom Exceptions (Recommended)
**Pros:** Type-safe, testable, consistent, follows Laravel conventions
**Cons:** Initial setup time (6-8 hours total)
**Verdict:** ✅ **Recommended** - Best long-term solution

---

## Cost-Benefit Analysis

### Costs
- **Development Time:** 6-8 hours total (can be done incrementally)
- **Testing Time:** 2-3 hours for comprehensive tests
- **Documentation:** 1 hour to document patterns

**Total:** 9-12 hours

### Benefits
- **Debugging Time Saved:** 15-20 minutes per bug (estimate 10 bugs/year = 2.5-3 hours saved)
- **Code Quality:** Clearer intent, easier maintenance
- **API Quality:** Better error messages for consumers
- **Developer Experience:** Faster issue resolution
- **Type Safety:** Catch specific exceptions, not generic ones

**ROI:** Positive after ~3-4 bugs debugged (3-4 months)

---

## Recommendation

### ✅ **Implement Custom Exceptions Incrementally**

**Start with Phase 1 (High Priority):**
1. `InvalidFilterSyntaxException` - Immediate benefit for Spells endpoint
2. `FileNotFoundException` - Removes duplication across 6 importers
3. `EntityNotFoundException` - Fixes 500 → 404 conversion

**Time investment:** 3-4 hours
**Immediate value:** Cleaner code, better error messages, proper HTTP status codes

**Then proceed to Phases 2-4 as time permits.**

---

## Next Steps

1. **Review this document** with the team
2. **Approve Phase 1 exceptions** (high priority)
3. **Create base exception classes** (foundation)
4. **Implement Phase 1** incrementally (one exception at a time)
5. **Write tests** for each exception
6. **Document patterns** in `CLAUDE.md`
7. **Schedule Phases 2-4** based on priority and available time

---

**Status:** 📋 Ready for Review
**Last Updated:** 2025-11-21

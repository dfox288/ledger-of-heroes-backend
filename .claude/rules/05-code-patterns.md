# Code Patterns

## Gold Standards

**Use these as reference implementations:**

| Pattern | Reference |
|---------|-----------|
| Controller + PHPDoc | `SpellController` |
| API Resource | `SpellResource` |
| Form Request | `SpellIndexRequest`, `SpellShowRequest` |
| Search Service | `SpellSearchService` |
| Model searchable | `Spell::searchableOptions()` |
| Importer | `SpellImporter` |
| Parser | `SpellXmlParser` |

## Controller Pattern

```php
// Form Request - every action has dedicated Request
public function index(SpellIndexRequest $request) { }

// Service Layer - controllers delegate to services
$results = $service->searchWithMeilisearch($dto);
return SpellResource::collection($results);
```

## Exception Handling

```php
// Custom Exceptions - service throws, controller catches or lets bubble
throw new InvalidFilterSyntaxException($filter, $message);  // 422
throw new EntityNotFoundException($type, $id);              // 404
```

## Form Request Naming

Convention: `{Entity}{Action}Request`

```php
SpellIndexRequest      // GET /api/v1/spells
SpellShowRequest       // GET /api/v1/spells/{id}
```

## Search Service Pattern

All entity search services should extend `AbstractSearchService`:

```php
final class SpellSearchService extends AbstractSearchService
{
    private const INDEX_RELATIONSHIPS = ['spellSchool', 'sources.source'];
    private const SHOW_RELATIONSHIPS = [...self::INDEX_RELATIONSHIPS, 'tags'];

    protected function getModelClass(): string { return Spell::class; }
    public function getIndexRelationships(): array { return self::INDEX_RELATIONSHIPS; }
    public function getShowRelationships(): array { return self::SHOW_RELATIONSHIPS; }
}
```

See `../wrapper/docs/backend/reference/SEARCH-SERVICE-ARCHITECTURE.md` for full documentation.

---

## Anti-Patterns (DO NOT USE)

### Fat Controllers
```php
// ❌ WRONG - Business logic in controller
public function index(Request $request) {
    $query = Spell::query();
    if ($request->level) {
        $query->where('level', $request->level);
    }
    // ... 50 more lines of query building
    return response()->json($query->paginate());
}

// ✅ CORRECT - Controller delegates to service
public function index(SpellIndexRequest $request) {
    $results = $this->service->search($request->toDto());
    return SpellResource::collection($results);
}
```

### Eloquent Filtering in Services
```php
// ❌ WRONG - Using Eloquent for user-facing search/filter
$spells = Spell::whereHas('classes', fn($q) => $q->where('slug', 'wizard'))
    ->where('level', '<=', 3)
    ->get();

// ✅ CORRECT - Use Meilisearch for all filtering
$results = Spell::search($query)
    ->filter('class_slugs IN [wizard] AND level <= 3')
    ->get();
```

### Missing Form Requests
```php
// ❌ WRONG - Using base Request
public function index(Request $request) { }

// ✅ CORRECT - Dedicated Form Request with validation
public function index(SpellIndexRequest $request) { }
```

### Raw Responses
```php
// ❌ WRONG - Returning raw data
return response()->json($spell);

// ✅ CORRECT - Using API Resource
return new SpellResource($spell);
return SpellResource::collection($spells);
```

### Business Logic in Resources
```php
// ❌ WRONG - Computation in Resource
'damage_average' => ($this->damage_min + $this->damage_max) / 2,

// ✅ CORRECT - Computation in Model or Service
'damage_average' => $this->damage_average, // accessor on model
```

# API Response Conventions

## Response Envelope

All API responses use Laravel's JSON Resource envelope:

### Single Resource
```json
{
  "data": {
    "id": 1,
    "slug": "phb:fireball",
    "name": "Fireball",
    "level": 3
  }
}
```

### Collection (Paginated)
```json
{
  "data": [
    { "id": 1, "slug": "phb:fireball", "name": "Fireball" },
    { "id": 2, "slug": "phb:magic-missile", "name": "Magic Missile" }
  ],
  "links": {
    "first": "http://localhost/api/v1/spells?page=1",
    "last": "http://localhost/api/v1/spells?page=10",
    "prev": null,
    "next": "http://localhost/api/v1/spells?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 24,
    "to": 24,
    "total": 240
  }
}
```

## Resource Patterns

### Required Fields First
```php
return [
    // Identity (always first)
    'id' => $this->id,
    'slug' => $this->slug,
    'name' => $this->name,

    // Core attributes
    'level' => $this->level,

    // Computed/derived
    'requires_concentration' => $this->needs_concentration,

    // Relationships (always last, use whenLoaded)
    'school' => new SpellSchoolResource($this->whenLoaded('spellSchool')),
    'classes' => ClassResource::collection($this->whenLoaded('classes')),
];
```

### Conditional Fields
```php
// Only include if relationship is loaded
'sources' => EntitySourceResource::collection($this->whenLoaded('sources')),

// Only include if value is not null
'area_of_effect' => $this->when(
    $this->area_of_effect !== null,
    fn () => new AreaOfEffectResource($this->area_of_effect)
),
```

## Error Responses

### Validation Error (422)
```json
{
  "message": "The filter field has an invalid format.",
  "errors": {
    "filter": ["The filter syntax is invalid: unclosed bracket"]
  }
}
```

### Not Found (404)
```json
{
  "message": "Spell not found: invalid-slug"
}
```

### Filter Syntax Error (422)
```json
{
  "message": "Invalid filter syntax",
  "errors": {
    "filter": ["Unknown filter field: invalid_field"]
  }
}
```

## Pagination Defaults

- Default per page: 24
- Max per page: 100
- Query param: `?per_page=50`

## Controller PHPDoc

Document filters in controller PHPDoc for OpenAPI generation:

```php
/**
 * @operationId spells.index
 * @tags Spells
 *
 * @queryParam q string Search query. Example: fireball
 * @queryParam filter string Meilisearch filter. Example: level <= 3 AND school = "Evocation"
 * @queryParam per_page integer Items per page (max 100). Example: 24
 * @queryParam page integer Page number. Example: 1
 */
public function index(SpellIndexRequest $request): AnonymousResourceCollection
```

## Scramble OpenAPI Schema Inference

Scramble generates OpenAPI schemas by analyzing Resource classes. Follow these patterns to ensure proper schema generation.

### Use Explicit Inline Casts for Non-String Types

Scramble infers types from inline casts in the resource array. For `int` and `bool` fields, use explicit PHP casts:

```php
// ✅ CORRECT - Explicit inline casts
class CharacterListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => (int) $this->total_level,      // Cast to int
            'is_complete' => (bool) $this->is_complete, // Cast to bool
        ];
    }
}

// ❌ WRONG - No cast, Scramble infers string
class CharacterListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'level' => $this->total_level,    // Scramble sees as string
            'is_complete' => $this->is_complete, // Scramble sees as string
        ];
    }
}
```

**Note:** Model `$casts` and accessor return types do NOT affect Scramble inference. Only inline casts in the Resource matter.

### Controller Return Types

Controllers should return Resource types directly, not `JsonResponse`:

```php
// ✅ CORRECT - Scramble infers schema from Resource
public function levelUp(Character $character): LevelUpResource
{
    $result = $this->service->levelUp($character);
    return new LevelUpResource($result);
}

// ❌ WRONG - Scramble can't infer schema from JsonResponse
public function levelUp(Character $character): JsonResponse
{
    return (new LevelUpResource($result))
        ->response()
        ->setStatusCode(200);
    // Results in: { "type": "object" }
}
```

Use `abort()` for errors instead of returning JsonResponse:

```php
// ✅ CORRECT
abort_if(! $class, 404, 'Class not found');
return new LevelUpResource($result);

// ❌ WRONG
if (! $class) {
    return response()->json(['message' => 'Class not found'], 404);
}
```

### Never Use `@response` Annotations with Inline Arrays

Scramble infers schemas from Resources. Hardcoded `@response` annotations override inference:

```php
// ❌ WRONG - Creates inline schema, breaks $ref
/**
 * @response array{data: array{id: int, name: string}}
 */
public function show(Spell $spell): SpellResource

// ✅ CORRECT - Let Scramble infer from Resource
/**
 * Get spell details.
 */
public function show(Spell $spell): SpellResource
```

### Paginated Collections Must Use Eloquent Paginator

Manual `LengthAwarePaginator` with cached collections breaks type inference:

```php
// ❌ WRONG - Scramble shows items as "string" type
$cached = $cache->getAll(); // Returns Collection
$paginator = new LengthAwarePaginator($cached->forPage(...), ...);
return SizeResource::collection($paginator);

// ✅ CORRECT - Scramble properly infers Resource type
return SizeResource::collection($query->paginate($perPage));
```

### Model-Backed Resources Should Use `@mixin`

For Resources that wrap Eloquent models, add `@mixin` for IDE support:

```php
use App\Models\ProficiencyType;

/**
 * @mixin ProficiencyType
 */
class ProficiencyTypeResource extends JsonResource
```

### Pass-Through Resources Need Full Type Definitions

Resources that pass through array data (not models) need complete `@return` types:

```php
// Resource wrapping service output (not a model)
class CharacterExportResource extends JsonResource
{
    /**
     * @return array{
     *     format_version: string,
     *     exported_at: string,
     *     character: array{
     *         public_id: string,
     *         name: string,
     *         // ... all fields explicitly defined
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource; // Pass-through
    }
}
```

### Known Limitations

**Dictionary/Map types with dynamic keys:**
When using `groupBy()` or manual array construction, Scramble shows `dictionary[string, array]` instead of `dictionary[string, ResourceType[]]`. The nested fields ARE documented, but the array type label is generic.

```php
// CharacterNotesGroupedResource groups by category
// Docs show: data: dictionary[string, array]
// Fields ARE documented, but not labeled as CharacterNoteResource[]
// This is a Scramble limitation - accept it.
```

### Summary Table

| Pattern | Result |
|---------|--------|
| `@return array<string, mixed>` | Generic dictionary, no fields |
| `@return array{field: type}` | Proper field definitions |
| Return `JsonResponse` | `{"type": "object"}` only |
| Return `Resource` directly | Proper `$ref` to schema |
| Manual `LengthAwarePaginator` | Items shown as strings |
| `$query->paginate()` | Proper Resource type inference |
| `@response` annotation | Overrides inference (avoid) |

## Testing Response Shape

```php
it('returns correct response structure', function () {
    $spell = Spell::factory()->create();

    $response = $this->getJson("/api/v1/spells/{$spell->slug}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'slug',
                'name',
                'level',
            ]
        ]);
});

it('returns paginated collection', function () {
    Spell::factory()->count(30)->create();

    $response = $this->getJson('/api/v1/spells');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [['id', 'slug', 'name']],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
});
```

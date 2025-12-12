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

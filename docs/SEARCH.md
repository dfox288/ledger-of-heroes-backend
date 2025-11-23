# Search System Documentation

## Overview

This D&D compendium uses Laravel Scout with Meilisearch for fast, typo-tolerant search across all entities.

## Technology Stack

- **Laravel Scout 10.x** - Laravel's official search abstraction
- **Meilisearch 1.10** - Fast, typo-tolerant search engine
- **Docker** - Meilisearch runs as Sail service

## Searchable Entities

| Entity | Index | Documents | Searchable Fields |
|--------|-------|-----------|-------------------|
| Spells | `spells` | 477 | name, description, school, sources, classes |
| Items | `items` | 2,107 | name, description, type, sources |
| Races | `races` | 115 | name, size, sources |
| Classes | `classes` | 131 | name, description, sources |
| Backgrounds | `backgrounds` | 34 | name, sources |
| Feats | `feats` | 138 | name, description, prerequisites, sources |

**Total: 3,002 documents indexed**

## API Endpoints

### Entity-Specific Search

**Endpoint:** `GET /api/v1/{entity}?q={query}`

**Parameters:**
- `q` - Search query (min 2 chars, required for search)
- Entity-specific filters (level, school, rarity, etc.)
- `per_page` - Results per page (default: 20)

**Examples:**
```bash
# Search spells
curl "http://localhost:8080/api/v1/spells?q=fire&level=3"

# Search items
curl "http://localhost:8080/api/v1/items?q=sword&rarity=rare"

# Search races
curl "http://localhost:8080/api/v1/races?q=dwarf"
```

### Global Search

**Endpoint:** `GET /api/v1/search?q={query}`

**Parameters:**
- `q` - Search query (required, min 2 chars)
- `types[]` - Filter by entity types (optional)
- `limit` - Results per type (default: 20, max: 100)
- `debug` - Show debug info (optional)

**Response Format:**
```json
{
  "data": {
    "spells": [...],
    "items": [...],
    "races": [...],
    "classes": [...],
    "backgrounds": [...],
    "feats": [...]
  },
  "meta": {
    "query": "dragon",
    "types_searched": ["spell", "item", ...],
    "limit_per_type": 20,
    "total_results": 47
  }
}
```

**Examples:**
```bash
# Search everything
curl "http://localhost:8080/api/v1/search?q=dragon"

# Search specific types
curl "http://localhost:8080/api/v1/search?q=fire&types[]=spell&types[]=item"

# Debug mode
curl "http://localhost:8080/api/v1/search?q=magic&debug=1"
```

## Management Commands

### Automated Setup (Recommended)

The `import:all` command automatically configures and populates search indexes:

```bash
# Full database + import + search indexing (one command)
docker compose exec php php artisan import:all

# Skip search indexing if you only want database import
docker compose exec php php artisan import:all --skip-search
```

This command:
1. Imports all XML data
2. Configures Meilisearch index settings
3. Imports all entities to Scout automatically

**No manual Scout commands needed!**

### Manual Setup (If Needed)

```bash
# 1. Configure index settings
docker compose exec php php artisan search:configure-indexes

# 2. Import all entities
docker compose exec php php artisan scout:import "App\\Models\\Spell"
docker compose exec php php artisan scout:import "App\\Models\\Item"
docker compose exec php php artisan scout:import "App\\Models\\Monster"
docker compose exec php php artisan scout:import "App\\Models\\Race"
docker compose exec php php artisan scout:import "App\\Models\\CharacterClass"
docker compose exec php php artisan scout:import "App\\Models\\Background"
docker compose exec php php artisan scout:import "App\\Models\\Feat"
```

### Rebuilding Indexes

If search results are stale or incorrect:

```bash
# Delete all indexes and rebuild from scratch
docker compose exec php php artisan scout:delete-all-indexes
docker compose exec php php artisan search:configure-indexes

# Re-import all entities
docker compose exec php php artisan scout:import "App\\Models\\Spell"
docker compose exec php php artisan scout:import "App\\Models\\Item"
docker compose exec php php artisan scout:import "App\\Models\\Monster"
docker compose exec php php artisan scout:import "App\\Models\\Race"
docker compose exec php php artisan scout:import "App\\Models\\CharacterClass"
docker compose exec php php artisan scout:import "App\\Models\\Background"
docker compose exec php php artisan scout:import "App\\Models\\Feat"
```

### Test Isolation

Tests use a separate index namespace to avoid polluting production data:

- **Production indexes**: `spells`, `items`, `monsters`, etc.
- **Test indexes**: `test_spells`, `test_items`, `test_monsters`, etc.

Configured via `SCOUT_PREFIX=test_` in `phpunit.xml`. Test indexes are automatically flushed after each test.

## Performance

**Typical Response Times:**
- Entity search: 10-50ms
- Global search: 15-75ms
- MySQL fallback: 50-150ms

**Index Stats:**
- Total documents: 3,002
- Total indexes: 6
- Disk usage: ~15MB

## Troubleshooting

### No Results Returned

1. Check Meilisearch is running:
```bash
docker compose ps meilisearch
curl http://localhost:7700/health
```

2. Verify documents indexed:
```bash
curl http://localhost:7700/indexes/spells/stats -H "Authorization: Bearer masterKey"
```

3. Re-import if needed:
```bash
php artisan scout:flush "App\Models\Spell"
php artisan scout:import "App\Models\Spell"
```

### Slow Performance

1. Check index size and settings
2. Reduce searchable fields if too many
3. Increase Meilisearch memory limit
4. Use pagination (don't return 1000+ results)

### Connection Errors

1. Verify `.env` settings:
```
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=masterKey
```

2. Check Docker network:
```bash
docker compose logs meilisearch
```

## Architecture

**Models:**
- All searchable models use `Laravel\Scout\Searchable` trait
- `toSearchableArray()` - Defines what data goes into index (denormalized)
- `searchableWith()` - Eager loads relationships to prevent N+1
- `searchableAs()` - Defines index name

**Controllers:**
- Detect `?q=` parameter to enable search
- Use `Model::search($query)` for Scout
- Fallback to MySQL `LIKE` or `FULLTEXT` on errors
- Log failures for monitoring

**Service:**
- `GlobalSearchService` - Searches across all models
- Returns Collection per entity type
- Handles errors gracefully

## Index Configuration

Each index has configured:
- **Searchable attributes** - Which fields are searched (ranked by importance)
- **Filterable attributes** - Which fields can be filtered
- **Sortable attributes** - Which fields can be sorted

Example for spells:
```php
searchableAttributes: ['name', 'description', 'school_name']
filterableAttributes: ['level', 'school_code', 'concentration']
sortableAttributes: ['name', 'level']
```

### Current Index Configurations

**Spells:**
```php
searchableAttributes: ['name', 'description', 'school_name', 'source_names', 'class_names']
filterableAttributes: ['level', 'school_code', 'concentration', 'ritual', 'source_codes']
sortableAttributes: ['name', 'level']
```

**Items:**
```php
searchableAttributes: ['name', 'description', 'type_name', 'source_names']
filterableAttributes: ['type_code', 'rarity_code', 'attunement', 'magic', 'source_codes']
sortableAttributes: ['name', 'rarity_code']
```

**Races:**
```php
searchableAttributes: ['name', 'size_name', 'source_names']
filterableAttributes: ['size_code', 'source_codes']
sortableAttributes: ['name']
```

**Classes:**
```php
searchableAttributes: ['name', 'description', 'source_names']
filterableAttributes: ['is_spellcaster', 'source_codes']
sortableAttributes: ['name']
```

**Backgrounds:**
```php
searchableAttributes: ['name', 'source_names']
filterableAttributes: ['source_codes']
sortableAttributes: ['name']
```

**Feats:**
```php
searchableAttributes: ['name', 'description', 'prerequisite_text', 'source_names']
filterableAttributes: ['source_codes']
sortableAttributes: ['name']
```

## Best Practices

### When to Use Search vs. Filtering

**Use Search (`?q=`) when:**
- User types free-text query
- Looking for partial name matches
- Typo-tolerance needed
- Relevance ranking important

**Use Filters (no `?q=`) when:**
- Exact attribute matching (level=3, rarity=rare)
- Browsing/exploring all items
- Building faceted navigation
- Performance critical (filtering is faster)

### Combining Search + Filters

Both can be used together:
```bash
# Find fire spells, level 3 or higher
GET /api/v1/spells?q=fire&level[gte]=3

# Find rare swords
GET /api/v1/items?q=sword&rarity=rare
```

### Rate Limiting

Meilisearch is fast, but consider:
- Debounce autocomplete queries (300ms)
- Cache popular searches
- Implement API rate limiting for abuse prevention

## Future Enhancements

### Planned Features

1. **Faceted Search** - Return counts per filter value
   ```json
   {
     "facets": {
       "level": {"1": 52, "2": 41, "3": 38},
       "school": {"evocation": 87, "transmutation": 65}
     }
   }
   ```

2. **Synonyms** - "longsword" → "long sword"

3. **Custom Ranking** - Boost official content over homebrew

4. **Search Analytics** - Track popular queries, zero-result searches

5. **Autocomplete Endpoint** - Optimized for typeahead

6. **Geo-search** - If we add location-based features

## References

- [Laravel Scout Documentation](https://laravel.com/docs/scout)
- [Meilisearch Documentation](https://www.meilisearch.com/docs)
- [Meilisearch Cloud Dashboard](https://cloud.meilisearch.com)

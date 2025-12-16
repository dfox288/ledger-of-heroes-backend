# Search & Filtering Architecture

**Use Meilisearch for ALL filtering** - no Eloquent `whereHas()` in Services.

## Correct Approach

```bash
# CORRECT - Meilisearch filter syntax
GET /api/v1/spells?filter=class_slugs IN [bard] AND level <= 3

# WRONG - Don't add custom parameters
GET /api/v1/spells?classes=bard
```

## Key Points

- Filterable fields defined in model's `searchableOptions()`
- Data indexed via `toSearchableArray()`
- See `../wrapper/docs/backend/reference/MEILISEARCH-FILTERS.md` for syntax

## For Search/Filter Changes

When adding new filterable fields:

1. Add field to model's `toSearchableArray()`
2. Add to `searchableOptions()` → `filterableAttributes`
3. Re-index: `just scout-import ModelName`
4. Document in Controller PHPDoc

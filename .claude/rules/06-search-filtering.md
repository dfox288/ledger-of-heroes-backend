# Search & Filtering Architecture

**Use Meilisearch for public search/filter endpoints** — no `whereHas()` in code that services those endpoints.

## Scope of the rule

The ban on `whereHas()` applies specifically to the **public index / search endpoints** on large entity collections: `/spells`, `/monsters`, `/classes`, `/races`, `/items`, `/backgrounds`, `/feats`, `/optional-features`, and the global `/search`. These are unbounded over the full dataset and users drive the filter syntax — Meilisearch is mandatory because correlated subqueries degrade quickly at scale.

**`whereHas()` is fine** in the following categories (and the rule should not be cited against them):

1. **Model query scopes** (`app/Models/**`, `app/Models/Concerns/Has*Scopes.php`) — internal query-building helpers that may be composed into larger queries. Whether they use `whereHas` is the scope author's call.
2. **Character-state services** (`SpellManagerService`, `AvailableFeatsService`, the `ChoiceHandlers/*`) — queries bounded by a single character's state (e.g., "which spells does this character know", "which feats can this character take given its current classes"). Result sets are tiny and per-user.
3. **CLI / console commands** (`app/Console/Commands/**`) — not user-facing; offline execution tolerates any join cost.
4. **Small lookup-table endpoints** — endpoints like `/lookups/skills?ability=DEX` that constrain a tiny static table (skills: 18 rows, conditions: 15 rows, ability scores: 6 rows) via a relation. A join over a handful of rows is cheaper than maintaining a Meilisearch index.
5. **Per-character personalization endpoints** — e.g., `/characters/{id}/feature-selections/available` or similar, where the result set is bounded by a single character's attributes. These are not "search" in the public sense and shouldn't be indexed.

If you're unsure which bucket a query falls into, apply this test: **can a public anonymous caller influence the query against the full dataset?** If yes → Meilisearch. If the caller is implicitly bounded to their own state (a character, a party, a user), `whereHas` is fine.

## Public search endpoints — correct approach

```bash
# CORRECT - Meilisearch filter syntax
GET /api/v1/spells?filter=class_slugs IN [bard] AND level <= 3

# WRONG - Don't add custom query parameters that shadow the filter syntax
GET /api/v1/spells?classes=bard
```

## Key Points

- Filterable fields defined in model's `searchableOptions()`
- Data indexed via `toSearchableArray()`
- See `../wrapper/docs/backend/reference/MEILISEARCH-FILTERS.md` for syntax

## For Search/Filter Changes

When adding new filterable fields to a public entity endpoint:

1. Add field to model's `toSearchableArray()`
2. Add to `searchableOptions()` → `filterableAttributes`
3. Re-index: `just scout-import ModelName`
4. Document in Controller PHPDoc

## Service Layer Architecture

Search services backing public entity endpoints should extend `AbstractSearchService` for consistent behavior.

**Gold standard:** `SpellSearchService`

See `../wrapper/docs/backend/reference/SEARCH-SERVICE-ARCHITECTURE.md` for:
- When to extend AbstractSearchService
- Required methods to implement
- How to add a new searchable entity

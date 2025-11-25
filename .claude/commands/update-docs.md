---
description: "Update API documentation for a specific entity following SpellController gold standard"
hints: ["entity_name"]
---

# /update-docs

Update OpenAPI/Scramble documentation for: **$1**

Follow the SpellController gold standard pattern to ensure comprehensive API documentation.

---

## Step 1: Analyze Model

Read `app/Models/$1.php` and extract:

- **Filterable attributes** from `searchableOptions()` → `filterableAttributes`
- **Sortable attributes** from `searchableOptions()` → `sortableAttributes`
- **Data types** for each field:
  - `int` → operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `IN`, `NOT IN`
  - `string` → operators: `=`, `!=`, `IN`, `NOT IN`
  - `bool` → operators: `=`, `!=`
  - `array` → operators: `IN`, `NOT IN`, `IS NULL`, `IS NOT NULL`

---

## Step 2: Verify Resource Exposes All Data

Read `app/Http/Resources/${1}Resource.php` and verify:

- ✓ ALL filterable fields from `toSearchableArray()` are exposed in `toArray()`
- ✓ Relationships use `whenLoaded()` for lazy loading
- ✓ No searchable data is missing from the resource
- ✓ Array fields (like `source_codes`, `tag_slugs`) are properly exposed

---

## Step 3: Update Controller PHPDoc

Read `app/Http/Controllers/Api/${1}Controller.php` and update the `index()` method PHPDoc to include:

### Required Sections:

1. **Method description** with Meilisearch filtering reference
2. **Common Filter Examples** - 3-5 practical filter queries
3. **Filterable Fields** - EVERY field listed with:
   - Field name
   - Data type (`int`, `string`, `bool`, `array`)
   - Available operators
   - Example usage
4. **Use Cases** - 2-3 real-world query scenarios
5. **Query Parameters** section documenting:
   - Pagination: `?page=1`, `?per_page=50`
   - Sorting: `?sort=field` or `?sort=-field`
   - Relationships: `?with=relation1,relation2`
   - Search: `?q=search_term`

### Format Example:
```php
/**
 * Get paginated {entities} with search and filtering
 *
 * Supports filtering via Meilisearch syntax in the `filter` parameter.
 * See: https://www.meilisearch.com/docs/reference/api/search#filter
 *
 * **Common Filter Examples:**
 * - `field = value` - Exact match
 * - `field IN [val1, val2]` - Multiple values
 * - `field > 5 AND other_field = "text"` - Combined filters
 *
 * **Filterable Fields:**
 *
 * Integer fields (=, !=, >, >=, <, <=, IN, NOT IN):
 * - `id` - Unique identifier
 * - `field_name` - Description
 *
 * String fields (=, !=, IN, NOT IN):
 * - `slug` - URL-friendly identifier
 * - `field_name` - Description
 *
 * Boolean fields (=, !=):
 * - `is_something` - True/false value
 *
 * Array fields (IN, NOT IN, IS NULL, IS NOT NULL):
 * - `source_codes` - e.g. source_codes IN [phb, dmg]
 * - `tag_slugs` - e.g. tag_slugs IN [fire, damage]
 *
 * **Use Cases:**
 * - Find {practical example 1}
 * - Get {practical example 2}
 * - Search {practical example 3}
 *
 * **Query Parameters:**
 * - `q` (string) - Full-text search across name/description
 * - `filter` (string) - Meilisearch filter expression
 * - `sort` (string) - Field to sort by (prefix with `-` for descending)
 * - `page` (int) - Page number (default: 1)
 * - `per_page` (int) - Results per page (default: 15, max: 100)
 * - `with` (string) - Comma-separated relationships to include
 *
 * @return ResourceCollection
 */
```

---

## Step 4: Verify Alignment

Confirm the following alignment:

- ✓ **Model** `searchableOptions()` defines filterable fields
- ✓ **Model** `toSearchableArray()` indexes those fields
- ✓ **Resource** `toArray()` exposes all searchable fields
- ✓ **Controller** PHPDoc documents all filterable fields
- ✓ **Controller** eager-loads (via `searchableWith()`) all relationships used in Resource

---

## Step 5: Validate Against Gold Standard

Compare your work against: `app/Http/Controllers/Api/SpellController.php`

Check:
- ✓ PHPDoc structure matches exactly
- ✓ All data types are documented
- ✓ Operators are listed per data type
- ✓ Examples use valid Meilisearch syntax
- ✓ Use cases are practical and clear

---

## Entities Available

- `Spell` - 477 spells
- `Monster` - 598 monsters
- `CharacterClass` - 131 classes
- `Race` - 115 races
- `Item` - 516 items
- `Background` - 34 backgrounds
- `Feat` - 138 feats

---

**After completing the updates, report:**
1. What was changed
2. New filterable fields documented (if any)
3. Any misalignments found and corrected
4. Confirmation that alignment is verified

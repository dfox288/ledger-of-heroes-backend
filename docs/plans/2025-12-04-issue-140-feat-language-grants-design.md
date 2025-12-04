# Issue #140: Parse Language Grants from Feats

## Summary

Add language parsing to feats so that feats like Linguist can expose their language grants via the API.

## Problem

The Feat API currently doesn't include a `languages` field. Feats like Linguist grant "three languages of your choice" but this data isn't parsed or exposed.

## Solution

Use existing infrastructure (`entity_languages` table, `HasEntityLanguages` trait, `ImportsLanguages` trait) to add language support to Feats with minimal new code.

## Implementation Tasks

### Task 1: Add `parseLanguages()` to FeatXmlParser

**File:** `app/Services/Parsers/FeatXmlParser.php`

Add private method to parse language grants from description text:

```php
/**
 * Parse language grants from feat description text.
 *
 * Handles patterns like "You learn three languages of your choice"
 *
 * @return array<int, array<string, mixed>>
 */
private function parseLanguages(string $text): array
{
    // Pattern: "You learn X languages of your choice"
    if (preg_match('/you learn (one|two|three|four|five|six) languages? of your choice/i', $text, $match)) {
        return [[
            'language_id' => null,
            'is_choice' => true,
            'quantity' => $this->wordToNumber($match[1]),
        ]];
    }

    return [];
}
```

Update `parseFeat()` to include languages in return array:

```php
return [
    // ... existing fields
    'languages' => $this->parseLanguages($description),
];
```

**Tests:** Unit test in `tests/Unit/Parsers/FeatXmlParserTest.php`

---

### Task 2: Add `HasEntityLanguages` trait to Feat model

**File:** `app/Models/Feat.php`

```php
use App\Models\Concerns\HasEntityLanguages;

class Feat extends BaseModel
{
    use HasEntityLanguages;
    // ... existing traits
}
```

**Tests:** Unit test for relationship in `tests/Unit/Models/FeatTest.php`

---

### Task 3: Add language importing to FeatImporter

**File:** `app/Services/Importers/FeatImporter.php`

Add trait usage:

```php
use App\Services\Importers\Concerns\ImportsLanguages;

class FeatImporter extends BaseImporter
{
    use ImportsLanguages;
    // ... existing traits
}
```

Update `importEntity()`:

```php
protected function importEntity(array $data): Feat
{
    // ... existing upsert code

    // Clear existing polymorphic relationships (add languages)
    $feat->languages()->delete();

    // ... existing imports

    // Import languages
    $this->importEntityLanguages($feat, $data['languages'] ?? []);

    // ...
}
```

**Tests:** Feature test in `tests/Feature/Importers/FeatImporterTest.php`

---

### Task 4: Add `languages` to FeatResource

**File:** `app/Http/Resources/FeatResource.php`

```php
return [
    // ... existing fields
    'languages' => EntityLanguageResource::collection($this->whenLoaded('languages')),
];
```

**Tests:** Feature test for API response in `tests/Feature/Api/FeatApiTest.php`

---

### Task 5: Update FeatController eager loading

**File:** `app/Http/Controllers/Api/FeatController.php`

Add `languages.language` to eager loading in both `index()` and `show()` methods.

**Tests:** Covered by Task 4 API tests

---

### Task 6: Update Feat model's searchableWith

**File:** `app/Models/Feat.php`

Add `languages.language` to `searchableWith()` array for Meilisearch.

---

## Test Plan

1. **Unit Tests:**
   - `FeatXmlParserTest`: Test `parseLanguages()` with Linguist text
   - `FeatTest`: Test `languages()` relationship exists

2. **Feature Tests:**
   - `FeatImporterTest`: Test language import creates EntityLanguage records
   - `FeatApiTest`: Test API response includes `languages` array

3. **Manual Verification:**
   - Re-import feats: `docker compose exec php php artisan import:feats`
   - Check API: `GET /api/v1/feats/linguist` should show languages

## API Output

```json
{
  "name": "Linguist",
  "slug": "linguist",
  "languages": [
    {
      "language": null,
      "is_choice": true,
      "quantity": 3
    }
  ]
}
```

## Files Changed

| File | Change |
|------|--------|
| `app/Services/Parsers/FeatXmlParser.php` | Add `parseLanguages()` method |
| `app/Models/Feat.php` | Add `HasEntityLanguages` trait, update `searchableWith()` |
| `app/Services/Importers/FeatImporter.php` | Add `ImportsLanguages` trait, import languages |
| `app/Http/Resources/FeatResource.php` | Add `languages` field |
| `app/Http/Controllers/Api/FeatController.php` | Add eager loading |
| `tests/Unit/Parsers/FeatXmlParserTest.php` | Add language parsing tests |
| `tests/Feature/Importers/FeatImporterTest.php` | Add language import tests |
| `tests/Feature/Api/FeatApiTest.php` | Add API response tests |

## Related Issues

- #139 - Language choices endpoint (depends on this for feat language data)
- #131 - Frontend language step

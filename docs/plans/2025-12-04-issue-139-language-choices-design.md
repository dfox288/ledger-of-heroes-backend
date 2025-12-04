# Issue #139: Language Choices Endpoint

## Summary

Add API endpoints for character language management, following the established proficiency-choices pattern.

## Problem

Characters gain languages from race, background, and feats. Some are fixed (e.g., Common for Human), others are choices (e.g., "one language of your choice"). No endpoint exists to:
1. View available language choices
2. Save language selections
3. List all languages a character knows

## Data Model

### New Table: `character_languages`

```php
Schema::create('character_languages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('character_id')->constrained()->cascadeOnDelete();
    $table->foreignId('language_id')->constrained();
    $table->enum('source', ['race', 'background', 'feat'])->default('race');
    $table->string('choice_group')->nullable(); // e.g., "language_choice_1"
    $table->timestamp('created_at')->nullable();

    $table->unique(['character_id', 'language_id']); // Can't know same language twice
    $table->index('character_id');
});
```

### New Model: `CharacterLanguage`

```php
class CharacterLanguage extends Model
{
    protected $fillable = ['character_id', 'language_id', 'source', 'choice_group'];

    public function character(): BelongsTo;
    public function language(): BelongsTo;
}
```

## API Endpoints

### 1. GET /characters/{id}/languages

List all languages the character knows.

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "source": "race",
      "language": { "id": 1, "name": "Common", "slug": "common", "script": "Common" }
    },
    {
      "id": 2,
      "source": "race",
      "language": { "id": 5, "name": "Elvish", "slug": "elvish", "script": "Elvish" }
    }
  ]
}
```

### 2. GET /characters/{id}/language-choices

Get pending language choices organized by source.

**Response:**
```json
{
  "data": {
    "race": {
      "known": [
        { "id": 1, "name": "Common", "slug": "common" }
      ],
      "choices": {
        "quantity": 1,
        "remaining": 1,
        "selected": [],
        "options": [
          { "id": 2, "name": "Dwarvish", "slug": "dwarvish", "exotic": false },
          { "id": 3, "name": "Elvish", "slug": "elvish", "exotic": false }
        ]
      }
    },
    "background": {
      "known": [],
      "choices": {
        "quantity": 2,
        "remaining": 2,
        "selected": [],
        "options": [/* same options minus already known */]
      }
    },
    "feat": {
      "known": [],
      "choices": {
        "quantity": 0,
        "remaining": 0,
        "selected": [],
        "options": []
      }
    }
  }
}
```

**Options Logic:**
- Include all languages from `languages` table
- Exclude languages already known by character (from any source)
- Mark exotic languages with `exotic: true` flag

### 3. POST /characters/{id}/language-choices

Save language selection for a choice group.

**Request:**
```json
{
  "source": "race",
  "language_ids": [3]
}
```

**Validation:**
- `source`: required, in:race,background,feat
- `language_ids`: required, array, exists:languages,id
- Count must match remaining choices for that source
- Languages must be in available options (not already known)

**Response:**
```json
{
  "message": "Languages saved successfully",
  "data": [/* updated character languages */]
}
```

### 4. POST /characters/{id}/languages/populate

Auto-populate fixed languages from race/background/feat.

**Logic:**
- Get race's `entity_languages` where `is_choice = false`
- Get background's `entity_languages` where `is_choice = false`
- Get feat's `entity_languages` where `is_choice = false`
- Create `character_languages` records (idempotent)

**Response:**
```json
{
  "message": "Languages populated successfully",
  "data": [/* populated languages */]
}
```

## Implementation Tasks

### Task 1: Migration + Model
- Create `character_languages` migration
- Create `CharacterLanguage` model
- Add `languages()` relationship to `Character`
- Create `CharacterLanguageFactory`

### Task 2: Service Layer
- Create `CharacterLanguageService`
- Methods: `getCharacterLanguages()`, `getPendingChoices()`, `makeChoice()`, `populateFixed()`
- Follow `CharacterProficiencyService` patterns

### Task 3: Controller + Routes
- Create `CharacterLanguageController`
- Add routes to `api.php`
- Create `CharacterLanguageResource`

### Task 4: Feature Tests
- Test all 4 endpoints
- Test validation (wrong count, duplicate language, invalid source)
- Test options filtering (excludes already known)

### Task 5: Integration
- Add `languages` to `CharacterResource` when loaded
- Update `PopulateCharacterAbilities` listener to populate fixed languages

## Files to Create/Modify

| File | Action |
|------|--------|
| `database/migrations/..._create_character_languages_table.php` | Create |
| `app/Models/CharacterLanguage.php` | Create |
| `app/Models/Character.php` | Add relationship |
| `database/factories/CharacterLanguageFactory.php` | Create |
| `app/Services/CharacterLanguageService.php` | Create |
| `app/Http/Controllers/Api/CharacterLanguageController.php` | Create |
| `app/Http/Resources/CharacterLanguageResource.php` | Create |
| `routes/api.php` | Add routes |
| `tests/Feature/Api/CharacterLanguageApiTest.php` | Create |

## Test Plan

1. **Unit Tests:**
   - Service methods for choice calculation
   - Options filtering logic

2. **Feature Tests:**
   - `GET /characters/{id}/languages` returns known languages
   - `GET /characters/{id}/language-choices` returns structured choices
   - `POST /characters/{id}/language-choices` validates and saves
   - `POST /characters/{id}/languages/populate` is idempotent
   - Validation errors (wrong count, invalid language, duplicate)

## Related Issues

- #140 - Feat language parsing (completed - enables feat source)
- #131 - Frontend language step (consumes this API)

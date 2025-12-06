# API Deprecation Guide

This document tracks deprecated API endpoints and provides migration paths for API consumers.

**Last Updated:** December 2025
**Target Removal:** API v2 (June 2026)

---

## Summary

| Deprecated Endpoint | Sunset Date | Replacement |
|---------------------|-------------|-------------|
| `POST /characters/{id}/level-up` | June 1, 2026 | `POST /characters/{id}/classes/{class}/level-up` |
| `GET /characters/{id}/spell-slots/tracked` | June 1, 2026 | `GET /characters/{id}/spell-slots` |
| `GET /characters/{id}/proficiency-choices` | June 1, 2026 | `GET /characters/{id}/pending-choices?type=proficiency` |
| `POST /characters/{id}/proficiency-choices` | June 1, 2026 | `POST /characters/{id}/choices/{choiceId}` |
| `GET /characters/{id}/language-choices` | June 1, 2026 | `GET /characters/{id}/pending-choices?type=language` |
| `POST /characters/{id}/language-choices` | June 1, 2026 | `POST /characters/{id}/choices/{choiceId}` |
| `GET /characters/{id}/feature-selection-choices` | June 1, 2026 | `GET /characters/{id}/pending-choices?type=feature_selection` |

---

## Deprecated Endpoints

### 1. Level-Up Endpoint

**Deprecated:** `POST /api/v1/characters/{id}/level-up`
**Replacement:** `POST /api/v1/characters/{id}/classes/{classIdOrSlug}/level-up`

#### Why Deprecated?

The old endpoint automatically leveled up the character's primary (first) class, creating ambiguity in multiclass builds. The new endpoint provides explicit control over which class gains a level.

#### Migration

```diff
- POST /api/v1/characters/123/level-up
+ POST /api/v1/characters/123/classes/fighter/level-up
```

The request body remains the same (empty). The response structure is identical.

---

### 2. Spell Slots Tracked Endpoint

**Deprecated:** `GET /api/v1/characters/{id}/spell-slots/tracked`
**Replacement:** `GET /api/v1/characters/{id}/spell-slots`

#### Why Deprecated?

The `/spell-slots` endpoint now includes both slot maximums and tracked usage in a consolidated response.

#### Migration

```diff
- GET /api/v1/characters/123/spell-slots/tracked
+ GET /api/v1/characters/123/spell-slots
```

---

### 3. Proficiency Choices Endpoints

**Deprecated:**
- `GET /api/v1/characters/{id}/proficiency-choices`
- `POST /api/v1/characters/{id}/proficiency-choices`

**Replacement:** Unified Choice System
- `GET /api/v1/characters/{id}/pending-choices?type=proficiency`
- `POST /api/v1/characters/{id}/choices/{choiceId}`

#### Why Deprecated?

The unified choice system provides a consistent interface for all character choices (proficiencies, languages, feature selections, equipment, spells). This reduces the number of endpoints to learn and enables better choice tracking.

#### Migration - Listing Choices

```diff
- GET /api/v1/characters/123/proficiency-choices
+ GET /api/v1/characters/123/pending-choices?type=proficiency
```

**Old Response:**
```json
{
  "data": {
    "class": {
      "skill_choice_1": {
        "proficiency_type": "skill",
        "quantity": 2,
        "remaining": 2,
        "options": [...]
      }
    }
  }
}
```

**New Response:**
```json
{
  "data": {
    "choices": [
      {
        "id": "proficiency:class:skill_choice_1",
        "type": "proficiency",
        "source": "class",
        "quantity": 2,
        "remaining": 2,
        "options": [...]
      }
    ],
    "summary": {
      "total_pending": 1,
      "by_type": {"proficiency": 1}
    }
  }
}
```

#### Migration - Making a Choice

```diff
- POST /api/v1/characters/123/proficiency-choices
- {
-   "source": "class",
-   "choice_group": "skill_choice_1",
-   "skill_ids": [1, 5]
- }
+ POST /api/v1/characters/123/choices/proficiency:class:skill_choice_1
+ {
+   "selections": [1, 5]
+ }
```

---

### 4. Language Choices Endpoints

**Deprecated:**
- `GET /api/v1/characters/{id}/language-choices`
- `POST /api/v1/characters/{id}/language-choices`

**Replacement:** Unified Choice System
- `GET /api/v1/characters/{id}/pending-choices?type=language`
- `POST /api/v1/characters/{id}/choices/{choiceId}`

#### Migration - Listing Choices

```diff
- GET /api/v1/characters/123/language-choices
+ GET /api/v1/characters/123/pending-choices?type=language
```

#### Migration - Making a Choice

```diff
- POST /api/v1/characters/123/language-choices
- {
-   "source": "race",
-   "language_ids": [3]
- }
+ POST /api/v1/characters/123/choices/language:race:choice_1
+ {
+   "selections": [3]
+ }
```

---

### 5. Feature Selection Choices Endpoint

**Deprecated:** `GET /api/v1/characters/{id}/feature-selection-choices`
**Replacement:** `GET /api/v1/characters/{id}/pending-choices?type=feature_selection`

#### Why Deprecated?

Consolidated into the unified choice system for consistency.

#### Migration

```diff
- GET /api/v1/characters/123/feature-selection-choices
+ GET /api/v1/characters/123/pending-choices?type=feature_selection
```

**Note:** The `POST /characters/{id}/feature-selections` endpoint for adding feature selections remains active, as it handles direct feature management beyond the choice system.

---

## Endpoints NOT Deprecated

The following endpoints remain active and are NOT deprecated:

### Sync Endpoints (Still Required)
- `POST /characters/{id}/proficiencies/sync` - Syncs fixed proficiencies from class/race/background
- `POST /characters/{id}/languages/sync` - Syncs fixed languages from race/background
- `POST /characters/{id}/features/sync` - Syncs features from various sources

These sync endpoints serve a different purpose than choice resolution and remain necessary for character creation workflows.

### CRUD Endpoints (Still Required)
- `GET /characters/{id}/proficiencies` - List all proficiencies
- `GET /characters/{id}/languages` - List all languages
- `GET /characters/{id}/feature-selections` - List all feature selections
- `POST /characters/{id}/feature-selections` - Add a feature selection
- `DELETE /characters/{id}/feature-selections/{id}` - Remove a feature selection

These endpoints provide direct data access and manipulation, separate from the choice workflow.

### ASI Choice Endpoint
- `POST /characters/{id}/asi-choice` - Handle ASI/Feat selection

This specialized endpoint handles ASI and Feat choices at level-up and may be integrated into the unified system in a future version.

---

## HTTP Headers

All deprecated endpoints return the following HTTP headers:

```
Deprecation: true
Sunset: Sat, 01 Jun 2026 00:00:00 GMT
Link: </api/v1/characters/{id}/new-endpoint>; rel="successor-version"
```

Your API client can check for the `Deprecation: true` header to identify deprecated endpoints and log warnings.

---

## Timeline

| Date | Action |
|------|--------|
| December 2025 | Deprecation headers added to all deprecated endpoints |
| Q1 2026 | Monitor usage of deprecated endpoints |
| Q2 2026 | Final deprecation warnings in API documentation |
| June 1, 2026 | **Deprecated endpoints removed in API v2** |

---

## Questions?

If you have questions about migrating from deprecated endpoints, please open an issue at:
https://github.com/dfox288/dnd-rulebook-project/issues

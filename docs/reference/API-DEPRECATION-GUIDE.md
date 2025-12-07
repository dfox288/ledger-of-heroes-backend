# API Deprecation Guide

This document tracks deprecated API endpoints that have been removed.

**Last Updated:** December 2025

---

## Removed Endpoints

The following endpoints were deprecated and have been **removed** as of December 2025.

| Removed Endpoint | Replacement |
|------------------|-------------|
| `POST /characters/{id}/level-up` | `POST /characters/{id}/classes/{class}/level-up` |
| `GET /characters/{id}/spell-slots/tracked` | `GET /characters/{id}/spell-slots` |
| `GET /characters/{id}/proficiency-choices` | `GET /characters/{id}/pending-choices?type=proficiency` |
| `POST /characters/{id}/proficiency-choices` | `POST /characters/{id}/choices/{choiceId}` |
| `GET /characters/{id}/language-choices` | `GET /characters/{id}/pending-choices?type=language` |
| `POST /characters/{id}/language-choices` | `POST /characters/{id}/choices/{choiceId}` |
| `GET /characters/{id}/feature-selection-choices` | `GET /characters/{id}/pending-choices?type=feature_selection` |

---

## Migration Guide

### 1. Level-Up Endpoint

**Removed:** `POST /api/v1/characters/{id}/level-up`
**Use Instead:** `POST /api/v1/characters/{id}/classes/{classIdOrSlug}/level-up`

The old endpoint automatically leveled up the character's primary class. The new endpoint provides explicit control over which class gains a level.

```diff
- POST /api/v1/characters/123/level-up
+ POST /api/v1/characters/123/classes/fighter/level-up
```

---

### 2. Spell Slots Tracked Endpoint

**Removed:** `GET /api/v1/characters/{id}/spell-slots/tracked`
**Use Instead:** `GET /api/v1/characters/{id}/spell-slots`

The consolidated `/spell-slots` endpoint includes both slot maximums and tracked usage.

---

### 3. Choice Endpoints (Proficiency, Language, Feature Selection)

All legacy choice endpoints have been replaced by the **Unified Choice System**.

#### Listing Pending Choices

```diff
- GET /api/v1/characters/123/proficiency-choices
- GET /api/v1/characters/123/language-choices
- GET /api/v1/characters/123/feature-selection-choices
+ GET /api/v1/characters/123/pending-choices
+ GET /api/v1/characters/123/pending-choices?type=proficiency
+ GET /api/v1/characters/123/pending-choices?type=language
+ GET /api/v1/characters/123/pending-choices?type=feature_selection
```

#### Resolving Choices

```diff
- POST /api/v1/characters/123/proficiency-choices
- {"source": "class", "choice_group": "skill_choice_1", "skill_ids": [1, 5]}
+ POST /api/v1/characters/123/choices/proficiency:class:skill_choice_1
+ {"selections": [1, 5]}
```

```diff
- POST /api/v1/characters/123/language-choices
- {"source": "race", "language_ids": [3]}
+ POST /api/v1/characters/123/choices/language:race:choice_1
+ {"selections": [3]}
```

---

## Active Endpoints

The following endpoints remain active and are **not deprecated**:

### Sync Endpoints
- `POST /characters/{id}/proficiencies/sync` - Sync fixed proficiencies
- `POST /characters/{id}/languages/sync` - Sync fixed languages
- `POST /characters/{id}/features/sync` - Sync features from sources

### CRUD Endpoints
- `GET /characters/{id}/proficiencies` - List all proficiencies
- `GET /characters/{id}/languages` - List all languages
- `GET /characters/{id}/feature-selections` - List all feature selections
- `GET /characters/{id}/available-feature-selections` - List available features
- `POST /characters/{id}/feature-selections` - Add a feature selection
- `DELETE /characters/{id}/feature-selections/{id}` - Remove a feature selection

### Unified Choice System (Current)
- `GET /characters/{id}/pending-choices` - List all pending choices
- `GET /characters/{id}/pending-choices/{choiceId}` - Get specific choice details
- `POST /characters/{id}/choices/{choiceId}` - Resolve a choice
- `DELETE /characters/{id}/choices/{choiceId}` - Undo a choice

### Other Active Endpoints
- `POST /characters/{id}/asi-choice` - Handle ASI/Feat selection
- `POST /characters/{id}/classes/{class}/level-up` - Level up in a specific class
- `GET /characters/{id}/spell-slots` - Get consolidated spell slot data
- `POST /characters/{id}/spell-slots/use` - Use a spell slot

---

## Questions?

If you have questions about migrating from deprecated endpoints, please open an issue at:
https://github.com/dfox288/dnd-rulebook-project/issues

# Character API Restructuring Plan

**Created:** 2025-12-05
**Status:** Planning
**Breaking Changes:** Yes (with deprecation period)

## Overview

This plan addresses naming inconsistencies, conceptual overlaps, and usability issues identified in the Character Builder API audit. Changes are organized into phases to allow gradual migration.

---

## Phase 1: Non-Breaking Improvements (v1 Compatible)

These changes add functionality without breaking existing consumers.

### 1.1 Add Character Summary Endpoint

**Problem:** Consumers must call 6+ endpoints to understand complete character state.

**Solution:** Add `GET /characters/{id}/summary` endpoint.

```
GET /api/v1/characters/{id}/summary
```

**Response:**
```json
{
  "data": {
    "character": { "id": 1, "name": "Thorin", "total_level": 5 },
    "pending_choices": {
      "proficiencies": 2,
      "languages": 1,
      "spells": 3,
      "optional_features": 1,
      "asi": 0
    },
    "resources": {
      "hit_points": { "current": 35, "max": 45, "temp": 0 },
      "hit_dice": { "available": 3, "max": 5 },
      "spell_slots": {
        "1": { "available": 2, "max": 4 },
        "2": { "available": 1, "max": 3 },
        "3": { "available": 2, "max": 2 }
      },
      "features_with_uses": [
        { "name": "Second Wind", "uses": 1, "max": 1 },
        { "name": "Action Surge", "uses": 0, "max": 1 }
      ]
    },
    "combat_state": {
      "conditions": ["poisoned"],
      "death_saves": { "successes": 0, "failures": 0 },
      "is_conscious": true
    },
    "creation_complete": false,
    "missing_required": ["proficiency_choices", "language_choices"]
  }
}
```

**Backend Tasks:**
- [ ] Create `CharacterSummaryResource`
- [ ] Create `CharacterSummaryDTO`
- [ ] Add route and controller method
- [ ] Write tests

---

### 1.2 Consolidate Spell Slot Response

**Problem:** `/spell-slots` and `/spell-slots/tracked` return different data requiring two calls.

**Solution:** Merge tracked data into main `/spell-slots` response.

**Current:**
```json
// GET /spell-slots
{ "slots": { "1": 4, "2": 3 } }

// GET /spell-slots/tracked
{ "spent": { "1": 2, "2": 1 } }
```

**New Response for `/spell-slots`:**
```json
{
  "data": {
    "slots": {
      "1": { "total": 4, "spent": 2, "available": 2 },
      "2": { "total": 3, "spent": 1, "available": 2 },
      "3": { "total": 2, "spent": 0, "available": 2 }
    },
    "pact_magic": {
      "level": 2,
      "total": 2,
      "spent": 0,
      "available": 2
    },
    "preparation_limit": 8,
    "prepared_count": 6
  }
}
```

**Backend Tasks:**
- [ ] Update `SpellSlotsResource` to include tracked data
- [ ] Keep `/spell-slots/tracked` working (deprecated)
- [ ] Add deprecation header to `/spell-slots/tracked`
- [ ] Update tests

---

### 1.3 Document Wizard vs Play Flows

**Problem:** API serves two distinct usage patterns without documentation.

**Solution:** Add comprehensive flow documentation to OpenAPI.

**Character Creation Flow:**
```
1. POST /characters                     # Create shell
2. PATCH /characters/{id}               # Set race, background
3. POST /characters/{id}/classes        # Add primary class
4. GET /characters/{id}/proficiency-choices
5. POST /characters/{id}/proficiency-choices  # Make selections
6. GET /characters/{id}/language-choices
7. POST /characters/{id}/language-choices
8. GET /characters/{id}/available-spells?max_level=1
9. POST /characters/{id}/spells         # Learn starting spells
10. POST /characters/{id}/features/populate  # Apply features
11. GET /characters/{id}/summary        # Verify complete
```

**Gameplay Flow:**
```
Combat:
- POST /conditions                      # Apply condition
- DELETE /conditions/{slug}             # Remove condition
- POST /spell-slots/use                 # Cast spell
- POST /death-save                      # Death saving throw

Rest:
- POST /short-rest                      # Short rest
- POST /hit-dice/spend                  # Heal during short rest
- POST /long-rest                       # Long rest (full reset)

Level Up:
- POST /classes/{class}/level-up        # Gain level
- PUT /classes/{class}/subclass         # Choose subclass (level 3)
- POST /asi-choice                      # ASI or feat
- GET /optional-feature-choices         # Check new choices
- POST /optional-features               # Select invocations, etc.
```

**Backend Tasks:**
- [ ] Add flow documentation to controller PHPDocs
- [ ] Create dedicated docs page for character builder
- [ ] Add `x-flow` OpenAPI extension tags

---

### 1.4 Standardize Parameter Handling

**Problem:** Conditions accept ID or slug, other endpoints only accept ID.

**Solution:** Standardize all character sub-resource endpoints to accept slug OR ID.

**Affected Endpoints:**
- `DELETE /spells/{spell}` - Add slug support
- `PATCH /spells/{spell}/prepare` - Add slug support
- `PATCH /spells/{spell}/unprepare` - Add slug support
- `DELETE /equipment/{equipment}` - Already ID-only (keep, no slug)
- `DELETE /classes/{class}` - Add slug support
- `DELETE /optional-features/{optionalFeature}` - Add slug support

**Implementation Pattern:**
```php
// In route definition - already have this pattern for conditions
Route::delete('spells/{spellIdOrSlug}', ...);

// In controller
public function destroy(Character $character, string $spellIdOrSlug): Response
{
    $spell = is_numeric($spellIdOrSlug)
        ? Spell::findOrFail($spellIdOrSlug)
        : Spell::where('slug', $spellIdOrSlug)->firstOrFail();
    // ...
}
```

**Backend Tasks:**
- [ ] Update spell endpoints to accept slug
- [ ] Update class endpoints to accept slug
- [ ] Update optional-feature endpoints to accept slug
- [ ] Update tests for both ID and slug

---

### 1.5 Clarify Level-Up Endpoints

**Problem:** Two level-up endpoints with unclear distinction.

**Current:**
```
POST /characters/{id}/level-up                   # Single-action controller
POST /characters/{id}/classes/{class}/level-up  # Class-specific
```

**Solution:** Deprecate generic `/level-up`, keep class-specific.

**Reasoning:**
- D&D 5e requires choosing WHICH class to level when multiclassed
- Generic endpoint must guess or require body param anyway
- Class-specific endpoint is explicit and RESTful

**Backend Tasks:**
- [ ] Add deprecation notice to `CharacterLevelUpController`
- [ ] Document that `/classes/{class}/level-up` is preferred
- [ ] Add deprecation header to response
- [ ] Plan removal in v2

---

## Phase 2: Naming Improvements (Breaking with Deprecation)

These changes rename endpoints. Old endpoints return 301 redirects during deprecation period.

### 2.1 Rename "Optional Features" to "Feature Selections"

**Problem:** "Optional features" sounds like features you might not need. They're actually class-granted choices (invocations, maneuvers, metamagic).

**Current → New:**
```
GET  /optional-features                    → /feature-selections
GET  /available-optional-features          → /available-feature-selections
GET  /optional-feature-choices             → /feature-selection-choices
POST /optional-features                    → /feature-selections
DELETE /optional-features/{id}             → /feature-selections/{id}
```

**Also rename:**
- `CharacterOptionalFeatureController` → `FeatureSelectionController`
- `CharacterOptionalFeature` model → `FeatureSelection` (table: `feature_selections`)
- `CharacterOptionalFeatureResource` → `FeatureSelectionResource`

**Migration Period:** 6 months with redirects

**Backend Tasks:**
- [ ] Create new controller with new routes
- [ ] Add 301 redirects from old routes
- [ ] Add `Deprecation` header to old routes
- [ ] Create database migration to rename table (with alias)
- [ ] Update model and relationships
- [ ] Update all tests

**Frontend Tasks:**
- [ ] Update all `/optional-features` calls to `/feature-selections`
- [ ] Update TypeScript types
- [ ] Test thoroughly before old routes removed

---

### 2.2 Rename "Populate" to "Sync"

**Problem:** "Populate" is implementation jargon. "Sync" better describes the idempotent nature.

**Current → New:**
```
POST /proficiencies/populate  → POST /proficiencies/sync
POST /languages/populate      → POST /languages/sync
POST /features/populate       → POST /features/sync
```

**Backend Tasks:**
- [ ] Add new `/sync` routes alongside `/populate`
- [ ] Mark `/populate` as deprecated
- [ ] Add 301 redirects after deprecation period
- [ ] Update controller method names (keep aliases)

**Frontend Tasks:**
- [ ] Update all `/populate` calls to `/sync`
- [ ] Test idempotency behavior

---

### 2.3 Restructure Death Save Endpoints

**Problem:** `/death-save` and `/stabilize` are siblings but should be nested.

**Current → New:**
```
POST /death-save    → POST /death-saves
POST /stabilize     → POST /death-saves/stabilize
                    + DELETE /death-saves (reset)
```

**Backend Tasks:**
- [ ] Create new route structure
- [ ] Add redirects from old routes
- [ ] Add `DELETE /death-saves` for manual reset
- [ ] Update controller organization
- [ ] Update tests

---

### 2.4 Unify Choices vs Available Naming

**Problem:** Inconsistent `-choices` vs `-available` naming.

**Pattern Decision:** Use `-choices` for quota-based selections, `-available` for eligibility lists.

**Documentation Update:**
```
-choices endpoints:
  Return: { quantity, remaining, selected, options }
  Purpose: Track "pick N from list" selections
  Examples: proficiency-choices, language-choices, feature-selection-choices

-available endpoints:
  Return: Array of eligible items
  Purpose: Show what CAN be selected (no quota tracking)
  Examples: available-spells, available-feature-selections
```

**Backend Tasks:**
- [ ] Document the distinction in OpenAPI descriptions
- [ ] Ensure all endpoints follow the pattern
- [ ] Consider adding `-available` endpoints where missing

---

## Phase 3: v2 Considerations (Future Breaking Changes)

These are larger changes to consider for API v2.

### 3.1 Rename Character Classes Endpoint

**Problem:** `/classes` means different things at root vs nested level.

**Option A - Rename nested endpoint:**
```
GET /characters/{id}/classes → GET /characters/{id}/class-levels
```

**Option B - Add qualifier to root:**
```
GET /classes → GET /class-definitions (alias)
```

**Recommendation:** Option A for v2. "Class levels" matches D&D terminology for multiclass tracking.

---

### 3.2 Add Batch Finalization Endpoint

**New Endpoint:**
```
POST /characters/{id}/finalize
{
  "proficiency_choices": {
    "class": {
      "skill_choice_1": { "skill_ids": [1, 5] }
    }
  },
  "language_choices": {
    "race": { "language_ids": [3] }
  },
  "starting_spells": [101, 102, 103],
  "starting_equipment": "standard" | "gold"
}
```

**Response:**
```json
{
  "data": {
    "character": { /* full character */ },
    "applied": {
      "proficiencies": 4,
      "languages": 2,
      "spells": 3,
      "features": 7
    },
    "warnings": []
  }
}
```

---

### 3.3 Consolidate Related Endpoints Under Subresources

**Current (flat):**
```
GET /spells
GET /available-spells
GET /spell-slots
POST /spell-slots/use
```

**v2 (nested):**
```
GET /spellbook/known
GET /spellbook/available
GET /spellbook/slots
POST /spellbook/slots/use
POST /spellbook/prepare/{spell}
POST /spellbook/unprepare/{spell}
```

---

## Implementation Timeline

### Month 1-2: Phase 1 (Non-Breaking)
- [ ] 1.1 Character summary endpoint
- [ ] 1.2 Spell slot consolidation
- [ ] 1.3 Flow documentation
- [ ] 1.4 Standardize parameters
- [ ] 1.5 Deprecate generic level-up

### Month 3-4: Phase 2 (Deprecation Period Starts)
- [ ] 2.1 Optional features → Feature selections
- [ ] 2.2 Populate → Sync
- [ ] 2.3 Death save restructure
- [ ] 2.4 Document choices/available pattern

### Month 5-6: Frontend Migration
- [ ] Frontend updates all deprecated endpoints
- [ ] Testing period
- [ ] Monitor for stragglers via deprecation logs

### Month 7+: Cleanup
- [ ] Remove deprecated routes (301 → 410)
- [ ] Clean up redirect code
- [ ] Final documentation update

---

## Deprecation Strategy

### HTTP Headers
```
Deprecation: true
Sunset: Sat, 01 Jun 2026 00:00:00 GMT
Link: </api/v1/feature-selections>; rel="successor-version"
```

### Response Wrapper (Optional)
```json
{
  "data": { /* normal response */ },
  "_deprecated": {
    "message": "This endpoint is deprecated. Use /feature-selections instead.",
    "successor": "/api/v1/characters/{id}/feature-selections",
    "sunset": "2026-06-01"
  }
}
```

### Logging
- Log all requests to deprecated endpoints
- Include user agent for identifying stale clients
- Weekly report of deprecated endpoint usage

---

## Testing Requirements

### Each Renamed Endpoint Needs:
1. Tests for new endpoint (copy existing)
2. Tests for redirect behavior (301)
3. Tests for deprecation headers
4. Integration tests for full flows

### Regression Testing:
- Full character creation flow
- Full gameplay session flow
- Multiclass scenarios
- Edge cases (max level, all choices made, etc.)

---

## Related Issues

- Backend Implementation: GitHub Issue #TBD
- Frontend Migration: GitHub Issue #TBD

---

## Appendix A: Complete Endpoint Mapping

| Current Endpoint | Phase | New Endpoint | Notes |
|-----------------|-------|--------------|-------|
| `GET /characters/{id}/summary` | 1.1 | NEW | Character state summary |
| `GET /spell-slots` | 1.2 | Enhanced | Include tracked data |
| `GET /spell-slots/tracked` | 1.2 | Deprecated | Merged into /spell-slots |
| `POST /level-up` | 1.5 | Deprecated | Use /classes/{class}/level-up |
| `GET /optional-features` | 2.1 | `/feature-selections` | Rename |
| `GET /available-optional-features` | 2.1 | `/available-feature-selections` | Rename |
| `GET /optional-feature-choices` | 2.1 | `/feature-selection-choices` | Rename |
| `POST /optional-features` | 2.1 | `/feature-selections` | Rename |
| `DELETE /optional-features/{id}` | 2.1 | `/feature-selections/{id}` | Rename |
| `POST /proficiencies/populate` | 2.2 | `/proficiencies/sync` | Rename |
| `POST /languages/populate` | 2.2 | `/languages/sync` | Rename |
| `POST /features/populate` | 2.2 | `/features/sync` | Rename |
| `POST /death-save` | 2.3 | `/death-saves` | Pluralize |
| `POST /stabilize` | 2.3 | `/death-saves/stabilize` | Nest under death-saves |
| `GET /classes` (nested) | 3.1 | `/class-levels` | v2 consideration |

## Appendix B: Model/Class Renaming

| Current | New | Notes |
|---------|-----|-------|
| `CharacterOptionalFeature` | `FeatureSelection` | Model |
| `CharacterOptionalFeatureController` | `FeatureSelectionController` | Controller |
| `CharacterOptionalFeatureResource` | `FeatureSelectionResource` | Resource |
| `character_optional_features` | `feature_selections` | Table |
| `StoreCharacterOptionalFeatureRequest` | `StoreFeatureSelectionRequest` | Request |

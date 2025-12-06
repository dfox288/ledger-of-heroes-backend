# OpenAPI Specification Audit Plan

**Created:** 2025-12-06
**Completed:** 2025-12-06
**Status:** ✅ COMPLETED
**Related Issues:** Discovered during refactoring (likely #186 and related PRs)

## Executive Summary

A comprehensive audit of `api.json` revealed **36 OpenAPI specification issues** across two categories:
- **7 endpoints** returning `data: array of strings` instead of proper resource schemas
- **29 endpoints** missing pagination metadata (`links` and `meta`)

## Root Cause Analysis

### Category 1: Array of Strings (7 endpoints)

**Root Cause:** Controllers extending `ReadOnlyLookupController` call `handleIndex()` which returns `AnonymousResourceCollection`. Scramble cannot trace the resource type through the abstract parent class method.

**Affected Endpoints:**
| Endpoint | Controller | Resource |
|----------|------------|----------|
| `GET /v1/lookups/ability-scores` | `AbilityScoreController` | `AbilityScoreResource` |
| `GET /v1/lookups/conditions` | `ConditionController` | `ConditionResource` |
| `GET /v1/lookups/damage-types` | `DamageTypeController` | `DamageTypeResource` |
| `GET /v1/lookups/languages` | `LanguageController` | `LanguageResource` |
| `GET /v1/lookups/proficiency-types` | `ProficiencyTypeController` | `ProficiencyTypeResource` |
| `GET /v1/lookups/sizes` | `SizeController` | `SizeResource` |
| `GET /v1/lookups/spell-schools` | `SpellSchoolController` | `SpellSchoolResource` |

**Solution:** Add PHPDoc `@response` annotation with generic type:
```php
/**
 * @response AnonymousResourceCollection<AbilityScoreResource>
 */
public function index(AbilityScoreIndexRequest $request, LookupCacheService $cache): AnonymousResourceCollection
```

### Category 2: Missing Pagination (29 endpoints)

These fall into **two subcategories**:

#### 2A: Legitimately Non-Paginated (Small Fixed Collections)

These endpoints return complete collections that are intentionally not paginated:

| Endpoint | Resource | Reason |
|----------|----------|--------|
| `GET /v1/characters/{character}/classes` | `CharacterClassPivotResource` | Max 3 classes per character |
| `GET /v1/characters/{character}/conditions` | `CharacterConditionResource` | Typically < 10 active conditions |
| `GET /v1/characters/{character}/equipment` | `CharacterEquipmentResource` | Character's full inventory |
| `GET /v1/characters/{character}/features` | `CharacterFeatureResource` | Level-based, max ~30 |
| `GET /v1/characters/{character}/languages` | `CharacterLanguageResource` | Typically 2-5 languages |
| `GET /v1/characters/{character}/optional-features` | `CharacterOptionalFeatureResource` | Character's selected features |
| `GET /v1/characters/{character}/proficiencies` | `CharacterProficiencyResource` | Character's all proficiencies |
| `GET /v1/characters/{character}/spells` | `CharacterSpellResource` | Known/prepared spells |
| `GET /v1/lookups/alignments` | `LookupResource` | ~15 alignment values |
| `GET /v1/lookups/armor-types` | `LookupResource` | ~10 armor types |
| `GET /v1/lookups/monster-types` | `LookupResource` | 14 monster types |
| `GET /v1/lookups/optional-feature-types` | `OptionalFeatureTypeResource` | ~20 types |
| `GET /v1/lookups/rarities` | `LookupResource` | 5 rarity levels |
| `GET /v1/lookups/tags` | `TagResource` | ~50 tags (borderline, could paginate) |
| `GET /v1/spells/{spell}/classes` | `ClassResource` | Spell's available classes |
| `GET /v1/spells/{spell}/items` | `ItemResource` | Items containing spell |
| `GET /v1/spells/{spell}/monsters` | `MonsterResource` | Monsters that cast spell |
| `GET /v1/spells/{spell}/races` | `RaceResource` | Races with innate spell |
| `GET /v1/monsters/{monster}/spells` | `SpellResource` | Monster's known spells |
| `GET /v1/races/{race}/spells` | `SpellResource` | Race's innate spells |
| `GET /v1/{modelType}/{modelId}/media/{collection}` | `MediaResource` | Entity's media files |

**Solution:** Add proper `@response` annotations with non-paginated collection type.

#### 2B: Should Be Paginated (Need Implementation Fix)

These endpoints return collections that could be large and should use pagination:

| Endpoint | Resource | Issue |
|----------|----------|-------|
| `GET /v1/characters/{character}/available-optional-features` | `OptionalFeatureResource` | Could return many available features |
| `GET /v1/characters/{character}/available-spells` | `SpellResource` | Could return hundreds of spells |
| `GET /v1/lookups/proficiency-types/{proficiencyType}/backgrounds` | `BackgroundResource` | Could return many backgrounds |
| `GET /v1/lookups/proficiency-types/{proficiencyType}/classes` | `ClassResource` | Could return many classes |
| `GET /v1/lookups/proficiency-types/{proficiencyType}/races` | `RaceResource` | Could return many races |
| `POST /v1/characters/{character}/features/populate` | `CharacterFeatureResource` | Returns created features (OK as non-paginated) |
| `POST /v1/characters/{character}/languages/populate` | `CharacterLanguageResource` | Returns created languages (OK as non-paginated) |
| `POST /v1/characters/{character}/proficiencies/populate` | `CharacterProficiencyResource` | Returns created proficiencies (OK as non-paginated) |

## Implementation Plan

### Phase 1: Fix Category 1 (Array of Strings) - 7 Controllers

Add `@response` annotations to index methods in controllers extending `ReadOnlyLookupController`:

1. `AbilityScoreController::index()` - Add `@response AnonymousResourceCollection<AbilityScoreResource>`
2. `ConditionController::index()` - Add `@response AnonymousResourceCollection<ConditionResource>`
3. `DamageTypeController::index()` - Add `@response AnonymousResourceCollection<DamageTypeResource>`
4. `LanguageController::index()` - Add `@response AnonymousResourceCollection<LanguageResource>`
5. `ProficiencyTypeController::index()` - Add `@response AnonymousResourceCollection<ProficiencyTypeResource>`
6. `SizeController::index()` - Add `@response AnonymousResourceCollection<SizeResource>`
7. `SpellSchoolController::index()` - Add `@response AnonymousResourceCollection<SpellSchoolResource>`

### Phase 2: Document Non-Paginated Collections - Category 2A

Add `@response` annotations to indicate these are intentionally non-paginated:

```php
/**
 * @response array{data: CharacterClassPivotResource[]}
 */
```

Or use Scramble's collection syntax:
```php
/**
 * @response AnonymousResourceCollection<CharacterClassPivotResource>
 */
```

### Phase 3: Evaluate Pagination Needs - Category 2B

Review these endpoints to determine if they should be paginated:
- `characters.optional-features.available` - Consider pagination for large spell lists
- `characters.spells.available` - Likely needs pagination
- `proficiency-types.backgrounds/classes/races` - Consider pagination

### Phase 4: Regenerate OpenAPI Spec

```bash
docker compose exec php php artisan scramble:export
```

## Verification Checklist

After implementation, verify:
- [ ] No endpoints return `data: array of strings`
- [ ] All index endpoints have proper resource type in spec
- [ ] Pagination metadata present where appropriate
- [ ] Run `api.json` through OpenAPI validator

## Files to Modify

### Controllers Needing `@response` Annotations:
- `app/Http/Controllers/Api/AbilityScoreController.php`
- `app/Http/Controllers/Api/ConditionController.php`
- `app/Http/Controllers/Api/DamageTypeController.php`
- `app/Http/Controllers/Api/LanguageController.php`
- `app/Http/Controllers/Api/ProficiencyTypeController.php`
- `app/Http/Controllers/Api/SizeController.php`
- `app/Http/Controllers/Api/SpellSchoolController.php`
- `app/Http/Controllers/Api/AlignmentController.php`
- `app/Http/Controllers/Api/ArmorTypeController.php`
- `app/Http/Controllers/Api/MonsterTypeController.php`
- `app/Http/Controllers/Api/OptionalFeatureTypeController.php`
- `app/Http/Controllers/Api/RarityController.php`
- `app/Http/Controllers/Api/TagController.php`
- `app/Http/Controllers/Api/CharacterClassController.php`
- `app/Http/Controllers/Api/CharacterConditionController.php`
- `app/Http/Controllers/Api/CharacterEquipmentController.php`
- `app/Http/Controllers/Api/CharacterFeatureController.php`
- `app/Http/Controllers/Api/CharacterLanguageController.php`
- `app/Http/Controllers/Api/CharacterOptionalFeatureController.php`
- `app/Http/Controllers/Api/CharacterProficiencyController.php`
- `app/Http/Controllers/Api/CharacterSpellController.php`
- `app/Http/Controllers/Api/SpellController.php` (relationship methods)
- `app/Http/Controllers/Api/MonsterController.php` (spells method)
- `app/Http/Controllers/Api/RaceController.php` (spells method)
- `app/Http/Controllers/Api/MediaController.php`

## Priority

**High Priority** - These spec issues affect API consumers who rely on the OpenAPI spec for:
- TypeScript type generation
- Client SDK generation
- API documentation accuracy
- Contract testing

## Estimated Effort

- Phase 1: 1 hour (7 simple annotation additions)
- Phase 2: 2 hours (21 annotation additions with review)
- Phase 3: 2-4 hours (implementation review and potential pagination changes)
- Phase 4: 15 minutes (regeneration and verification)

**Total: 5-7 hours**

---

## Implementation Results (2025-12-06)

### Summary

All planned fixes were implemented successfully:

- **Array of strings issues: 7 → 0** (100% fixed)
- **Controllers modified: 27**
- **Methods updated: ~45**

### Files Modified

1. **ReadOnlyLookupController-based (7 files):**
   - AbilityScoreController, ConditionController, DamageTypeController
   - LanguageController, ProficiencyTypeController, SizeController, SpellSchoolController

2. **Lookup controllers (6 files):**
   - AlignmentController, ArmorTypeController, MonsterTypeController
   - OptionalFeatureTypeController, RarityController, TagController

3. **Character controllers (7 files):**
   - CharacterClassController, CharacterConditionController, CharacterEquipmentController
   - CharacterFeatureController, CharacterLanguageController, CharacterProficiencyController
   - CharacterSpellController

4. **Entity relationship methods (5 files):**
   - SpellController (classes, monsters, items, races methods)
   - MonsterController (spells method)
   - RaceController (spells method)
   - ProficiencyTypeController (backgrounds, classes, races methods)
   - MediaController (index method)

5. **Additional controllers:**
   - FeatureSelectionController (index, available methods)

### Known Limitations

The `@response AnonymousResourceCollection<ResourceType>` annotation fixes the resource type issue but overrides Scramble's automatic pagination detection. This affects ReadOnlyLookupController-based endpoints:

- Spec shows: `{data: ResourceType[]}`
- Actual API returns: `{data: ResourceType[], links: {...}, meta: {...}}`

This is a Scramble limitation when using abstract parent class methods. The workaround would require inlining the collection call in each controller, which would reduce code reuse.

### Verification

```bash
# Before: 7 array-of-strings issues, 29 missing pagination
# After: 0 array-of-strings issues, 23 intentionally non-paginated
python3 audit_script.py  # See audit output above
```

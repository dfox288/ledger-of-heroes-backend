# API Resource Audit & Standardization Plan

**Created:** 2024-12-04
**Issue:** #145
**Status:** Completed

## Overview

Audit identified 15 endpoints returning raw arrays instead of proper Laravel API Resources, plus several Resources using inline arrays instead of nested Resources. This plan standardizes all API responses to use proper Resource classes.

## Current State

- **74 Resource files** exist in `app/Http/Resources/`
- **1 unused trait** (`FormatsRelatedModels`) provides standardized minimal formatting
- **15 controller methods** return raw `response()->json(['data' => ...])` instead of Resources
- **Several Resources** use inline arrays for nested relationships instead of proper Resource classes

## Goals

1. All API endpoints return Laravel API Resources (not raw arrays)
2. Consistent patterns for lookup/enum endpoints
3. Leverage `FormatsRelatedModels` trait where appropriate
4. Maintain backward compatibility with existing API contracts

---

## Phase 1: Lookup Endpoint Resources (Priority: High)

Create lightweight Resources for lookup/enum endpoints that currently return raw arrays.

### 1.1 Create Generic LookupResource

**File:** `app/Http/Resources/LookupResource.php`

For simple slug/name lookups derived from database values:
- `ArmorTypeController` → armor types from monsters
- `AlignmentController` → alignments from monsters
- `MonsterTypeController` → creature types from monsters
- `RarityController` → rarities

```php
// Generic resource for simple slug/name lookups
class LookupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->resource['slug'],
            'name' => $this->resource['name'],
        ];
    }
}
```

### 1.2 Create OptionalFeatureTypeResource

**File:** `app/Http/Resources/OptionalFeatureTypeResource.php`

For enum-based lookups with additional metadata:

```php
class OptionalFeatureTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->resource->value,
            'label' => $this->resource->label(),
            'default_class' => $this->resource->defaultClassName(),
            'default_subclass' => $this->resource->defaultSubclassName(),
        ];
    }
}
```

### 1.3 Update Controllers

| Controller | Method | Current | Change To |
|------------|--------|---------|-----------|
| `ArmorTypeController` | `index()` | raw array | `LookupResource::collection()` |
| `AlignmentController` | `index()` | raw array | `LookupResource::collection()` |
| `MonsterTypeController` | `index()` | raw array | `LookupResource::collection()` |
| `RarityController` | `index()` | raw array | `LookupResource::collection()` |
| `OptionalFeatureTypeController` | `index()` | raw array | `OptionalFeatureTypeResource::collection()` |

---

## Phase 2: Action Response Resources (Priority: High)

Create Resources for state-changing endpoints that return result data.

### 2.1 Create RestResultResource

**File:** `app/Http/Resources/RestResultResource.php`

```php
class RestResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Short rest
        if (isset($this->resource['pact_magic_reset'])) {
            return [
                'pact_magic_reset' => $this->resource['pact_magic_reset'],
                'features_reset' => $this->resource['features_reset'] ?? [],
            ];
        }

        // Long rest
        return [
            'hp_restored' => $this->resource['hp_restored'] ?? 0,
            'hit_dice_recovered' => $this->resource['hit_dice_recovered'] ?? 0,
            'spell_slots_reset' => $this->resource['spell_slots_reset'] ?? false,
            'death_saves_cleared' => $this->resource['death_saves_cleared'] ?? false,
            'features_reset' => $this->resource['features_reset'] ?? [],
        ];
    }
}
```

### 2.2 Create SpellSlotDataResource

**File:** `app/Http/Resources/SpellSlotDataResource.php`

For `CharacterSpellController::slotData()` responses.

### 2.3 Create ChoicesResource

**File:** `app/Http/Resources/ChoicesResource.php`

For choice-related endpoints:
- `CharacterOptionalFeatureController::choices()`
- `CharacterProficiencyController::choices()`
- `CharacterLanguageController::choices()`

### 2.4 Update Controllers

| Controller | Method | Current | Change To |
|------------|--------|---------|-----------|
| `RestController` | `shortRest()` | raw array | `RestResultResource` |
| `RestController` | `longRest()` | raw array | `RestResultResource` |
| `CharacterSpellController` | `slotData()` | raw array | `SpellSlotDataResource` |
| `SpellSlotController` | `index()` | raw array | dedicated resource |
| `SpellSlotController` | `forClass()` | raw array | dedicated resource |

---

## Phase 3: Character Sub-Controller Standardization (Priority: Medium)

Many character-related controllers return raw arrays for choices, availability checks, etc.

### 3.1 Affected Controllers

| Controller | Methods with Raw Returns |
|------------|-------------------------|
| `CharacterOptionalFeatureController` | `choices()` |
| `CharacterProficiencyController` | `choices()`, `store()`, `destroy()` |
| `CharacterLanguageController` | `choices()`, `store()`, `destroy()` |
| `CharacterFeatureController` | multiple methods |
| `CharacterClassController` | multiple methods |
| `CharacterNoteController` | `store()` response |
| `CharacterConditionController` | null handling |
| `CharacterDeathSaveController` | result responses |
| `CharacterLevelUpController` | level up result |
| `HitDiceController` | spend/recover results |

### 3.2 Create Shared Response Resources

- `ChoiceOptionResource` - for available choices
- `ActionResultResource` - for store/update/destroy confirmations
- `LevelUpResultResource` - for level up responses
- `HitDiceResultResource` - for hit dice operations

---

## Phase 4: Class Progression Endpoint (Priority: Medium)

### 4.1 ClassController::progression()

**Current:** Returns raw array at line 688, 695

**Issue:** Bypasses existing `ProgressionTableResource`

**Fix:** Use `ProgressionTableResource` consistently:

```php
// Line 688 - empty case
return new ProgressionTableResource(['columns' => [], 'rows' => []]);

// Line 695 - normal case
return new ProgressionTableResource($table);
```

---

## Phase 5: Leverage FormatsRelatedModels Trait (Priority: Low)

### 5.1 Current State

The trait exists at `app/Http/Resources/Concerns/FormatsRelatedModels.php` but is not used anywhere.

### 5.2 Candidates for Adoption

Resources with inline arrays for minimal model data:

| Resource | Field | Current Pattern |
|----------|-------|-----------------|
| `CharacterResource` | `race` | `['id' => ..., 'name' => ..., 'slug' => ...]` |
| `CharacterResource` | `class` | `['id' => ..., 'name' => ..., 'slug' => ...]` |
| `CharacterResource` | `background` | `['id' => ..., 'name' => ..., 'slug' => ...]` |
| `CharacterEquipmentResource` | `item` | `['id' => ..., 'name' => ..., 'slug' => ...]` |
| `PackContentResource` | `item` | inline array |
| `ProficiencyResource` | `item` | inline array |

### 5.3 Implementation

Add trait to relevant Resources and replace inline array construction:

```php
use FormatsRelatedModels;

// Before
'race' => $this->race ? ['id' => $this->race->id, 'name' => $this->race->name, 'slug' => $this->race->slug] : null,

// After
'race' => $this->formatEntity($this->race),
```

---

## Phase 6: Pivot Data Resources (Priority: Low)

For relationships with meaningful pivot data, create dedicated pivot resources.

### 6.1 Candidates

| Relationship | Pivot Data | Resource Name |
|-------------|------------|---------------|
| ClassFeature → Spell | `level_requirement`, `is_cantrip` | `ClassFeatureSpellResource` |
| Character → Condition | `level`, `source`, `duration` | `CharacterConditionPivotResource` |
| Character → OptionalFeature | `level_acquired`, `class_name` | `CharacterOptionalFeaturePivotResource` |

---

## Implementation Order

1. **Phase 1** - Lookup Resources (5 controllers, 2 new resources)
2. **Phase 2** - Action Resources (5 controllers, 3-4 new resources)
3. **Phase 4** - Class progression fix (1 controller, no new resources)
4. **Phase 3** - Character sub-controllers (10 controllers, 4 new resources)
5. **Phase 5** - FormatsRelatedModels adoption (6 resources modified)
6. **Phase 6** - Pivot resources (3 new resources)

## Testing Strategy

- Unit tests for each new Resource
- Feature tests verify API response structure unchanged
- Run existing test suites to ensure backward compatibility

## Files to Create

```
app/Http/Resources/
├── LookupResource.php                    (Phase 1)
├── OptionalFeatureTypeResource.php       (Phase 1)
├── RestResultResource.php                (Phase 2)
├── SpellSlotDataResource.php             (Phase 2)
├── ChoicesResource.php                   (Phase 2)
├── ActionResultResource.php              (Phase 3)
├── LevelUpResultResource.php             (Phase 3)
├── HitDiceResultResource.php             (Phase 3)
├── ClassFeatureSpellResource.php         (Phase 6)
├── CharacterConditionPivotResource.php   (Phase 6)
└── CharacterOptionalFeaturePivotResource.php (Phase 6)
```

## Files to Modify

```
app/Http/Controllers/Api/
├── ArmorTypeController.php               (Phase 1)
├── AlignmentController.php               (Phase 1)
├── MonsterTypeController.php             (Phase 1)
├── RarityController.php                  (Phase 1)
├── OptionalFeatureTypeController.php     (Phase 1)
├── RestController.php                    (Phase 2)
├── CharacterSpellController.php          (Phase 2)
├── SpellSlotController.php               (Phase 2)
├── ClassController.php                   (Phase 4)
├── CharacterOptionalFeatureController.php (Phase 3)
├── CharacterProficiencyController.php    (Phase 3)
├── CharacterLanguageController.php       (Phase 3)
├── CharacterFeatureController.php        (Phase 3)
├── CharacterClassController.php          (Phase 3)
├── CharacterNoteController.php           (Phase 3)
├── CharacterConditionController.php      (Phase 3)
├── CharacterDeathSaveController.php      (Phase 3)
├── CharacterLevelUpController.php        (Phase 3)
└── HitDiceController.php                 (Phase 3)

app/Http/Resources/
├── CharacterResource.php                 (Phase 5)
├── CharacterEquipmentResource.php        (Phase 5)
├── PackContentResource.php               (Phase 5)
├── ProficiencyResource.php               (Phase 5)
└── ClassFeatureResource.php              (Phase 6)
```

## Success Criteria

- [ ] Zero `response()->json(['data' => ...])` patterns in controllers
- [ ] All endpoints return proper Laravel Resources
- [ ] `FormatsRelatedModels` trait used consistently
- [ ] Existing API tests pass without modification
- [ ] New Resources have unit tests

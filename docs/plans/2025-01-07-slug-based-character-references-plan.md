# Slug-Based Character References - Implementation Plan

**Design Doc:** `docs/plans/2025-01-07-slug-based-character-references-design.md`
**Branch:** `feature/slug-based-character-references`
**Runner:** Sail (`sail artisan`, `sail composer`, etc.)

---

## Phase 1: Entity Tables - Add `full_slug` Column

Add source-prefixed slug column to all entity tables that characters reference.

### 1.1 Migration: Add `full_slug` to Entity Tables

**File:** `database/migrations/xxxx_add_full_slug_to_entity_tables.php`

```php
Schema::table('races', function (Blueprint $table) {
    $table->string('full_slug', 150)->nullable()->unique()->after('slug');
});
// Repeat for: backgrounds, classes, spells, items, languages,
// skills, proficiency_types, conditions, optional_features, feats, senses
```

**Tables to update:**
- `races`
- `backgrounds`
- `classes`
- `spells`
- `items`
- `languages`
- `skills`
- `proficiency_types`
- `conditions`
- `optional_features`
- `feats`
- `senses`

**Verification:**
```bash
sail artisan migrate
sail artisan tinker --execute="Schema::hasColumn('races', 'full_slug')"
```

### 1.2 Backfill Command: Populate `full_slug` for Existing Data

**File:** `app/Console/Commands/BackfillFullSlugs.php`

Logic:
1. For each entity type, query all records
2. Look up the source code from `entity_sources` pivot table
3. Generate `full_slug` = `{source_code}:{slug}`
4. Handle entities with multiple sources (use primary/first source)

**Verification:**
```bash
sail artisan backfill:full-slugs
sail artisan tinker --execute="App\Models\Race::whereNotNull('full_slug')->count()"
```

### 1.3 Update Entity Models

Add `full_slug` to `$fillable` and add accessor if needed.

**Files:**
- `app/Models/Race.php`
- `app/Models/Background.php`
- `app/Models/CharacterClass.php`
- `app/Models/Spell.php`
- `app/Models/Item.php`
- `app/Models/Language.php`
- `app/Models/Skill.php`
- `app/Models/ProficiencyType.php`
- `app/Models/Condition.php`
- `app/Models/OptionalFeature.php`
- `app/Models/Feat.php`
- `app/Models/Sense.php`

**Verification:**
```bash
sail artisan tinker --execute="App\Models\Race::first()->full_slug"
```

---

## Phase 2: Update Importers - Generate `full_slug` on Import

### 2.1 Update Base Importer / Trait

Add helper method to generate `full_slug` from source code and slug.

**File:** `app/Services/Importers/ImportsEntities.php` (or base trait)

```php
protected function generateFullSlug(string $sourceCode, string $slug): string
{
    return strtolower($sourceCode) . ':' . $slug;
}
```

### 2.2 Update Individual Importers

Each importer sets `full_slug` during entity creation/update.

**Files:**
- `app/Services/Importers/RaceImporter.php`
- `app/Services/Importers/BackgroundImporter.php`
- `app/Services/Importers/ClassImporter.php`
- `app/Services/Importers/SpellImporter.php`
- `app/Services/Importers/ItemImporter.php`
- `app/Services/Importers/LanguageImporter.php`
- `app/Services/Importers/SkillImporter.php`
- `app/Services/Importers/ProficiencyTypeImporter.php`
- `app/Services/Importers/ConditionImporter.php`
- `app/Services/Importers/OptionalFeatureImporter.php`
- `app/Services/Importers/FeatImporter.php`
- `app/Services/Importers/SenseImporter.php`

**Verification:**
```bash
sail artisan import:races --source=PHB
sail artisan tinker --execute="App\Models\Race::where('full_slug', 'like', 'phb:%')->count()"
```

### 2.3 Update Importer Tests

Add assertions for `full_slug` generation.

**Verification:**
```bash
sail artisan test --testsuite=Importers --filter=full_slug
```

---

## Phase 3: Character Table Schema Changes

### 3.1 Migration: Change Character Tables to Use Slugs

**File:** `database/migrations/xxxx_convert_character_tables_to_slugs.php`

```php
// characters table
Schema::table('characters', function (Blueprint $table) {
    $table->dropForeign(['race_id']);
    $table->dropForeign(['background_id']);
    $table->dropColumn(['race_id', 'background_id']);
    $table->string('race_slug', 150)->nullable()->after('name');
    $table->string('background_slug', 150)->nullable()->after('race_slug');
});

// character_classes table
Schema::table('character_classes', function (Blueprint $table) {
    $table->dropForeign(['class_id']);
    $table->dropForeign(['subclass_id']);
    $table->dropColumn(['class_id', 'subclass_id']);
    $table->string('class_slug', 150)->after('character_id');
    $table->string('subclass_slug', 150)->nullable()->after('class_slug');
});

// Repeat for: character_spells, character_equipment, character_languages,
// character_proficiencies, character_conditions, feature_selections
```

**Verification:**
```bash
sail artisan migrate
sail artisan tinker --execute="Schema::hasColumn('characters', 'race_slug')"
```

### 3.2 Update Character Models - Relationships

**File:** `app/Models/Character.php`

```php
public function race(): BelongsTo
{
    return $this->belongsTo(Race::class, 'race_slug', 'full_slug');
}

public function background(): BelongsTo
{
    return $this->belongsTo(Background::class, 'background_slug', 'full_slug');
}
```

**Files to update:**
- `app/Models/Character.php`
- `app/Models/CharacterClassPivot.php`
- `app/Models/CharacterSpell.php`
- `app/Models/CharacterEquipment.php`
- `app/Models/CharacterLanguage.php`
- `app/Models/CharacterProficiency.php`
- `app/Models/CharacterCondition.php`
- `app/Models/FeatureSelection.php`

**Verification:**
```bash
sail artisan tinker --execute="App\Models\Character::factory()->create(['race_slug' => 'phb:human'])->race"
```

### 3.3 Update Character Factories

Update factories to use slug format.

**Files:**
- `database/factories/CharacterFactory.php`
- `database/factories/CharacterClassPivotFactory.php`
- `database/factories/CharacterSpellFactory.php`
- `database/factories/CharacterEquipmentFactory.php`
- `database/factories/CharacterLanguageFactory.php`
- `database/factories/CharacterProficiencyFactory.php`
- `database/factories/CharacterConditionFactory.php`
- `database/factories/FeatureSelectionFactory.php`

**Verification:**
```bash
sail artisan tinker --execute="App\Models\Character::factory()->create()"
```

---

## Phase 4: API Layer Changes

### 4.1 Update Form Requests

Change validation from `exists:table,id` to string validation.
Add `prepareForValidation()` to map API field names to DB columns.

**Files:**
- `app/Http/Requests/Character/CharacterStoreRequest.php`
- `app/Http/Requests/Character/CharacterUpdateRequest.php`
- `app/Http/Requests/Character/AddCharacterClassRequest.php`
- `app/Http/Requests/Character/ReplaceCharacterClassRequest.php`
- `app/Http/Requests/Character/SetSubclassRequest.php`
- `app/Http/Requests/Character/ResolveChoiceRequest.php`
- `app/Http/Requests/CharacterEquipment/StoreEquipmentRequest.php`
- `app/Http/Requests/CharacterCondition/StoreCharacterConditionRequest.php`

**Example transformation:**
```php
// Before
'race_id' => ['required', 'exists:races,id'],

// After
'race' => ['sometimes', 'nullable', 'string', 'max:150'],

protected function prepareForValidation(): void
{
    if ($this->has('race')) {
        $this->merge(['race_slug' => $this->input('race')]);
    }
}
```

**Verification:**
```bash
sail artisan test --testsuite=Feature-DB --filter=CharacterStoreRequest
```

### 4.2 Update Controllers

Update controllers to use slug-based lookups.

**Files:**
- `app/Http/Controllers/Api/CharacterController.php`
- `app/Http/Controllers/Api/CharacterClassController.php`
- `app/Http/Controllers/Api/CharacterSpellController.php`
- `app/Http/Controllers/Api/CharacterEquipmentController.php`
- `app/Http/Controllers/Api/CharacterLanguageController.php`
- `app/Http/Controllers/Api/CharacterProficiencyController.php`
- `app/Http/Controllers/Api/CharacterConditionController.php`
- `app/Http/Controllers/Api/CharacterChoiceController.php`
- `app/Http/Controllers/Api/CharacterFeatureController.php`

**Verification:**
```bash
sail artisan test --testsuite=Feature-DB --filter=CharacterController
```

### 4.3 Update API Resources

Update resources to output slugs instead of IDs.

**Files:**
- `app/Http/Resources/CharacterResource.php`
- `app/Http/Resources/CharacterClassPivotResource.php`
- `app/Http/Resources/CharacterSpellResource.php`
- `app/Http/Resources/CharacterEquipmentResource.php`
- `app/Http/Resources/CharacterLanguageResource.php`
- `app/Http/Resources/CharacterProficiencyResource.php`
- `app/Http/Resources/CharacterConditionResource.php`
- `app/Http/Resources/FeatureSelectionResource.php`

**Example transformation:**
```php
// Before
'race_id' => $this->race_id,
'race' => new RaceResource($this->whenLoaded('race')),

// After
'race' => $this->race_slug,
'race_data' => new RaceResource($this->whenLoaded('race')),
```

**Verification:**
```bash
sail artisan test --testsuite=Feature-DB --filter=CharacterResource
```

---

## Phase 5: Service Layer Changes

### 5.1 Update Character Services

Update services to work with slugs.

**Files:**
- `app/Services/CharacterChoiceService.php`
- `app/Services/CharacterProficiencyService.php`
- `app/Services/CharacterLanguageService.php`
- `app/Services/SpellManagerService.php`
- `app/Services/EquipmentManagerService.php`
- `app/Services/AddClassService.php`
- `app/Services/ReplaceClassService.php`
- `app/Services/HitDiceService.php`
- `app/Services/SpellSlotService.php`
- `app/Services/CharacterStatCalculator.php`

**Verification:**
```bash
sail artisan test --testsuite=Unit-DB --filter=Service
```

---

## Phase 6: Validation Endpoint

### 6.1 Create Character Validation Service

**File:** `app/Services/CharacterValidationService.php`

Methods:
- `validate(Character $character): ValidationResult`
- `validateAll(): Collection<ValidationResult>`

Checks for:
- Missing race/background/class references
- Missing spell references
- Missing item references
- Missing language references
- Missing proficiency references
- Subclass requirement warnings

### 6.2 Create Validation Controller

**File:** `app/Http/Controllers/Api/CharacterValidationController.php`

Endpoints:
- `GET /api/v1/characters/{character}/validate`
- `GET /api/v1/characters/validate-all`

### 6.3 Create Validation Resource

**File:** `app/Http/Resources/CharacterValidationResource.php`

### 6.4 Add Routes

**File:** `routes/api.php`

```php
Route::get('characters/{character}/validate', [CharacterValidationController::class, 'show']);
Route::get('characters/validate-all', [CharacterValidationController::class, 'index']);
```

### 6.5 Write Validation Tests

**File:** `tests/Feature/Api/CharacterValidationApiTest.php`

**Verification:**
```bash
sail artisan test --filter=CharacterValidation
```

---

## Phase 7: Export/Import Feature

### 7.1 Create Character Export Service

**File:** `app/Services/CharacterExportService.php`

Method: `export(Character $character): array`

Returns portable JSON structure with all slugs.

### 7.2 Create Character Import Service

**File:** `app/Services/CharacterImportService.php`

Method: `import(array $data, User $user): Character`

Creates character from exported JSON.

### 7.3 Create Export/Import Controller

**File:** `app/Http/Controllers/Api/CharacterExportController.php`

Endpoints:
- `GET /api/v1/characters/{character}/export`
- `POST /api/v1/characters/import`

### 7.4 Write Export/Import Tests

**File:** `tests/Feature/Api/CharacterExportApiTest.php`

**Verification:**
```bash
sail artisan test --filter=CharacterExport
```

---

## Phase 8: Test Suite Updates

### 8.1 Update Character API Tests

Update all existing character tests to use slug format.

**Files:**
- `tests/Feature/Api/CharacterApiTest.php`
- `tests/Feature/Api/CharacterClassApiTest.php`
- `tests/Feature/Api/CharacterSpellApiTest.php`
- `tests/Feature/Api/CharacterEquipmentApiTest.php`
- `tests/Feature/Api/CharacterLanguageApiTest.php`
- `tests/Feature/Api/CharacterProficiencyApiTest.php`
- `tests/Feature/Api/CharacterConditionApiTest.php`
- `tests/Feature/Api/CharacterChoiceApiTest.php`

### 8.2 Update Unit Tests

**Files:**
- `tests/Unit/Services/CharacterChoiceServiceTest.php`
- `tests/Unit/Services/CharacterProficiencyServiceTest.php`
- `tests/Unit/Services/CharacterLanguageServiceTest.php`
- `tests/Unit/Services/SpellManagerServiceTest.php`
- `tests/Unit/Services/EquipmentManagerServiceTest.php`

### 8.3 Run Full Test Suite

```bash
sail artisan test --testsuite=Unit-Pure
sail artisan test --testsuite=Unit-DB
sail artisan test --testsuite=Feature-DB
sail artisan test --testsuite=Feature-Search
```

---

## Phase 9: Quality Gates & Documentation

### 9.1 Code Formatting

```bash
sail composer pint
```

### 9.2 Static Analysis (if configured)

```bash
sail composer analyse
```

### 9.3 Update API Documentation

Update OpenAPI/Scramble annotations for changed endpoints.

### 9.4 Update CHANGELOG.md

Document breaking changes.

### 9.5 Update PROJECT-STATUS.md

Update metrics if applicable.

---

## Execution Order Summary

| Phase | Description | Depends On | Estimated Tasks |
|-------|-------------|------------|-----------------|
| 1 | Entity Tables - Add `full_slug` | - | 3 |
| 2 | Update Importers | Phase 1 | 3 |
| 3 | Character Table Schema | Phase 1 | 3 |
| 4 | API Layer | Phase 3 | 3 |
| 5 | Service Layer | Phase 3, 4 | 1 |
| 6 | Validation Endpoint | Phase 4, 5 | 5 |
| 7 | Export/Import | Phase 4, 5 | 4 |
| 8 | Test Updates | Phase 4, 5 | 3 |
| 9 | Quality & Docs | Phase 8 | 5 |

---

## GitHub Issues Breakdown

1. **Phase 1:** Add `full_slug` to entity tables
2. **Phase 2:** Update importers to generate `full_slug`
3. **Phase 3:** Migrate character tables to slug-based references
4. **Phase 4:** Update API layer (requests, controllers, resources)
5. **Phase 5:** Update service layer
6. **Phase 6:** Add character validation endpoint
7. **Phase 7:** Add character export/import feature
8. **Phase 8:** Update test suite
9. **Phase 9:** Quality gates and documentation

# Implementation Plan: D&D 5e Conditions & Proficiency Types

**Date:** 2025-11-18
**Branch:** `schema-redesign`
**Estimated Effort:** 4-6 hours

## Project Context

**Current State:**
- 294 tests passing
- 44 database tables
- 9 existing seeders
- Sail (Docker) environment

**Goal:**
Add two static lookup tables (`conditions`, `proficiency_types`) to normalize D&D 5e game mechanics, enabling structured queries and filtering while maintaining backward compatibility with existing free-text proficiency storage.

---

## Phase 1: Scaffolding & Preparation

### Task 1.1: Verify Environment
```bash
# Confirm Sail is running
docker compose ps

# Verify current test state (baseline: 294 passing)
docker compose exec php php artisan test

# Check current migration state
docker compose exec php php artisan migrate:status
```

**Success Criteria:** All services running, all tests passing

---

## Phase 2: Data Model - Conditions Table

### Task 2.1: Create Conditions Migration (TDD)
**Test First:**
- File: `tests/Feature/Migrations/ConditionsTableTest.php`
- Verify table exists with correct columns
- Verify unique constraint on slug
- 3 tests total

**Then Implement:**
- File: `database/migrations/2025_11_18_XXXXXX_create_conditions_table.php`
- Columns: `id`, `name`, `slug`, `description`
- Unique index on `slug`
- No timestamps (static data)

```bash
docker compose exec php php artisan make:migration create_conditions_table
docker compose exec php php artisan test --filter=ConditionsTableTest
```

**Commit:** `feat: add conditions table migration with tests`

### Task 2.2: Create Condition Model
**File:** `app/Models/Condition.php`
- No timestamps
- Fillable: name, slug, description
- Cast: slug to lowercase

**Test:**
- File: `tests/Feature/Models/ConditionModelTest.php`
- Test basic model creation
- Test slug normalization

**Commit:** `feat: add Condition model with tests`

### Task 2.3: Create Condition Seeder (TDD)
**Test First:**
- File: `tests/Feature/Seeders/ConditionSeederTest.php`
- Verify 15 conditions seeded
- Verify specific conditions exist (Charmed, Frightened, etc.)
- Verify slugs are normalized

**Then Implement:**
- File: `database/seeders/ConditionSeeder.php`
- Seed all 15 D&D 5e conditions:
  - Blinded, Charmed, Deafened, Frightened, Grappled
  - Incapacitated, Invisible, Paralyzed, Petrified, Poisoned
  - Prone, Restrained, Stunned, Unconscious, Exhaustion

**Update:** `database/seeders/DatabaseSeeder.php` - Add ConditionSeeder

```bash
docker compose exec php php artisan make:seeder ConditionSeeder
docker compose exec php php artisan db:seed --class=ConditionSeeder
docker compose exec php php artisan test --filter=ConditionSeederTest
```

**Commit:** `feat: add ConditionSeeder with 15 D&D 5e conditions`

---

## Phase 3: Data Model - Proficiency Types Table

### Task 3.1: Create Proficiency Types Migration (TDD)
**Test First:**
- File: `tests/Feature/Migrations/ProficiencyTypesTableTest.php`
- Verify table structure
- Verify category enum/check constraint
- Verify nullable item_id FK
- 4 tests total

**Then Implement:**
- File: `database/migrations/2025_11_18_XXXXXX_create_proficiency_types_table.php`
- Columns: `id`, `name`, `category`, `item_id` (nullable)
- Category values: weapon, armor, tool, vehicle, language, gaming_set, musical_instrument
- Foreign key: `item_id` references `items.id`
- Index on category

```bash
docker compose exec php php artisan make:migration create_proficiency_types_table
docker compose exec php php artisan test --filter=ProficiencyTypesTableTest
```

**Commit:** `feat: add proficiency_types table migration with tests`

### Task 3.2: Create ProficiencyType Model
**File:** `app/Models/ProficiencyType.php`
- No timestamps
- Fillable: name, category, item_id
- Relationship: `belongsTo(Item::class)`
- Scope: `byCategory($category)`

**Test:**
- File: `tests/Feature/Models/ProficiencyTypeModelTest.php`
- Test model creation
- Test item relationship
- Test category scope

**Commit:** `feat: add ProficiencyType model with tests`

### Task 3.3: Create ProficiencyType Seeder (TDD)
**Test First:**
- File: `tests/Feature/Seeders/ProficiencyTypeSeederTest.php`
- Verify proficiency types by category
- Verify armor types: Light, Medium, Heavy, Shields
- Verify weapon categories: Simple, Martial
- Verify specific weapons: Longsword, Shortsword, Longbow, etc.
- Verify tools: Smith's tools, Thieves' tools, etc.

**Then Implement:**
- File: `database/seeders/ProficiencyTypeSeeder.php`
- Seed ~60 proficiency types across categories:
  - **Armor:** Light armor, Medium armor, Heavy armor, Shields
  - **Weapons:** Simple weapons, Martial weapons, specific weapons (Longsword, Shortsword, etc.)
  - **Tools:** Artisan's tools (Smith's, Brewer's, Mason's, etc.), Thieves' tools
  - **Vehicles:** Land vehicles, Water vehicles
  - **Languages:** Common, Elvish, Dwarvish, etc.
  - **Gaming Sets:** Dice set, Playing card set
  - **Musical Instruments:** Lute, Flute, Horn, etc.

**Update:** `database/seeders/DatabaseSeeder.php` - Add ProficiencyTypeSeeder

```bash
docker compose exec php php artisan make:seeder ProficiencyTypeSeeder
docker compose exec php php artisan db:seed --class=ProficiencyTypeSeeder
docker compose exec php php artisan test --filter=ProficiencyTypeSeederTest
```

**Commit:** `feat: add ProficiencyTypeSeeder with 60+ proficiency types`

---

## Phase 4: Data Model - Entity Conditions Junction

### Task 4.1: Create Entity Conditions Migration (TDD)
**Test First:**
- File: `tests/Feature/Migrations/EntityConditionsTableTest.php`
- Verify polymorphic columns
- Verify condition_id FK
- Verify effect_type column
- Verify indexes

**Then Implement:**
- File: `database/migrations/2025_11_18_XXXXXX_create_entity_conditions_table.php`
- Polymorphic: `reference_type`, `reference_id`
- `condition_id` FK to conditions
- `effect_type` enum: inflicts, immunity, resistance, advantage
- Indexes on polymorphic pair and condition_id

```bash
docker compose exec php php artisan make:migration create_entity_conditions_table
docker compose exec php php artisan test --filter=EntityConditionsTableTest
```

**Commit:** `feat: add entity_conditions polymorphic junction table`

### Task 4.2: Create EntityCondition Model
**File:** `app/Models/EntityCondition.php`
- No timestamps
- Fillable: reference_type, reference_id, condition_id, effect_type
- Polymorphic: `morphTo('reference')`
- Relationship: `belongsTo(Condition::class)`

**Test:**
- File: `tests/Feature/Models/EntityConditionModelTest.php`
- Test polymorphic relationships (Spell, Monster, Item)
- Test condition relationship

**Commit:** `feat: add EntityCondition model with polymorphic tests`

---

## Phase 5: Update Proficiencies Table

### Task 5.1: Add proficiency_type_id Column (TDD)
**Test First:**
- File: `tests/Feature/Migrations/AddProficiencyTypeIdTest.php`
- Verify column exists
- Verify nullable FK to proficiency_types
- Verify index

**Then Implement:**
- File: `database/migrations/2025_11_18_XXXXXX_add_proficiency_type_id_to_proficiencies.php`
- Add `proficiency_type_id` (nullable, FK to proficiency_types)
- Add index

```bash
docker compose exec php php artisan make:migration add_proficiency_type_id_to_proficiencies_table
docker compose exec php php artisan test --filter=AddProficiencyTypeIdTest
```

**Commit:** `feat: add proficiency_type_id FK to proficiencies table`

### Task 5.2: Update Proficiency Model
**File:** `app/Models/Proficiency.php`
- Add `proficiency_type_id` to fillable
- Add relationship: `belongsTo(ProficiencyType::class, 'proficiency_type_id')`
- Add cast for proficiency_type_id

**Test:**
- Update: `tests/Feature/Models/ProficiencyModelTest.php`
- Test proficiency type relationship
- Test eager loading

**Commit:** `feat: add proficiencyType relationship to Proficiency model`

---

## Phase 6: Model Factories

### Task 6.1: Create Condition Factory
**File:** `database/factories/ConditionFactory.php`
- Basic factory (though likely won't be used - static seeded data)
- States: none needed (use seeded data in tests)

**Test:**
- File: `tests/Unit/Factories/ConditionFactoryTest.php`
- Verify basic creation works

**Commit:** `feat: add Condition factory`

### Task 6.2: Create ProficiencyType Factory
**File:** `database/factories/ProficiencyTypeFactory.php`
- States: `weapon()`, `armor()`, `tool()`

**Test:**
- File: `tests/Unit/Factories/ProficiencyTypeFactoryTest.php`
- Test factory states
- Test category assignment

**Commit:** `feat: add ProficiencyType factory with states`

### Task 6.3: Create EntityCondition Factory
**File:** `database/factories/EntityConditionFactory.php`
- State: `forEntity($type, $id)`
- State: `inflicts()`, `immunity()`, `resistance()`

**Test:**
- File: `tests/Unit/Factories/EntityConditionFactoryTest.php`
- Test polymorphic assignment
- Test effect types

**Commit:** `feat: add EntityCondition factory with polymorphic states`

### Task 6.4: Update Proficiency Factory
**File:** `database/factories/ProficiencyFactory.php`
- Add state: `withType(ProficiencyType $type)`

**Test:**
- Update: `tests/Unit/Factories/ProficiencyFactoryTest.php`
- Test proficiency type relationship

**Commit:** `feat: update ProficiencyFactory to support proficiency types`

---

## Phase 7: API Layer - Lookup Endpoints

### Task 7.1: Create Condition API (TDD)
**Test First:**
- File: `tests/Feature/Api/ConditionApiTest.php`
- Test index endpoint
- Test show endpoint
- Test pagination
- Test search by name/slug
- 5 tests total

**Then Implement:**
- File: `app/Http/Controllers/Api/ConditionController.php`
  - `index()` - paginated list
  - `show(Condition $condition)` - single resource
- File: `app/Http/Resources/ConditionResource.php`
  - Fields: id, name, slug, description
- Routes: `routes/api.php` - Add condition routes

```bash
docker compose exec php php artisan make:controller Api/ConditionController --api
docker compose exec php php artisan make:resource ConditionResource
docker compose exec php php artisan test --filter=ConditionApiTest
```

**Commit:** `feat: add Condition API endpoints with tests`

### Task 7.2: Create ProficiencyType API (TDD)
**Test First:**
- File: `tests/Feature/Api/ProficiencyTypeApiTest.php`
- Test index endpoint
- Test show endpoint
- Test category filter
- Test search
- Test item relationship
- 6 tests total

**Then Implement:**
- File: `app/Http/Controllers/Api/ProficiencyTypeController.php`
  - `index()` - paginated, filterable by category
  - `show(ProficiencyType $proficiencyType)`
- File: `app/Http/Resources/ProficiencyTypeResource.php`
  - Fields: id, name, category
  - Relationships: item (whenLoaded)
- Routes: `routes/api.php`

```bash
docker compose exec php php artisan make:controller Api/ProficiencyTypeController --api
docker compose exec php php artisan make:resource ProficiencyTypeResource
docker compose exec php php artisan test --filter=ProficiencyTypeApiTest
```

**Commit:** `feat: add ProficiencyType API endpoints with tests`

### Task 7.3: Update Lookup API Tests
**File:** `tests/Feature/Api/LookupApiTest.php`
- Add tests for new lookup endpoints
- Verify count increased from 8 to 10 lookup endpoints

**Commit:** `test: update LookupApiTest for new condition/proficiency endpoints`

---

## Phase 8: API Layer - Resource Updates

### Task 8.1: Create EntityCondition Resource
**File:** `app/Http/Resources/EntityConditionResource.php`
- Fields: id, effect_type
- Relationships: condition (always loaded)

**Test:**
- Integration with existing entity resources (next task)

**Commit:** `feat: add EntityConditionResource`

### Task 8.2: Update Proficiency Resource
**File:** `app/Http/Resources/ProficiencyResource.php`
- Add: proficiency_type relationship (whenLoaded)

**Test:**
- Update existing API tests to verify new field

**Commit:** `feat: add proficiencyType to ProficiencyResource`

### Task 8.3: Add Conditions to Spell Resource (Future Placeholder)
**Note:** Parser integration needed first - mark as TODO
- `SpellResource` would include `conditions` relationship
- Requires SpellXmlParser updates to extract conditions

**Skip for now** - Phase 4 handles parser integration

---

## Phase 9: Quality Gates

### Task 9.1: Run Full Test Suite
```bash
docker compose exec php php artisan test
```

**Success Criteria:**
- All existing 294 tests pass
- ~40 new tests pass (migrations, models, seeders, API)
- Total: ~334 tests passing

### Task 9.2: Verify Database State
```bash
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php php artisan tinker --execute="
  echo 'Conditions: ' . \App\Models\Condition::count() . PHP_EOL;
  echo 'Proficiency Types: ' . \App\Models\ProficiencyType::count() . PHP_EOL;
"
```

**Success Criteria:**
- 15 conditions seeded
- 60+ proficiency types seeded

### Task 9.3: Check API Documentation
```bash
# Visit http://localhost/docs/api
# Verify new endpoints appear:
# - GET /api/v1/conditions
# - GET /api/v1/proficiency-types
```

**Success Criteria:** Scramble auto-documentation updated with 2 new endpoints (25 total)

### Task 9.4: Verify Routes
```bash
docker compose exec php php artisan route:list --path=api/v1
```

**Success Criteria:**
- conditions.index, conditions.show routes exist
- proficiency-types.index, proficiency-types.show routes exist

---

## Phase 10: Documentation

### Task 10.1: Update CLAUDE.md
Add to sections:
- **Database Architecture:** Document new lookup tables
- **API Structure:** List new endpoints
- **Available Seeders:** Add ConditionSeeder, ProficiencyTypeSeeder
- Update test count, table count

### Task 10.2: Create Handover Document
**File:** `docs/HANDOVER-2025-11-18-CONDITIONS-PROFICIENCIES.md`
- Session summary
- Schema changes
- API endpoints
- Seeder data
- Next steps (parser integration)

### Task 10.3: Update PROJECT-STATUS.md
- Increment table count: 44 → 47
- Increment seeder count: 9 → 11
- Increment test count: 294 → ~334
- Add conditions/proficiency types to completed features

---

## Rollout & Observability

### Migration Safety
- All migrations reversible with `down()` methods
- Foreign keys use `onDelete('cascade')` where appropriate
- Nullable columns for backward compatibility

### Seeder Idempotency
```php
// Use updateOrCreate to prevent duplicates
Condition::updateOrCreate(
    ['slug' => 'charmed'],
    ['name' => 'Charmed', 'description' => '...']
);
```

### No Breaking Changes
- Existing proficiencies table unaffected (proficiency_type_id is nullable)
- All existing tests remain passing
- API adds new endpoints, doesn't modify existing

---

## Summary

**Deliverables:**
- 3 new tables: conditions, proficiency_types, entity_conditions
- 1 modified table: proficiencies (add FK column)
- 3 new models: Condition, ProficiencyType, EntityCondition
- 2 new seeders: ConditionSeeder, ProficiencyTypeSeeder
- 4 new API endpoints: conditions (index/show), proficiency-types (index/show)
- 3 new resources: ConditionResource, ProficiencyTypeResource, EntityConditionResource
- 4 new factories: Condition, ProficiencyType, EntityCondition, updated Proficiency
- ~40 new tests across migrations, models, seeders, factories, API

**Estimated Effort:** 4-6 hours

**Quality Gates:**
- All 294+ existing tests pass
- 40+ new tests pass
- Seeders populate data successfully
- API documentation auto-updates
- Zero breaking changes

---

**Next Steps After Completion:**
1. Parser integration to normalize existing proficiency data
2. Extract conditions from spell/monster descriptions
3. Add filtering to existing endpoints (e.g., `?condition=charmed`)

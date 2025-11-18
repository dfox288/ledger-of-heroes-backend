# Rename ClassModel to CharacterClass Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Rename `ClassModel` to `CharacterClass` throughout the codebase to maintain naming consistency with other entities (Spell, Race, Item, etc.) while avoiding PHP's reserved `class` keyword.

**Architecture:** Comprehensive rename across models, migrations, relationships, tests, factories, seeders, and API resources. Use search-and-replace with verification at each step to ensure nothing breaks.

**Tech Stack:** Laravel 11.x, PHP 8.4, MySQL 8.0, PHPUnit for testing

**Rationale:** The `Model` suffix is inconsistent with other entity models (Spell, Race, Item, Feat, Background, Monster) which don't have suffixes. `CharacterClass` clearly indicates it's a D&D character class while avoiding the PHP reserved word `class`.

---

## Problem Analysis

### Current State

**Model file:** `app/Models/ClassModel.php`

**Usage throughout codebase:**
- Relationships: `Spell::classes()` returns `BelongsToMany<ClassModel>`
- Foreign keys: `class_id` in junction tables
- Factories: `ClassModelFactory` (if exists)
- Tests: References to `ClassModel::class`
- API: `ClassController`, `ClassResource`
- Seeders: Creating `ClassModel` records

**Issues:**
1. Inconsistent naming - other entities don't have `Model` suffix
2. Confusing for developers - "Is it ClassModel or just Class?"
3. Breaks naming convention established by other entities

### Proposed Solution

**Rename:** `ClassModel` → `CharacterClass`

**Impact:**
- File: `app/Models/ClassModel.php` → `app/Models/CharacterClass.php`
- Class name: `class ClassModel` → `class CharacterClass`
- All imports: `use App\Models\ClassModel` → `use App\Models\CharacterClass`
- All instantiations: `ClassModel::` → `CharacterClass::`
- All type hints: `: ClassModel` → `: CharacterClass`
- All docblocks: `@return ClassModel` → `@return CharacterClass`

**NOT Changed:**
- Database table name: `classes` (remains unchanged)
- Foreign keys: `class_id` (remains unchanged)
- Junction tables: `class_spells` (remains unchanged)
- API routes: `/api/v1/classes` (remains unchanged)

---

## Phase 1: Preparation and Analysis

### Task 1: Analyze All Usages of ClassModel

**Files:**
- None (analysis only)

**Step 1: Find all files that reference ClassModel**

Run: `grep -r "ClassModel" app/ tests/ database/ --include="*.php" | wc -l`
Expected: Shows count of references

**Step 2: List all files that need updating**

Run: `grep -r "ClassModel" app/ tests/ database/ --include="*.php" | cut -d: -f1 | sort -u`
Expected: Shows list of files

**Step 3: Check for any potential conflicts with "CharacterClass" name**

Run: `grep -r "CharacterClass" app/ tests/ database/ --include="*.php"`
Expected: No results (name is available)

**Step 4: Document findings**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== ClassModel Usage Analysis ===\n\";
echo \"Total classes in database: \" . \App\Models\ClassModel::count() . \"\n\";
echo \"Classes with spells: \" . \App\Models\ClassModel::has('spells')->count() . \"\n\";

\$sample = \App\Models\ClassModel::first();
if (\$sample) {
    echo \"Sample class: \" . \$sample->name . \"\n\";
    echo \"Sample has \" . \$sample->spells()->count() . \" spells\n\";
}
"`
Expected: Shows current state of ClassModel data

**Step 5: Create backup**

Run: `git status`
Expected: Clean working directory (commit any pending changes first)

Run: `git checkout -b refactor/rename-classmodel-to-characterclass`
Expected: New branch created

---

## Phase 2: Rename Model File and Class

### Task 2: Rename Model File and Update Class Definition

**Files:**
- Rename: `app/Models/ClassModel.php` → `app/Models/CharacterClass.php`
- Modify: `app/Models/CharacterClass.php` (class name)

**Step 1: Copy file to new name**

Run: `cp app/Models/ClassModel.php app/Models/CharacterClass.php`
Expected: New file created

**Step 2: Update class name in new file**

Edit `app/Models/CharacterClass.php`:

Find:
```php
class ClassModel extends Model
```

Replace with:
```php
class CharacterClass extends Model
```

**Step 3: Verify file compiles**

Run: `docker compose exec php php -l app/Models/CharacterClass.php`
Expected: "No syntax errors detected"

**Step 4: Keep old file temporarily for testing**

Do NOT delete `ClassModel.php` yet - we'll verify everything works first

**Step 5: Verify new class can be instantiated**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\$class = new \App\Models\CharacterClass();
echo 'CharacterClass instantiated: ' . get_class(\$class) . \"\n\";
echo 'Table name: ' . \$class->getTable() . \"\n\";
"`
Expected: "CharacterClass instantiated: App\Models\CharacterClass"

**Step 6: Commit new file**

```bash
git add app/Models/CharacterClass.php
git commit -m "feat: add CharacterClass model (copy of ClassModel)"
```

---

## Phase 3: Update All Model Relationships

### Task 3: Update Spell Model Relationship

**Files:**
- Modify: `app/Models/Spell.php`
- Modify: `tests/Feature/Models/SpellModelTest.php` (if exists)

**Step 1: Write failing test**

Create or edit `tests/Feature/Models/SpellModelTest.php`:

```php
public function test_spell_belongs_to_many_character_classes(): void
{
    $spell = Spell::first();
    $characterClass = CharacterClass::first();

    // Create class_spells association
    DB::table('class_spells')->insert([
        'spell_id' => $spell->id,
        'class_id' => $characterClass->id,
    ]);

    $spell->refresh();
    $spell->load('classes');

    $this->assertInstanceOf(CharacterClass::class, $spell->classes->first());
    $this->assertEquals($characterClass->id, $spell->classes->first()->id);
}
```

**Step 2: Run test to verify it fails**

Run: `docker compose exec php php artisan test --filter=test_spell_belongs_to_many_character_classes`
Expected: FAIL - "Class 'CharacterClass' not found" (in test file, needs import)

Add import to test file:
```php
use App\Models\CharacterClass;
```

Run again - should still fail because Spell model still references ClassModel

**Step 3: Update Spell model**

Edit `app/Models/Spell.php`:

Find:
```php
use App\Models\ClassModel;
```

Replace with:
```php
use App\Models\CharacterClass;
```

Find:
```php
public function classes(): BelongsToMany
{
    return $this->belongsToMany(ClassModel::class, 'class_spells', 'spell_id', 'class_id');
}
```

Replace with:
```php
public function classes(): BelongsToMany
{
    return $this->belongsToMany(CharacterClass::class, 'class_spells', 'spell_id', 'class_id');
}
```

**Step 4: Run test to verify it passes**

Run: `docker compose exec php php artisan test --filter=test_spell_belongs_to_many_character_classes`
Expected: PASS

**Step 5: Run all Spell tests**

Run: `docker compose exec php php artisan test --filter=SpellTest`
Expected: All spell tests pass

**Step 6: Commit**

```bash
git add app/Models/Spell.php tests/Feature/Models/SpellModelTest.php
git commit -m "refactor: update Spell model to use CharacterClass"
```

---

### Task 4: Update Source Model Relationship

**Files:**
- Modify: `app/Models/Source.php`

**Step 1: Update Source model**

Edit `app/Models/Source.php`:

Find:
```php
use App\Models\ClassModel;
```

Replace with:
```php
use App\Models\CharacterClass;
```

Find:
```php
public function classes(): HasMany
{
    return $this->hasMany(ClassModel::class);
}
```

Replace with:
```php
public function classes(): HasMany
{
    return $this->hasMany(CharacterClass::class);
}
```

**Step 2: Verify**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\$source = \App\Models\Source::first();
\$classes = \$source->classes;
echo 'Source has ' . \$classes->count() . ' classes\n';
if (\$classes->count() > 0) {
    echo 'First class type: ' . get_class(\$classes->first()) . \"\n\";
}
"`
Expected: "First class type: App\Models\CharacterClass"

**Step 3: Commit**

```bash
git add app/Models/Source.php
git commit -m "refactor: update Source model to use CharacterClass"
```

---

## Phase 4: Update Migrations and Seeders

### Task 5: Update Migration Comments and Seeders

**Files:**
- Modify: `database/migrations/*_create_classes_table.php` (comments only)
- Modify: `database/migrations/*_seed_core_classes.php`

**Step 1: Update seed_core_classes migration**

Find the file:
Run: `ls -la database/migrations/*_seed_core_classes.php`

Edit `database/migrations/2025_11_17_234013_seed_core_classes.php`:

Find:
```php
use App\Models\ClassModel;
```

Replace with:
```php
use App\Models\CharacterClass;
```

Find all occurrences of:
```php
ClassModel::create([
```

Replace with:
```php
CharacterClass::create([
```

**Step 2: Verify migration can run (rollback/migrate)**

Run: `docker compose exec php php artisan migrate:rollback --step=1`
Expected: Rollback successful

Run: `docker compose exec php php artisan migrate`
Expected: Migration successful, classes seeded

**Step 3: Verify data**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo 'Total CharacterClass records: ' . \App\Models\CharacterClass::count() . \"\n\";
echo 'Sample class: ' . \App\Models\CharacterClass::first()->name . \"\n\";
"`
Expected: Shows 13 classes

**Step 4: Commit**

```bash
git add database/migrations/*_seed_core_classes.php
git commit -m "refactor: update seed migration to use CharacterClass"
```

---

## Phase 5: Update Tests

### Task 6: Update All Test Files

**Files:**
- Modify: All test files that reference ClassModel

**Step 1: Find all test files**

Run: `grep -r "ClassModel" tests/ --include="*.php" -l`
Expected: List of test files

**Step 2: Update imports in all test files**

For each test file found, perform find-and-replace:

Find:
```php
use App\Models\ClassModel;
```

Replace with:
```php
use App\Models\CharacterClass;
```

Find:
```php
ClassModel::
```

Replace with:
```php
CharacterClass::
```

Find:
```php
@var ClassModel
```

Replace with:
```php
@var CharacterClass
```

**Files to update:**
- `tests/Feature/Migrations/ClassesTableTest.php`
- `tests/Feature/Migrations/ClassRelatedTablesTest.php`
- `tests/Feature/Migrations/ClassSpellsTableTest.php`
- `tests/Feature/Importers/SpellImporterTest.php`
- Any other test files found

**Step 3: Run all tests**

Run: `docker compose exec php php artisan test`
Expected: All tests pass

**Step 4: Commit**

```bash
git add tests/
git commit -m "refactor: update all tests to use CharacterClass"
```

---

## Phase 6: Update API Resources and Controllers

### Task 7: Update SpellResource to Return CharacterClass

**Files:**
- Modify: `app/Http/Resources/SpellResource.php`

**Step 1: Check current SpellResource**

Run: `grep -n "ClassModel" app/Http/Resources/SpellResource.php`
Expected: Shows any references (might be in comments or type hints)

**Step 2: Update SpellResource**

Edit `app/Http/Resources/SpellResource.php`:

If there are imports:
```php
use App\Models\ClassModel;
```

Replace with:
```php
use App\Models\CharacterClass;
```

Update any type hints or docblocks that reference ClassModel

**Step 3: Verify API response**

Run: `docker compose exec php php artisan test --filter=SpellApiTest`
Expected: All API tests pass

**Step 4: Test API manually**

Run: `curl -s "http://localhost:8080/api/v1/spells/1" | python3 -m json.tool | grep -A10 "classes"`
Expected: Shows classes array properly formatted

**Step 5: Commit**

```bash
git add app/Http/Resources/SpellResource.php
git commit -m "refactor: update SpellResource to use CharacterClass"
```

---

### Task 8: Check and Update Class API Controller/Resource (if exists)

**Files:**
- Check: `app/Http/Controllers/Api/ClassController.php` (if exists)
- Check: `app/Http/Resources/ClassResource.php` (if exists)

**Step 1: Check if Class API exists**

Run: `ls -la app/Http/Controllers/Api/ClassController.php 2>/dev/null || echo "Does not exist"`
Run: `ls -la app/Http/Resources/ClassResource.php 2>/dev/null || echo "Does not exist"`

**Step 2: If files exist, update them**

For `app/Http/Controllers/Api/ClassController.php`:

Find:
```php
use App\Models\ClassModel;
```

Replace with:
```php
use App\Models\CharacterClass;
```

Find:
```php
ClassModel::
```

Replace with:
```php
CharacterClass::
```

For `app/Http/Resources/ClassResource.php`:

Same replacements as above.

**Step 3: If files exist, run tests**

Run: `docker compose exec php php artisan test --filter=ClassApiTest`
Expected: All tests pass (if tests exist)

**Step 4: Commit (if changes made)**

```bash
git add app/Http/Controllers/Api/ClassController.php app/Http/Resources/ClassResource.php
git commit -m "refactor: update Class API to use CharacterClass"
```

---

## Phase 7: Update Importers and Parsers

### Task 9: Update SpellImporter

**Files:**
- Modify: `app/Services/Importers/SpellImporter.php`

**Step 1: Check for ClassModel usage**

Run: `grep -n "ClassModel" app/Services/Importers/SpellImporter.php`
Expected: Shows any references

**Step 2: Update SpellImporter**

Edit `app/Services/Importers/SpellImporter.php`:

Find:
```php
use App\Models\ClassModel;
```

Replace with:
```php
use App\Models\CharacterClass;
```

Find:
```php
ClassModel::
```

Replace with:
```php
CharacterClass::
```

**Step 3: Run importer tests**

Run: `docker compose exec php php artisan test --filter=SpellImporterTest`
Expected: All tests pass

**Step 4: Commit**

```bash
git add app/Services/Importers/SpellImporter.php
git commit -m "refactor: update SpellImporter to use CharacterClass"
```

---

## Phase 8: Update Factories (if exists)

### Task 10: Rename and Update ClassModelFactory

**Files:**
- Rename: `database/factories/ClassModelFactory.php` → `database/factories/CharacterClassFactory.php` (if exists)

**Step 1: Check if factory exists**

Run: `ls -la database/factories/ClassModelFactory.php 2>/dev/null || echo "Does not exist"`

**Step 2: If factory exists, rename it**

Run: `mv database/factories/ClassModelFactory.php database/factories/CharacterClassFactory.php`

**Step 3: Update factory class name**

Edit `database/factories/CharacterClassFactory.php`:

Find:
```php
class ClassModelFactory extends Factory
```

Replace with:
```php
class CharacterClassFactory extends Factory
```

Find:
```php
protected $model = ClassModel::class;
```

Replace with:
```php
protected $model = CharacterClass::class;
```

Update import:
```php
use App\Models\CharacterClass;
```

**Step 4: Test factory**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

\$class = \App\Models\CharacterClass::factory()->make();
echo 'Factory created: ' . get_class(\$class) . \"\n\";
echo 'Name: ' . \$class->name . \"\n\";
"`
Expected: Factory works correctly

**Step 5: Commit**

```bash
git add database/factories/
git commit -m "refactor: rename ClassModelFactory to CharacterClassFactory"
```

---

## Phase 9: Final Cleanup and Verification

### Task 11: Remove Old ClassModel File

**Files:**
- Delete: `app/Models/ClassModel.php`

**Step 1: Run full test suite**

Run: `docker compose exec php php artisan test`
Expected: All tests pass

**Step 2: Verify no remaining references to ClassModel**

Run: `grep -r "ClassModel" app/ tests/ database/ --include="*.php"`
Expected: No results (except possibly in comments)

**Step 3: Delete old ClassModel file**

Run: `rm app/Models/ClassModel.php`
Expected: File deleted

**Step 4: Run tests again to ensure nothing broke**

Run: `docker compose exec php php artisan test`
Expected: All tests still pass

**Step 5: Verify application works**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== Final Verification ===\n\";
echo \"CharacterClass count: \" . \App\Models\CharacterClass::count() . \"\n\";
echo \"Spells count: \" . \App\Models\Spell::count() . \"\n\";

\$spell = \App\Models\Spell::with('classes')->first();
if (\$spell && \$spell->classes->count() > 0) {
    echo \"Sample spell: \" . \$spell->name . \"\n\";
    echo \"First class: \" . \$spell->classes->first()->name . \"\n\";
    echo \"Class type: \" . get_class(\$spell->classes->first()) . \"\n\";
}
"`
Expected: Shows CharacterClass data correctly

**Step 6: Commit**

```bash
git add app/Models/ClassModel.php
git commit -m "refactor: remove old ClassModel file"
```

---

### Task 12: Update Routes and API Documentation

**Files:**
- Check: `routes/api.php`
- Check: Any API documentation

**Step 1: Check routes file**

Run: `grep -n "ClassModel" routes/api.php`
Expected: No results (routes use controllers, not models directly)

**Step 2: Verify routes still work**

Run: `docker compose exec php php artisan route:list | grep classes`
Expected: Shows /api/v1/classes routes (if they exist)

**Step 3: Test API endpoints**

Run: `curl -s "http://localhost:8080/api/v1/spells/1" | python3 -m json.tool`
Expected: API response includes classes array

**Step 4: Update any API documentation comments**

If there are docblocks in controllers or resources that mention "ClassModel", update them to say "CharacterClass"

**Step 5: Commit (if changes made)**

```bash
git add routes/ app/Http/
git commit -m "docs: update API documentation to reference CharacterClass"
```

---

### Task 13: Update Project Documentation

**Files:**
- Modify: `docs/HANDOVER-2025-11-18.md` (or latest handover)
- Modify: `CLAUDE.md` (if it mentions ClassModel)
- Check: Any other documentation files

**Step 1: Search for ClassModel in documentation**

Run: `grep -r "ClassModel" docs/ *.md 2>/dev/null`
Expected: Shows any documentation references

**Step 2: Update handover document**

Add to handover document:

```markdown
## Refactoring: ClassModel → CharacterClass ✅

**Date:** 2025-11-18

**What Changed:**
- Renamed `ClassModel` to `CharacterClass` for naming consistency
- Updated all relationships, tests, factories, migrations, importers
- Database table name remains `classes` (unchanged)
- API routes remain `/api/v1/classes` (unchanged)

**Rationale:**
- Other entities don't have `Model` suffix (Spell, Race, Item, etc.)
- `CharacterClass` clearly indicates D&D character class
- Avoids PHP reserved word `class`

**Files Changed:**
- Model: `app/Models/ClassModel.php` → `app/Models/CharacterClass.php`
- All relationships updated (Spell, Source, etc.)
- All tests updated
- Migrations/seeders updated
- API resources updated
- Factories updated (if existed)

**Testing:**
- All tests passing (~180+ tests)
- API responses verified
- Database relationships verified
```

**Step 3: Commit**

```bash
git add docs/ CLAUDE.md
git commit -m "docs: document ClassModel → CharacterClass rename"
```

---

### Task 14: Final Testing and Merge

**Files:**
- None (verification only)

**Step 1: Run full test suite one final time**

Run: `docker compose exec php php artisan test`
Expected: All tests pass

**Step 2: Check for any remaining ClassModel references**

Run: `grep -r "ClassModel" app/ tests/ database/ routes/ config/ --include="*.php" | grep -v "# old comment"`
Expected: No results

**Step 3: Verify database state**

Run: `docker compose exec php php -r "
require 'vendor/autoload.php';
\$app = require_once 'bootstrap/app.php';
\$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo \"=== Final State ===\n\";
echo \"CharacterClass count: \" . \App\Models\CharacterClass::count() . \"\n\";
echo \"class_spells count: \" . DB::table('class_spells')->count() . \"\n\";

\$wizard = \App\Models\CharacterClass::where('name', 'Wizard')->first();
if (\$wizard) {
    echo \"Wizard has \" . \$wizard->spells()->count() . \" spells\n\";
}
"`
Expected: All data intact

**Step 4: Test API endpoints manually**

```bash
# Test classes endpoint (if exists)
curl -s "http://localhost:8080/api/v1/classes" | python3 -m json.tool | head -30

# Test spell with classes
curl -s "http://localhost:8080/api/v1/spells/1" | python3 -m json.tool | grep -A10 "classes"
```

Expected: All API responses working correctly

**Step 5: Review all commits**

Run: `git log --oneline refactor/rename-classmodel-to-characterclass`
Expected: Shows all commits in logical order

**Step 6: Merge to main branch**

Run: `git checkout schema-redesign`
Run: `git merge refactor/rename-classmodel-to-characterclass --no-ff -m "refactor: rename ClassModel to CharacterClass for consistency

- Renamed ClassModel to CharacterClass throughout codebase
- Updated all relationships (Spell, Source)
- Updated all tests, factories, migrations, seeders
- Updated API resources and controllers
- Database table name 'classes' unchanged
- API routes unchanged

Rationale: Maintain naming consistency with other entities (Spell, Race, Item)
while avoiding PHP reserved word 'class'.

All tests passing."`

**Step 7: Push changes**

Run: `git push origin schema-redesign`

---

## Summary

**Total Tasks:** 14

**What Was Changed:**
1. ✅ Model renamed: `ClassModel` → `CharacterClass`
2. ✅ All relationships updated (Spell, Source)
3. ✅ All migrations/seeders updated
4. ✅ All tests updated
5. ✅ All API resources/controllers updated
6. ✅ All importers/parsers updated
7. ✅ All factories updated (if existed)
8. ✅ Old ClassModel file removed
9. ✅ Documentation updated

**What Was NOT Changed:**
- Database table: `classes` (remains unchanged)
- Foreign keys: `class_id` (remains unchanged)
- Junction tables: `class_spells` (remains unchanged)
- API routes: `/api/v1/classes` (remains unchanged)

**Key Benefits:**
- Consistent naming with other entities (Spell, Race, Item, Feat, Background, Monster)
- Clear indication this is a D&D character class
- Avoids PHP reserved word `class`
- No database changes required

**Testing:**
- All existing tests pass
- No functionality broken
- API responses unchanged
- Database relationships intact

**Estimated Implementation:** 2-3 hours with careful verification

**Next Steps:** Continue with remaining vertical slices or implement multiple sources per entity

---

## Execution Options

Plan complete and saved to `docs/plans/2025-11-18-rename-classmodel-to-characterclass.md`. Two execution options:

**1. Subagent-Driven (this session)** - I dispatch fresh subagent per task, review between tasks, fast iteration

**2. Parallel Session (separate)** - Open new session with executing-plans, batch execution with checkpoints

Which approach would you like to use?

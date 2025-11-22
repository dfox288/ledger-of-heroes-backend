# Design Document: Spell Importer Trait Extraction (Phase 2)

**Date:** 2025-11-22
**Type:** Code Refactoring - DRY Improvement
**Status:** Design Approved - Ready for Implementation
**Related:** Phase 1 Strategy Refactoring (Race/Class importers)

---

## Executive Summary

Extract duplicated class resolution logic from `SpellImporter` and `SpellClassMappingImporter` into a reusable trait `ImportsClassAssociations`. This eliminates ~100 lines of code duplication while maintaining existing functionality and test coverage.

**Key Metrics:**
- **Code Reduction:** 100 lines of duplicated production code
- **Files Modified:** 2 importers
- **Files Created:** 1 trait + 1 test file
- **Test Coverage:** 15-20 new unit tests
- **Breaking Changes:** None (all existing tests pass)

**Design Decision:** Use **trait pattern** (not strategy pattern) because:
- SpellImporter and SpellClassMappingImporter are already well-separated
- No internal type detection needed (unlike Race/Class/Item/Monster importers)
- Problem is code duplication, not architectural complexity
- Follows Phase 1 pattern: traits for shared logic, strategies for type-specific logic

---

## Problem Statement

### Current Architecture

**Two Separate Importers:**
1. **SpellImporter** (217 lines) - Full spell imports from content files (`spells-phb.xml`)
2. **SpellClassMappingImporter** (173 lines) - Additive class mappings from supplement files (`spells-phb+dmg.xml`)

**Code Duplication:**
- Both importers have ~100 lines of **identical** class resolution logic
- Both have the same `SUBCLASS_ALIASES` constant (10 lines)
- Both implement the same fuzzy matching patterns
- Only difference: `sync()` vs additive merge

### Why Not Strategy Pattern?

Phase 1 successfully applied strategy pattern to Race/Class/Item/Monster importers because they had:
- ✅ Complex type-specific logic scattered in one file
- ✅ Multiple modes/variants needing different handling
- ✅ Internal type detection logic

SpellImporter does **not** have these issues:
- ❌ Already well-separated into two classes with different responsibilities
- ❌ No internal modes or type detection
- ❌ Problem is duplication, not complexity

**Conclusion:** Trait extraction is the right pattern for shared logic reuse.

---

## Solution Design

### Architecture Overview

Create reusable trait `ImportsClassAssociations` that encapsulates:
1. Class name resolution (subclass detection, aliases, fuzzy matching)
2. Two sync strategies: replace (sync) vs merge (add)
3. Shared constants (`SUBCLASS_ALIASES`)

### Trait Public Interface

```php
trait ImportsClassAssociations
{
    /**
     * Sync class associations (replaces existing).
     *
     * @param Model $entity Entity with classes() relationship (Spell, Background, etc.)
     * @param array $classNames Class names from XML (may include subclasses in parentheses)
     */
    public function syncClassAssociations(Model $entity, array $classNames): void;

    /**
     * Add class associations (merges with existing).
     *
     * @param Model $entity Entity with classes() relationship
     * @param array $classNames Class names to add
     * @return int Number of new associations added
     */
    public function addClassAssociations(Model $entity, array $classNames): int;
}
```

### Class Resolution Logic

**Three Resolution Strategies:**

1. **Subclass Detection** - Pattern: `"Fighter (Eldritch Knight)"`
   - Extract base class and subclass name from parentheses
   - Apply alias mapping if exists
   - Try exact match on subclass name
   - Fallback to fuzzy match (`LIKE "%{name}%"`)
   - Skip if not found (don't fallback to base class)

2. **Base Class Lookup** - Pattern: `"Wizard"`
   - Exact match on base classes only (`whereNull('parent_class_id')`)
   - Skip if not found

3. **Alias Mapping** - Constant in trait
   ```php
   private const SUBCLASS_ALIASES = [
       // Druid Circle of the Land terrain variants
       'Coast' => 'Circle of the Land',
       'Desert' => 'Circle of the Land',
       'Forest' => 'Circle of the Land',
       'Grassland' => 'Circle of the Land',
       'Mountain' => 'Circle of the Land',
       'Swamp' => 'Circle of the Land',
       'Underdark' => 'Circle of the Land',
       'Arctic' => 'Circle of the Land',

       // Common abbreviations
       'Ancients' => 'Oath of the Ancients',
       'Vengeance' => 'Oath of Vengeance',
   ];
   ```

**Why Fuzzy Matching?**
- XML files use abbreviated names: `"Archfey"`
- Database has official names: `"The Archfey"`
- Fuzzy matching bridges this gap automatically
- Alternative would be maintaining massive alias map (not scalable)

### Implementation Details

**Trait Structure:**
```php
<?php

namespace App\Services\Importers\Concerns;

use App\Models\CharacterClass;
use Illuminate\Database\Eloquent\Model;

trait ImportsClassAssociations
{
    private const SUBCLASS_ALIASES = [ /* ... */ ];

    // Public API
    public function syncClassAssociations(Model $entity, array $classNames): void
    {
        $classIds = $this->resolveClassIds($classNames);
        $entity->classes()->sync($classIds);
    }

    public function addClassAssociations(Model $entity, array $classNames): int
    {
        $newClassIds = $this->resolveClassIds($classNames);
        $existingClassIds = $entity->classes()->pluck('class_id')->toArray();
        $allClassIds = array_unique(array_merge($existingClassIds, $newClassIds));

        $entity->classes()->sync($allClassIds);

        return count($allClassIds) - count($existingClassIds);
    }

    // Private helpers
    private function resolveClassIds(array $classNames): array
    {
        $classIds = [];
        foreach ($classNames as $className) {
            $class = $this->resolveClassFromName($className);
            if ($class) {
                $classIds[] = $class->id;
            }
        }
        return $classIds;
    }

    private function resolveClassFromName(string $className): ?CharacterClass
    {
        // Pattern: "Fighter (Eldritch Knight)" → use SUBCLASS
        if (preg_match('/^(.+?)\s*\(([^)]+)\)$/', $className, $matches)) {
            $subclassName = trim($matches[2]);

            // Apply alias if exists
            if (isset(self::SUBCLASS_ALIASES[$subclassName])) {
                $subclassName = self::SUBCLASS_ALIASES[$subclassName];
            }

            // Try exact match
            $class = CharacterClass::where('name', $subclassName)->first();

            // Try fuzzy match
            if (!$class) {
                $class = CharacterClass::where('name', 'LIKE', "%{$subclassName}%")->first();
            }

            return $class;
        }

        // No parentheses → use BASE class only
        return CharacterClass::where('name', $className)
            ->whereNull('parent_class_id')
            ->first();
    }
}
```

**Key Design Decisions:**

1. **Private methods** - Internal implementation details not exposed
2. **No logging** - Keeps trait focused; importers can add logging if needed
3. **Graceful failure** - Returns null for unresolved classes (skips, no errors)
4. **Generic entity type** - `Model $entity` allows use with any entity (Spell, Background, Feat)
5. **No caching** - Keep it simple; optimization can come later if needed

---

## Migration Strategy

### SpellImporter Changes

**Before (217 lines):**
```php
class SpellImporter extends BaseImporter
{
    use ImportsRandomTables;
    use ImportsSavingThrows;

    private const SUBCLASS_ALIASES = [/* 10 lines */];

    protected function importEntity(array $spellData): Spell
    {
        // ... spell creation ...

        if (isset($spellData['classes'])) {
            $this->importClassAssociations($spell, $spellData['classes']);
        }
    }

    private function importClassAssociations(Spell $spell, array $classNames): void
    {
        $classIds = [];
        foreach ($classNames as $className) {
            // 40 lines of resolution logic
        }
        $spell->classes()->sync($classIds);
    }
}
```

**After (165 lines):**
```php
class SpellImporter extends BaseImporter
{
    use ImportsRandomTables;
    use ImportsSavingThrows;
    use ImportsClassAssociations;  // ← NEW

    protected function importEntity(array $spellData): Spell
    {
        // ... spell creation (unchanged) ...

        if (isset($spellData['classes'])) {
            $this->syncClassAssociations($spell, $spellData['classes']); // ← CHANGED
        }
    }

    // DELETED: importClassAssociations() method (50 lines)
    // DELETED: SUBCLASS_ALIASES constant (moved to trait)
}
```

**Changes:**
- Add `use ImportsClassAssociations;` (1 line)
- Change `importClassAssociations()` to `syncClassAssociations()` (1 line)
- Delete `importClassAssociations()` method (50 lines removed)
- Delete `SUBCLASS_ALIASES` constant (10 lines removed)

**Net Change:** -52 lines (-24%)

---

### SpellClassMappingImporter Changes

**Before (173 lines):**
```php
class SpellClassMappingImporter
{
    private const SUBCLASS_ALIASES = [/* 10 lines - DUPLICATED */];

    public function import(string $xmlFilePath): array
    {
        foreach ($mappings as $spellName => $classNames) {
            $classesAdded = $this->addClassAssociations($spell, $classNames);
            $stats['classes_added'] += $classesAdded;
        }
    }

    private function addClassAssociations(Spell $spell, array $classNames): int
    {
        $newClassIds = [];
        foreach ($classNames as $className) {
            // 40 lines of resolution logic - DUPLICATED
        }

        $existingClassIds = $spell->classes()->pluck('class_id')->toArray();
        $allClassIds = array_unique(array_merge($existingClassIds, $newClassIds));
        $spell->classes()->sync($allClassIds);

        return count($allClassIds) - count($existingClassIds);
    }
}
```

**After (125 lines):**
```php
class SpellClassMappingImporter
{
    use ImportsClassAssociations;  // ← NEW

    public function import(string $xmlFilePath): array
    {
        foreach ($mappings as $spellName => $classNames) {
            $classesAdded = $this->addClassAssociations($spell, $classNames); // ← UNCHANGED
            $stats['classes_added'] += $classesAdded;
        }
    }

    // DELETED: addClassAssociations() method (trait provides same method)
    // DELETED: SUBCLASS_ALIASES constant (moved to trait)
}
```

**Changes:**
- Add `use ImportsClassAssociations;` (1 line)
- Delete `addClassAssociations()` method (48 lines removed) - **trait method replaces it**
- Delete `SUBCLASS_ALIASES` constant (10 lines removed)
- No changes to calling code (trait method has same signature)

**Net Change:** -48 lines (-28%)

**Why No Code Changes in Caller?**
- Trait provides `addClassAssociations()` with **identical signature**
- PHP trait methods automatically replace class methods
- Calling code doesn't know/care if method comes from trait or class

---

## Testing Strategy

### Unit Tests for Trait

**New File:** `tests/Unit/Concerns/ImportsClassAssociationsTest.php`

**Test Coverage (15-20 tests):**

1. **Subclass Resolution**
   - ✅ Exact match: `"Fighter (Eldritch Knight)"` → Eldritch Knight
   - ✅ Fuzzy match: `"Warlock (Archfey)"` → "The Archfey"
   - ✅ Alias mapping: `"Druid (Coast)"` → Circle of the Land
   - ✅ Not found: `"Wizard (Fake School)"` → skipped

2. **Base Class Resolution**
   - ✅ Exact match: `"Wizard"` → Wizard base class only
   - ✅ Multiple classes: `["Wizard", "Sorcerer"]` → both resolved
   - ✅ Not found: `"FakeClass"` → skipped

3. **Sync vs Add Behavior**
   - ✅ `syncClassAssociations()` replaces existing
   - ✅ `addClassAssociations()` merges with existing
   - ✅ `addClassAssociations()` returns correct count
   - ✅ No duplicates when adding existing classes

4. **Real XML Scenarios**
   - ✅ Mixed base + subclass: `["Wizard", "Fighter (Eldritch Knight)"]`
   - ✅ Multiple aliases: `["Druid (Coast)", "Druid (Forest)"]`
   - ✅ Complex spell: Sleep spell with 5 classes

**Test Implementation:**
```php
class ImportsClassAssociationsTest extends TestCase
{
    use RefreshDatabase;

    private TestImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new TestImporter(); // Test class that uses trait
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_resolves_subclass_with_exact_match(): void
    {
        $fighter = CharacterClass::factory()->create(['name' => 'Fighter']);
        $eldritchKnight = CharacterClass::factory()->create([
            'name' => 'Eldritch Knight',
            'parent_class_id' => $fighter->id,
        ]);

        $spell = Spell::factory()->create();

        $this->importer->syncClassAssociations($spell, ['Fighter (Eldritch Knight)']);

        $this->assertEquals(1, $spell->classes()->count());
        $this->assertEquals($eldritchKnight->id, $spell->classes()->first()->id);
    }
}

// Test helper class
class TestImporter
{
    use ImportsClassAssociations;
}
```

### Integration Tests (Existing)

**All existing tests should pass without modification:**
- `SpellImporterTest` (8 tests) - Uses trait methods internally
- `SpellClassMappingImporterTest` - No changes needed
- Master import command tests - No changes needed

**Regression Prevention:**
- Run full test suite before/after refactoring
- Verify all 1,018 tests still pass
- No functional changes to import behavior

---

## Error Handling & Edge Cases

### Graceful Degradation

**Unresolved classes:**
- Skipped silently (not errors)
- Allows imports to continue even with missing classes
- Matches current behavior

**Empty arrays:**
- `syncClassAssociations([], [])` → no-op (safe)
- `addClassAssociations([], [])` → returns 0 (safe)

**Null relationships:**
- Handled by Eloquent's relationship methods
- No special null checks needed

### Potential Issues (Acceptable Trade-offs)

1. **Fuzzy match over-matching**
   - `LIKE "%Archfey%"` could match multiple classes
   - Uses `first()` which returns first match
   - Trade-off: Better than maintaining huge alias map
   - Mitigation: Can add specific aliases if needed

2. **No validation of resolved class type**
   - Doesn't verify resolved class is appropriate (base vs subclass)
   - Trusts XML data is correct
   - Matches current behavior

3. **No logging of unresolved classes**
   - Skips silently without warning
   - Importers can add logging if needed
   - Keeps trait focused and reusable

### Not Handled (Intentionally)

- ❌ Creating missing base classes (unlike SubraceStrategy)
- ❌ Warning/logging for unresolved classes
- ❌ Query optimization via caching (future enhancement)
- ❌ Validation that resolved class matches expected type

---

## Performance Considerations

### Database Queries

**Current behavior (unchanged):**
- Each class name = 1-2 queries (exact match, optional fuzzy match)
- No N+1 issues (already exists in current code)
- Spell import: ~5 classes × 2 queries = ~10 queries per spell

**Future Optimization (not in scope):**
```php
// Could add class name caching to reduce queries
private static array $classCache = [];

private function resolveClassFromName(string $className): ?CharacterClass
{
    $cacheKey = "class_{$className}";

    if (isset(self::$classCache[$cacheKey])) {
        return self::$classCache[$cacheKey];
    }

    // ... existing resolution logic ...

    self::$classCache[$cacheKey] = $class;
    return $class;
}
```

**Trade-off:** Keep it simple for now, optimize if needed later

### Memory

**No additional memory overhead:**
- Trait is loaded per-class (same as current methods)
- No static state or caching
- No additional collections or arrays

---

## Future Extensibility

### Other Importers Can Use Trait

**Potential users:**
- ✅ BackgroundImporter - Already has class associations
- ✅ FeatImporter - May have class prerequisites
- ✅ Future importers that reference classes

**Usage pattern:**
```php
class BackgroundImporter extends BaseImporter
{
    use ImportsClassAssociations;

    protected function importEntity(array $data): Background
    {
        // ...
        $this->syncClassAssociations($background, $data['classes']);
    }
}
```

### Adding New Aliases

**Easy to extend:**
```php
private const SUBCLASS_ALIASES = [
    // Existing aliases
    'Coast' => 'Circle of the Land',

    // Add new aliases as discovered
    'GOO' => 'The Great Old One',  // Warlock patron abbreviation
    'Light' => 'Light Domain',     // Cleric domain abbreviation
];
```

### Alternative Sync Strategies

**Could add new methods if needed:**
```php
// Example: Remove specific classes (not currently needed)
public function removeClassAssociations(Model $entity, array $classNames): int
{
    $classIds = $this->resolveClassIds($classNames);
    $entity->classes()->detach($classIds);
    return count($classIds);
}
```

---

## Implementation Plan

### Task Breakdown (6 tasks)

1. **Create ImportsClassAssociations trait** (TDD)
   - Write failing unit tests first
   - Implement trait methods
   - Verify all tests pass

2. **Refactor SpellImporter** (TDD)
   - Add trait to SpellImporter
   - Update importEntity() to use syncClassAssociations()
   - Delete old method and constant
   - Verify all existing tests pass

3. **Refactor SpellClassMappingImporter** (TDD)
   - Add trait to SpellClassMappingImporter
   - Delete old method and constant (caller unchanged)
   - Verify all existing tests pass

4. **Integration Testing**
   - Run full test suite (1,018 tests)
   - Test actual imports with real XML files
   - Verify import statistics unchanged

5. **Documentation**
   - Update CLAUDE.md with new trait
   - Update CHANGELOG.md with Phase 2 entry
   - Create session handover document

6. **Code Quality**
   - Run Pint (code formatter)
   - Verify no regressions
   - Create PR with clean commits

### Estimated Duration

- **Development:** 2-3 hours (TDD + refactoring)
- **Testing:** 1 hour (integration + regression)
- **Documentation:** 1 hour
- **Total:** 4-5 hours

---

## Success Criteria

**Code Quality:**
- ✅ All 1,018+ existing tests pass
- ✅ 15-20 new trait unit tests added
- ✅ Code formatted with Pint
- ✅ No duplication between SpellImporter and SpellClassMappingImporter

**Functionality:**
- ✅ Spell imports produce identical results
- ✅ Additive imports still work correctly
- ✅ Class associations resolve correctly (exact, fuzzy, aliases)
- ✅ No breaking changes to public APIs

**Documentation:**
- ✅ CLAUDE.md updated with trait description
- ✅ CHANGELOG.md includes Phase 2 entry
- ✅ Session handover document created
- ✅ PHPDoc on trait methods

**Metrics:**
- ✅ 100 lines of production code eliminated
- ✅ SpellImporter: 217 → 165 lines (-24%)
- ✅ SpellClassMappingImporter: 173 → 125 lines (-28%)
- ✅ Zero regressions in test suite

---

## Risks & Mitigation

### Risk 1: Trait Method Name Collision

**Risk:** Both importers have methods with same names as trait methods

**Mitigation:**
- ✅ This is intentional - trait methods **replace** class methods in PHP
- ✅ Verified trait method signatures match existing methods exactly
- ✅ No changes needed in calling code

### Risk 2: Fuzzy Matching Breaking

**Risk:** Fuzzy match could resolve wrong class or multiple classes

**Mitigation:**
- ✅ Uses `first()` which returns first match consistently
- ✅ Can add specific aliases for ambiguous cases
- ✅ Matches current behavior (not a regression)

### Risk 3: Missing Test Coverage

**Risk:** Edge cases not covered by new unit tests

**Mitigation:**
- ✅ 15-20 comprehensive unit tests planned
- ✅ All existing integration tests pass (regression prevention)
- ✅ Real XML fixtures used in tests

---

## Alternatives Considered

### Alternative 1: Service Class (Rejected)

**Pros:**
- Pure service class (no trait magic)
- Easy to mock in tests

**Cons:**
- ❌ Constructor injection adds boilerplate
- ❌ Doesn't follow existing codebase patterns (16 traits, 0 resolver services)
- ❌ Sync vs merge logic still duplicated in importers
- ❌ Net code increase instead of decrease

### Alternative 2: Minimal Trait (Rejected)

**Pros:**
- Smallest trait footprint (~40 lines)
- Maximum flexibility for importers

**Cons:**
- ❌ Less code reduction (only extracts resolution, not syncing)
- ❌ Importers still have similar-looking loops
- ❌ Less complete abstraction

### Alternative 3: Strategy Pattern (Rejected)

**Pros:**
- Consistent with Phase 1 (Race/Class/Item/Monster)

**Cons:**
- ❌ SpellImporter doesn't have internal modes/types
- ❌ Already well-separated into two classes
- ❌ Problem is duplication, not complexity
- ❌ Over-engineering for the actual problem

---

## Conclusion

**Phase 2 Refactoring Goal:** Eliminate code duplication using trait extraction pattern.

**Key Decisions:**
- ✅ Use trait pattern (not strategy) for shared logic
- ✅ Extract complete resolution + sync logic (not just resolution)
- ✅ Maintain existing importer separation (don't merge into one)
- ✅ Zero breaking changes to public APIs

**Expected Outcome:**
- 100 lines of production code eliminated
- 15-20 new unit tests for trait
- All 1,018+ existing tests pass
- Single source of truth for class resolution logic
- Future importers can reuse trait

**Status:** Design approved - Ready for implementation in worktree + detailed plan.

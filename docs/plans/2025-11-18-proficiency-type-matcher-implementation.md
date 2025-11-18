# Implementation Plan: Proficiency Type Matcher for Importers

**Date:** 2025-11-18
**Branch:** `schema-redesign`
**Estimated Effort:** 2 hours

## Project Context

**Current State:**
- 80 proficiency types seeded in `proficiency_types` table
- Importers create proficiencies with `proficiency_name` only
- `proficiency_type_id` FK exists but unpopulated

**Goal:**
Update RaceXmlParser and BackgroundXmlParser to automatically match proficiency names to proficiency_types during import, populating `proficiency_type_id` for normalized data.

---

## Phase 1: Scaffolding & Verification

### Task 1.1: Verify Environment
```bash
# Confirm Sail is running
docker compose ps

# Verify current test state (baseline: 308 passing)
docker compose exec php php artisan test --compact

# Check proficiency types seeded
docker compose exec php php artisan tinker --execute="
  echo 'Proficiency Types: ' . \App\Models\ProficiencyType::count() . PHP_EOL;
"
```

**Success Criteria:** 308 tests passing, 80 proficiency types seeded

---

## Phase 2: Shared Matcher Trait (TDD)

### Task 2.1: Create Matcher Trait Test (Write Test First)
**Test First:**
- File: `tests/Unit/Services/Parsers/MatchesProficiencyTypesTest.php`
- Test exact match (case-insensitive)
- Test apostrophe normalization ("Smith's" → "Smiths")
- Test no match returns null
- Test cache initialization
- 5 tests total

**Then Implement:**
- File: `app/Services/Parsers/Concerns/MatchesProficiencyTypes.php`
- Trait with proficiency type matching logic
- Cache proficiency types on initialization
- Normalize names for matching

```bash
# Run tests (should fail initially)
docker compose exec php php artisan test --filter=MatchesProficiencyTypesTest

# Implement trait to make tests pass
```

**Commit:** `feat: add MatchesProficiencyTypes trait with tests`

---

## Phase 3: Update RaceXmlParser (TDD)

### Task 3.1: Update RaceXmlParser Unit Tests
**File:** `tests/Unit/Parsers/RaceXmlParserTest.php`

**Add New Tests:**
- Test proficiency type matching for weapons
- Test proficiency type matching for armor
- Test proficiency type matching for skills
- Test fallback to proficiency_name when no match
- Test apostrophe variants match correctly
- 5 new tests

```bash
# Run tests (should fail - parser not updated yet)
docker compose exec php php artisan test --filter=RaceXmlParserTest
```

### Task 3.2: Implement RaceXmlParser Updates
**File:** `app/Services/Parsers/RaceXmlParser.php`

**Changes:**
- Add `use MatchesProficiencyTypes` trait
- Call `initializeProficiencyTypes()` in constructor
- Update `parseProficiencies()` to call `matchProficiencyType()`
- Add `proficiency_type_id` to returned array

```bash
# Run tests (should pass now)
docker compose exec php php artisan test --filter=RaceXmlParserTest
```

**Commit:** `feat: update RaceXmlParser to match proficiency types`

### Task 3.3: Verify Race Reconstruction Tests Still Pass
```bash
docker compose exec php php artisan test --filter=RaceXmlReconstructionTest
```

**Success Criteria:** All 7 race reconstruction tests pass (1 incomplete expected)

**Commit:** `test: verify RaceXmlParser backward compatibility`

---

## Phase 4: Update BackgroundXmlParser (TDD)

### Task 4.1: Update BackgroundXmlParser Unit Tests
**File:** `tests/Unit/Parsers/BackgroundXmlParserTest.php`

**Add New Tests:**
- Test proficiency type matching for skills
- Test proficiency type matching for tools
- Test fallback when no match
- 3 new tests

```bash
# Run tests (should fail - parser not updated yet)
docker compose exec php php artisan test --filter=BackgroundXmlParserTest
```

### Task 4.2: Implement BackgroundXmlParser Updates
**File:** `app/Services/Parsers/BackgroundXmlParser.php`

**Changes:**
- Add `use MatchesProficiencyTypes` trait
- Call `initializeProficiencyTypes()` in constructor
- Update `parseProficiencies()` to call `matchProficiencyType()`
- Add `proficiency_type_id` to returned array

```bash
# Run tests (should pass now)
docker compose exec php php artisan test --filter=BackgroundXmlParserTest
```

**Commit:** `feat: update BackgroundXmlParser to match proficiency types`

### Task 4.3: Verify Background Reconstruction Tests Still Pass
```bash
docker compose exec php php artisan test --filter=BackgroundXmlReconstructionTest
```

**Success Criteria:** All 5 background reconstruction tests pass

**Commit:** `test: verify BackgroundXmlParser backward compatibility`

---

## Phase 5: Quality Gates

### Task 5.1: Run Full Test Suite
```bash
docker compose exec php php artisan test
```

**Success Criteria:**
- All 308+ tests pass (expect +13 new tests = ~321 total)
- 1 incomplete (existing race reconstruction edge case)
- No new failures

### Task 5.2: Fresh Import Test
```bash
# Fresh database with seeds
docker compose exec php php artisan migrate:fresh --seed

# Import races
docker compose exec php bash -c 'for file in import-files/races-*.xml; do php artisan import:races "$file"; done'

# Import backgrounds
docker compose exec php bash -c 'for file in import-files/backgrounds-*.xml; do php artisan import:backgrounds "$file"; done'

# Check results
docker compose exec php php artisan test --filter=Reconstruction
```

**Success Criteria:** Reconstruction tests pass with fresh data

### Task 5.3: Verify Proficiency Type Matching
```bash
docker compose exec php php artisan tinker --execute="
  \$total = \App\Models\Proficiency::count();
  \$matched = \App\Models\Proficiency::whereNotNull('proficiency_type_id')->count();
  \$skills = \App\Models\Proficiency::whereNotNull('skill_id')->count();

  echo 'Total Proficiencies: ' . \$total . PHP_EOL;
  echo 'Matched to Types: ' . \$matched . ' (' . round((\$matched/\$total)*100, 1) . '%)' . PHP_EOL;
  echo 'Skills (have skill_id): ' . \$skills . PHP_EOL;

  // Show unmatched
  \$unmatched = \App\Models\Proficiency::whereNull('proficiency_type_id')
    ->whereNull('skill_id')
    ->pluck('proficiency_name')
    ->unique();

  if (\$unmatched->isNotEmpty()) {
    echo PHP_EOL . 'Unmatched Proficiencies:' . PHP_EOL;
    \$unmatched->each(fn(\$name) => print '  - ' . \$name . PHP_EOL);
  }
"
```

**Success Criteria:**
- Match rate > 85%
- Unmatched proficiencies documented (expected for generic references)

---

## Phase 6: Documentation

### Task 6.1: Update CLAUDE.md
Add to **Architecture Decisions** section:
- Proficiency Type Matching pattern
- Trait reusability across parsers
- Fallback to proficiency_name

### Task 6.2: Create Mini Handover
**File:** `docs/HANDOVER-2025-11-18-PROFICIENCY-MATCHER.md`
- Summary of changes
- Match rate statistics
- List of unmatched proficiencies
- Future enhancement notes

---

## Rollout & Observability

### Migration Safety
- No database migrations needed (FK already exists)
- Changes are additive (populates proficiency_type_id)
- Backward compatible (proficiency_name still populated)

### Testing Strategy
- Unit tests for trait matching logic
- Parser tests verify proficiency_type_id populated
- Reconstruction tests ensure XML integrity
- Fresh import verifies end-to-end flow

### No Breaking Changes
- Existing proficiencies table schema unchanged
- API reads proficiency_type relationship automatically
- Old data (proficiency_name only) still works

---

## Summary

**Deliverables:**
- 1 new trait: MatchesProficiencyTypes
- 2 updated parsers: RaceXmlParser, BackgroundXmlParser
- 13 new tests (5 trait + 5 race + 3 background)
- 100% backward compatibility
- Automatic proficiency type matching on import

**Estimated Effort:** 2 hours

**Quality Gates:**
- All existing tests pass
- 13+ new tests pass
- Fresh import works
- Match rate > 85%

**Benefits:**
- ✅ Normalized proficiency data
- ✅ Enables proficiency-based queries
- ✅ Reusable matching logic
- ✅ Zero performance impact (cached lookups)

---

**Next Steps After Completion:**
1. Import all race/background XML files
2. Verify match statistics
3. Document unmatched proficiencies for review
4. Consider adding proficiency type matching to ItemXmlParser (future)

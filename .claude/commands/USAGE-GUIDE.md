# Slash Command Usage Guide

Quick reference for custom slash commands in this project.

---

## `/organize-docs` - Autonomous Documentation Organization

### What It Does

Automatically archives, cleans up, and organizes your `docs/` folder by:

1. **Analyzing project state** from CHANGELOG.md, PROJECT-STATUS.md, and TODO.md
2. **Archiving completed work:**
   - Old handovers (>7 days)
   - Completed plans (mentioned in CHANGELOG)
   - Backup files (*.backup, *.md.backup)
   - Temporary scripts (*.py, *.sql, *.php older than 7 days)
   - Old analysis files (>14 days)
3. **Organizing active work:**
   - Recent handovers → `docs/handovers/YYYY-MM/`
   - Active plans → `docs/plans/`
4. **Updating references** in TODO.md and PROJECT-STATUS.md
5. **Creating archive documentation** explaining what was moved

### When to Use

- After completing a major feature or phase
- When docs/ root has too many files (>15-20 files)
- Before starting a new feature (clean slate)
- After a long coding session with multiple handovers
- Weekly maintenance to keep docs organized

### Usage

```bash
/organize-docs
```

No arguments needed - completely autonomous!

### Expected Results

**Before:**
```
docs/
├── 25+ .md files (mixed ages)
├── 3 .backup files
├── 2 .py scripts
├── 1 .sql file
└── Various SESSION-HANDOVER-*.md files
```

**After:**
```
docs/
├── PROJECT-STATUS.md          ✓ Current metrics
├── LATEST-HANDOVER.md         ✓ Symlink to latest
├── HANDOVER.md                ✓ Most recent
├── TODO.md                    ✓ Active tasks
├── DND-FEATURES.md            ✓ Reference
├── README.md                  ✓ Docs
├── plans/                     ✓ 4 active plans
├── handovers/
│   └── 2025-11/              ✓ Recent handovers (7-30 days)
└── archive/
    └── 2025-11-25/           ✓ Completed work + README
```

### What Gets Archived

**Always Archived:**
- ✓ Backup files (*.backup)
- ✓ Completed implementation summaries (*-COMPLETE.md)
- ✓ Handovers older than 7 days
- ✓ Plans mentioned in CHANGELOG as completed

**Conditionally Archived:**
- ✓ Analysis files older than 14 days
- ✓ Temporary scripts older than 7 days
- ✓ Correction files where fixes are in CHANGELOG

**Never Archived:**
- ✗ PROJECT-STATUS.md
- ✗ LATEST-HANDOVER.md (symlink)
- ✗ DND-FEATURES.md
- ✗ README.md
- ✗ Files modified within last 7 days

### Verification

Run this before/after to see improvements:

```bash
./scripts/verify-docs-structure.sh
```

Expected output after organizing:
```
✓ All checks passed!
```

---

## `/update-docs` - API Documentation Updates

### What It Does

Updates OpenAPI/Scramble documentation for a specific entity following the SpellController gold standard:

1. Analyzes model's `searchableOptions()` for filterable fields
2. Verifies API Resource exposes all searchable data
3. Updates Controller PHPDoc with comprehensive filter documentation
4. Validates alignment: Model → Resource → Controller

### When to Use

- After adding new filterable fields to a model
- After modifying a model's `toSearchableArray()`
- When API documentation is outdated
- Before releasing a new API version

### Usage

```bash
/update-docs Spell
/update-docs Monster
/update-docs CharacterClass
/update-docs Race
/update-docs Item
/update-docs Background
/update-docs Feat
```

### Expected Results

**Updated Controller PHPDoc with:**
- ✓ Filterable fields grouped by data type (Integer/String/Boolean/Array)
- ✓ Available operators per field type
- ✓ Common filter examples
- ✓ Real-world use cases
- ✓ Query parameter documentation

**Verified Alignment:**
- ✓ Model defines filterable fields in `searchableOptions()`
- ✓ Model indexes fields in `toSearchableArray()`
- ✓ Resource exposes fields in `toArray()`
- ✓ Controller documents all filterable fields in PHPDoc

### Gold Standard Reference

The command follows `app/Http/Controllers/Api/SpellController.php` as the pattern:

```php
/**
 * Get paginated spells with search and filtering
 *
 * **Integer fields (=, !=, >, >=, <, <=, IN, NOT IN):**
 * - `level` - Spell level (0-9)
 *   - Example: `filter=level = 3`
 *   - Example: `filter=level >= 5 AND level <= 9`
 *
 * **String fields (=, !=, IN, NOT IN):**
 * - `school_code` - School of magic
 *   - Example: `filter=school_code = 'EV'`
 *
 * ... etc
 */
```

---

## Helper Scripts

### `scripts/verify-docs-structure.sh`

**Purpose:** Verify docs/ structure is healthy

**Usage:**
```bash
./scripts/verify-docs-structure.sh
```

**Checks:**
- ✓ Required files exist (PROJECT-STATUS.md, LATEST-HANDOVER.md, etc.)
- ✓ Symlinks are valid
- ✓ No backup files in root
- ✓ No temporary scripts in root
- ✓ Subdirectories exist (plans/, handovers/, archive/)
- ✓ No old handovers (>7 days) in root

**Exit Codes:**
- `0` - All checks passed (or warnings only)
- `1` - Errors found (run `/organize-docs` to fix)

**Output:**
```bash
✓ All checks passed!           # Green - Perfect
⚠ 3 warning(s) found            # Yellow - Should clean up
✗ 2 error(s), 1 warning(s)      # Red - Must fix
```

---

## Common Workflows

### Weekly Documentation Maintenance

```bash
# 1. Check current state
./scripts/verify-docs-structure.sh

# 2. Organize if needed (warnings found)
/organize-docs

# 3. Verify cleanup worked
./scripts/verify-docs-structure.sh
# Expected: ✓ All checks passed!
```

### After Completing a Feature

```bash
# 1. Update CHANGELOG.md (you do this manually)
# 2. Update API docs if needed
/update-docs EntityName

# 3. Create handover document (you do this)
# 4. Organize docs
/organize-docs

# 5. Verify
./scripts/verify-docs-structure.sh
```

### Starting a New Feature

```bash
# 1. Clean up old docs first
/organize-docs

# 2. Check what's active
cat docs/TODO.md
cat docs/HANDOVER.md

# 3. Verify structure is clean
./scripts/verify-docs-structure.sh
```

---

## Tips & Best Practices

### Documentation Organization

1. **Run `/organize-docs` regularly** - Don't let docs/ accumulate 30+ files
2. **Archive early** - If a plan is done, archive it
3. **Keep root clean** - Only active work should be in docs/ root
4. **Use symlinks** - LATEST-HANDOVER.md should always point to most recent
5. **Document archives** - Archive README.md explains what was moved

### API Documentation

1. **Update after model changes** - Always run `/update-docs` after modifying searchableOptions()
2. **Follow gold standard** - SpellController is the pattern to match
3. **Test filters** - Verify documented filters actually work
4. **Be comprehensive** - Document ALL filterable fields, not just some

### Verification

1. **Run checks before commits** - Ensure docs/ structure is clean
2. **Fix warnings promptly** - Don't let them accumulate
3. **Automate in CI/CD** - Add verification script to pre-commit hooks

---

## Troubleshooting

### "Broken symlink" Error

```bash
# Check current target
ls -la docs/LATEST-HANDOVER.md

# Fix manually
rm docs/LATEST-HANDOVER.md
ln -s SESSION-HANDOVER-2025-11-25-PHASE3.md docs/LATEST-HANDOVER.md
```

### "Too many files in docs/" Warning

```bash
# Count files
ls docs/*.md | wc -l

# If >20, definitely run:
/organize-docs
```

### "Archive date collision"

If `docs/archive/2025-11-25/` already exists:
- Files are merged (not overwritten)
- README.md is appended to (not replaced)
- Safe to re-run `/organize-docs` multiple times

### "Git-tracked file moved incorrectly"

The command checks `git ls-files` before moving:
- Tracked files → `git mv` (preserves history)
- Untracked files → `mv` (regular move)

Should never break git history!

---

## Current Status

**As of 2025-11-25:**

```bash
./scripts/verify-docs-structure.sh
```

**Output:**
```
⚠ 3 warning(s) found
- 1 backup file in root
- 3 temporary scripts in root
- Missing handovers/ directory
```

**Recommendation:** Run `/organize-docs` to clean up warnings.

---

**Questions?** Check `.claude/commands/README.md` or `.claude/commands/organize-docs.md` for implementation details.

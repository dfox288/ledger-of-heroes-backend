---
description: "Autonomously archive, clean up, and organize the docs/ folder structure"
---

# /organize-docs

Autonomously organize and archive documentation files in `docs/` directory.

**Directory Structure:**
- `docs/plans/` - Active implementation plans
- `docs/handovers/` - Recent handover documents
- `docs/archive/` - Outdated/completed docs (organized by date)
- `docs/TODO.md` - Current todo list (references plans)
- `docs/HANDOVER.md` - Most recent handover (may reference plans/todos)
- `docs/PROJECT-STATUS.md` - Project health metrics (links to HANDOVER.md)

---

## Step 1: Analyze Current State

Read and analyze:

1. **Root CHANGELOG.md** (limit 200 lines):
   - Extract completed features from `[Unreleased]` section
   - Note recent version numbers
   - Identify what work is "done"

2. **docs/PROJECT-STATUS.md**:
   - Current project metrics
   - Which HANDOVER.md it references
   - Project health indicators

3. **docs/TODO.md** (if exists):
   - Active tasks
   - References to plan files
   - Completion status

4. **docs/HANDOVER.md** (if exists):
   - Most recent handover timestamp
   - Referenced plans/todos
   - Current work context

5. **Scan docs/ directory**:
   ```bash
   ls -lh docs/*.md docs/*.py docs/*.sql docs/*.backup 2>/dev/null
   ```
   - Identify timestamped files (`SESSION-HANDOVER-2025-11-*`, etc.)
   - Find backup files (`*.backup`, `*.md.backup`)
   - Locate analysis files (`*-ANALYSIS.md`, `*-PLAN.md`, `*-SUMMARY.md`)
   - Check for temporary scripts (`*.py`, `*.sql`, `*.php` in docs/)

---

## Step 2: Classification Rules

Classify each file using these rules:

### ARCHIVE (move to `docs/archive/YYYY-MM-DD/`):

**Completed Handovers:**
- `SESSION-HANDOVER-*.md` files older than 7 days (except LATEST-HANDOVER.md target)
- Any handover not referenced by `LATEST-HANDOVER.md` symlink

**Completed Plans:**
- `*-PLAN.md` files where plan tasks appear in CHANGELOG.md as completed
- `*-IMPLEMENTATION-SUMMARY.md` files (summaries = work done)
- `*-COMPLETE.md` files (explicitly marked complete)

**Analysis/Audit Files (Completed):**
- `*-ANALYSIS.md` files older than 14 days
- `*-AUDIT-*.md` files older than 14 days
- `*-CORRECTIONS.md` files where corrections are in CHANGELOG.md

**Backup Files:**
- `*.backup`, `*.md.backup` files (always archive)

**Temporary Scripts:**
- `*.py`, `*.sql`, `*.php` files in docs/ root (archive if older than 7 days)

### KEEP IN ROOT:

**Core Docs:**
- `PROJECT-STATUS.md` - Always keep
- `LATEST-HANDOVER.md` - Always keep (symlink)
- `DND-FEATURES.md` - Reference documentation
- `README.md` - Repository docs
- `TODO.md` - Active task list

**Active Plans:**
- Plans referenced in `TODO.md`
- Plans modified within last 7 days

**Recent Handovers:**
- Current handover (LATEST-HANDOVER.md target)
- Handovers from last 7 days

**Active Analysis:**
- Analysis files modified within last 7 days
- Files explicitly marked as "ACTIVE" or "WIP"

### MOVE TO `docs/handovers/`:

- `SESSION-HANDOVER-*.md` files from last 30 days (not older than 30 days)
- Organize by month: `docs/handovers/2025-11/`

### MOVE TO `docs/plans/`:

- `*-PLAN.md` files referenced in TODO.md
- Active implementation plans (modified within 14 days)
- Plans explicitly marked as "ACTIVE"

---

## Step 3: Execute Organization

**⚠️ CRITICAL: Use `git mv` for tracked files, `mv` for untracked**

Check file tracking status:
```bash
cd /Users/dfox/Development/dnd/importer
git ls-files docs/ | grep -E "file_pattern"
```

### 3.1 Create Archive Directories

```bash
mkdir -p docs/archive/$(date +%Y-%m-%d)
mkdir -p docs/handovers/2025-11
mkdir -p docs/plans
```

### 3.2 Archive Files

For each file to archive:

```bash
# Check if tracked
if git ls-files --error-unmatch "docs/file.md" 2>/dev/null; then
    # Tracked file - use git mv
    git mv docs/file.md docs/archive/2025-11-25/file.md
else
    # Untracked file - use regular mv
    mv docs/file.md docs/archive/2025-11-25/file.md
fi
```

**Archive Organization:**
- Group by date: `docs/archive/2025-11-25/`
- Preserve original filenames
- Keep related files together (e.g., `*.backup` with original)

### 3.3 Organize Handovers

Move recent handovers (7-30 days old) to `docs/handovers/YYYY-MM/`:

```bash
# Extract date from filename: SESSION-HANDOVER-2025-11-25-*.md
# Move to docs/handovers/2025-11/
```

### 3.4 Organize Plans

Move active plans to `docs/plans/`:

```bash
# Only plans referenced in TODO.md or modified recently
```

---

## Step 4: Update References

After moving files, update references:

### 4.1 Update `docs/TODO.md`

- Fix any broken plan file references
- Update paths: `docs/PLAN.md` → `docs/plans/PLAN.md`
- Remove references to archived plans

### 4.2 Update `docs/PROJECT-STATUS.md`

- Verify HANDOVER.md link still works
- Update any hardcoded paths
- Ensure metrics are current

### 4.3 Verify `docs/LATEST-HANDOVER.md` Symlink

```bash
# Check symlink target
ls -la docs/LATEST-HANDOVER.md

# Ensure target exists and is not archived
```

---

## Step 5: Clean Up Empty Directories

```bash
# Remove empty subdirectories in archive/
find docs/archive -type d -empty -delete

# Remove empty plans/handovers if nothing moved there
```

---

## Step 6: Generate Organization Report

Create `docs/archive/YYYY-MM-DD/README.md`:

```markdown
# Archive: [Date]

## Archived Files

### Handovers
- [file1] - Completed session from [date]
- [file2] - Superseded by [newer file]

### Plans
- [plan1] - Completed (see CHANGELOG.md [version])
- [plan2] - Obsolete (replaced by [newer plan])

### Analysis
- [analysis1] - Completed analysis from [date]

### Backups
- [backup1] - Backup of [original file]

### Scripts
- [script1] - Temporary script from [date]

## Organization Context

**CHANGELOG.md Status:**
- Latest version: [version]
- Recent features: [list]

**PROJECT-STATUS.md:**
- Tests: [count] passing
- Latest handover: [filename]

**Active Plans:**
- [list from docs/plans/]

**Files Kept in Root:**
- [list of current docs/*.md files]
```

---

## Step 7: Verification Checklist

After organization, verify:

- [ ] `docs/PROJECT-STATUS.md` exists and is up-to-date
- [ ] `docs/LATEST-HANDOVER.md` symlink points to valid file
- [ ] `docs/TODO.md` exists (or create if needed)
- [ ] `docs/HANDOVER.md` is most recent handover
- [ ] All `docs/*.backup` files archived
- [ ] All temporary scripts (`*.py`, `*.sql`, `*.php`) handled
- [ ] Recent handovers (< 30 days) in `docs/handovers/`
- [ ] Active plans in `docs/plans/`
- [ ] Old handovers/plans in `docs/archive/YYYY-MM-DD/`
- [ ] Archive has README.md explaining what was moved
- [ ] No broken references in TODO.md or PROJECT-STATUS.md

---

## Step 8: Summary Report

Print summary:

```markdown
# Documentation Organization Complete

## Files Archived: [count]
- Handovers: [count] → docs/archive/[date]/
- Plans: [count] → docs/archive/[date]/
- Analysis: [count] → docs/archive/[date]/
- Backups: [count] → docs/archive/[date]/
- Scripts: [count] → docs/archive/[date]/

## Files Organized: [count]
- Handovers: [count] → docs/handovers/YYYY-MM/
- Plans: [count] → docs/plans/

## Current Structure:
docs/
├── PROJECT-STATUS.md ✓
├── LATEST-HANDOVER.md ✓ (→ [target])
├── HANDOVER.md ✓
├── TODO.md ✓
├── plans/ ([count] active)
├── handovers/ ([count] recent)
└── archive/ ([count] dates)

## Verification:
- All symlinks valid: [✓/✗]
- References updated: [✓/✗]
- Archive documented: [✓/✗]

## Next Steps:
[Any recommendations for user]
```

---

## Edge Cases

**Missing TODO.md:**
- Create basic TODO.md template referencing active plans

**Missing HANDOVER.md:**
- Identify most recent SESSION-HANDOVER file
- Copy/link as HANDOVER.md

**Conflicting Archive Dates:**
- If archive/[date]/ already exists, merge files
- Don't overwrite existing archive README.md (append instead)

**Git-Tracked vs Untracked:**
- ALWAYS check `git ls-files` before moving
- Use `git mv` for tracked, `mv` for untracked
- This prevents git history issues

---

## Example Execution

```bash
# 1. Analyze
cat CHANGELOG.md | head -200
cat docs/PROJECT-STATUS.md
ls -lh docs/*.md

# 2. Archive old handovers
git mv docs/SESSION-HANDOVER-2025-11-20.md docs/archive/2025-11-25/

# 3. Organize recent handovers
git mv docs/SESSION-HANDOVER-2025-11-23.md docs/handovers/2025-11/

# 4. Move active plans
mv docs/ACTIVE-PLAN.md docs/plans/

# 5. Update references
# (edit TODO.md, PROJECT-STATUS.md as needed)

# 6. Create archive README
cat > docs/archive/2025-11-25/README.md << 'EOF'
# Archive: 2025-11-25
...
EOF

# 7. Report
echo "✓ Organized 15 files (10 archived, 5 organized)"
```

---

**Goal:** Maintain clean, organized docs/ structure that aligns with CHANGELOG.md and current project state.

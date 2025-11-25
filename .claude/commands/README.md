# Claude Code Slash Commands

Custom slash commands for this project.

## Available Commands

### `/organize-docs`

**Purpose:** Autonomously archive, clean up, and organize the `docs/` folder structure.

**What it does:**
- Analyzes CHANGELOG.md to identify completed work
- Archives old handovers, completed plans, and backup files
- Organizes recent handovers into `docs/handovers/YYYY-MM/`
- Moves active plans to `docs/plans/`
- Updates references in TODO.md and PROJECT-STATUS.md
- Creates archive README documenting what was moved
- Generates comprehensive organization report

**Usage:**
```bash
/organize-docs
```

**Target Structure:**
```
docs/
├── PROJECT-STATUS.md         # Project health metrics
├── LATEST-HANDOVER.md        # Symlink to most recent handover
├── HANDOVER.md               # Current handover document
├── TODO.md                   # Active task list
├── DND-FEATURES.md           # Reference docs
├── README.md                 # Repository documentation
├── plans/                    # Active implementation plans
│   └── 2025-11-24-*.md
├── handovers/                # Recent handovers (last 30 days)
│   └── 2025-11/
│       └── SESSION-HANDOVER-*.md
└── archive/                  # Completed/outdated docs
    └── 2025-11-25/
        ├── README.md         # Explains what was archived
        └── *.md              # Archived files
```

**Classification Rules:**

**Archived:**
- Handovers older than 7 days
- Completed plans (mentioned in CHANGELOG.md)
- Analysis/audit files older than 14 days
- All `.backup` files
- Temporary scripts (`*.py`, `*.sql`, `*.php`) older than 7 days

**Kept in Root:**
- Core docs (PROJECT-STATUS.md, README.md, DND-FEATURES.md)
- Current HANDOVER.md
- Active TODO.md
- Files modified within last 7 days

**Moved to Subdirectories:**
- Recent handovers (7-30 days) → `handovers/YYYY-MM/`
- Active plans → `plans/`

---

### `/update-docs`

**Purpose:** Update API documentation for a specific entity following the SpellController gold standard.

**Usage:**
```bash
/update-docs Spell
/update-docs Monster
/update-docs CharacterClass
```

**What it does:**
- Analyzes model's `searchableOptions()` for filterable fields
- Verifies API Resource exposes all searchable data
- Updates Controller PHPDoc with comprehensive filter documentation
- Validates alignment between Model → Resource → Controller
- Compares against SpellController gold standard

**Available Entities:**
- Spell, Monster, CharacterClass, Race, Item, Background, Feat

---

## Creating New Commands

1. Create `.claude/commands/your-command.md`
2. Add frontmatter:
   ```yaml
   ---
   description: "Brief description of what this command does"
   hints: ["arg1", "arg2"]  # Optional argument hints
   ---
   ```
3. Write command instructions in Markdown
4. Use `$1`, `$2`, etc. for arguments

**Example:**
```markdown
---
description: "Run tests for a specific feature"
hints: ["feature_name"]
---

# /test-feature

Run PHPUnit tests for: **$1**

1. Find test files matching pattern: `tests/**/*$1*Test.php`
2. Run: `php artisan test --filter=$1`
3. Report results
```

---

## Best Practices

- **Be specific:** Commands should have clear, focused purposes
- **Be autonomous:** Commands should require minimal user input
- **Be thorough:** Include verification steps and error handling
- **Be consistent:** Follow existing command patterns
- **Document well:** Explain what the command does and why

---

## Tips

- Use commands instead of repeating multi-step processes
- Commands maintain context between sessions
- Commands can reference project-specific patterns (like "SpellController gold standard")
- Commands can orchestrate complex workflows autonomously

---

**Last Updated:** 2025-11-25

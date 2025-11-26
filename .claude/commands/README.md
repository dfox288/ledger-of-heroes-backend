# Claude Code Slash Commands

Custom slash commands for this project.

## Available Commands

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
- Validates alignment between Model -> Resource -> Controller
- Compares against SpellController gold standard

**Available Entities:**
- Spell, Monster, CharacterClass, Race, Item, Background, Feat

---

## Global Commands

The following commands are available globally (defined in `~/.claude/commands/`):

### `/organize-docs`

**Purpose:** Autonomously archive, clean up, and organize the `docs/` folder structure.

**What it does:**
- Moves old handovers to `docs/archive/handovers/YYYY-MM/`
- Moves stable reference docs to `docs/reference/`
- Organizes active plans in `docs/plans/`
- Updates `LATEST-HANDOVER.md` symlink
- Simplifies `docs/README.md` (removes metrics)
- Ensures handovers have HHMM timestamps

**Target Structure:**
```
docs/
├── PROJECT-STATUS.md         # Metrics (single source of truth)
├── LATEST-HANDOVER.md        # Symlink to most recent handover
├── TECH-DEBT.md              # Technical debt tracking
├── README.md                 # Simple index
├── reference/                # Stable reference docs
├── plans/                    # Active plans only
├── proposals/                # Feature proposals
├── handovers/                # Recent handovers (last 7 days)
└── archive/                  # Historical docs by type/month
    ├── handovers/YYYY-MM/
    ├── plans/YYYY-MM/
    └── analysis/YYYY-MM/
```

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
- Global commands in `~/.claude/commands/` work across all projects

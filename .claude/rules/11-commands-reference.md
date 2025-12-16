# Commands Reference

All commands are managed through `just`. Run `just --list` to see all available recipes.

## Standard Commands

```bash
just artisan <command>   # Run any artisan command
just pint                # Format code
just test-pure           # Run Unit-Pure tests (fastest)
```

## Tinker

```bash
just tinker                     # Open tinker REPL
just tinker-file /tmp/code.php  # Run tinker with file input
```

For multiline tinker scripts, use a temp file:

```bash
cat > /tmp/tinker.php << 'EOF'
<?php
use App\Models\Spell;
$spell = Spell::where('slug', 'fireball')->first();
dump($spell->name, $spell->level, $spell->school);
EOF
just tinker-file /tmp/tinker.php
```

## Test Suites

```bash
just test                # Run all tests
just test-pure           # Unit-Pure (no DB, fastest)
just test-unit           # Unit-DB (needs database)
just test-feature        # Feature-DB (API, no search)
just test-search         # Feature-Search (needs Meilisearch)
just test-importers      # XML import tests
just test-health         # Smoke tests
just test-file <path>    # Run specific test file
just test-coverage       # Run with coverage
```

## Test Commands (Custom Artisan)

### Create Multiclass Test Characters

```bash
# Single multiclass
just test-multiclass "wizard:5,cleric:5"

# Triple class at level 20
just test-multiclass "fighter:6,rogue:7,wizard:7"

# With non-PHB class
just test-multiclass "erlw:artificer:5,cleric:5"
```

Options (pass via artisan):
- `--combinations` - Required. Class:level pairs
- `--count` - Characters per combination (default: 1)
- `--seed` - Random seed for reproducibility
- `--cleanup` - Delete after creation
- `--no-force` - Respect multiclass prerequisites

### Other Test Commands

```bash
just test-wizard         # Test wizard flow with chaos
just test-levelup        # Test level-up flow
just test-all-classes    # Test all class/subclass combos
just test-optional       # Test optional features
```

## Import Commands

```bash
just import-all          # Fresh DB + import all XML
just import-test         # Import to test DB (for Feature-Search)
just import-sources      # Import sources only
just import-spells       # Import spells
just import-classes      # Import classes
just import-races        # Import races
just import-backgrounds  # Import backgrounds
just import-feats        # Import feats
just import-items        # Import items
just import-monsters     # Import monsters
```

## Audit Commands

```bash
just audit-classes       # Audit class/subclass matrix
just audit-optional      # Audit optional feature counters
```

## Fixture Commands

```bash
just fixtures-export     # Export fixture characters
just fixtures-import     # Import fixture characters
just fixtures-extract    # Extract fixture data
```

## Database

```bash
just migrate             # Run migrations
just migrate-fresh       # Drop all + re-migrate
just migrate-fresh-seed  # Fresh + seed
just migrate-rollback    # Rollback last batch
just migrate-status      # Show migration status
just seed                # Seed database
```

## Docker

```bash
just up                  # Start all services
just down                # Stop all services
just restart             # Restart services
just logs                # View logs (all services)
just logs php            # View logs (specific service)
just shell               # Open shell in PHP container
just ps                  # Show container status
```

## GitHub Issues & Inbox

```bash
just inbox               # Check handoffs + backend + both issues
just issues              # List backend issues (default label)
just issues both         # List issues for both teams
just issue-view <num>    # View specific issue details
```

## Git Workflow

```bash
just gs                  # Quick status + recent commits
just branches            # Show local branches with tracking
just branches-clean      # Delete merged branches (except main)
just push                # Push current branch, set upstream

# Branch creation
just branch-feature <num> <desc>  # feature/issue-N-description
just branch-fix <num> <desc>      # fix/issue-N-description
just branch-chore <num> <desc>    # chore/issue-N-description

# Commits
just commit <type> <msg> # Quick conventional commit

# Pull Requests
just pr-create           # Create PR to backend repo
just pr-list             # List open PRs
just pr-checks <num>     # View PR checks status
```

## Slash Commands

Available in `.claude/commands/`:

| Command | Purpose |
|---------|---------|
| `/inbox` | Check backend inbox for handoffs |
| `/issue-inbox` | Check assigned GitHub issues |
| `/issue-new` | Create a new GitHub issue |
| `/issue-view` | View a specific issue |
| `/issue-close` | Close an issue |
| `/update-docs` | Update API docs for an entity |

## Command Format Rules

See `08-git-conventions.md` for single-line command requirements (backslash continuations break auto-approval).

# Commands Reference

## Standard Commands

```bash
docker compose exec php php artisan <command>
docker compose exec php ./vendor/bin/pint
docker compose exec php ./vendor/bin/pest --testsuite=Unit-Pure
```

## Tinker (one-liner)

```bash
docker compose exec php php artisan tinker --execute='use App\Models\Spell; dump(Spell::count());'
```

## Tinker (multiline)

Use a temp file - heredocs trigger approval prompts:

```bash
cat > /tmp/tinker.php << 'EOF'
<?php
use App\Models\Spell;
$spell = Spell::where('slug', 'fireball')->first();
dump($spell->name, $spell->level, $spell->school);
EOF
docker compose exec -T php php artisan tinker < /tmp/tinker.php
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

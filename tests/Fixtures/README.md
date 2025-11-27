# Test Fixtures

JSON fixture files for test data seeding. Extracted from production database.

## Structure

- `entities/` - Main entity fixtures (spells, monsters, classes, etc.)
- `lookups/` - Lookup table fixtures (sources, schools, damage types, etc.)

## Format

Uses slugs for relationships (resolved at seed time):

```json
{
  "name": "Fireball",
  "slug": "fireball",
  "school": "evocation",
  "classes": ["sorcerer", "wizard"],
  "source": "phb"
}
```

## Regenerating Fixtures

```bash
docker compose exec php php artisan fixtures:extract
```

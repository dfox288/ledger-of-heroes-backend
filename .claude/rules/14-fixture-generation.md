# Fixture Generation Process

## Class/Subclass Fixture Campaign

Generate fixtures systematically: **one subclass at a time, audit after each**.

## Workflow

```
1. Generate ONE subclass    → fixtures:export-characters --subclass=X --seed=42
2. Audit spell progression  → Check subclass spells at each level
3. Verify with different seed → Re-run with --seed=9999, compare
4. If PASS → Move to next subclass
5. If FAIL → Fix issue before continuing
```

## Commands

```bash
# Generate single subclass (all milestone levels: 1, 3, 5, 10, 15, 20)
just fixtures-export --subclass=erlw:artificer-alchemist --seed=42

# Audit subclass spell progression
cat storage/fixtures/class-tests/SUBCLASS-L03.json | jq '[.character.spells[] | select(.source == "subclass")] | [.[].spell] | sort'

# Full class audit
just audit-classes --class=erlw:artificer --detailed
```

## Audit Checklist

For each subclass with bonus spells:

- [ ] L03: First 2 subclass spells present
- [ ] L05: 4 total subclass spells
- [ ] L10: 6 total subclass spells (L9 tier)
- [ ] L15: 8 total subclass spells (L13 tier)
- [ ] L20: 10 total subclass spells (L17 tier)

## Audit Patterns by Class Type

Different classes have different subclass spell expectations:

| Type | Classes | Subclass Spells |
|------|---------|-----------------|
| **Domain/Oath spells** | Cleric, Paladin | 2→4→6→10→10→10 |
| **Expanded spells** | Ranger, Warlock | 2→4→6→8→10 |
| **Artificer specialties** | Artificer | 2→4→6→8→10 |
| **Non-casters** | Barbarian, Fighter, Monk, Rogue | 0 (verify none) |
| **No subclass spells** | Bard, Druid, Sorcerer, Wizard | 0 (class spells only) |

## Class Order

Track progress through all 13 base classes:

| Class | Subclasses | Fixtures | Status |
|-------|------------|----------|--------|
| Artificer | 4 | 24 | ✅ Complete |
| Barbarian | 8 | 48 | ✅ Complete |
| Bard | 8 | 48 | ✅ Complete |
| Cleric | 14 | 84 | ✅ Complete |
| Druid | 7 | 0 | ⏳ Pending |
| Fighter | 10 | 0 | ⏳ Pending |
| Monk | 9 | 0 | ⏳ Pending |
| Paladin | 9 | 0 | ⏳ Pending |
| Ranger | 7 | 0 | ⏳ Pending |
| Rogue | 9 | 0 | ⏳ Pending |
| Sorcerer | 6 | 0 | ⏳ Pending |
| Warlock | 9 | 0 | ⏳ Pending |
| Wizard | 13 | 0 | ⏳ Pending |

**Progress:** 4/13 classes complete (204/684 fixtures)

## Output Location

Fixtures saved to: `storage/fixtures/class-tests/`

Naming: `{class}-{subclass}-L{level}.json` (e.g., `artificer-alchemist-L03.json`)

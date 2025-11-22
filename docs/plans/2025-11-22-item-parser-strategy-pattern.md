# Implementation Plan: Item Parser Strategy Pattern

**Created:** 2025-11-22
**Status:** Ready for Implementation
**Estimated Effort:** 8-12 hours (5 strategies × ~2 hours each)

---

## Overview

Refactor `ItemXmlParser` from a 481-line monolith into a composable strategy-based architecture for improved parsing accuracy, maintainability, and reliability across 16 item types.

**Goals:**
- ✅ Higher parsing accuracy for type-specific data
- ✅ Easier maintenance via focused strategy classes
- ✅ Reduced bugs through isolated changes
- ✅ Better observability with structured logging

---

## Architecture Decision

**Pattern:** Strategy Pattern with Composable Strategies

**Why not split parsers?**
- Items can be both Charged AND Legendary (composition needed)
- Shared traits prevent code duplication
- Single XML format change affects base parser, not 5 separate parsers
- Strategies testable in isolation without full XML parsing

**Structure:**
```
ItemXmlParser (base, ~200 lines)
  ├─ Common parsing (name, rarity, cost, AC, etc.)
  ├─ Delegates to type strategies for specialized extraction
  │
TypeStrategies/
  ├─ ChargedItemStrategy (ST, WD, RD) - Spell references, charges
  ├─ ScrollStrategy (SC) - Spell level, protection vs spell scrolls
  ├─ PotionStrategy (P) - Duration, effect parsing
  ├─ TattooStrategy (W + "tattoo") - Body location, activation
  └─ LegendaryStrategy (legendary/artifact) - Sentience, alignment
```

---

## Phase 1: Foundation & Infrastructure

### Task 1.1: Create Strategy Interface & Base Class

**Files:**
- `app/Services/Parsers/Strategies/ItemTypeStrategy.php` (interface)
- `app/Services/Parsers/Strategies/AbstractItemStrategy.php` (base class)
- `tests/Unit/Services/Parsers/Strategies/AbstractItemStrategyTest.php`

**Interface Contract:**
```php
interface ItemTypeStrategy {
    public function appliesTo(array $baseData, SimpleXMLElement $xml): bool;
    public function enhanceModifiers(array $modifiers, array $baseData, SimpleXMLElement $xml): array;
    public function enhanceAbilities(array $abilities, array $baseData, SimpleXMLElement $xml): array;
    public function enhanceRelationships(array $baseData, SimpleXMLElement $xml): array;
    public function extractMetadata(): array;
}
```

**Key Features:**
- Granular methods for complex items
- Metadata tracking (warnings, metrics)
- Reuses `CachesLookupTables` trait

**Verification:**
```bash
docker compose exec php php artisan test --filter=AbstractItemStrategyTest
```

**Commit:** `feat: add strategy pattern foundation and logging infrastructure`

---

### Task 1.2: Create Custom Log Channel

**File:** `config/logging.php`

```php
'import-strategy' => [
    'driver' => 'daily',
    'path' => storage_path('logs/import-strategy.log'),
    'level' => env('LOG_LEVEL', 'debug'),
    'days' => 14,
],
```

**Log Format:** Structured JSON
```json
{
  "strategy": "ChargedItemStrategy",
  "item": "Staff of Fire",
  "enhancements": {
    "charges_max": 10,
    "spell_references_found": 3
  },
  "warnings": []
}
```

**Verification:**
```bash
docker compose exec php php artisan tinker --execute="
\Log::channel('import-strategy')->info('Test', ['test' => true]);
"
cat storage/logs/import-strategy-*.log
```

---

## Phase 2: ChargedItemStrategy (Highest Value)

### Task 2.1: Write Failing Tests

**File:** `tests/Unit/Services/Parsers/Strategies/ChargedItemStrategyTest.php`

**Test Cases:**
1. ✅ Applies to magic staves/wands/rods
2. ✅ Does NOT apply to non-magic staves
3. ✅ Extracts spell references from Staff of Fire (real XML)
4. ✅ Warns when magic staff has no charges
5. ✅ Handles variable recharge formulas (2d8+4)
6. ✅ Handles missing spell references gracefully

**Real XML Fixture:**
```xml
<item>
    <name>Staff of Fire</name>
    <type>ST</type>
    <magic>YES</magic>
    <text>The staff has 10 charges. While holding it, you can use an action to expend 1 or more of its charges to cast one of the following spells from it, using your spell save DC: burning hands (1 charge), fireball (3 charges), or wall of fire (4 charges).</text>
</item>
```

**Run (expect failures):**
```bash
docker compose exec php php artisan test --filter=ChargedItemStrategyTest
```

---

### Task 2.2: Implement ChargedItemStrategy

**File:** `app/Services/Parsers/Strategies/ChargedItemStrategy.php`

**Key Features:**
- Detects magic staves/wands/rods
- Extracts spell names via regex: `/([a-z\s\']+)\s*\((\d+)\s+charges?\)/i`
- Fuzzy matches spells in database (title case)
- Falls back to storing spell name if not found
- Enhanced charge detection (recharge formulas)
- Tracks metrics: `spell_references_found`, `spell_references_missing`

**Uses Traits:**
- `ParsesCharges` (reused from base parser)
- `CachesLookupTables` (via AbstractItemStrategy)

**Run (expect pass):**
```bash
docker compose exec php php artisan test --filter=ChargedItemStrategyTest
```

**Commit:** `feat: implement ChargedItemStrategy with spell extraction`

---

### Task 2.3: Integrate into ItemXmlParser

**File:** `app/Services/Parsers/ItemXmlParser.php`

**Changes:**
1. Add `protected array $strategies = []` property
2. Instantiate strategies in `__construct()`
3. After parsing base data, iterate strategies:
   ```php
   foreach ($this->strategies as $strategy) {
       if (!$strategy->appliesTo($baseData, $element)) continue;

       $baseData['modifiers'] = $strategy->enhanceModifiers(...);
       $baseData['abilities'] = $strategy->enhanceAbilities(...);
       $baseData = array_merge($baseData, $strategy->enhanceRelationships(...));

       $this->logStrategyMetrics($baseData['name'], $strategy->extractMetadata());
   }
   ```

**Test:** Full suite should remain green
```bash
docker compose exec php php artisan test
```

**Commit:** `feat: integrate ChargedItemStrategy into ItemXmlParser`

---

### Task 2.4: Update ItemImporter for Spell References

**File:** `app/Services/Importers/ItemImporter.php`

**Add after item creation:**
```php
// Handle spell references from ChargedItemStrategy
if (!empty($itemData['spell_references'])) {
    foreach ($itemData['spell_references'] as $spellRef) {
        $item->spells()->attach($spellRef['spell_id'] ?? null, [
            'spell_name' => $spellRef['name'],
            'charges_cost' => $spellRef['charges'] ?? null,
            'description' => "Cast via item charges",
        ]);
    }
}
```

**Verification:**
```bash
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php bash -c 'php artisan import:items import-files/items-dmg.xml'

docker compose exec php php artisan tinker --execute="
\$staff = App\Models\Item::where('name', 'Staff of Fire')->with('spells')->first();
echo 'Spells: ' . \$staff->spells->pluck('name')->join(', ');
"
# Expected: "Burning Hands, Fireball, Wall of Fire"
```

**Commit:** `feat: handle spell references in ItemImporter`

---

## Phase 3: Remaining Strategies

### Task 3.1: ScrollStrategy

**Detection:** `type_code === 'SC'`

**Key Patterns:**
- Spell scrolls: Extract spell level from name ("Spell Scroll (3rd Level)")
- Protection scrolls: "Scroll of Protection from X" → not a spell scroll
- Duration mechanics from text

**Files:**
- `app/Services/Parsers/Strategies/ScrollStrategy.php`
- `tests/Unit/Services/Parsers/Strategies/ScrollStrategyTest.php`

**Test Fixture (items-dmg.xml):**
```xml
<item>
    <name>Scroll of Protection from Aberrations</name>
    <type>SC</type>
    <text>Using an action to read the scroll encloses you in an invisible barrier that extends from you to form a 5-foot-radius, 10-foot-high cylinder. For 5 minutes...</text>
</item>
```

**Commit:** `feat: implement ScrollStrategy`

---

### Task 3.2: PotionStrategy

**Detection:** `type_code === 'P'`

**Key Patterns:**
- Duration: "for 1 hour", "for 10 minutes"
- Effect categorization: healing, resistance, buff, debuff
- Already partially working (resistance parsing exists)

**Files:**
- `app/Services/Parsers/Strategies/PotionStrategy.php`
- `tests/Unit/Services/Parsers/Strategies/PotionStrategyTest.php`

**Test Fixture:**
```xml
<item>
    <name>Potion of Fire Resistance</name>
    <type>P</type>
    <text>When you drink this potion, you gain resistance to fire damage for 1 hour.</text>
</item>
```

**Commit:** `feat: implement PotionStrategy`

---

### Task 3.3: TattooStrategy

**Detection:** `type_code === 'W' && str_contains(strtolower($name), 'tattoo')`

**Key Patterns:**
- Body location extraction
- Activation methods
- Attunement details

**Files:**
- `app/Services/Parsers/Strategies/TattooStrategy.php`
- `tests/Unit/Services/Parsers/Strategies/TattooStrategyTest.php`

**Test Fixture (items-tce.xml):**
```xml
<item>
    <name>Absorbing Tattoo</name>
    <type>W</type>
    <text>Produced by a special needle, this magic tattoo features designs that emphasize one color...</text>
</item>
```

**Commit:** `feat: implement TattooStrategy`

---

### Task 3.4: LegendaryStrategy

**Detection:** `in_array($rarity, ['legendary', 'artifact'])`

**Key Patterns:**
- Sentience detection
- Alignment requirements (from detail field)
- Personality traits
- Special properties

**Files:**
- `app/Services/Parsers/Strategies/LegendaryStrategy.php`
- `tests/Unit/Services/Parsers/Strategies/LegendaryStrategyTest.php`

**Commit:** `feat: implement LegendaryStrategy`

---

## Phase 4: Import Command Enhancement

### Task 4.1: Add Strategy Statistics

**File:** `app/Services/Importers/ItemImporter.php`

**Track:**
- Items processed per strategy
- Warnings generated
- Success metrics

**Expected Output:**
```
✓ 2,155 items imported successfully

Strategy Statistics:
  ChargedItemStrategy: 102 items enhanced, 3 warnings
  ScrollStrategy: 20 items enhanced, 0 warnings
  PotionStrategy: 46 items enhanced, 1 warning
  TattooStrategy: 15 items enhanced, 0 warnings
  LegendaryStrategy: 8 items enhanced, 0 warnings

⚠ Check logs: storage/logs/import-strategy-2025-11-22.log
```

**Commit:** `feat: add strategy statistics to import command output`

---

## Phase 5: Quality Gates & Documentation

### Task 5.1: Run Full Quality Checks

```bash
# All tests (expect 900+ passing)
docker compose exec php php artisan test

# Code formatting
docker compose exec php ./vendor/bin/pint

# End-to-end import verification
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php php artisan import:all

# Check logs
cat storage/logs/import-strategy-*.log | jq .
```

**Success Criteria:**
- ✅ All tests passing (existing + new strategy tests)
- ✅ Pint formatting clean
- ✅ All 2,232 items import successfully
- ✅ Strategy logs show metrics
- ✅ No performance regression (<10s total import time)

---

### Task 5.2: Update Documentation

**File:** `CLAUDE.md`

Add section after "XML Import System":

```markdown
### Item Parser Strategy Pattern

**Architecture:** Type-specific parsing strategies for accuracy and maintainability.

**Available Strategies:**
- `ChargedItemStrategy` - Staves, wands, rods (spell references + charges)
- `ScrollStrategy` - Spell level extraction + protection scrolls
- `PotionStrategy` - Duration and effect categorization
- `TattooStrategy` - Body location + activation methods
- `LegendaryStrategy` - Sentience + alignment detection

**Strategy Logging:** `storage/logs/import-strategy-{date}.log` (structured JSON)

**Adding New Strategies:**
1. Extend `AbstractItemStrategy`
2. Implement `appliesTo()` and enhancement methods
3. Add to `ItemXmlParser::__construct()`
4. Write tests with real XML fixtures (85%+ coverage)
5. Verify with `php artisan test --filter=YourStrategyTest`
```

**File:** Create `docs/SESSION-HANDOVER-2025-11-22-ITEM-PARSER-REFACTORING.md`

---

### Task 5.3: Update CHANGELOG

**File:** `CHANGELOG.md`

```markdown
## [Unreleased]

### Added
- Item parser strategy pattern for type-specific parsing accuracy
- ChargedItemStrategy: Extract spell references from staves/wands/rods
- ScrollStrategy: Detect spell vs protection scrolls with level extraction
- PotionStrategy: Enhanced duration and effect categorization
- TattooStrategy: Tattoo-specific pattern detection
- LegendaryStrategy: Sentient item extraction (alignment, personality)
- Strategy logging channel with structured JSON output (import-strategy.log)
- Import command shows per-strategy statistics and warnings

### Changed
- Refactored ItemXmlParser from 481-line monolith to composable strategies (~200 base + 5 strategies)
- Spell references from charged items now create entity_spells relationships automatically

### Improved
- Item parsing accuracy for type-specific mechanics (charges, spell references, durations)
- Maintainability via focused strategy classes (average ~100 lines each)
- Observability with detailed parsing logs and metrics
- Test coverage: 85%+ on all strategies with real XML fixtures
```

---

## Final Commit

```bash
git add .
git commit -m "feat: implement item parser strategy pattern with 5 strategies

Architecture changes:
- Refactored ItemXmlParser (481 → ~200 lines base)
- Added 5 composable type-specific strategies
- Reduced code duplication via shared traits

Strategies implemented:
- ChargedItemStrategy: spell references + enhanced charge detection
- ScrollStrategy: spell level extraction + protection scroll detection
- PotionStrategy: duration + effect categorization
- TattooStrategy: body location + activation methods
- LegendaryStrategy: sentience + alignment detection

Quality improvements:
- Added structured logging (import-strategy channel)
- 85%+ test coverage on all strategies
- Import command shows per-strategy statistics
- Tests use real XML fixtures from source files

Performance: No regression (<10s total import time)

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>"
```

---

## Success Checklist

Before marking complete:

- [ ] All 900+ tests passing
- [ ] Pint formatting passes
- [ ] Import all 2,232 items successfully
- [ ] Strategy logs show metrics for applicable items
- [ ] Spell references created for charged items (verify Staff of Fire)
- [ ] Import command shows strategy statistics
- [ ] Documentation updated (CLAUDE.md, CHANGELOG.md, session handover)
- [ ] No performance regression (import still <10 seconds)
- [ ] Commit message follows convention
- [ ] Session handover created for next session

---

## Notes & Decisions

**Why Strategy Pattern?**
- Items can have multiple behaviors (Charged + Legendary)
- Shared parsing logic via traits (no duplication)
- Isolated testing per strategy
- Single point of change for XML format updates

**Why Not Split Parsers?**
- Would need complex orchestration for multi-behavior items
- Duplicate base parsing logic across 5+ parsers
- Harder to test (need full XML parsing context)
- Breaking changes affect multiple parsers

**Testing Philosophy:**
- 85-90% coverage target (critical path + known pitfalls)
- Real XML fixtures (not synthetic)
- Test edge cases we've encountered (missing charges, unknown spells)
- Skip exhaustive permutations (not worth the maintenance)

**Performance Considerations:**
- Strategies instantiated once, reused across items
- Lookup caches shared via traits
- Relationship inserts handled by importer (no N+1)
- Import time: not a concern (willing to trade for accuracy)

---

**Estimated Timeline:**
- Phase 1 (Foundation): 1-2 hours
- Phase 2 (ChargedItemStrategy): 2-3 hours
- Phase 3 (4 remaining strategies): 4-6 hours (1-1.5h each)
- Phase 4 (Import stats): 30 minutes
- Phase 5 (QA + docs): 1 hour

**Total: 8-12 hours** across multiple sessions

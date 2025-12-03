# Spell Saving Throws Analysis & Implementation Plan

**Date:** 2025-11-21
**Status:** Proposed Enhancement
**Priority:** Medium (affects 49.9% of spells)

---

## 📊 Analysis Results

### Coverage Statistics

| Metric | Value | Percentage |
|--------|-------|------------|
| **Total Spells** | 477 | 100% |
| **Spells with Saving Throws** | 238 | 49.9% |
| **Spells with Multiple Saves** | 26 | 5.5% |

### Saving Throw Frequency

| Ability Score | Count | Percentage of All Spells |
|---------------|-------|--------------------------|
| **Dexterity** | 79 | 16.6% (most common) |
| **Constitution** | 55 | 11.5% |
| **Wisdom** | 53 | 11.1% |
| **Strength** | 22 | 4.6% |
| **Charisma** | 17 | 3.6% |
| **Intelligence** | 12 | 2.5% (least common) |

**Total:** 238 unique saving throw mentions (some spells have multiple)

---

## 🎯 D&D 5e Context

### Why Saving Throws Matter

**Gameplay Significance:**
- Determine if spell effect applies to target
- Critical for spell selection and strategy
- Affects which creatures are vulnerable
- Influences multiclassing decisions (Resilient feat, proficiency bonuses)

**Examples:**
- **Dexterity saves:** Area damage (Fireball, Lightning Bolt) - avoid/reduce damage
- **Wisdom saves:** Mind-affecting (Charm Person, Hold Person) - resist mental effects
- **Constitution saves:** Ongoing effects (Poison, Concentration) - maintain durability
- **Strength saves:** Forced movement (Thunderwave) - resist physical force
- **Intelligence saves:** Illusions (Phantasmal Force) - see through deception
- **Charisma saves:** Banishment (Banishment, Divine Word) - resist planar effects

### Current State

**What We Capture:**
- ✅ Spell damage via `spell_effects` table
- ✅ Spell classes via `class_spell` pivot
- ✅ Spell schools, components, range, duration
- ✅ Source citations, tags

**What We're Missing:**
- ❌ Required saving throw(s)
- ❌ Save effect (half damage, negates, etc.)
- ❌ Queryability (can't filter "all Dex save spells")

---

## 📐 Proposed Solution

### Database Schema

#### New Table: `spell_saving_throws`

```sql
CREATE TABLE spell_saving_throws (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    spell_id BIGINT UNSIGNED NOT NULL,
    ability_score_id BIGINT UNSIGNED NOT NULL,
    save_effect VARCHAR(50) NULL, -- 'negates', 'half_damage', 'ends_effect', 'reduced_duration'
    is_initial_save BOOLEAN DEFAULT TRUE, -- vs. recurring save
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (spell_id) REFERENCES spells(id) ON DELETE CASCADE,
    FOREIGN KEY (ability_score_id) REFERENCES ability_scores(id) ON DELETE CASCADE,

    UNIQUE KEY unique_spell_ability (spell_id, ability_score_id, is_initial_save)
);
```

**Design Decisions:**
- **M2M relationship:** Spell ↔ AbilityScore (many-to-many via pivot)
- **save_effect field:** Captures what happens on successful save
- **is_initial_save flag:** Distinguishes initial vs. recurring saves (e.g., Moonbeam)
- **Reuses ability_scores table:** Already seeded with STR, DEX, CON, INT, WIS, CHA

### Model Relationship

```php
// app/Models/Spell.php
public function savingThrows(): BelongsToMany
{
    return $this->belongsToMany(
        AbilityScore::class,
        'spell_saving_throws',
        'spell_id',
        'ability_score_id'
    )
    ->withPivot('save_effect', 'is_initial_save')
    ->withTimestamps();
}

// app/Models/AbilityScore.php
public function spellsRequiringSave(): BelongsToMany
{
    return $this->belongsToMany(
        Spell::class,
        'spell_saving_throws',
        'ability_score_id',
        'spell_id'
    )
    ->withPivot('save_effect', 'is_initial_save')
    ->withTimestamps();
}
```

---

## 🔍 Parser Implementation

### Pattern Detection

**Common Patterns Found:**
```
"must succeed on a {Ability} saving throw"
"must make a {Ability} saving throw"
"A target must succeed on a {Ability} saving throw or {effect}"
"{Ability} saving throw or take {damage}"
"{Ability} saving throw or be {condition}"
"succeed on a {Ability} saving throw at the end of each of its turns"
```

### Parser Method

```php
// app/Services/Parsers/SpellXmlParser.php

/**
 * Extract saving throw requirements from spell description
 *
 * @return array<int, array{ability: string, effect: string|null, recurring: bool}>
 */
private function parseSavingThrows(string $description): array
{
    $savingThrows = [];
    $abilities = ['Strength', 'Dexterity', 'Constitution', 'Intelligence', 'Wisdom', 'Charisma'];

    foreach ($abilities as $ability) {
        // Pattern: "{Ability} saving throw"
        if (preg_match_all('/(' . $ability . ')\s+saving\s+throw/i', $description, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $context = substr($description, max(0, $match[1] - 100), 200);

                // Determine if recurring save
                $recurring = (
                    stripos($context, 'at the end of each of its turns') !== false ||
                    stripos($context, 'on each of your turns') !== false ||
                    stripos($context, 'end of each turn') !== false
                );

                // Determine save effect
                $effect = null;
                if (preg_match('/or take.*?damage/i', $context)) {
                    $effect = 'half_damage'; // Common in AoE spells
                } elseif (preg_match('/or be (charmed|frightened|paralyzed|stunned|poisoned|restrained)/i', $context, $conditionMatch)) {
                    $effect = 'negates'; // Save negates condition
                } elseif (stripos($context, 'take half') !== false) {
                    $effect = 'half_damage';
                } elseif (stripos($context, 'end') !== false && $recurring) {
                    $effect = 'ends_effect';
                }

                $savingThrows[] = [
                    'ability' => $ability,
                    'effect' => $effect,
                    'recurring' => $recurring,
                ];
            }
        }
    }

    // Remove duplicates (same ability + recurring state)
    $unique = [];
    foreach ($savingThrows as $save) {
        $key = $save['ability'] . ($save['recurring'] ? '_recurring' : '_initial');
        if (!isset($unique[$key])) {
            $unique[$key] = $save;
        }
    }

    return array_values($unique);
}
```

### Importer Integration

```php
// app/Services/Importers/SpellImporter.php

protected function importEntity(array $spellData): Spell
{
    // ... existing spell creation code ...

    // Import saving throws
    $this->importSavingThrows($spell, $spellData);

    return $spell;
}

private function importSavingThrows(Spell $spell, array $spellData): void
{
    $savingThrows = $spellData['saving_throws'] ?? [];

    // Clear existing associations
    $spell->savingThrows()->detach();

    foreach ($savingThrows as $save) {
        $abilityScore = $this->cachedLookup(
            AbilityScore::class,
            'code',
            strtoupper(substr($save['ability'], 0, 3)) // STR, DEX, CON, etc.
        );

        if ($abilityScore) {
            $spell->savingThrows()->attach($abilityScore->id, [
                'save_effect' => $save['effect'],
                'is_initial_save' => !$save['recurring'],
            ]);
        }
    }
}
```

---

## 📊 Expected Coverage

### Parsing Accuracy

**High Confidence (95%+ accuracy):**
- ✅ Dexterity saves (79 spells) - Very consistent pattern
- ✅ Constitution saves (55 spells) - Usually for ongoing effects
- ✅ Wisdom saves (53 spells) - Mental/charm effects

**Medium Confidence (85%+ accuracy):**
- ⚠️ Strength saves (22 spells) - Sometimes ambiguous context
- ⚠️ Charisma saves (17 spells) - Often in complex descriptions
- ⚠️ Intelligence saves (12 spells) - Rare, sometimes buried in text

**Edge Cases:**
- Spells with optional saves (e.g., "willing creature")
- Spells where save happens "at a later time"
- Spells with conditional saves (e.g., "if target is X")

### Save Effect Accuracy

| Effect Type | Confidence | Count (Estimate) |
|-------------|-----------|------------------|
| **half_damage** | 90% | ~80 spells |
| **negates** | 85% | ~120 spells |
| **ends_effect** | 75% | ~30 spells |
| **reduced_duration** | 60% | ~10 spells |

---

## 🎨 API Enhancement

### New Resource Field

```php
// app/Http/Resources/SpellResource.php

public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        // ... existing fields ...

        'saving_throws' => SpellSavingThrowResource::collection($this->whenLoaded('savingThrows')),
    ];
}
```

### New Resource Class

```php
// app/Http/Resources/SpellSavingThrowResource.php

class SpellSavingThrowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ability_score' => [
                'id' => $this->id,
                'name' => $this->name,
                'code' => $this->code,
            ],
            'save_effect' => $this->pivot->save_effect,
            'is_initial_save' => (bool) $this->pivot->is_initial_save,
        ];
    }
}
```

### Example API Response

```json
{
  "id": 42,
  "name": "Fireball",
  "level": 3,
  "school": "Evocation",
  "saving_throws": [
    {
      "ability_score": {
        "id": 2,
        "name": "Dexterity",
        "code": "DEX"
      },
      "save_effect": "half_damage",
      "is_initial_save": true
    }
  ]
}
```

---

## 🔎 Use Cases

### Frontend Applications

**Character Builder:**
```javascript
// Filter spells by saving throw
const dexSaveSpells = spells.filter(spell =>
  spell.saving_throws.some(st => st.ability_score.code === 'DEX')
);

// Show wizard which spells target enemies' weak saves
const lowWisSaveSpells = spells.filter(spell =>
  spell.saving_throws.some(st => st.ability_score.code === 'WIS')
);
```

**Spell Comparison:**
```javascript
// Compare spell effectiveness
console.log(`${spell.name} requires ${spell.saving_throws.length} saving throw(s)`);
console.log(`Effect on success: ${spell.saving_throws[0].save_effect}`);
```

### Database Queries

**Find all spells targeting Dexterity:**
```php
$dexSaveSpells = Spell::whereHas('savingThrows', function ($query) {
    $query->where('ability_score_id', $dexAbilityScore->id);
})->get();
```

**Find spells with recurring saves:**
```php
$recurringSpells = Spell::whereHas('savingThrows', function ($query) {
    $query->where('is_initial_save', false);
})->get();
```

**Find spells where save negates effect:**
```php
$negateSpells = Spell::whereHas('savingThrows', function ($query) {
    $query->where('save_effect', 'negates');
})->get();
```

---

## 🧪 Testing Strategy

### Unit Tests

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_parses_single_saving_throw()
{
    $description = "A target must succeed on a Dexterity saving throw or take 8d6 fire damage.";
    $parser = new SpellXmlParser();

    $saves = $parser->parseSavingThrows($description);

    $this->assertCount(1, $saves);
    $this->assertEquals('Dexterity', $saves[0]['ability']);
    $this->assertEquals('half_damage', $saves[0]['effect']);
    $this->assertFalse($saves[0]['recurring']);
}

#[\PHPUnit\Framework\Attributes\Test]
public function it_parses_multiple_saving_throws()
{
    $description = "Creatures must make a Strength saving throw or be knocked prone. They can repeat the save at the end of each turn (Wisdom).";
    $parser = new SpellXmlParser();

    $saves = $parser->parseSavingThrows($description);

    $this->assertCount(2, $saves);
    $this->assertEquals('Strength', $saves[0]['ability']);
    $this->assertEquals('Wisdom', $saves[1]['ability']);
    $this->assertTrue($saves[1]['recurring']);
}
```

### Integration Tests

```php
#[\PHPUnit\Framework\Attributes\Test]
public function it_imports_spell_with_saving_throw()
{
    $xml = '...'; // Fireball XML
    $importer->importFromFile($xml);

    $spell = Spell::where('name', 'Fireball')->first();
    $this->assertCount(1, $spell->savingThrows);
    $this->assertEquals('DEX', $spell->savingThrows->first()->code);
    $this->assertEquals('half_damage', $spell->savingThrows->first()->pivot->save_effect);
}
```

---

## 📅 Implementation Plan

### Phase 1: Database & Models (1-2 hours)
1. Create migration for `spell_saving_throws` table
2. Add relationship methods to Spell and AbilityScore models
3. Create SpellSavingThrowResource
4. Write factory for testing

### Phase 2: Parser (2-3 hours)
1. Implement `parseSavingThrows()` method
2. Add to SpellXmlParser
3. Write 10-15 unit tests for parser
4. Test against real spell descriptions

### Phase 3: Importer (1-2 hours)
1. Add `importSavingThrows()` method
2. Integrate into SpellImporter
3. Update SpellResource to include saving throws
4. Update SpellController to eager-load saving throws

### Phase 4: Testing & Validation (2-3 hours)
1. Run importer on all 477 spells
2. Manual spot-checks (sample 20-30 spells)
3. Write XML reconstruction test
4. Validate API responses
5. Update documentation

**Total Estimated Time:** 6-10 hours with TDD

---

## ⚠️ Known Limitations

### Parser Challenges

**Will Miss:**
- Saving throws mentioned only in "At Higher Levels" section
- Conditional saves ("if creature is undead, make WIS save")
- Saves in unusual phrasing ("resist with Charisma")

**May Misclassify:**
- Complex multi-clause sentences
- Saves that happen "on a later turn"
- Optional saves for willing creatures

### Workarounds

1. **Manual review:** Spot-check high-value spells (Fireball, Counterspell, etc.)
2. **Fallback:** Keep original description intact for human verification
3. **Iterative improvement:** Add edge cases to parser as discovered
4. **Admin interface:** Allow manual override of parsed saves

---

## 🚀 Benefits

### Immediate Value

✅ **Queryable Data** - Filter spells by saving throw type
✅ **Better UX** - Character builders can show relevant spells
✅ **Strategic Insights** - "What spells target enemy's weak save?"
✅ **Complete API** - Expose all mechanical aspects of spells
✅ **Competitive Feature** - Most D&D APIs don't expose this

### Long-Term Value

✅ **Monster Integration** - When implementing monsters, can cross-reference saves
✅ **Feat Integration** - Resilient feat can recommend which save to improve
✅ **Analytics** - "Most common save in 3rd level spells?"
✅ **AI/ML Ready** - Structured data for spell recommendation algorithms

---

## 📊 Success Metrics

**After Implementation:**
- [ ] 90%+ of spells with saves have data populated
- [ ] 95%+ accuracy on manual spot-check (30 spells)
- [ ] API response time <50ms (with eager loading)
- [ ] Zero test failures
- [ ] Documentation updated
- [ ] Session handover includes analysis

---

## 🎯 Recommendation

**Priority:** Medium-High (after Monster importer)

**Rationale:**
- Affects 50% of spells (high impact)
- Relatively straightforward implementation
- Reuses existing ability_scores table
- Follows established patterns (M2M relationships)
- Adds significant value to API consumers
- Good candidate for TDD workflow

**Next Steps:**
1. Review this analysis with stakeholder
2. Approve schema design
3. Create feature branch `feature/spell-saving-throws`
4. Follow TDD: Migration → Tests → Parser → Importer
5. Validate with real data
6. Merge and document

---

*Analysis completed: 2025-11-21*
*Estimated implementation: 6-10 hours*
*Expected coverage: 238 spells (49.9%)*

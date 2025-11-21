# Saving Throw Effects - Pattern Analysis

**Date:** 2025-11-21
**Current Coverage:** 73/248 (29.4%) save effects detected
**Undetermined:** 175 cases

---

## 📊 Pattern Categories (From 30-Spell Sample)

### Category 1: FULL DAMAGE (No Half) - ~40% of undetermined

**Pattern:** Spell deals FULL damage on fail, ZERO on success (not half)

**Examples:**
- Arms of Hadar: "must make a Strength saving throw. On a failed save..."
- Blight: "takes 8d8 necrotic damage on a failed save"
- Circle of Death: "takes 8d6 necrotic damage on a failed save"
- Disintegrate: "The target takes 10d6 + 40 force damage on a failed save"
- Chain Lightning: (similar pattern)

**Distinguishing Features:**
- Says "takes Xd8 damage on a failed save"
- Does NOT mention "half" anywhere
- No "or" clause before damage

**Current Issue:** Our "or takes damage" pattern requires "or", but many spells say "on a failed save" without "or"

**Proposed Solution:**
```php
// Add to half_damage check:
preg_match('/on\s+a\s+failed\s+save.*takes?\s+\d+d\d+/i', $context) &&
!stripos($context, 'half')
```

---

### Category 2: "BECOMES" CONDITION - ~25% of undetermined

**Pattern:** Uses "become" or "becomes" instead of "be"

**Examples:**
- Bestow Curse: "or become cursed for the duration"
- Blindness/Deafness: "the target is either blinded or deafened"
- Crown of Madness: "or become charmed by you"

**Current Issue:** Our negates pattern only catches "or be [condition]", missing "become" and "becomes"

**Proposed Solution:**
```php
// Expand negates pattern:
preg_match('/or\s+(be|become|becomes)\s+(charmed|frightened|...)/i', $context)
```

---

### Category 3: DURATION-BASED EFFECTS - ~15% of undetermined

**Pattern:** Effect applies "for the duration" with no explicit "on failed save" clause

**Examples:**
- Bane: "makes an attack roll or a saving throw before the spell ends"
- Calm Emotions: "emotions in a group... for the duration"
- Compulsion: "On a failed save... for the duration"

**Distinguishing Features:**
- Mentions "for the duration" or "until the spell ends"
- No clear damage or condition clause
- Effect is a behavioral change or debuff

**Proposed Solution:**
```php
// New effect type: 'applies_for_duration'
if (
    stripos($context, 'for the duration') !== false &&
    !stripos($context, 'damage') &&
    !stripos($context, 'half')
) {
    return 'applies_for_duration';
}
```

---

### Category 4: ONGOING DAMAGE/EFFECTS - ~10% of undetermined

**Pattern:** Damage occurs repeatedly (not just on initial fail)

**Examples:**
- Cloudkill: "creature that starts its turn in the area..."
- Blade Barrier: "passes through the wall... or starts its turn there"

**Distinguishing Features:**
- "starts its turn" or "enters the area"
- Damage is environmental/persistent
- May have saves each turn

**Current Handling:** Some caught by recurring save detection, but effect unclear

**Proposed Solution:**
- New effect type: 'ongoing_damage' or 'environmental'
- Look for "starts its turn" + damage

---

### Category 5: SPECIAL MECHANICS - ~10% of undetermined

**Pattern:** Unique spell mechanics that don't fit standard patterns

**Examples:**
- Beacon of Hope: "advantage on Wisdom saving throws" (buff, not attack)
- Contagion: Multiple saves with different effects
- Contact Other Plane: "can strain or even break your mind" (narrative, not mechanical)

**Recommendation:** Leave as null - too context-specific to automate

---

## 🎯 Proposed Improvements (Priority Order)

### High Priority (Would catch ~40% of undetermined)

**1. Full Damage Detection**
```php
// Add before current half_damage check:
if (
    preg_match('/on\s+a\s+failed\s+save.*takes?\s+\d+d\d+/i', $context) &&
    !stripos($context, 'half')
) {
    return 'full_damage'; // New effect type
}
```

### Medium Priority (Would catch ~25%)

**2. Expand "Becomes" Pattern**
```php
// Update negates pattern:
preg_match('/or\s+(be|become|becomes?)\s+(charmed|frightened|...)/i', $context)
```

### Low Priority (Would catch ~15%)

**3. Duration-Based Effects**
```php
// Add new effect type detection:
if (
    stripos($context, 'for the duration') !== false &&
    !stripos($context, 'damage')
) {
    return 'applies_for_duration';
}
```

---

## 📈 Expected Coverage After Improvements

- **Current:** 73/248 (29.4%)
- **After High Priority:** ~140/248 (56%)
- **After Medium Priority:** ~160/248 (65%)
- **After Low Priority:** ~185/248 (75%)
- **Remaining:** Special mechanics + complex conditionals (~25%)

---

## 💡 Alternative Approach: Effect Type Enum

Instead of trying to detect ALL edge cases, consider:

**Explicit Effect Types:**
- `half_damage` - Save for half (current)
- `full_damage` - Save or take full (NEW)
- `negates` - Save negates entirely (current)
- `ends_effect` - Save to end ongoing (current)
- `applies_for_duration` - Effect lasts duration (NEW)
- `reduced_duration` - Shorter duration (current)
- `ongoing_damage` - Environmental damage (NEW)
- `null` - Complex/special mechanics (acceptable)

**Benefits:**
- More descriptive for frontend
- Easier to query ("show all full-damage saves")
- Clearer what null means (special case, not parsing failure)

---

## 🔍 Next Steps

1. Implement High Priority fix (full_damage detection)
2. Re-import and measure improvement
3. If >50% coverage, implement Medium Priority
4. Consider adding new effect types to enum
5. Document acceptable edge cases in README

---

*Analysis based on 30-spell sample representing ~17% of undetermined cases*

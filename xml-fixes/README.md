# XML Fixes for Upstream PR

This directory contains corrected XML files with fixes for data errors discovered in the FightClub5eXML repository.

**Upstream Repository:** https://github.com/kinkofer/FightClub5eXML

## Summary of Fixes

### 1. Wizard - Arcane Recovery Level (class-wizard-phb.xml)

**Issue:** Arcane Recovery feature is at Level 6, should be at Level 1 per PHB p.115.

**PHB Reference:** "You have learned to regain some of your magical energy by studying your spellbook. Once per day when you finish a short rest..."

This is a 1st-level Wizard feature, gained alongside Spellcasting.

**Fix:** Move the `<feature>` element from `<autolevel level="6">` to `<autolevel level="1">`.

**Lines affected:** The Arcane Recovery feature block needs to move from the level 6 autolevel to level 1.

---

### 2. Rogue - Sneak Attack Roll Levels (class-rogue-phb.xml)

**Issue:** The `<roll>` elements for Sneak Attack use sequential levels 1-9, but Sneak Attack damage only increases at **odd** character levels (1, 3, 5, 7, 9, 11, 13, 15, 17, 19).

**PHB Reference (p.96):** "The amount of the extra damage increases by 1d6 every odd level you gain in this class, to a maximum of 10d6 at 19th level."

**Current (incorrect):**
```xml
<roll description="Extra Damage" level="1">1d6</roll>
<roll description="Extra Damage" level="2">2d6</roll>
<roll description="Extra Damage" level="3">3d6</roll>
...
<roll description="Extra Damage" level="9">9d6</roll>
```

**Correct:**
```xml
<roll description="Extra Damage" level="1">1d6</roll>
<roll description="Extra Damage" level="3">2d6</roll>
<roll description="Extra Damage" level="5">3d6</roll>
<roll description="Extra Damage" level="7">4d6</roll>
<roll description="Extra Damage" level="9">5d6</roll>
<roll description="Extra Damage" level="11">6d6</roll>
<roll description="Extra Damage" level="13">7d6</roll>
<roll description="Extra Damage" level="15">8d6</roll>
<roll description="Extra Damage" level="17">9d6</roll>
<roll description="Extra Damage" level="19">10d6</roll>
```

**Note:** Also adds the missing 10d6 at level 19.

---

### 3. Barbarian - Rage Damage Counter (class-barbarian-phb.xml)

**Issue:** No structured data for Rage Damage bonus progression. The values are only in prose text.

**PHB Reference (p.48):** "When you make a melee weapon attack using Strength, you gain a bonus to the damage roll that increases as you gain levels as a barbarian. At 1st level, you have a +2 bonus to damage. Your bonus increases to +3 at 9th level and to +4 at 16th."

**Suggested Addition:** Add `<roll>` elements to the Rage feature to capture this progression:

```xml
<roll description="Rage Damage" level="1">+2</roll>
<roll description="Rage Damage" level="9">+3</roll>
<roll description="Rage Damage" level="16">+4</roll>
```

This would allow applications to display the Rage Damage progression alongside other class progressions.

---

## Files

- `class-wizard-phb.xml` - Wizard with Arcane Recovery at Level 1
- `class-rogue-phb.xml` - Rogue with corrected Sneak Attack roll levels
- `class-barbarian-phb.xml` - Barbarian with Rage Damage roll elements (optional enhancement)

## How to Use

These files can be used to create a Pull Request to the upstream FightClub5eXML repository. Each fix addresses verified discrepancies between the XML data and the official Player's Handbook (2014).

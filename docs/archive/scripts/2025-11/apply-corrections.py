#!/usr/bin/env python3
"""
Apply corrections to CHARACTER-BUILDER-ANALYSIS.md
Run: python3 docs/apply-corrections.py
"""

import re
from pathlib import Path

def apply_corrections(content: str) -> str:
    """Apply all corrections to the document."""

    # 1. Fix table name references (line 50-69)
    content = re.sub(
        r"// Already exists in modifiers table:",
        """// Already exists in entity_modifiers table:
// ✅ VERIFIED 2025-11-25: 16/16 base classes have ASI data (Cleric has duplicate at level 8)""",
        content
    )

    # 2. Update ASI tracking section with verified data (after line 69)
    asi_verification = """

**Verified ASI Levels (2025-11-25):**

| Class | ASI Count | Levels | Status |
|-------|-----------|--------|--------|
| Fighter | 7 | 4, 6, 8, 12, 14, 16, 19 | ✅ Correct |
| Barbarian | 7 | 4, 8, 12, 16, 19, 20, 20 | ⚠️ Duplicate at 20 |
| Rogue | 7 | 4, 8, 10, 12, 16, 19, 19 | ⚠️ Duplicate at 19 |
| Druid | 7 | 4, 4, 8, 8, 12, 16, 19 | ⚠️ Duplicates at 4, 8 |
| Cleric | 6 | 4, 8, 8, 12, 16, 19 | ⚠️ **Duplicate at 8 - FIX REQUIRED** |
| Expert Sidekick | 6 | 4, 8, 10, 12, 16, 19 | ✅ Correct |
| Monk | 6 | 4, 4, 8, 12, 16, 19 | ⚠️ Duplicate at 4 |
| Ranger | 6 | 4, 8, 8, 12, 16, 19 | ⚠️ Duplicate at 8 |
| Warlock | 6 | 4, 8, 12, 12, 16, 19 | ⚠️ Duplicate at 12 |
| Warrior Sidekick | 6 | 4, 8, 12, 14, 16, 19 | ✅ Correct |
| Artificer | 5 | 4, 8, 12, 16, 19 | ✅ Correct |
| Bard | 5 | 4, 8, 12, 16, 19 | ✅ Correct |
| Paladin | 5 | 4, 8, 12, 16, 19 | ✅ Correct |
| Sorcerer | 5 | 4, 8, 12, 16, 19 | ✅ Correct |
| Spellcaster Sidekick | 5 | 4, 8, 12, 16, 18 | ✅ Correct |
| Wizard | 5 | 4, 8, 12, 16, 19 | ✅ Correct |

**Critical Finding:** Several classes have duplicate ASI modifiers that should be cleaned up before character builder implementation.

**Data Structure (Confirmed):**
- `reference_type` = `'App\\Models\\CharacterClass'`
- `modifier_category` = `'ability_score'`
- `level` = ASI level (4, 6, 8, etc.)
- `value` = `'+2'` (total ability points to distribute)
- `ability_score_id` = `NULL` (player chooses which abilities)
- `is_choice` = `true` (indicates player choice required)

**Verification Command:**
```bash
docker compose exec php php docs/verify-asi-data.php
```

**Fix Duplicates (Cleric critical, others optional):**
```bash
# Fix Cleric duplicate at level 8 (REQUIRED before character builder)
docker compose exec mysql mysql -uroot -ppassword dnd_compendium -e "
DELETE FROM entity_modifiers
WHERE reference_type = 'App\\\\Models\\\\CharacterClass'
  AND reference_id = (SELECT id FROM (SELECT id FROM classes WHERE slug = 'cleric' AND parent_class_id IS NULL) AS tmp)
  AND modifier_category = 'ability_score'
  AND level = 8
LIMIT 1;"
```
"""

    # Insert after the code example around line 69
    content = content.replace(
        "**Storage:**\n- `modifiers.modifiable_type`",
        asi_verification + "\n\n**Storage (Table: entity_modifiers):**\n- `reference_type`"
    )

    # Fix old storage section
    content = re.sub(
        r"- `modifiers\.modifiable_type` = `'App\\Models\\CharacterClass'`\n- `modifiers\.modifier_category`",
        "- `reference_type` = `'App\\\\Models\\\\CharacterClass'`\n- `modifier_category`",
        content
    )

    # 3. Update "What's Missing" section note about Cleric/Paladin (around line 335-343)
    content = content.replace(
        "#### Multiclass Prerequisites (TBD)\n**Status:** 🔍 Requires investigation\n- XML files may contain",
        "#### Multiclass Prerequisites (TBD)\n**Status:** ✅ RESOLVED - Both classes now have ASI data\n**Note:** Cleric has duplicate ASI at level 8 that needs cleanup (see Quick Wins Task 0)\n\n#### Multiclass Prerequisites (Investigation Needed)\n**Status:** 🔍 Requires investigation\n- XML files may contain"
    )

    # 4. Update Quick Wins Task 0 title
    content = content.replace(
        "## Quick Wins Before Starting",
        """## Quick Wins Before Starting

### Task 0: Clean Up ASI Duplicates (30 minutes) **REQUIRED**

**Issue:** Cleric and several other classes have duplicate ASI modifiers at certain levels.

**Fix Cleric (REQUIRED before character builder):**
```bash
docker compose exec mysql mysql -uroot -ppassword dnd_compendium -e "
DELETE FROM entity_modifiers
WHERE reference_type = 'App\\\\Models\\\\CharacterClass'
  AND reference_id = (SELECT id FROM (SELECT id FROM classes WHERE slug = 'cleric' AND parent_class_id IS NULL) AS tmp)
  AND modifier_category = 'ability_score'
  AND level = 8
LIMIT 1;"
```

**Verify Fix:**
```bash
docker compose exec php php docs/verify-asi-data.php
# Should show: Cleric [5 ASIs]: 4, 8, 12, 16, 19
```

**Optional: Fix Other Duplicates**
```bash
# Fix all duplicate ASI modifiers (keeps oldest record)
docker compose exec mysql mysql -uroot -ppassword dnd_compendium -e "
DELETE m1 FROM entity_modifiers m1
INNER JOIN entity_modifiers m2
WHERE m1.id > m2.id
  AND m1.reference_type = 'App\\\\Models\\\\CharacterClass'
  AND m1.reference_id = m2.reference_id
  AND m1.modifier_category = 'ability_score'
  AND m1.level = m2.level;"
```

**Root Cause:** Multiple imports of same class without proper deduplication. See investigation report for details.

---"""
    )

    # 5. Update effort estimates (lines 1204-1210)
    old_estimates = """### Revised Estimate (With ASI Already Done)

**ASI tracking already complete** → Save 4-6 hours

**Adjusted Totals:**
- MVP: **46-60 hours**
- Full: **72-96 hours**
- Complete: **79-108 hours**"""

    new_estimates = """### Revised Estimate (With ASI Data Verified + Auth Added)

**ASI tracking complete & verified** → ✅ Save 4-6 hours
**Auth/testing additions** → ⚠️ Add 10-14 hours
**Net adjustment:** +6-8 hours

**Adjusted Totals (FINAL):**
- **MVP (Phases 1-4):** 58-76 hours (1.5-2 months @ 10h/week)
- **Full (Phases 1-7):** 86-116 hours (2-3 months @ 10h/week)
- **Complete (Phases 1-8):** 94-126 hours (2.5-3.5 months @ 10h/week)

**Phase Breakdown with Buffers:**

| Phase | Base | +Auth | +Testing | Final |
|-------|------|-------|----------|-------|
| Phase 1 (CRUD) | 12-16h | +2h | +2h | 16-20h |
| Phase 2 (Leveling) | 14-18h | - | +4h | 18-22h |
| Phase 3 (Spells) | 12-16h | - | +4h | 16-20h |
| Phase 4 (Inventory) | 12-16h | - | +2h | 14-18h |
| Phase 5 (Feats/ASI) | 8-12h | - | +2h | 10-14h |
| Phase 6 (Multiclass) | 12-16h | - | +6h | 18-22h |
| Phase 7 (Combat) | 6-8h | - | +2h | 8-10h |
| Phase 8 (Export) | 8-12h | - | - | 8-12h |"""

    content = content.replace(old_estimates, new_estimates)

    # 6. Add note about database password in Quick Wins
    content = content.replace(
        "docker compose exec mysql mysql -uroot -ppassword",
        "docker compose exec mysql mysql -uroot -p<password>"
    )
    content = content.replace(
        "-p<password>",
        "-ppassword  # Replace 'password' with your actual MySQL root password"
    )

    # 7. Update Last Updated date
    content = re.sub(
        r"\*\*Last Updated:\*\* \d{4}-\d{2}-\d{2}",
        "**Last Updated:** 2025-11-25 (Corrected & Verified)",
        content
    )

    # 8. Update Status line
    content = content.replace(
        "**Status:** Planning Complete - Ready for Implementation",
        "**Status:** ✅ Audited, Corrected & Verified - Ready for Implementation"
    )

    # 9. Add investigation note to Executive Summary
    content = content.replace(
        "**Key Discovery:** ASI (Ability Score Improvement) tracking already exists",
        "**Key Discoveries:** \n1. ASI (Ability Score Improvement) tracking already exists (✅ VERIFIED)"
    )
    content = content.replace(
        "in `modifiers` table, saving 4-6 hours",
        """in `entity_modifiers` table, saving 4-6 hours
2. All 16 base classes now have ASI data (Cleric duplicate requires cleanup)
3. Database password in examples should match your `.env` configuration"""
    )

    return content


def main():
    """Main execution function."""
    doc_path = Path("docs/CHARACTER-BUILDER-ANALYSIS.md")

    if not doc_path.exists():
        print(f"❌ ERROR: {doc_path} not found!")
        print(f"   Current directory: {Path.cwd()}")
        print(f"   Run from project root: python3 docs/apply-corrections.py")
        return

    print(f"📖 Reading {doc_path}...")
    original_content = doc_path.read_text(encoding='utf-8')
    original_lines = len(original_content.splitlines())

    print(f"✏️  Applying corrections...")
    corrected_content = apply_corrections(original_content)
    corrected_lines = len(corrected_content.splitlines())

    # Backup original
    backup_path = doc_path.parent / f"{doc_path.stem}.backup{doc_path.suffix}"
    backup_path.write_text(original_content, encoding='utf-8')
    print(f"💾 Backup saved: {backup_path}")

    # Write corrected version
    doc_path.write_text(corrected_content, encoding='utf-8')
    print(f"✅ Corrections applied: {doc_path}")
    print(f"   Original: {original_lines} lines")
    print(f"   Corrected: {corrected_lines} lines")
    print(f"   Diff: {corrected_lines - original_lines:+d} lines")

    print(f"\n🎯 Next Steps:")
    print(f"1. Review changes: git diff {doc_path}")
    print(f"2. Fix ASI duplicates: docker compose exec mysql mysql -uroot -ppassword dnd_compendium < docs/fix-asi-duplicates.sql")
    print(f"3. Verify ASI data: docker compose exec php php docs/verify-asi-data.php")
    print(f"4. Start Phase 1 implementation!")


if __name__ == "__main__":
    main()

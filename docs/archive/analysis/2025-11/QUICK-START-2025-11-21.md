# Quick Start Guide - 2025-11-21 Morning

**TL;DR:** Implementation plan ready for Class Importer enhancements. ~6 hours estimated.

---

## 🚀 Fastest Path to Start

```bash
# 1. Read the plan (5 minutes)
cat docs/plans/2025-11-20-class-importer-enhancements.md | less

# 2. Read the handover (2 minutes)
cat docs/SESSION-HANDOVER-2025-11-20.md | less

# 3. Create branch and start
git checkout -b feature/class-importer-enhancements

# 4. Begin with BATCH 0 (environment setup)
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php bash -c 'for file in import-files/class-*.xml; do php artisan import:classes "$file"; done'
docker compose exec php php artisan test  # Should be 426+ passing
```

---

## 📁 Key Files

| File | Purpose |
|------|---------|
| `docs/plans/2025-11-20-class-importer-enhancements.md` | **⭐ MAIN PLAN** - 13 batches, step-by-step |
| `docs/SESSION-HANDOVER-2025-11-20.md` | Context, decisions, insights |
| `docs/CLASS-IMPORTER-ISSUES-FOUND.md` | Original investigation findings |

---

## 🎯 What You're Fixing

### Issue 1: Spells Known (Batches 2.1-2.5)
- **Problem:** "Spells Known" in wrong table (counters instead of progression)
- **Fix:** Add column, update parser/importer, migrate data, update API
- **Time:** ~3.5 hours

### Issue 2: Proficiency Choices (Batches 3.1-3.4)
- **Problem:** No way to indicate "choose 2 skills from list"
- **Fix:** Add choice fields, update parser/importer, update API
- **Time:** ~2 hours

### Investigation: Feature Elements (Batch 1.1)
- **Task:** Search XML files for modifiers/proficiencies in features
- **Time:** ~30 minutes
- **Likely Result:** Non-issue (probably don't exist)

---

## 📊 Execution Flow

```
BATCH 0: Setup (30 min)
   ↓
BATCH 1.1: Investigation (30 min)
   ↓
BATCH 2.1: Add spells_known column (30 min)
   ↓
BATCH 2.2: Update parser (1 hour)
   ↓
BATCH 2.3: Update importer (45 min)
   ↓
BATCH 2.4: Data migration (1 hour)
   ↓
BATCH 2.5: Update API (15 min)
   ↓
BATCH 3.1: Add choice fields (30 min)
   ↓
BATCH 3.2: Update parser (45 min)
   ↓
BATCH 3.3: Update importer (30 min)
   ↓
BATCH 3.4: Update API (15 min)
   ↓
BATCH 4.1: Full verification (30 min)
   ↓
BATCH 4.2: Update docs (30 min)
   ↓
BATCH 4.3: Git cleanup (15 min)
   ↓
DONE! ✅
```

---

## ⚠️ Important Notes

1. **TDD Required:** Write tests BEFORE implementation in every batch
2. **One Commit Per Batch:** Keep changes atomic for easy rollback
3. **Fresh Imports:** After major changes, reimport to verify
4. **Full Test Suite:** Must pass after each batch
5. **Don't Skip Investigation:** BATCH 1.1 determines if scope expands

---

## 🧪 Test Files You'll Create

```
tests/Unit/Parsers/
├── ClassXmlParserSpellsKnownTest.php         (BATCH 2.2)
└── ClassXmlParserProficiencyChoicesTest.php  (BATCH 3.2)

tests/Feature/Migrations/
├── ClassLevelProgressionSpellsKnownTest.php  (BATCH 2.1)
├── MigrateSpellsKnownDataTest.php            (BATCH 2.4)
└── ProficiencyChoiceFieldsTest.php           (BATCH 3.1)

tests/Feature/Importers/
└── ClassImporterTest.php                     (update in BATCH 2.3, 3.3)

tests/Feature/Api/
├── ClassApiTest.php                          (update in BATCH 3.4)
└── ClassResourceCompleteTest.php             (update in BATCH 2.5)
```

---

## 🗄️ Database Changes

### Migrations You'll Create

1. `2025_11_20_create_spells_known_column.php`
   - Add `spells_known` to `class_level_progression`

2. `2025_11_20_migrate_spells_known_data.php`
   - Move data from `class_counters` to `class_level_progression`

3. `2025_11_20_add_choice_fields_to_proficiencies.php`
   - Add `is_choice`, `choices_allowed`, `choice_group`

---

## 🎨 Expected Outcomes

### Before
```json
// Spells Known as counter (wrong!)
{
  "counters": [
    {"name": "Spells Known", "value": 3, "level": 3}
  ]
}

// Proficiencies without choice info
{
  "proficiencies": [
    {"name": "Acrobatics", "type": "skill"},
    {"name": "Athletics", "type": "skill"}
    // ... 8 more, but should choose only 2!
  ]
}
```

### After
```json
// Spells Known in progression (correct!)
{
  "level_progression": [
    {"level": 3, "spells_known": 3, "spell_slots_1st": 2}
  ]
}

// Proficiencies with choice metadata
{
  "proficiencies": [
    {
      "name": "Acrobatics",
      "type": "skill",
      "is_choice": true,
      "choices_allowed": 2,
      "choice_group": "initial_skills"
    }
    // ... 9 more in same choice group
  ]
}
```

---

## 📝 Commit Message Template

```
feat: [descriptive message]

- Bullet point 1
- Bullet point 2
- Bullet point 3

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>
```

---

## 🆘 If You Get Stuck

1. **Check the main plan:** `docs/plans/2025-11-20-class-importer-enhancements.md`
2. **Review the batch:** Each batch has detailed code examples
3. **Run tests:** `docker compose exec php php artisan test --filter=NameOfTest`
4. **Check tinker:** Verify data directly in database
5. **Rollback if needed:** `git reset --hard` and try again

---

## ✅ Success Criteria

By end of session:
- [ ] 426+ tests passing (no regressions)
- [ ] ~15 new tests passing
- [ ] No "Spells Known" counters in database
- [ ] Eldritch Knight has spells_known in level_progression
- [ ] Fighter skills marked as is_choice=true with choices_allowed=2
- [ ] All code formatted with Pint
- [ ] Documentation updated
- [ ] Branch pushed to remote

---

## ⏰ Time Checkpoints

- **1 hour in:** Should have completed BATCH 0 + 1.1
- **3 hours in:** Should have completed through BATCH 2.3
- **5 hours in:** Should have completed through BATCH 3.4
- **6 hours in:** Should be wrapping up BATCH 4.1-4.3

---

**Created:** 2025-11-20 Evening
**Ready to Execute:** ✅ Yes
**First Command:** `cat docs/plans/2025-11-20-class-importer-enhancements.md`

Good luck! You've got this! 💪

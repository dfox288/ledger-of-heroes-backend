# Character Builder API - Design Proposal

**Date:** 2025-11-23
**Status:** 📋 Proposal (Not Started)
**Estimated Effort:** 8-12 hours
**Prerequisites:** All core entity APIs complete ✅

---

## 🎯 Goal

Create a rules-compliant D&D 5e Character Builder API that leverages our existing data (67 races, 16 classes, 34 backgrounds, 138 feats, 477 spells, 2,156 items).

**User Outcome:**
- Frontend can build character creation wizards without D&D rule knowledge
- API enforces D&D 5e rules (no invalid character builds)
- Automatic stat calculations (AC, HP, spell slots, modifiers)
- Character state always valid (no broken invariants)

---

## 📊 Available Data (Already Imported)

**Core Entities:**
- ✅ **67 Races** - With ability score modifiers, speeds, sizes
- ✅ **16 Base Classes** - Hit dice, spellcasting abilities, proficiencies, traits
- ✅ **129 Subclasses** - Class archetypes with additional features
- ✅ **34 Backgrounds** - Skill/tool proficiencies, language choices
- ✅ **138 Feats** - 53 with prerequisites, 88 with stat modifiers
- ✅ **477 Spells** - All levels (0-9), class spell lists available
- ✅ **2,156 Items** - Weapons, armor, equipment
- ✅ **Polymorphic Relationships** - Traits, proficiencies, modifiers, prerequisites

**Lookup Tables:**
- ✅ 6 Ability Scores, 18 Skills, 82 Proficiency Types, 30 Languages

**Data Quality:** All entities fully imported with relationships intact.

---

## 🗄️ Data Model Changes Needed

### New Tables (5 Total)

#### 1. `characters`
Primary character data and base stats.

```sql
CREATE TABLE characters (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,  -- NULL for now (no auth), FK later
    name VARCHAR(255) NOT NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 1,  -- 1-20
    experience_points INT UNSIGNED NOT NULL DEFAULT 0,

    -- Core choices
    race_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    background_id BIGINT UNSIGNED NULL,

    -- Ability scores (base values before modifiers)
    strength TINYINT UNSIGNED NOT NULL,
    dexterity TINYINT UNSIGNED NOT NULL,
    constitution TINYINT UNSIGNED NOT NULL,
    intelligence TINYINT UNSIGNED NOT NULL,
    wisdom TINYINT UNSIGNED NOT NULL,
    charisma TINYINT UNSIGNED NOT NULL,

    -- Hit points
    max_hit_points SMALLINT UNSIGNED NOT NULL,
    current_hit_points SMALLINT UNSIGNED NOT NULL,
    temp_hit_points SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    -- Calculated fields (cached for performance)
    armor_class TINYINT UNSIGNED NULL,
    proficiency_bonus TINYINT UNSIGNED GENERATED ALWAYS AS (2 + FLOOR((level - 1) / 4)),

    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_user_id (user_id),
    INDEX idx_race_id (race_id),
    INDEX idx_class_id (class_id),

    FOREIGN KEY (race_id) REFERENCES races(id),
    FOREIGN KEY (class_id) REFERENCES character_classes(id),
    FOREIGN KEY (background_id) REFERENCES backgrounds(id)
);
```

#### 2. `character_spells`
Tracks known/prepared spells per character.

```sql
CREATE TABLE character_spells (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,
    spell_id BIGINT UNSIGNED NOT NULL,

    -- Spell state
    preparation_status ENUM('known', 'prepared', 'always_prepared') NOT NULL DEFAULT 'known',
    source ENUM('class', 'race', 'feat', 'item') NOT NULL DEFAULT 'class',
    level_acquired TINYINT UNSIGNED NULL,  -- Level when learned

    created_at TIMESTAMP,

    INDEX idx_character_id (character_id),
    INDEX idx_spell_id (spell_id),
    UNIQUE KEY unique_character_spell (character_id, spell_id),

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (spell_id) REFERENCES spells(id)
);
```

#### 3. `character_features`
Tracks class/race/feat features acquired.

```sql
CREATE TABLE character_features (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,

    -- Polymorphic source
    feature_type VARCHAR(50) NOT NULL,  -- 'trait', 'class_feature', 'feat'
    feature_id BIGINT UNSIGNED NOT NULL,
    source ENUM('race', 'class', 'background', 'feat', 'item') NOT NULL,
    level_acquired TINYINT UNSIGNED NOT NULL,

    -- Usage tracking (for limited-use features)
    uses_remaining TINYINT UNSIGNED NULL,
    max_uses TINYINT UNSIGNED NULL,

    created_at TIMESTAMP,

    INDEX idx_character_id (character_id),
    INDEX idx_feature_type_id (feature_type, feature_id),

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
);
```

#### 4. `character_equipment`
Character inventory and equipped items.

```sql
CREATE TABLE character_equipment (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,

    quantity TINYINT UNSIGNED NOT NULL DEFAULT 1,
    equipped BOOLEAN NOT NULL DEFAULT FALSE,
    location ENUM('equipped', 'backpack', 'stored') NOT NULL DEFAULT 'backpack',

    created_at TIMESTAMP,

    INDEX idx_character_id (character_id),
    INDEX idx_item_id (item_id),

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
);
```

#### 5. `character_proficiencies`
Character-specific proficiencies (skills, tools, languages).

```sql
CREATE TABLE character_proficiencies (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,

    -- Polymorphic proficiency (skill OR proficiency type)
    proficiency_type_id BIGINT UNSIGNED NULL,  -- For tools/weapons/armor
    skill_id BIGINT UNSIGNED NULL,             -- For skills

    source ENUM('race', 'class', 'background', 'feat') NOT NULL,
    expertise BOOLEAN NOT NULL DEFAULT FALSE,  -- Double proficiency (Rogue/Bard)

    created_at TIMESTAMP,

    INDEX idx_character_id (character_id),
    INDEX idx_proficiency_type_id (proficiency_type_id),
    INDEX idx_skill_id (skill_id),

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (proficiency_type_id) REFERENCES proficiency_types(id),
    FOREIGN KEY (skill_id) REFERENCES skills(id),

    CHECK (proficiency_type_id IS NOT NULL OR skill_id IS NOT NULL)
);
```

**Note:** Leverage existing polymorphic relationships where possible.

---

## 🌐 API Endpoints

### Character CRUD
```
POST   /api/v1/characters                    Create character
GET    /api/v1/characters                    List user's characters
GET    /api/v1/characters/{id}               Show character (with full stats)
PATCH  /api/v1/characters/{id}               Update character name/notes
DELETE /api/v1/characters/{id}               Delete character
```

### Character Creation Flow
```
POST   /api/v1/characters/{id}/choose-race          Choose race
POST   /api/v1/characters/{id}/choose-class         Choose class
POST   /api/v1/characters/{id}/assign-abilities     Assign ability scores
POST   /api/v1/characters/{id}/choose-background    Choose background
POST   /api/v1/characters/{id}/choose-skills        Select skill proficiencies
POST   /api/v1/characters/{id}/choose-languages     Select bonus languages
```

### Available Choices (KEY FEATURE!)
```
GET    /api/v1/characters/{id}/available-choices    What can be chosen now?
```

**Response Example:**
```json
{
  "current_step": "choose_skills",
  "required_choices": {
    "skills": {
      "required": 2,
      "available": ["Athletics", "Acrobatics", "Stealth", "Sleight of Hand"]
    }
  },
  "optional_choices": {
    "languages": {
      "available": 1,
      "options": ["Elvish", "Dwarvish", "Draconic", ...]
    }
  }
}
```

### Spell Management
```
GET    /api/v1/characters/{id}/spells                List character spells
POST   /api/v1/characters/{id}/spells                Learn new spell
DELETE /api/v1/characters/{id}/spells/{spell_id}     Forget spell
PATCH  /api/v1/characters/{id}/spells/{spell_id}     Toggle preparation
GET    /api/v1/characters/{id}/available-spells      Spells learnable now
```

### Level Progression
```
POST   /api/v1/characters/{id}/level-up              Level up character
GET    /api/v1/characters/{id}/level-features        Features gained at level X
```

### Equipment
```
GET    /api/v1/characters/{id}/equipment             List equipment
POST   /api/v1/characters/{id}/equipment             Add item
PATCH  /api/v1/characters/{id}/equipment/{id}        Equip/unequip
DELETE /api/v1/characters/{id}/equipment/{id}        Remove item
```

### Calculated Stats (Read-only)
```
GET    /api/v1/characters/{id}/stats                 All calculated stats
```

**Response Example:**
```json
{
  "ability_scores": {
    "STR": 16, "DEX": 14, "CON": 12,
    "INT": 18, "WIS": 13, "CHA": 11
  },
  "modifiers": {
    "STR": +3, "DEX": +2, "CON": +1,
    "INT": +4, "WIS": +1, "CHA": 0
  },
  "armor_class": 15,
  "initiative": +2,
  "proficiency_bonus": +2,
  "spell_save_dc": 15,
  "spell_attack_bonus": +7,
  "spell_slots": { "1": 4, "2": 3, "3": 2 },
  "saving_throws": {
    "STR": +3, "DEX": +2, "CON": +1,
    "INT": +8, "WIS": +5, "CHA": 0
  },
  "skills": {
    "Athletics": +7, "Acrobatics": +2, "Stealth": +2,
    "Arcana": +8, "History": +8, "Investigation": +8,
    "Perception": +3, "Insight": +3
  }
}
```

---

## 🏗️ Services Architecture

### 1. CharacterBuilderService
**Responsibility:** Character creation flow

```php
class CharacterBuilderService
{
    public function createCharacter(array $data): Character
    public function chooseRace(Character $character, int $raceId): Character
    public function chooseClass(Character $character, int $classId): Character
    public function assignAbilities(Character $character, array $scores): Character
    public function chooseBackground(Character $character, int $backgroundId): Character
    public function chooseSkills(Character $character, array $skillIds): Character
}
```

### 2. CharacterProgressionService
**Responsibility:** Leveling and feature unlocks

```php
class CharacterProgressionService
{
    public function levelUp(Character $character): Character
    public function getFeaturesAtLevel(Character $character, int $level): Collection
    public function getAvailableFeatureChoices(Character $character): array
    public function grantAbilityScoreIncrease(Character $character, array $increases): Character
}
```

### 3. SpellManagerService
**Responsibility:** Spell learning and preparation

```php
class SpellManagerService
{
    public function getAvailableSpells(Character $character): Collection
    public function learnSpell(Character $character, int $spellId): CharacterSpell
    public function forgetSpell(Character $character, int $spellId): void
    public function prepareSpells(Character $character, array $spellIds): void
    public function getSpellSlots(Character $character): array
}
```

### 4. CharacterStatCalculator
**Responsibility:** Derived stat calculations

```php
class CharacterStatCalculator
{
    public function calculateAC(Character $character): int
    public function calculateHP(Character $character): int
    public function calculateProficiencyBonus(int $level): int
    public function calculateSkillModifiers(Character $character): array
    public function calculateSavingThrows(Character $character): array
    public function calculateSpellSlots(Character $character): array
    public function calculateAttackBonus(Character $character): array
}
```

### 5. ChoiceValidationService
**Responsibility:** Rule enforcement

```php
class ChoiceValidationService
{
    public function validateRaceChoice(int $raceId): bool
    public function validateClassChoice(int $classId): bool
    public function validateAbilityScores(array $scores, string $method): bool
    public function validateSpellChoice(Character $character, int $spellId): bool
    public function validateSkillChoice(Character $character, array $skillIds): bool
    public function validateFeatPrerequisites(Character $character, int $featId): bool
}
```

---

## 🧪 Testing Strategy

### Test Coverage Goals
- **Unit Tests:** 40+ tests (stat calculations, validators)
- **Feature Tests:** 30+ tests (API endpoints, character creation flow)
- **Integration Tests:** 10+ tests (full character build + level up)

### Key Test Cases

**Unit Tests:**
- ✅ AC calculation with armor + shield + DEX
- ✅ HP calculation per level (hit die + CON modifier)
- ✅ Spell slot calculation by class and level
- ✅ Proficiency bonus by level (2 at lvl 1, +1 per 4 levels)
- ✅ Skill modifier = ability modifier + proficiency + expertise

**Feature Tests:**
- ✅ Character creation flow (race → class → abilities)
- ✅ Invalid ability score distribution rejected
- ✅ Spell selection respects class spell list
- ✅ Spell limit enforced (can't know 100 spells at level 1)
- ✅ Feat prerequisites validated
- ✅ Level up grants correct features

**Edge Cases:**
- Multi-class spell slot calculation (v2)
- Expertise on same skill from multiple sources (doesn't stack)
- Feat prerequisites with ability score requirements
- Negative Constitution modifier affecting HP
- Equipment over carrying capacity

---

## ⚡ Performance Considerations

### Caching Strategy
- **Character stats:** Cache for 15 minutes, invalidate on character update
- **Available choices:** Cache for 1 hour (static based on class/level)
- **Spell lists:** Cache for 1 hour (rarely changes)

### Query Optimization
- **Eager loading:** Always load `race`, `class`, `background`, `spells` together
- **N+1 Prevention:** Spell lists pre-loaded with class data
- **Indexes:** `user_id`, `race_id`, `class_id`, `character_id`

### Expected Performance
- Character creation: <100ms
- Stat calculation: <50ms (with caching)
- Spell selection: <75ms
- Level up: <150ms

---

## 📋 Implementation Plan (8-12 hours)

### Phase 1: Foundation (3-4 hours)
1. Create 5 migrations (characters, character_spells, character_equipment, character_proficiencies, character_features)
2. Create Character model with relationships
3. Create CharacterFactory + related factories
4. Write failing tests for CharacterStatCalculator
5. Implement CharacterStatCalculator with tests passing

### Phase 2: Character Creation (3-4 hours)
6. Create CharacterBuilderService
7. Implement race/class/background selection with validation
8. Implement ability score assignment (point buy + standard array + manual)
9. Create API endpoints for creation flow
10. Write Form Requests for validation
11. Create CharacterResource for API responses
12. Write 20+ feature tests for creation flow

### Phase 3: Spell Management (2-3 hours)
13. Create SpellManagerService
14. Implement spell selection with class restrictions
15. Implement spell preparation logic (wizard vs sorcerer)
16. Calculate spell slots by level
17. Create spell management endpoints
18. Write 15+ tests for spell system

### Phase 4: Leveling & Stats (2-3 hours)
19. Create CharacterProgressionService
20. Implement level up with feature unlocks
21. Implement calculated stats endpoint
22. Create equipment system (basic)
23. Write 10+ tests for leveling
24. Performance test with 100+ characters

---

## ✅ Success Criteria

- ✅ Can create valid D&D 5e character via API (race → class → abilities → background)
- ✅ All stats calculated correctly (AC, HP, spell slots, modifiers, saves, skills)
- ✅ Invalid choices rejected with clear error messages (400 Bad Request with details)
- ✅ Spell selection enforces class spell lists and known spell limits
- ✅ Level up grants correct features automatically
- ✅ 80+ tests covering character building (50+ unit, 30+ feature)
- ✅ API documented in Scramble/OpenAPI
- ✅ Performance: <100ms for character creation, <50ms for stat calculation

---

## 🎯 Open Questions (For Future Decision)

1. **Authentication:** User-owned characters or public for now?
   - **Proposal:** NULL `user_id` for v1 (public characters), add auth in v2

2. **Multi-class:** Include in v1 or defer to v2?
   - **Proposal:** Defer to v2 (complex spell slot calculations)

3. **Starting Equipment:** Auto-assign class starting gear or manual selection?
   - **Proposal:** Auto-assign in v1, manual selection in v2

4. **Hit Points:** Roll hit dice or take average on level up?
   - **Proposal:** Average in v1 (predictable), add roll option in v2

5. **Spell Preparation:** Daily limit or flexible?
   - **Proposal:** Flexible in v1 (no time tracking), add daily reset in v2

---

## 📚 Related Documentation

- [Database Design](2025-11-17-dnd-compendium-database-design.md) - Original schema
- [CLAUDE.md](../CLAUDE.md) - Development standards
- [Race API](../app/Http/Controllers/Api/RaceController.php) - Reference implementation
- [Spell API](../app/Http/Controllers/Api/SpellController.php) - Reference implementation

---

## 🚀 Next Steps

When ready to implement:
1. Review this proposal for any updates needed
2. Use `superpowers-laravel:writing-plans` to create detailed implementation plan
3. Follow TDD approach (write tests first)
4. Implement in phases (foundation → creation → spells → leveling)
5. Performance test with realistic character counts
6. Document API with Scramble

---

**Status:** 📋 Proposal Ready for Review
**Estimated Effort:** 8-12 hours
**Complexity:** Medium-High (rule validation, stat calculations)
**Risk Level:** Low (leverages existing data, no breaking changes)

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>

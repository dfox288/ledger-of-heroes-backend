# Character Builder API - Design Proposal v2

**Date:** 2025-11-30
**Status:** 📋 Proposal (Approved for Planning)
**Supersedes:** `2025-11-23-character-builder-api-proposal.md`
**GitHub Issue:** #21

---

## Overview

### Goal

Build a persistent D&D 5e character management API with:
- **v1:** Character creation + stat calculation (foundation)
- **v2:** Full character lifecycle (leveling, equipment, multiclass)

### User Outcome

- Frontend can build character creation wizards without D&D rule knowledge
- API enforces D&D 5e rules (no invalid character builds)
- Automatic stat calculations (AC, HP, spell slots, modifiers)
- Full audit trail of where every bonus comes from
- Character state always valid (no broken invariants)

---

## What Changed Since Original Proposal (Nov 23)

| Area | Original | Now |
|------|----------|-----|
| **Spell Choices** | Not considered | `entity_spells` supports choices with constraints |
| **Model Traits** | Monolithic | 11 reusable concerns (`HasEntitySpells`, etc.) |
| **Subclass Spells** | Not parsed | Domain/Circle/Expanded spells fully implemented |
| **Modifiers** | Basic | Passive scores, skill advantages supported |
| **Prerequisites** | Basic | OR syntax, subrace prerequisites supported |
| **Ability Tracking** | Single field | New `character_ability_adjustments` for audit trail |

---

## Data Model

### New Tables (7)

#### 1. `characters`
Primary character identity and base ability scores.

```sql
CREATE TABLE characters (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,  -- NULL for v1 (no auth)
    name VARCHAR(255) NOT NULL,

    -- Core choices
    race_id BIGINT UNSIGNED NOT NULL,
    subrace_id BIGINT UNSIGNED NULL,
    background_id BIGINT UNSIGNED NULL,

    -- Base ability scores (BEFORE any bonuses)
    base_str TINYINT UNSIGNED NOT NULL DEFAULT 10,
    base_dex TINYINT UNSIGNED NOT NULL DEFAULT 10,
    base_con TINYINT UNSIGNED NOT NULL DEFAULT 10,
    base_int TINYINT UNSIGNED NOT NULL DEFAULT 10,
    base_wis TINYINT UNSIGNED NOT NULL DEFAULT 10,
    base_cha TINYINT UNSIGNED NOT NULL DEFAULT 10,

    -- Hit points
    current_hp SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    temp_hp SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    -- Status
    status ENUM('draft', 'complete') NOT NULL DEFAULT 'draft',

    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_user_id (user_id),
    FOREIGN KEY (race_id) REFERENCES races(id),
    FOREIGN KEY (background_id) REFERENCES backgrounds(id)
);
```

#### 2. `character_classes`
Pivot table supporting multiclass (character can have multiple classes).

```sql
CREATE TABLE character_classes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    subclass_id BIGINT UNSIGNED NULL,  -- NULL until subclass level

    level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_primary BOOLEAN NOT NULL DEFAULT FALSE,  -- First class taken
    hit_dice_used TINYINT UNSIGNED NOT NULL DEFAULT 0,

    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_character_id (character_id),
    UNIQUE KEY unique_character_class (character_id, class_id),

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES character_classes(id),
    FOREIGN KEY (subclass_id) REFERENCES subclasses(id)
);
```

#### 3. `character_spells`
Spells known, prepared, or in spellbook.

```sql
CREATE TABLE character_spells (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,
    spell_id BIGINT UNSIGNED NOT NULL,

    -- Source tracking (where did this spell come from?)
    source_type VARCHAR(50) NOT NULL,  -- 'class', 'race', 'feat', 'item'
    source_id BIGINT UNSIGNED NOT NULL,

    -- Spell state
    is_prepared BOOLEAN NOT NULL DEFAULT FALSE,
    is_always_prepared BOOLEAN NOT NULL DEFAULT FALSE,  -- Domain spells, etc.
    in_spellbook BOOLEAN NOT NULL DEFAULT FALSE,  -- Wizards only

    created_at TIMESTAMP,

    INDEX idx_character_id (character_id),
    UNIQUE KEY unique_character_spell_source (character_id, spell_id, source_type, source_id),

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (spell_id) REFERENCES spells(id)
);
```

#### 4. `character_proficiencies`
All proficiencies with source tracking.

```sql
CREATE TABLE character_proficiencies (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,

    -- What type of proficiency
    proficiency_type ENUM('skill', 'tool', 'weapon', 'armor', 'language', 'saving_throw') NOT NULL,

    -- Polymorphic reference to the proficiency
    skill_id BIGINT UNSIGNED NULL,
    proficiency_type_id BIGINT UNSIGNED NULL,
    language_id BIGINT UNSIGNED NULL,
    ability_score_id BIGINT UNSIGNED NULL,  -- For saving throws

    -- Source tracking
    source_type VARCHAR(50) NOT NULL,  -- 'race', 'class', 'background', 'feat'
    source_id BIGINT UNSIGNED NOT NULL,

    has_expertise BOOLEAN NOT NULL DEFAULT FALSE,

    created_at TIMESTAMP,

    INDEX idx_character_id (character_id),

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id),
    FOREIGN KEY (proficiency_type_id) REFERENCES proficiency_types(id),
    FOREIGN KEY (language_id) REFERENCES languages(id),
    FOREIGN KEY (ability_score_id) REFERENCES ability_scores(id)
);
```

#### 5. `character_features`
Acquired features from race, class, background, feats.

```sql
CREATE TABLE character_features (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,

    -- Polymorphic feature reference
    feature_type VARCHAR(50) NOT NULL,  -- 'racial_trait', 'class_feature', 'feat', 'background_feature'
    feature_id BIGINT UNSIGNED NOT NULL,

    -- Source tracking
    source_type VARCHAR(50) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    level_acquired TINYINT UNSIGNED NOT NULL DEFAULT 1,

    -- Usage tracking (for limited-use features)
    uses_remaining TINYINT UNSIGNED NULL,
    uses_max TINYINT UNSIGNED NULL,
    recharge_type ENUM('short_rest', 'long_rest', 'daily', 'dawn') NULL,

    created_at TIMESTAMP,

    INDEX idx_character_id (character_id),
    INDEX idx_feature (feature_type, feature_id),

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
);
```

#### 6. `character_equipment`
Inventory with equipped and attunement state.

```sql
CREATE TABLE character_equipment (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,

    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    equipped BOOLEAN NOT NULL DEFAULT FALSE,
    attuned BOOLEAN NOT NULL DEFAULT FALSE,

    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_character_id (character_id),

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id)
);
```

#### 7. `character_ability_adjustments`
Audit trail for ALL ability score modifications.

```sql
CREATE TABLE character_ability_adjustments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    character_id BIGINT UNSIGNED NOT NULL,
    ability_score_id BIGINT UNSIGNED NOT NULL,

    adjustment_value TINYINT NOT NULL,  -- Can be negative

    -- Source tracking
    source_type VARCHAR(50) NOT NULL,  -- 'race', 'feat', 'asi', 'item'
    source_id BIGINT UNSIGNED NULL,

    created_at TIMESTAMP,

    INDEX idx_character_id (character_id),

    FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
    FOREIGN KEY (ability_score_id) REFERENCES ability_scores(id)
);
```

---

## Services Architecture

### 1. CharacterStatCalculator
Pure calculation engine - no side effects.

```php
class CharacterStatCalculator
{
    // Core calculations
    public function abilityModifier(int $score): int;
    public function proficiencyBonus(int $totalLevel): int;

    // Derived stats
    public function calculateAbilityScores(Character $character): array;
    public function calculateArmorClass(Character $character): int;
    public function calculateMaxHitPoints(Character $character): int;
    public function calculateSavingThrows(Character $character): array;
    public function calculateSkillModifiers(Character $character): array;
    public function calculateSpellcastingStats(Character $character): ?array;
    public function calculateSpellSlots(Character $character): array;

    // Full stat block
    public function calculate(Character $character): CharacterStatsDTO;
}
```

### 2. CharacterBuilderService
Orchestrates character creation flow.

```php
class CharacterBuilderService
{
    public function createCharacter(string $name, ?int $userId = null): Character;
    public function chooseRace(Character $character, int $raceId, ?int $subraceId = null): Character;
    public function chooseClass(Character $character, int $classId): Character;
    public function assignAbilities(Character $character, array $scores, string $method): Character;
    public function chooseBackground(Character $character, int $backgroundId): Character;
    public function resolveChoices(Character $character, array $choices): Character;
    public function getPendingChoices(Character $character): PendingChoicesDTO;
    public function finalizeCharacter(Character $character): Character;
}
```

### 3. ChoiceValidationService
Enforces D&D 5e rules.

```php
class ChoiceValidationService
{
    public function validateAbilityScores(array $scores, string $method): ValidationResult;
    public function validateSpellChoice(Character $character, int $spellId, string $source): ValidationResult;
    public function validateFeatPrerequisites(Character $character, int $featId): ValidationResult;
    public function validateSkillChoice(Character $character, array $skillIds, string $source): ValidationResult;
    public function validateMulticlassPrerequisites(Character $character, int $classId): ValidationResult;
}
```

### 4. SpellManagerService
Handles spell learning and preparation.

```php
class SpellManagerService
{
    public function getAvailableSpells(Character $character, ?int $maxLevel = null): Collection;
    public function learnSpell(Character $character, int $spellId, string $sourceType, int $sourceId): CharacterSpell;
    public function prepareSpell(Character $character, int $spellId): void;
    public function unprepareSpell(Character $character, int $spellId): void;
    public function getPreparationLimit(Character $character): int;
    public function getKnownSpellLimit(Character $character): ?int;  // NULL = unlimited (prepared casters)
}
```

### 5. CharacterProgressionService (v1.5)
Handles level up flow.

```php
class CharacterProgressionService
{
    public function levelUp(Character $character, int $classId): LevelUpResult;
    public function getAvailableFeats(Character $character): Collection;
    public function takeFeat(Character $character, int $featId, array $choices): void;
    public function applyAbilityScoreIncrease(Character $character, array $increases): void;
}
```

---

## API Endpoints

### Character CRUD
```
POST   /api/v1/characters                    Create draft character
GET    /api/v1/characters                    List user's characters
GET    /api/v1/characters/{id}               Full character + computed stats
PATCH  /api/v1/characters/{id}               Update name
DELETE /api/v1/characters/{id}               Delete character
```

### Character Creation Flow
```
POST   /api/v1/characters/{id}/race          Choose race (+ optional subrace)
POST   /api/v1/characters/{id}/class         Choose starting class
POST   /api/v1/characters/{id}/abilities     Assign ability scores
POST   /api/v1/characters/{id}/background    Choose background
GET    /api/v1/characters/{id}/pending-choices   What needs to be resolved?
POST   /api/v1/characters/{id}/choices       Resolve pending choices (skills, spells, etc.)
POST   /api/v1/characters/{id}/finalize      Mark character complete
```

### Computed Stats
```
GET    /api/v1/characters/{id}/stats         All computed stats (cached)
```

### Spell Management
```
GET    /api/v1/characters/{id}/spells            List character's spells
GET    /api/v1/characters/{id}/available-spells  Spells eligible to learn/prepare
POST   /api/v1/characters/{id}/spells            Learn a spell
DELETE /api/v1/characters/{id}/spells/{spell_id} Forget a spell (if allowed)
POST   /api/v1/characters/{id}/spells/{spell_id}/prepare    Prepare spell
DELETE /api/v1/characters/{id}/spells/{spell_id}/prepare    Unprepare spell
```

### Level Up (v1.5)
```
POST   /api/v1/characters/{id}/level-up          Level up in a class
GET    /api/v1/characters/{id}/available-feats   Feats character qualifies for
POST   /api/v1/characters/{id}/feats             Take a feat (at ASI levels)
```

### Validation Helpers
```
POST   /api/v1/character-builder/validate-abilities   Validate point buy/standard array
```

---

## Key Invariants (Rules Engine Must Enforce)

### Ability Scores
- Base scores: 1-20 range
- Racial bonuses apply on top of base
- "Set" items (Gauntlets of Ogre Power) override if higher
- Final score capped at 20 (30 with magic items/boons)

### Character Level
- Total level = SUM(class levels), max 20
- Proficiency bonus = 2 + floor((total_level - 1) / 4)

### Spellcasting
- Preparation limit varies by class (see table below)
- Cantrip count from class table
- Domain/Circle/Always-prepared spells don't count against limit
- Warlock uses Pact Magic (separate from spell slots)

| Class | Prepares | Formula | Spellbook |
|-------|----------|---------|-----------|
| Wizard | Yes | INT mod + level | Yes |
| Cleric | Yes | WIS mod + level | No |
| Druid | Yes | WIS mod + level | No |
| Paladin | Yes | CHA mod + half level | No |
| Sorcerer | No (knows) | Table | No |
| Bard | No (knows) | Table | No |
| Warlock | No (knows) | Table | No |
| Ranger | No (knows) | Table | No |

### Proficiencies
- Same proficiency from multiple sources = only get once
- Expertise requires existing proficiency
- Expertise doesn't stack

### Feats
- Can't take same feat twice (most feats)
- Prerequisites validated before granting
- Feat-granted spells use feat's ability score

### Equipment (v1.5)
- Attunement limit: 3 items
- Armor affects AC calculation
- Magic item bonuses only apply if equipped (and attuned if required)

---

## Performance Strategy

### Eager Loading
```php
private const FULL_RELATIONSHIPS = [
    'race.modifiers',
    'classes.class',
    'classes.subclass',
    'background',
    'spells.spell',
    'proficiencies.skill',
    'proficiencies.proficiencyType',
    'features',
    'equipment.item.modifiers',
    'abilityAdjustments.abilityScore',
];
```

### Caching
- Character stats: 15 min TTL
- Invalidate on any character modification event

### Meilisearch
- Use for spell/feat filtering (already indexed)
- `Spell::search('')->filter("class_slugs IN [wizard] AND level <= 3")`

---

## Testing Strategy

### TDD Entry Point
Start with `CharacterStatCalculator` - pure logic, no DB.

### Test Counts (Target: ~75 tests)
| Category | Count | Focus |
|----------|-------|-------|
| Unit: StatCalculator | ~20 | Ability mods, prof bonus, AC, HP, spell slots |
| Unit: Validators | ~15 | Point buy, prerequisites, spell eligibility |
| Feature: Creation Flow | ~15 | POST endpoints, choice resolution |
| Feature: Character CRUD | ~10 | Basic API operations |
| Feature: Spell Management | ~10 | Learn, prepare, available spells |
| Integration: Full Build | ~5 | Complete character creation |

### Edge Cases
- CON modifier change → HP recalculates retroactively
- Wizard with INT 10 → can prepare 1 spell at level 1
- Same skill from race AND background → replacement choice
- Expertise on non-proficient skill → rejected
- Domain spells → always prepared, don't count against limit

---

## Implementation Phases

### Phase 1: Foundation (v1 Core)
- [ ] Migrations for all 7 tables
- [ ] Character model + relationships
- [ ] CharacterFactory
- [ ] CharacterStatCalculator with full tests
- [ ] Basic CRUD endpoints

### Phase 2: Creation Flow (v1 Core)
- [ ] CharacterBuilderService
- [ ] ChoiceValidationService
- [ ] Race/Class/Background selection endpoints
- [ ] Ability score assignment (point buy, standard array)
- [ ] Pending choices resolution
- [ ] Form Requests for validation
- [ ] CharacterResource for API responses

### Phase 3: Spell Management (v1 Core)
- [ ] SpellManagerService
- [ ] Spell learning/preparation endpoints
- [ ] Available spells filtering (via Meilisearch)
- [ ] Preparation limits by class

### Phase 4: Level Up (v1.5)
- [ ] CharacterProgressionService
- [ ] Level up endpoint
- [ ] ASI / Feat choice at appropriate levels
- [ ] Subclass selection

### Phase 5: Equipment (v2)
- [ ] Equipment management endpoints
- [ ] Item stat integration in calculator
- [ ] Attunement tracking
- [ ] "Set" ability scores (Gauntlets, Belts)

### Phase 6: Multiclass (v2)
- [ ] Multiclass prerequisites validation
- [ ] Combined spell slot calculation
- [ ] Proficiency restrictions for new classes

---

## Estimated Effort

| Phase | Effort | Priority |
|-------|--------|----------|
| Phase 1: Foundation | 4-5 hours | v1 |
| Phase 2: Creation Flow | 5-6 hours | v1 |
| Phase 3: Spell Management | 3-4 hours | v1 |
| Phase 4: Level Up | 4-5 hours | v1.5 |
| Phase 5: Equipment | 3-4 hours | v2 |
| Phase 6: Multiclass | 4-6 hours | v2 |

**v1 Total: 12-15 hours**
**Full Feature: 23-30 hours**

---

## Open Questions (Resolved)

| Question | Decision |
|----------|----------|
| User authentication | NULL user_id for v1, add auth later |
| Multiclass in v1 | Defer to v2 |
| Starting equipment | Auto-assign in v1, manual in v2 |
| HP on level up | Average (not rolled) for v1 |
| Item stat bonuses | Schema ready in v1, full calc in v2 |

---

## Related Documents

- Original proposal: `docs/plans/2025-11-23-character-builder-api-proposal.md`
- Spell choices: `docs/plans/2025-11-30-feat-spell-choices.md`
- Subclass spells: `docs/plans/2025-11-30-subclass-spell-lists.md`

---

**Status:** 📋 Ready for Implementation Planning

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>

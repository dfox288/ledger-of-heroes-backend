# Slug-Based Character References Design

**Date:** 2025-01-07
**Status:** Approved
**Goal:** Decouple character data from database IDs to survive sourcebook reimports

## Problem

Characters currently store integer foreign keys (`race_id`, `class_id`, `spell_id`, etc.) that reference imported sourcebook data. When sourcebooks are reimported:

1. IDs can change if import order changes
2. New sourcebooks might shift existing IDs
3. Characters with stored `race_id=5` now reference the wrong race (or nothing)

## Solution

Replace all `*_id` foreign keys in character tables with `*_slug` columns storing source-prefixed slugs in the format `{source_code}:{slug}`.

### Slug Format

**Format:** `{source_code}:{slug}`

**Examples:**
- `phb:high-elf`
- `phb:wizard`
- `xge:shadow-blade`
- `homebrew:cool-sword`

**Separator:** Colon (`:`) chosen for:
- Clear namespace semantics
- Docker/URN precedent (`nginx:latest`, `urn:isbn:...`)
- URL-safe (RFC 3986)
- Meilisearch filter compatible

### Entities Using `slug` vs `code`

Most entities use `slug` (kebab-case), but some lookup tables use `code`:

| Entity | Identifier Column | Format | Examples |
|--------|------------------|--------|----------|
| races | `slug` | kebab-case | `high-elf`, `mountain-dwarf` |
| classes | `slug` | kebab-case | `wizard`, `fighter` |
| spells | `slug` | kebab-case | `fireball`, `magic-missile` |
| items | `slug` | kebab-case | `longsword`, `potion-of-healing` |
| backgrounds | `slug` | kebab-case | `sage`, `soldier` |
| feats | `slug` | kebab-case | `alert`, `lucky` |
| optional_features | `slug` | kebab-case | `agonizing-blast` |
| languages | `slug` | kebab-case | `common`, `elvish` |
| conditions | `slug` | kebab-case | `blinded`, `charmed` |
| skills | `slug` | kebab-case | `athletics`, `acrobatics` |
| proficiency_types | `slug` | kebab-case | `light-armor`, `longsword` |
| senses | `slug` | kebab-case | `darkvision` |
| ability_scores | `code` | 3-letter uppercase | `STR`, `DEX`, `CON` |
| sizes | `code` | 1-letter uppercase | `T`, `S`, `M`, `L` |
| damage_types | `code` | 1-letter uppercase | `A`, `B`, `C`, `F` |
| spell_schools | `code` | 1-letter uppercase | `A`, `C`, `D`, `E` |
| sources | `code` | 3-4 letter uppercase | `PHB`, `DMG`, `XGE` |
| item_types | `code` | 1-letter uppercase | `A`, `M`, `R` |
| item_properties | `code` | 1-letter uppercase | `A`, `F`, `H` |

For source-prefixed slugs, we use the source `code` as prefix regardless of whether the entity uses `slug` or `code` internally.

## Schema Changes

### `characters` table

```diff
- race_id        INT NULL
- background_id  INT NULL
+ race_slug        VARCHAR(150) NULL
+ background_slug  VARCHAR(150) NULL
```

### `character_classes` table

```diff
- class_id       INT
- subclass_id    INT NULL
+ class_slug       VARCHAR(150) NOT NULL
+ subclass_slug    VARCHAR(150) NULL
```

### `character_spells` table

```diff
- spell_id       INT
+ spell_slug       VARCHAR(150) NOT NULL
```

### `character_equipment` table

```diff
- item_id        INT NULL
+ item_slug        VARCHAR(150) NULL
```
(NULL preserved for custom/freetext items)

### `character_languages` table

```diff
- language_id    INT
+ language_slug    VARCHAR(150) NOT NULL
```

### `character_proficiencies` table

```diff
- skill_id              INT NULL
- proficiency_type_id   INT NULL
+ skill_slug              VARCHAR(150) NULL
+ proficiency_type_slug   VARCHAR(150) NULL
```

### `character_conditions` table

```diff
- condition_id   INT
+ condition_slug   VARCHAR(150) NOT NULL
```

### `feature_selections` table

```diff
- optional_feature_id  INT
- class_id             INT
+ optional_feature_slug  VARCHAR(150) NOT NULL
+ class_slug             VARCHAR(150) NOT NULL
```

### Indexes

Add indexes on all new `*_slug` columns for query performance.

## Model Changes

### Relationship Definitions

Models change from ID-based to slug-based foreign keys:

```php
// Before
public function race(): BelongsTo
{
    return $this->belongsTo(Race::class);
}

// After
public function race(): BelongsTo
{
    return $this->belongsTo(Race::class, 'race_slug', 'slug');
}
```

**Note:** This requires entity tables to have composite unique keys on `(source_code, slug)` or a computed `full_slug` column. See "Entity Table Changes" section.

### Entity Table Changes

Entity tables need a `full_slug` column storing the source-prefixed slug:

```php
// races table adds:
full_slug VARCHAR(150) UNIQUE  -- e.g., "phb:high-elf"
```

Generated on import: `$fullSlug = "{$sourceCode}:{$slug}";`

The relationship then becomes:

```php
public function race(): BelongsTo
{
    return $this->belongsTo(Race::class, 'race_slug', 'full_slug');
}
```

## API Changes

### Request Field Naming

API requests use implicit naming (no `_slug` suffix):

**Before:**
```json
{"race_id": 5, "class_id": 3, "background_id": 12}
```

**After:**
```json
{"race": "phb:high-elf", "class": "phb:wizard", "background": "phb:sage"}
```

### Form Request Mapping

Form Requests map API fields to database columns:

```php
public function rules(): array
{
    return [
        'race' => ['sometimes', 'nullable', 'string', 'max:150'],
        'class' => ['sometimes', 'nullable', 'string', 'max:150'],
        'background' => ['sometimes', 'nullable', 'string', 'max:150'],
    ];
}

// Map to database columns
protected function prepareForValidation(): void
{
    $this->merge([
        'race_slug' => $this->input('race'),
        'class_slug' => $this->input('class'),
        'background_slug' => $this->input('background'),
    ]);
}
```

### Validation Strategy: Dangling References Allowed

Write operations do **not** validate that slugs exist in the database. This enables:
- Importing characters before all sourcebooks are loaded
- Sharing characters between instances with different content

### Integrity Validation Endpoint

New endpoint to check character data integrity:

```
GET /api/v1/characters/{character}/validate
```

**Response:**
```json
{
  "valid": false,
  "missing": {
    "race": "phb:high-elf",
    "spells": ["phb:wish", "phb:meteor-swarm"],
    "items": ["dmg:staff-of-the-magi"]
  },
  "warnings": [
    "Character is level 5 wizard but has no subclass selected"
  ]
}
```

### Bulk Validation Endpoint

```
GET /api/v1/characters/validate-all
```

**Response:**
```json
{
  "total": 42,
  "valid": 38,
  "invalid": 4,
  "characters": [
    {"public_id": "brave-wizard-x7k2", "missing": {"race": "phb:high-elf"}},
    {"public_id": "dark-rogue-m3p1", "missing": {"spells": ["phb:shadow-blade"]}}
  ]
}
```

## Export Format

Character export uses pure slugs:

```json
{
  "public_id": "brave-wizard-x7k2",
  "name": "Gandalf",
  "race": "phb:human",
  "background": "phb:sage",
  "classes": [
    {"class": "phb:wizard", "subclass": "phb:school-of-evocation", "level": 20}
  ],
  "spells": [
    {"spell": "phb:fireball", "source": "class", "prepared": true}
  ],
  "equipment": [
    {"item": "dmg:staff-of-power", "equipped": true, "quantity": 1},
    {"custom_name": "Pipe of Gandalf", "quantity": 1}
  ],
  "languages": ["phb:common", "phb:elvish", "phb:dwarvish"],
  "proficiencies": {
    "skills": ["phb:arcana", "phb:history"],
    "types": ["phb:quarterstaffs"]
  },
  "conditions": []
}
```

This format is directly importable to any instance with matching sourcebook data.

## Importer Changes

### Entity Importers

All entity importers must generate `full_slug` during import:

```php
// In SpellImporter, RaceImporter, etc.
$fullSlug = "{$sourceCode}:{$slug}";
$entity->full_slug = $fullSlug;
```

### Source Code Resolution

Importers already know the source code from the import context. This is passed through the import chain.

## Migration Strategy

### Breaking Change

This is a breaking change. Existing characters will not be migrated - they can be recreated using the new format.

### Migration Steps

1. Add `full_slug` column to all entity tables
2. Populate `full_slug` for existing entities (one-time script)
3. Create new character tables with `*_slug` columns (or alter existing)
4. Drop old `*_id` columns from character tables
5. Update all models, controllers, requests, resources
6. Update all tests

## Affected Files

### Models (update relationships)
- `app/Models/Character.php`
- `app/Models/CharacterClassPivot.php`
- `app/Models/CharacterSpell.php`
- `app/Models/CharacterEquipment.php`
- `app/Models/CharacterLanguage.php`
- `app/Models/CharacterProficiency.php`
- `app/Models/CharacterCondition.php`
- `app/Models/FeatureSelection.php`

### Entity Models (add full_slug)
- `app/Models/Race.php`
- `app/Models/Background.php`
- `app/Models/CharacterClass.php`
- `app/Models/Spell.php`
- `app/Models/Item.php`
- `app/Models/Language.php`
- `app/Models/Skill.php`
- `app/Models/ProficiencyType.php`
- `app/Models/Condition.php`
- `app/Models/OptionalFeature.php`
- `app/Models/Feat.php`
- `app/Models/Sense.php`

### Controllers
- `app/Http/Controllers/Api/CharacterController.php`
- `app/Http/Controllers/Api/CharacterClassController.php`
- `app/Http/Controllers/Api/CharacterSpellController.php`
- `app/Http/Controllers/Api/CharacterEquipmentController.php`
- `app/Http/Controllers/Api/CharacterLanguageController.php`
- `app/Http/Controllers/Api/CharacterProficiencyController.php`
- `app/Http/Controllers/Api/CharacterConditionController.php`
- `app/Http/Controllers/Api/CharacterChoiceController.php`
- `app/Http/Controllers/Api/CharacterFeatureController.php`

### Form Requests
- `app/Http/Requests/Character/CharacterStoreRequest.php`
- `app/Http/Requests/Character/CharacterUpdateRequest.php`
- `app/Http/Requests/Character/AddCharacterClassRequest.php`
- `app/Http/Requests/Character/ReplaceCharacterClassRequest.php`
- `app/Http/Requests/Character/SetSubclassRequest.php`
- `app/Http/Requests/Character/ResolveChoiceRequest.php`
- `app/Http/Requests/CharacterEquipment/StoreEquipmentRequest.php`
- All other character-related requests

### API Resources
- `app/Http/Resources/CharacterResource.php`
- `app/Http/Resources/CharacterClassPivotResource.php`
- `app/Http/Resources/CharacterSpellResource.php`
- `app/Http/Resources/CharacterEquipmentResource.php`
- `app/Http/Resources/CharacterLanguageResource.php`
- `app/Http/Resources/CharacterProficiencyResource.php`
- All other character-related resources

### Services
- `app/Services/CharacterChoiceService.php`
- `app/Services/CharacterProficiencyService.php`
- `app/Services/CharacterLanguageService.php`
- `app/Services/SpellManagerService.php`
- `app/Services/EquipmentManagerService.php`
- `app/Services/AddClassService.php`
- `app/Services/ReplaceClassService.php`

### Importers (add full_slug generation)
- All importers in `app/Services/Importers/`

### Tests
- All character-related tests need updates

## Open Questions

None - design approved.

## Appendix: Lookup Tables Without Changes

The following lookup tables use `code` and don't need `full_slug` because they're not source-dependent (same across all sourcebooks):

- `ability_scores` - STR, DEX, CON, INT, WIS, CHA (universal)
- `sizes` - T, S, M, L, H, G (universal)
- `damage_types` - A, B, C, etc. (universal)
- `spell_schools` - A, C, D, E, etc. (universal)
- `item_types` - A, M, R, etc. (universal)
- `item_properties` - A, F, H, etc. (universal)

These are referenced by their `code` directly, not source-prefixed.

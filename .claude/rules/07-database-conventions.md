# Database Conventions

## Table Naming Patterns

Three patterns are used:

| Pattern | Example Tables | Convention |
|---------|----------------|------------|
| **Standard Laravel** | `class_spells`, `item_property` | Alphabetical pivot naming |
| **Polymorphic** | `entity_sources`, `entity_spells` | `entity_*` prefix for MorphMany/MorphToMany |
| **HasMany Children** | `monster_actions`, `class_features` | `parent_children` naming |

## Models with Custom Table Names

Models whose `$table` diverges from Laravel's naming convention:

| Model | Table | Reason |
|-------|-------|--------|
| `CharacterClass` | `classes` | "Class" is a PHP reserved word |
| `CharacterClassPivot` | `character_classes` | Multiclass pivot — name collides with CharacterClass model's table |
| `CharacterEquipment` | `character_equipment` | Same-form plural (uncountable noun) |
| `CharacterTrait` | `entity_traits` | Polymorphic consistency |
| `ClassLevelProgression` | `class_level_progression` | Singular by design (one row per class/level) |
| `ClassOptionalFeature` | `class_optional_feature` | Singular — non-standard pivot |
| `Modifier` | `entity_modifiers` | Polymorphic consistency |
| `Proficiency` | `entity_proficiencies` | Polymorphic consistency |

## Polymorphic Tables and Their Entity Types

Source of truth: grep `morphMany|morphToMany` across `app/Models/` + `use HasX` for the `Concerns/` traits.

| Table | Used By |
|-------|---------|
| `entity_sources` | Background, CharacterClass, Feat, Item, Monster, OptionalFeature, Race, Spell |
| `entity_spells` | ClassFeature, Feat, Monster, Race |
| `entity_conditions` | Feat, Monster, Race |
| `entity_languages` | Background, CharacterClass, Feat, Race |
| `entity_senses` | Monster, Race |
| `entity_proficiencies` | Background, CharacterClass, ClassFeature, Feat, Item, Race |
| `entity_modifiers` | CharacterClass, ClassFeature, Feat, Item, Monster, Race |
| `entity_prerequisites` | Feat, Item, OptionalFeature |
| `entity_items` | Background, CharacterClass, Item |
| `entity_traits` | Background, CharacterClass, Monster, Race |
| `entity_data_tables` | CharacterTrait, ClassFeature, Item, OptionalFeature, Spell |
| `entity_counters` | CharacterClass, CharacterTrait, Feat |
| `entity_choices` | Background, CharacterClass, ClassFeature, Feat, Item, Race |
| `entity_saving_throws` | Importer-only — no Eloquent model; written by `ClassImporter` / `ItemImporter` and read via `SavingThrowResource` |

Notes:
- `entity_counters` replaces the legacy `class_counters` table (renamed Dec 2025). `App\Models\ClassCounter` is a deprecated alias kept for backwards compatibility.
- `entity_choices` is the general-purpose choice storage introduced when per-entity `*_choices` JSON columns were ripped out.

## Polymorphic Concerns Traits

Do **not** duplicate a polymorphic relationship — use the trait. Traits live in `app/Models/Concerns/`:

| Trait | Table |
|-------|-------|
| `HasSources` | `entity_sources` |
| `HasEntitySpells` | `entity_spells` |
| `HasConditions` | `entity_conditions` |
| `HasEntityLanguages` | `entity_languages` |
| `HasSenses` | `entity_senses` |
| `HasProficiencies` | `entity_proficiencies` |
| `HasModifiers` | `entity_modifiers` |
| `HasPrerequisites` | `entity_prerequisites` |
| `HasEntityTraits` | `entity_traits` (relationship target: `CharacterTrait`) |
| `HasDataTables` | `entity_data_tables` |
| `HasEntityChoices` | `entity_choices` |

Tables with no dedicated trait (`entity_items`, `entity_counters`, `entity_saving_throws`) use inline `morphMany` on the owning model.

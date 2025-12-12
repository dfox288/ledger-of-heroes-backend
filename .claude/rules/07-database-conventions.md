# Database Conventions

## Table Naming Patterns

Three patterns are used:

| Pattern | Example Tables | Convention |
|---------|----------------|------------|
| **Standard Laravel** | `class_spells`, `item_property` | Alphabetical pivot naming |
| **Polymorphic** | `entity_sources`, `entity_spells` | `entity_*` prefix for MorphMany/MorphToMany |
| **HasMany Children** | `monster_actions`, `class_features` | `parent_children` naming |

## Models with Custom Table Names

| Model | Table | Reason |
|-------|-------|--------|
| `CharacterClass` | `classes` | "Class" is PHP reserved word |
| `Proficiency` | `entity_proficiencies` | Polymorphic consistency |
| `Modifier` | `entity_modifiers` | Polymorphic consistency |
| `CharacterTrait` | `entity_traits` | Polymorphic consistency |

## Polymorphic Tables and Their Entity Types

| Table | Used By |
|-------|---------|
| `entity_sources` | Background, CharacterClass, Feat, Item, OptionalFeature, Race, Spell |
| `entity_spells` | Feat, Monster, Race |
| `entity_conditions` | Feat, Race |
| `entity_languages` | Background, Race |
| `entity_senses` | Monster, Race |
| `entity_proficiencies` | Background, CharacterClass, Feat, Item, Race |
| `entity_modifiers` | CharacterClass, Feat, Item, Monster, Race |
| `entity_prerequisites` | Feat, Item, OptionalFeature |
| `entity_items` | Background, CharacterClass |
| `entity_traits` | Background, CharacterClass, Race |
| `entity_data_tables` | CharacterTrait, ClassFeature, Item, Spell |

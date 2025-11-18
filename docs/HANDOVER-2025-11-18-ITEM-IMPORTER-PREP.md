# Handover: Item Importer Preparation

**Date:** 2025-11-18
**Branch:** `schema-redesign`
**Status:** Ready for Implementation Planning
**Next Steps:** Create detailed implementation plan, then execute

---

## Session Accomplishments

### Documentation & Testing Complete ✅
1. **Cleaned up 11 outdated documents**
2. **Created comprehensive XML reconstruction tests:**
   - 7 spell reconstruction tests (all passing)
   - 5 race reconstruction tests (all passing, 1 incomplete)
   - Found and fixed 4 real bugs via reconstruction testing
3. **Test suite growth:** 228 → 240 tests, 1309 → 1412 assertions
4. **Coverage verified:** Spells ~95%, Races ~90%
5. **Documentation updated:** CLAUDE.md, PROJECT-STATUS.md, session summary

---

## Item Importer: Current State Analysis

### What EXISTS ✅
- **2 migrations:**
  - `2025_11_17_204910_create_items_table.php` (21 fields)
  - `2025_11_17_214319_create_item_related_tables.php` (item_properties, item_property junction, item_abilities)
- **2 lookup models:** `ItemType.php`, `ItemProperty.php`
- **12 XML files:** `items-base-phb.xml`, `items-dmg.xml`, `items-magic-dmg+*.xml`, etc.

### What's MISSING ❌
1. **Item Model** - Core Eloquent model
2. **ItemAbility Model** - For magic item abilities
3. **Resources:** ItemResource, ItemPropertyResource, ItemTypeResource, ItemAbilityResource
4. **Factories:** ItemFactory, ItemAbilityFactory
5. **Seeders:** ItemTypeSeeder, ItemPropertySeeder (data currently in migration - needs extraction)
6. **Parser:** ItemXmlParser
7. **Importer:** ItemImporter
8. **Command:** ImportItems artisan command
9. **Tests:** ItemXmlReconstructionTest (following spell/race pattern)

### CRITICAL ISSUE: Old Architecture 🚨
**Items table has OLD single-source pattern:**
```php
// Lines 42-43 in items migration
$table->unsignedBigInteger('source_id');
$table->string('source_pages', 50);
```

**MUST MIGRATE TO:** Multi-source architecture using `entity_sources` polymorphic table (like Spells and Races)

**Fix Required:**
1. Remove `source_id` and `source_pages` columns from items table
2. Use `entity_sources` polymorphic table with `reference_type = 'App\Models\Item'`
3. Update migration before creating models

---

## XML Structure Analysis

### Sample Item XML:
```xml
<item>
  <name>Battleaxe</name>
  <detail>common</detail>               <!-- Rarity: common/uncommon/rare/very rare/legendary -->
  <type>M</type>                         <!-- Item type code: A=Ammo, M=Melee, R=Ranged, etc. -->
  <weight>4</weight>                     <!-- Weight in pounds -->
  <value>10.0</value>                    <!-- Cost in GOLD pieces (not copper!) -->
  <property>V,M</property>               <!-- Comma-separated property codes -->
  <dmg1>1d8</dmg1>                       <!-- Primary damage dice -->
  <dmg2>1d10</dmg2>                      <!-- Versatile damage (two-handed) -->
  <dmgType>S</dmgType>                   <!-- Damage type code: S=Slashing, P=Piercing, B=Bludgeoning -->
  <ac>16</ac>                            <!-- Armor class (for armor) -->
  <strength>13</strength>                <!-- STR requirement (for heavy armor) -->
  <stealth>YES</stealth>                 <!-- Stealth disadvantage (for armor) -->
  <range>25/100</range>                  <!-- Weapon range: normal/maximum -->
  <text>Description text here.

  Proficiency: martial, battleaxe

  Source: Player's Handbook (2014) p. 149</text>
</item>
```

### XML Element Mapping:
| XML Element | Database Field | Type | Notes |
|-------------|----------------|------|-------|
| `<name>` | `items.name` | string | Primary key candidate |
| `<detail>` | `items.rarity` | string | common/uncommon/rare/very rare/legendary/artifact |
| `<type>` | `items.item_type_id` | FK | Lookup: A, M, R, MA, HA, LA, G, $, P, RD, RG, WD, SC, etc. |
| `<weight>` | `items.weight` | decimal | In pounds |
| `<value>` | `items.cost_cp` | int | **Convert GP to CP (× 100)** |
| `<property>` | `item_property` junction | M2M | Parse comma-separated codes |
| `<dmg1>` | `items.damage_dice` | string | "1d8", "2d6", etc. |
| `<dmg2>` | `items.versatile_damage` | string | Two-handed damage |
| `<dmgType>` | `items.damage_type_id` | FK | Lookup: S, P, B, etc. |
| `<ac>` | `items.armor_class` | int | Armor only |
| `<strength>` | `items.strength_requirement` | int | Heavy armor only |
| `<stealth>` | `items.stealth_disadvantage` | bool | "YES" → true |
| `<range>` | `items.weapon_range` | string | Store as-is: "25/100" or "Melee" |
| `<text>` | `items.description` | text | Extract source, proficiencies |

### Parsing Challenges:

1. **Cost Conversion:**
   - XML: `<value>10.0</value>` (GOLD pieces)
   - DB: `cost_cp` (COPPER pieces)
   - **Conversion:** Multiply by 100 (1 GP = 100 CP)

2. **Property Parsing:**
   - XML: `<property>V,M,A</property>`
   - DB: Many-to-many via `item_property` junction
   - **Lookup:** V=Versatile, M=Martial, A=Ammunition, etc.

3. **Range Parsing:**
   - Formats: "25/100", "Melee", "Ranged", "5/20 ft", "80/320"
   - **Strategy:** Store as-is (string), don't over-parse

4. **Proficiency Extraction (NEW REQUIREMENT):**
   - XML: Embedded in `<text>`: "Proficiency: martial, battleaxe"
   - DB: `proficiencies` polymorphic table
   - **Regex:** `/Proficiency:\s*([^\\n]+)/i`
   - **Parse:** Comma-separated list → create multiple proficiency records
   - **Type:** Infer from context (weapon, armor, tool)

5. **Damage Type:**
   - XML: Single character code: S, P, B, F, C, L, A, T, Fc, N, Ps, R, Th
   - DB: FK to `damage_types.id`
   - **Lookup:** Case-insensitive match on `damage_types.code`

6. **Source Extraction:**
   - XML: "Source: Player's Handbook (2014) p. 149"
   - DB: `entity_sources` polymorphic table
   - **Reuse:** Same logic as SpellXmlParser

---

## Implementation Approach (Recommended)

### Phase 1: Foundation (Schema & Seeders)
1. **Fix items migration** - Remove single-source columns
2. **Create ItemTypeSeeder** - Extract from migration, seed 15-20 item types
3. **Create ItemPropertySeeder** - Extract from migration, seed 11 properties
4. **Run migration fresh** - Verify schema

### Phase 2: Models & Factories
5. **Create Item model** - Relationships: itemType, damageType, properties (M2M), abilities, sources (polymorphic), proficiencies (polymorphic)
6. **Create ItemAbility model** - For magic items
7. **Create ItemFactory** - States: weapon(), armor(), magic(), withProperties()
8. **Create ItemAbilityFactory** - For testing magic items

### Phase 3: API Layer
9. **Create ItemResource** - Match all Item fillable fields
10. **Create ItemPropertyResource** - Lookup data
11. **Create ItemTypeResource** - Lookup data
12. **Create ItemAbilityResource** - For magic item abilities

### Phase 4: Parser & Importer
13. **Create ItemXmlParser** - Parse XML to array structure
14. **Create ItemImporter** - Import array to database
15. **Create ImportItems command** - Artisan command

### Phase 5: Testing
16. **Create ItemXmlReconstructionTest** - 7-10 test cases covering weapons, armor, magic items
17. **Run reconstruction tests** - Find and fix bugs
18. **Verify coverage** - Aim for ~90% attribute reconstruction

---

## Key Patterns to Follow

### From Spell/Race Importers:

1. **Parser Pattern:**
```php
class ItemXmlParser
{
    public function parse(string $xmlContent): array
    {
        $xml = new SimpleXMLElement($xmlContent);
        $items = [];
        foreach ($xml->item as $itemElement) {
            $items[] = $this->parseItem($itemElement);
        }
        return $items;
    }

    private function parseItem(SimpleXMLElement $element): array
    {
        // Extract source from text
        // Parse properties (comma-separated)
        // Parse proficiencies from text
        // Convert cost GP → CP
        // Return normalized array
    }
}
```

2. **Importer Pattern:**
```php
class ItemImporter
{
    public function import(array $itemData): Item
    {
        // Lookup item_type_id by code
        // Lookup damage_type_id by code
        // Create/update item
        // Import sources (polymorphic)
        // Import properties (M2M)
        // Import proficiencies (polymorphic)
        // Import abilities (magic items)
    }
}
```

3. **Multi-Source Pattern:**
```php
// Clear old sources
$item->sources()->delete();

// Create new sources
foreach ($itemData['sources'] as $sourceData) {
    EntitySource::create([
        'reference_type' => Item::class,
        'reference_id' => $item->id,
        'source_id' => $source->id,
        'pages' => $sourceData['pages'],
    ]);
}
```

4. **Proficiency Pattern (NEW):**
```php
// Parse from text: "Proficiency: martial, battleaxe"
if (preg_match('/Proficiency:\s*([^\n]+)/i', $text, $matches)) {
    $profList = array_map('trim', explode(',', $matches[1]));
    foreach ($profList as $profName) {
        Proficiency::create([
            'reference_type' => Item::class,
            'reference_id' => $item->id,
            'proficiency_type' => 'weapon', // or 'armor' based on item type
            'proficiency_name' => $profName,
        ]);
    }
}
```

---

## Item Types Reference

From XML samples and design doc:

| Code | Type | Examples |
|------|------|----------|
| A | Ammunition | Arrows, bolts, bullets |
| M | Melee Weapon | Battleaxe, longsword, dagger |
| R | Ranged Weapon | Longbow, crossbow, blowgun |
| LA | Light Armor | Padded, leather, studded leather |
| MA | Medium Armor | Hide, chain shirt, scale mail, breastplate |
| HA | Heavy Armor | Ring mail, chain mail, splint, plate |
| S | Shield | Shield |
| G | Adventuring Gear | Rope, torch, backpack |
| $ | Trade Goods | Gems, art objects |
| P | Potion | Healing potions, oils |
| RD | Rod | Rods (magic items) |
| RG | Ring | Rings (magic items) |
| WD | Wand | Wands (magic items) |
| SC | Scroll | Spell scrolls |

**Seeder needs:** Full list with descriptions

---

## Item Properties Reference

| Code | Property | Description |
|------|----------|-------------|
| A | Ammunition | Requires ammunition to fire |
| F | Finesse | Use DEX instead of STR |
| H | Heavy | Small creatures have disadvantage |
| L | Light | Can dual-wield |
| LD | Loading | Only one attack per action |
| R | Reach | 5 ft extra reach |
| T | Thrown | Can throw as ranged attack |
| 2H | Two-Handed | Requires two hands |
| V | Versatile | Can use one or two hands |
| M | Martial | Requires martial proficiency |
| S | Special | Special rules in description |

**Seeder needs:** Full list with descriptions

---

## Expected Bugs (from Reconstruction Tests)

Based on Spell/Race reconstruction findings, expect:

1. **Cost conversion errors** - GP to CP math
2. **Property code mismatches** - Case sensitivity or unknown codes
3. **Damage type lookups** - Missing damage types in seed data
4. **Range format variations** - "5/20 ft" vs "5/20" vs "Melee"
5. **Proficiency extraction** - Text variations: "Proficiency with:", "Proficiencies:", etc.
6. **Magic item abilities** - Complex parsing, may need enhancement

---

## Success Criteria

1. ✅ Items table uses multi-source architecture (no source_id column)
2. ✅ All 15+ item types seeded in database
3. ✅ All 11 item properties seeded in database
4. ✅ Item, ItemAbility models with full relationships
5. ✅ 4 API Resources created and field-complete
6. ✅ 2 Factories created with useful states
7. ✅ ItemXmlParser parses all XML elements correctly
8. ✅ ItemImporter handles properties, proficiencies, sources polymorphically
9. ✅ ImportItems command works with all 12 XML files
10. ✅ ItemXmlReconstructionTest covers weapons, armor, magic items
11. ✅ ~90% attribute reconstruction coverage

---

## Files to Create

### Models (2 files):
- `app/Models/Item.php`
- `app/Models/ItemAbility.php`

### Resources (4 files):
- `app/Http/Resources/ItemResource.php`
- `app/Http/Resources/ItemPropertyResource.php`
- `app/Http/Resources/ItemTypeResource.php`
- `app/Http/Resources/ItemAbilityResource.php`

### Factories (2 files):
- `database/factories/ItemFactory.php`
- `database/factories/ItemAbilityFactory.php`

### Seeders (2 files):
- `database/seeders/ItemTypeSeeder.php`
- `database/seeders/ItemPropertySeeder.php`

### Parser & Importer (2 files):
- `app/Services/Parsers/ItemXmlParser.php`
- `app/Services/Importers/ItemImporter.php`

### Command (1 file):
- `app/Console/Commands/ImportItems.php`

### Tests (2 files):
- `tests/Feature/Importers/ItemXmlReconstructionTest.php`
- `tests/Unit/Parsers/ItemXmlParserTest.php`

### Migration Update (1 file):
- Modify `database/migrations/2025_11_17_204910_create_items_table.php`

**Total:** 16 new files + 1 migration modification

---

## Next Steps for Next Agent

1. **Read this handover completely**
2. **Use `superpowers:writing-plans` skill** to create detailed implementation plan
3. **Create plan document:** `docs/plans/2025-11-18-item-importer-implementation.md`
4. **Review plan with user**
5. **Use `superpowers:executing-plans` skill** to implement in batches
6. **Follow TDD:** Write reconstruction tests, watch fail, implement, watch pass
7. **Document findings** in CLAUDE.md after completion

---

## Current Branch Status

**Branch:** `schema-redesign`
**Tests:** 240 passing (1 incomplete), 1412 assertions
**Clean working tree:** Ready for new work

```bash
# Verify current state
git status
git log --oneline -5

# Start implementation
# (Use superpowers:using-git-worktrees if isolating work)
```

---

## Reference Documents

- `docs/PROJECT-STATUS.md` - Project overview
- `docs/plans/2025-11-17-dnd-compendium-database-design.md` - Database architecture
- `docs/plans/2025-11-18-xml-reconstruction-test-plan.md` - Testing strategy
- `docs/SESSION-2025-11-18-XML-RECONSTRUCTION-TESTS.md` - Recent session summary
- `CLAUDE.md` - Comprehensive project guide

---

**Handover Status:** ✅ Complete
**Ready for:** Implementation planning and execution
**Estimated Time:** 6-8 hours for complete Item importer with tests

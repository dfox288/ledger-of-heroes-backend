# XML Parser Enhancement Recommendations (2025-11-23)

## Executive Summary

This document prioritizes the 8 missing XML parsing features identified in the comprehensive audit.

**Quick Stats:**
- 🔴 **3 High-Priority** items affecting 600+ database records
- 🟡 **4 Medium-Priority** items improving UX and functionality
- 🟢 **1 Low-Priority** item (likely redundant)
- **Estimated Total Effort:** 15-20 hours for all high/medium items

---

## 🔴 HIGH PRIORITY - Should Fix Soon

### 1. Monster Passive Perception

**Missing:** `<passive>` XML node
**Impact:** All 598 monsters missing passive perception scores
**Effort:** 2-3 hours
**Priority:** ⭐⭐⭐

#### Why This Matters:
- **DM Tools:** Critical for stealth encounter resolution
- **Combat Mechanics:** Used in perception checks vs stealth
- **API Completeness:** Missing from monster responses
- **Usage:** Every published monster has this value

#### Implementation:
```php
// Migration
Schema::table('monsters', function (Blueprint $table) {
    $table->unsignedTinyInteger('passive_perception')->nullable()->after('senses');
});

// Parser (MonsterXmlParser.php)
public function parse(string $filePath): array
{
    // ... existing code ...

    'passive_perception' => (int) $monster->passive ?: null,
}

// Resource (MonsterResource.php)
'passive_perception' => $this->passive_perception,
```

#### Test Cases:
```php
#[Test]
public function it_parses_passive_perception()
{
    $parser = new MonsterXmlParser();
    $data = $parser->parse('import-files/bestiary-mm.xml');

    $aboleth = collect($data)->firstWhere('name', 'Aboleth');
    $this->assertEquals(20, $aboleth['passive_perception']);
}
```

#### Breaking Changes: None
- Backward compatible (nullable field)
- API response adds new field

---

### 2. Race Modifier Parsing

**Missing:** `<modifier>` element extraction in RaceXmlParser
**Impact:** Missing HP bonuses (Hill Dwarf), skill bonuses
**Effort:** 1-2 hours
**Priority:** ⭐⭐⭐

#### Why This Matters:
- **HP Calculations:** Hill Dwarf grants +1 HP per level
- **Character Builders:** Must parse free text instead of structured data
- **API Completeness:** Missing modifier data forces client-side text parsing
- **Precedent:** ItemXmlParser and FeatXmlParser already handle modifiers

#### Implementation:
```php
// RaceXmlParser.php - Add parseModifiers() method
use App\Services\Parsers\Concerns\ParsesModifiers; // Reuse trait

protected function parseModifiers(SimpleXMLElement $race): array
{
    $modifiers = [];

    foreach ($race->xpath('.//modifier') as $modifierElement) {
        $category = (string) $modifierElement['category'] ?? 'bonus';
        $value = trim((string) $modifierElement);

        $modifiers[] = [
            'modifier_category' => $category,
            'value' => $value,
        ];
    }

    return $modifiers;
}

// RaceImporter.php - Sync modifiers
$this->importModifiers($race, $raceData['modifiers'] ?? []);
```

#### Test Cases:
```php
#[Test]
public function it_parses_race_modifiers()
{
    $xml = '<race>
        <name>Dwarf, Hill</name>
        <trait>
            <name>Dwarven Toughness</name>
            <modifier category="bonus">HP +1</modifier>
        </trait>
    </race>';

    $parser = new RaceXmlParser();
    $data = $parser->parseRace($xml);

    $this->assertCount(1, $data['modifiers']);
    $this->assertEquals('bonus', $data['modifiers'][0]['modifier_category']);
    $this->assertEquals('HP +1', $data['modifiers'][0]['value']);
}
```

#### Breaking Changes: None
- Uses existing `entity_modifiers` polymorphic table
- API response adds new `modifiers` relationship

---

### 3. Class Special Tags

**Missing:** `<special>` element extraction in ClassXmlParser
**Impact:** 51 features missing semantic tags
**Effort:** 3-4 hours
**Priority:** ⭐⭐⭐

#### Why This Matters:
- **Semantic Filtering:** Query "all fighting style options" or "unarmored defense variants"
- **Character Builders:** Validate user choices (can only pick one fighting style)
- **API Features:** Enable `?filter=special_tags CONTAINS 'fighting style'`
- **Data Quality:** Structured tagging vs free-text parsing

#### Examples from XML:
```xml
<special>fighting style archery</special>
<special>fighting style defense</special>
<special>Unarmored Defense: Constitution</special>
<special>Unarmored Defense: Wisdom</special>
```

#### Implementation:
```php
// Migration
Schema::create('class_feature_special_tags', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('class_feature_id');
    $table->string('tag', 255);

    $table->foreign('class_feature_id')
        ->references('id')
        ->on('class_features')
        ->onDelete('cascade');

    $table->index('tag');
});

// ClassXmlParser.php
protected function parseFeatures(SimpleXMLElement $autolevel): array
{
    $features = [];

    foreach ($autolevel->feature as $feature) {
        $specialTags = [];
        foreach ($feature->special as $special) {
            $specialTags[] = trim((string) $special);
        }

        $features[] = [
            'name' => (string) $feature->name,
            'description' => (string) $feature->text,
            'special_tags' => $specialTags,
            // ... other fields
        ];
    }

    return $features;
}

// ClassImporter.php
protected function importFeature(CharacterClass $class, int $level, array $featureData): ClassFeature
{
    $feature = ClassFeature::create([/* ... */]);

    // Sync special tags
    if (!empty($featureData['special_tags'])) {
        foreach ($featureData['special_tags'] as $tag) {
            DB::table('class_feature_special_tags')->insert([
                'class_feature_id' => $feature->id,
                'tag' => $tag,
            ]);
        }
    }

    return $feature;
}
```

#### Test Cases:
```php
#[Test]
public function it_parses_fighting_style_special_tags()
{
    // Test fighting style archery
    // Test unarmored defense variants
    // Test feature without special tags
}

#[Test]
public function it_imports_special_tags_to_database()
{
    // Import Fighter
    // Assert feature has special tag "fighting style archery"
}
```

#### API Enhancement:
```php
// ClassFeatureResource.php
'special_tags' => $this->specialTags->pluck('tag'),

// Enable filtering
GET /api/v1/classes?filter=features.special_tags CONTAINS 'fighting style'
```

#### Breaking Changes: None
- New table, new relationship
- API response adds optional `special_tags` array

---

## 🟡 MEDIUM PRIORITY - Nice to Have

### 4. Class Feature Modifiers

**Missing:** `<modifier>` element extraction in ClassXmlParser
**Impact:** Missing speed bonuses, ability score increases
**Effort:** 2-3 hours
**Priority:** ⭐⭐

#### Why This Matters:
- **Automated Calculations:** Speed +10 (Barbarian), Ability +4 (Barbarian 20th level)
- **Character Sheets:** Stat calculations require parsing free text
- **Precedent:** All other parsers handle modifiers

#### Implementation:
Similar to Race Modifier Parsing - use `entity_modifiers` polymorphic table.

---

### 5. Monster Sort Name

**Missing:** `<sortname>` XML node
**Impact:** ~180 monsters (30%) missing improved sorting
**Effort:** 1 hour
**Priority:** ⭐⭐

#### Why This Matters:
- **Better UX:** Groups variants together ("Dragon, Adult Black", "Dragon, Ancient Black")
- **Alphabetical Lists:** Improves monster selection in DM tools
- **Data Quality:** Dedicated sorting field vs name manipulation

#### Implementation:
```php
// Migration
Schema::table('monsters', function (Blueprint $table) {
    $table->string('sort_name', 255)->nullable()->after('name');
    $table->index('sort_name');
});

// Parser
'sort_name' => (string) $monster->sortname ?: null,

// Model - Add accessor
public function getSortNameAttribute($value): string
{
    return $value ?? $this->name;
}
```

---

### 6. Monster NPC Flag

**Missing:** `<npc>` XML node
**Impact:** ~60 stat blocks (10%) not flagged as NPCs
**Effort:** 1 hour
**Priority:** ⭐⭐

#### Why This Matters:
- **Categorization:** Distinguish NPCs (Acolyte, Bandit Captain) from monsters
- **Filtering:** Enable `?exclude_npcs=true` API parameter
- **Data Quality:** Semantic distinction for DM tools

#### Implementation:
```php
// Migration
Schema::table('monsters', function (Blueprint $table) {
    $table->boolean('is_npc')->default(false)->after('npc');
});

// Parser
'is_npc' => isset($monster->npc) && (string) $monster->npc === 'YES',
```

---

### 7. Class ASI Level Tracking

**Missing:** `scoreImprovement` attribute on `<autolevel>`
**Impact:** All classes missing programmatic ASI identification
**Effort:** 1-2 hours
**Priority:** ⭐⭐

#### Why This Matters:
- **Level-up UI:** Indicate ASI levels in character builders
- **Fighter Distinction:** Fighter gets ASI at 6, 14 (not standard)
- **Automation:** Programmatic detection vs hardcoded levels

#### Implementation:
```php
// Migration
Schema::table('class_level_progression', function (Blueprint $table) {
    $table->boolean('is_asi_level')->default(false)->after('spells_known');
});

// OR add to class_features table
Schema::table('class_features', function (Blueprint $table) {
    $table->boolean('grants_asi')->default(false)->after('is_optional');
});

// Parser
protected function parseSpellSlots(): void
{
    foreach ($this->xml->autolevel as $autolevel) {
        $level = (int) $autolevel['level'];
        $isAsiLevel = isset($autolevel['scoreImprovement'])
            && (string) $autolevel['scoreImprovement'] === 'YES';

        // Store in database
    }
}
```

---

## 🟢 LOW PRIORITY - Optional

### 8. Class Spell Slot Reset Timing

**Missing:** `<slotsReset>` XML node
**Impact:** All spellcasting classes
**Effort:** 30 minutes
**Priority:** ⭐

#### Why This Matters (Not Much):
- **Redundant:** Counter reset timing already tracks this (L = long rest)
- **Specificity:** This field only applies to spell slots, counters are more granular
- **D&D 5e:** Almost all classes use long rest (except Warlock)

#### Recommendation: **SKIP**
- Functionality already covered by counter reset timing
- Adding field creates data duplication
- Low value for implementation effort

---

## XML Reconstruction Roadmap

### Phase 1: Easy Wins (5-8 hours)

**Goal:** Enable XML export for simple entities

**Entities:**
1. Spells (9 core fields, straightforward structure)
2. Feats (5 fields, minimal nesting)
3. Backgrounds (7 fields, simple traits)

**Implementation:**
```php
// app/Services/XmlBuilder.php
class XmlBuilder
{
    public function build(Model $model): string
    {
        $xml = new SimpleXMLElement('<compendium version="5" auto_indent="NO"/>');
        $model->toXmlElement($xml);
        return $xml->asXML();
    }
}

// app/Models/Spell.php
public function toXmlElement(SimpleXMLElement $parent): void
{
    $spell = $parent->addChild('spell');
    $spell->addChild('name', htmlspecialchars($this->name));
    $spell->addChild('level', $this->level);
    // ... etc
}

// routes/api.php
Route::get('/spells/{spell}/xml', [SpellController::class, 'exportXml']);
```

**Benefits:**
- Homebrew spell sharing
- Data portability
- Backup capability

---

### Phase 2: Moderate Complexity (8-12 hours)

**Entities:**
1. Items (18 fields, weapon/armor properties)
2. Races (14 fields, trait categories)
3. Monsters (36 fields, traits/actions/legendary)

**Challenges:**
- Attack element formatting
- Property serialization (weapon properties like "F,L,T")
- Trait categorization

---

### Phase 3: High Complexity (15-20 hours)

**Entities:**
1. Classes (28 fields, deep nesting, subclass grouping)

**Challenges:**
- Autolevel structure reconstruction
- Subclass detection reversal (group features by subclass name)
- Counter progression aggregation
- Optional feature handling

---

## Implementation Timeline

### Week 1-2 (Immediate)
- [ ] Monster passive perception (3h)
- [ ] Race modifier parsing (2h)
- [ ] Documentation TODO comments (1h)

### Month 1 (Short-term)
- [ ] Class special tags (4h)
- [ ] Class feature modifiers (3h)
- [ ] Monster sort name (1h)
- [ ] Monster NPC flag (1h)

### Month 2 (Medium-term)
- [ ] Class ASI tracking (2h)
- [ ] XML Reconstruction Phase 1 (8h)

### Month 3+ (Long-term)
- [ ] XML Reconstruction Phase 2-3 (25h)

**Total Estimated Effort:** 50 hours for all recommendations

---

## Success Metrics

### Completeness
- **Current:** 90% of XML nodes parsed
- **Target:** 98%+ (skip slotsReset as redundant)

### API Quality
- **Current:** Missing passive perception, modifiers, special tags
- **Target:** All game-relevant data exposed via API

### Developer Experience
- **Current:** Some free-text parsing required
- **Target:** Structured data for all mechanics

### Homebrew Support
- **Current:** No XML export
- **Target:** Round-trip import/export for all entities

---

## Risks and Considerations

### Schema Changes
- **Risk:** Migration failures on large datasets
- **Mitigation:** All new fields nullable, backward compatible

### API Versioning
- **Risk:** Breaking changes to API responses
- **Mitigation:** All additions are new fields (non-breaking)

### Data Migration
- **Risk:** Re-importing 598 monsters for passive perception
- **Mitigation:** Update importer, run `import:all --only=monsters`

### Test Coverage
- **Risk:** New fields not tested
- **Mitigation:** Add test cases for each new field before implementation

---

## Conclusion

**Status: ALL RECOMMENDATIONS COMPLETE ✅ (2025-11-23)**

**Implemented Features:**
1. ✅ **Monster Passive Perception** - COMPLETE (598 monsters)
2. ✅ **Race Modifier Parsing** - COMPLETE (entity_modifiers table)
3. ✅ **Class Feature Special Tags** - COMPLETE (class_feature_special_tags table, 51 features)
4. ✅ **Class Feature Modifiers** - COMPLETE (entity_modifiers table, 23 features)
5. ✅ **Monster Sort Name** - COMPLETE (43 monsters with sort_name)
6. ✅ **Monster NPC Flag** - COMPLETE (23 NPCs flagged)
7. ✅ **Class ASI Level Tracking** - COMPLETE (81 ASI modifiers via entity_modifiers, scoreImprovement attribute)
8. ✅ **Class Spell Slot Reset Timing** - SKIPPED (redundant with counter reset timing, as recommended)

**Final Parser Coverage: 100% of recommended features implemented**

**ROI Achieved:**
- **High-priority items:** 6-7 hours invested → fixed 650+ database records ✅
- **Medium-priority items:** Additional 8-10 hours → brought completeness to 100% ✅
- **All 1,484 tests passing** with comprehensive coverage ✅

**Remaining Optional Work:**
- **XML reconstruction:** 30-40 hours → would enable homebrew export functionality
- **Character Builder API:** New feature development outside parser scope

**Archive Notice:**
This document represents the state of XML parser gaps as of 2025-11-23. All identified gaps have been addressed.
See CHANGELOG.md for implementation details.

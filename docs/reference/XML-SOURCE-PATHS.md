# XML Source Paths Reference

This document maps the 9 D&D 5e sources we import to their locations in the upstream fightclub_forked repository.

## Repository Location

**Upstream Repository:** `../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/`

## Source Mappings

| Source | Abbreviation | Category | Relative Path |
|--------|--------------|----------|---------------|
| Player's Handbook | PHB | Core | `01_Core/01_Players_Handbook/` |
| Dungeon Master's Guide | DMG | Core | `01_Core/02_Dungeon_Masters_Guide/` |
| Monster Manual | MM | Core | `01_Core/03_Monster_Manual/` |
| Xanathar's Guide to Everything | XGE | Supplement | `02_Supplements/Xanathars_Guide_to_Everything/` |
| Tasha's Cauldron of Everything | TCE | Supplement | `02_Supplements/Tashas_Cauldron_of_Everything/` |
| Volo's Guide to Monsters | VGM | Supplement | `02_Supplements/Volos_Guide_to_Monsters/` |
| Sword Coast Adventurer's Guide | SCAG | Campaign Setting | `03_Campaign_Settings/Sword_Coast_Adventurers_Guide/` |
| Eberron: Rising from the Last War | ERLW | Campaign Setting | `03_Campaign_Settings/Eberron_Rising_From_the_Last_War/` |
| The Wild Beyond the Witchlight | TWBTW | Adventure | `05_Adventures/The_Wild_Beyond_the_Witchlight/` |

## Full Paths

Base path from importer project: `../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/`

```
PHB:   ../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/01_Core/01_Players_Handbook/
DMG:   ../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/01_Core/02_Dungeon_Masters_Guide/
MM:    ../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/01_Core/03_Monster_Manual/
XGE:   ../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/02_Supplements/Xanathars_Guide_to_Everything/
TCE:   ../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/02_Supplements/Tashas_Cauldron_of_Everything/
VGM:   ../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/02_Supplements/Volos_Guide_to_Monsters/
SCAG:  ../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/03_Campaign_Settings/Sword_Coast_Adventurers_Guide/
ERLW:  ../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/03_Campaign_Settings/Eberron_Rising_From_the_Last_War/
TWBTW: ../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast/05_Adventures/The_Wild_Beyond_the_Witchlight/
```

## Files by Source

### PHB (Player's Handbook)
- `source-phb.xml`
- `backgrounds-phb.xml`
- `bestiary-phb.xml`
- `class-{barbarian,bard,cleric,druid,fighter,monk,paladin,ranger,rogue,sorcerer,warlock,wizard}-phb.xml`
- `feats-phb.xml`
- `items-base-phb.xml`, `items-phb.xml`, `items-magic-phb+phb.xml`
- `optionalfeatures-phb.xml`
- `races-phb.xml`
- `spells-phb.xml`

### DMG (Dungeon Master's Guide)
- `source-dmg.xml`
- `bestiary-dmg.xml`
- `class-cleric-dmg.xml`, `class-paladin-dmg.xml`
- `items-base-dmg.xml`, `items-dmg.xml`, `items-magic-dmg+dmg.xml`, `items-magic-dmg+phb.xml`, `items-magic-phb+dmg.xml`
- `races-dmg.xml`
- `spells-phb+dmg.xml`

### MM (Monster Manual)
- `source-mm.xml`
- `bestiary-mm.xml`
- `items-mm.xml`, `items-magic-dmg+mm.xml`, `items-magic-phb+mm.xml`

### XGE (Xanathar's Guide to Everything)
- `source-xge.xml`
- `bestiary-xge.xml`
- `class-{barbarian,bard,cleric,druid,fighter,monk,paladin,ranger,rogue,sorcerer,warlock,wizard}-xge.xml`
- `feats-xge.xml`
- `items-xge.xml`, `items-magic-dmg+xge.xml`, `items-magic-erlw+xge.xml`, `items-magic-phb+xge.xml`, `items-magic-scag+xge.xml`
- `optionalfeatures-xge.xml`
- `spells-xge.xml`, `spells-ai+xge.xml`, `spells-phb+xge.xml`

### TCE (Tasha's Cauldron of Everything)
- `source-tce.xml`
- `bestiary-tce.xml`
- `class-artificer-tce.xml`
- `class-{barbarian,bard,cleric,druid,fighter,monk,paladin,ranger,rogue,sorcerer,warlock,wizard}-tce.xml`
- `class-sidekick-{expert,spellcaster,warrior}.xml`
- `feats-tce.xml`
- `items-tce.xml`
- `optionalfeatures-tce.xml`
- `races-tce.xml`
- `spells-tce.xml`, `spells-phb+tce.xml`

### VGM (Volo's Guide to Monsters)
- `source-vgm.xml`
- `bestiary-vgm.xml`
- `items-vgm.xml`, `items-magic-erlw+vgm.xml`, `items-magic-phb+vgm.xml`
- `races-vgm.xml`

### SCAG (Sword Coast Adventurer's Guide)
- `source-scag.xml`
- `backgrounds-scag.xml`
- `bestiary-scag.xml`
- `class-{barbarian,cleric,fighter,monk,paladin,warlock}-scag.xml`
- `class-{rogue,sorcerer,wizard}-scag#Deprecated.xml` (deprecated versions)
- `items-base-scag.xml`, `items-scag.xml`, `items-magic-scag+bomt.xml`, `items-magic-scag+dmg.xml`
- `races-scag.xml`
- `spells-phb+scag.xml`, `spells-scag#Deprecated.xml`

### ERLW (Eberron: Rising from the Last War)
- `source-erlw.xml`
- `backgrounds-erlw.xml`
- `bestiary-erlw.xml`
- `feats-erlw.xml`
- `items-base-erlw.xml`, `items-erlw.xml`
- `items-magic-erlw+{ai,dmg,mm,phb}.xml`, `items-magic-phb+erlw.xml`
- `races-erlw.xml`
- `spells-phb+erlw#Deprecated.xml`, `spells-xge+erlw.xml`

### TWBTW (The Wild Beyond the Witchlight)
- `source-twbtw.xml`
- `backgrounds-twbtw.xml`
- `bestiary-twbtw.xml`
- `items-twbtw.xml`
- `races-twbtw.xml`

## Implementation Options

### Option A: Symlink (Simplest - No Code Changes)

Create a symlink so the existing import-files references just work:

```bash
# Remove the existing import-files directory (after backup if needed)
rm -rf import-files

# Create symlink pointing to upstream
# Note: This won't work because files are in subdirectories
```

**Problem:** The upstream repo organizes files into subdirectories by source book, but our importers expect all files in a flat directory. This option requires flattening.

### Option B: Config Variable with Multi-Directory Glob (Recommended)

Add configuration that knows about the directory structure and globs across all source directories.

**Step 1:** Add to `.env`:
```
XML_SOURCE_PATH=../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast
```

**Step 2:** Create `config/import.php`:
```php
return [
    'xml_source_path' => env('XML_SOURCE_PATH', base_path('import-files')),

    'sources' => [
        'phb'   => '01_Core/01_Players_Handbook',
        'dmg'   => '01_Core/02_Dungeon_Masters_Guide',
        'mm'    => '01_Core/03_Monster_Manual',
        'xge'   => '02_Supplements/Xanathars_Guide_to_Everything',
        'tce'   => '02_Supplements/Tashas_Cauldron_of_Everything',
        'vgm'   => '02_Supplements/Volos_Guide_to_Monsters',
        'scag'  => '03_Campaign_Settings/Sword_Coast_Adventurers_Guide',
        'erlw'  => '03_Campaign_Settings/Eberron_Rising_From_the_Last_War',
        'twbtw' => '05_Adventures/The_Wild_Beyond_the_Witchlight',
    ],
];
```

**Step 3:** Update `ImportAllDataCommand.php` to glob across multiple directories.

### Option C: Copy Script (Hybrid Approach)

Keep the flat `import-files/` structure but use a script to sync from upstream:

```bash
#!/bin/bash
# scripts/sync-xml-sources.sh

SOURCE_BASE="../fightclub_forked/Sources/PHB2014/WizardsOfTheCoast"
TARGET="import-files"

# Clear existing (optional - or use rsync)
# rm -f $TARGET/*.xml

# Copy from each source directory
cp $SOURCE_BASE/01_Core/01_Players_Handbook/*.xml $TARGET/
cp $SOURCE_BASE/01_Core/02_Dungeon_Masters_Guide/*.xml $TARGET/
cp $SOURCE_BASE/01_Core/03_Monster_Manual/*.xml $TARGET/
cp $SOURCE_BASE/02_Supplements/Xanathars_Guide_to_Everything/*.xml $TARGET/
cp $SOURCE_BASE/02_Supplements/Tashas_Cauldron_of_Everything/*.xml $TARGET/
cp $SOURCE_BASE/02_Supplements/Volos_Guide_to_Monsters/*.xml $TARGET/
cp $SOURCE_BASE/03_Campaign_Settings/Sword_Coast_Adventurers_Guide/*.xml $TARGET/
cp $SOURCE_BASE/03_Campaign_Settings/Eberron_Rising_From_the_Last_War/*.xml $TARGET/
cp $SOURCE_BASE/05_Adventures/The_Wild_Beyond_the_Witchlight/*.xml $TARGET/

echo "Synced XML files from upstream repository"
```

**Pros:** No code changes, simple to understand, easy to control which sources are imported.
**Cons:** Duplicate files, need to remember to run sync.

## Required Code Changes (for Option B)

### Files to Modify

1. **`ImportAllDataCommand.php`** - Main orchestrator
   - Change `base_path('import-files')` to use config
   - Update `importEntityType()` to glob across all source directories
   - Update `importClassesBatch()` similarly
   - Update `importAdditiveSpellFiles()` similarly

2. **`ImportClassesBatch.php`** - Class batch importer
   - Update pattern handling to work with multi-directory structure

3. **Individual Import Commands** (if they have hardcoded paths)
   - `ImportSources.php`
   - `ImportClasses.php`
   - `ImportSpells.php`
   - etc.

### Helper Method Example

```php
/**
 * Get all XML files matching a pattern across all configured source directories.
 */
private function getSourceFiles(string $pattern): array
{
    $basePath = config('import.xml_source_path');
    $sources = config('import.sources');

    // If using flat directory (legacy), just glob there
    if (!$sources || !is_dir($basePath)) {
        return File::glob(base_path("import-files/{$pattern}"));
    }

    // Glob across all source directories
    $files = [];
    foreach ($sources as $abbrev => $subdir) {
        $dirPath = "{$basePath}/{$subdir}";
        if (is_dir($dirPath)) {
            $found = File::glob("{$dirPath}/{$pattern}");
            $files = array_merge($files, $found);
        }
    }

    return $files;
}
```

## Other Available Sources (Not Currently Imported)

The upstream repository contains many additional sources:

### Supplements
- Bigby Presents Glory of the Giants
- Book of Many Things
- Fizban's Treasury of Dragons
- Mordenkainen's Tome of Foes
- Mordenkainen's Monsters of the Multiverse
- One Grung Above
- The Tortle Package

### Campaign Settings
- Acquisitions Incorporated
- Critical Role: Call of the Netherdeep
- Guildmaster's Guide to Ravnica
- Mythic Odysseys of Theros
- Planescape: Adventures in the Multiverse
- Spelljammer: Adventures in Space
- Strixhaven: A Curriculum of Chaos
- Van Richten's Guide to Ravenloft

### Adventures (30+ available)
- Lost Mine of Phandelver
- Curse of Strahd
- Tomb of Annihilation
- Waterdeep: Dragon Heist
- Baldur's Gate: Descent Into Avernus
- Icewind Dale: Rime of the Frostmaiden
- And many more...

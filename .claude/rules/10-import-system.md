# XML Import System

**XML files are read directly from the fightclub_forked repository** (mounted at `/var/www/fightclub_forked`).

## Import Commands

```bash
# Production - one command (reads from fightclub_forked)
just import-all

# Test DB (required for search tests)
just import-test
```

**Import order matters:** Sources -> Items -> Classes -> Spells -> Others

## Source Configuration

The importer reads from 9 source directories configured in `config/import.php`:

| Source | Path |
|--------|------|
| PHB | `01_Core/01_Players_Handbook/` |
| DMG | `01_Core/02_Dungeon_Masters_Guide/` |
| MM | `01_Core/03_Monster_Manual/` |
| XGE | `02_Supplements/Xanathars_Guide_to_Everything/` |
| TCE | `02_Supplements/Tashas_Cauldron_of_Everything/` |
| VGM | `02_Supplements/Volos_Guide_to_Monsters/` |
| SCAG | `03_Campaign_Settings/Sword_Coast_Adventurers_Guide/` |
| ERLW | `03_Campaign_Settings/Eberron_Rising_From_the_Last_War/` |
| TWBTW | `05_Adventures/The_Wild_Beyond_the_Witchlight/` |

## Environment Variable

```
XML_SOURCE_PATH=/var/www/fightclub_forked/Sources/PHB2014/WizardsOfTheCoast
```

**Reference:** `../wrapper/docs/backend/reference/XML-SOURCE-PATHS.md`

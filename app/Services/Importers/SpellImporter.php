<?php

namespace App\Services\Importers;

use App\Models\CharacterClass;
use App\Models\DamageType;
use App\Models\RandomTable;
use App\Models\RandomTableEntry;
use App\Models\Spell;
use App\Models\SpellSchool;
use App\Services\Importers\Concerns\ImportsRandomTables;
use App\Services\Importers\Concerns\ImportsSavingThrows;
use App\Services\Parsers\SpellXmlParser;

class SpellImporter extends BaseImporter
{
    use ImportsRandomTables;
    use ImportsSavingThrows;

    /**
     * Subclass name aliases for XML → Database mapping.
     *
     * XML files use abbreviated/variant names that differ from official subclass names.
     * This map handles special cases where fuzzy matching won't work.
     *
     * Format: 'XML Name' => 'Database Name'
     */
    private const SUBCLASS_ALIASES = [
        // Druid Circle of the Land variants (Coast, Desert, Forest, etc. are terrain options, not separate subclasses)
        'Coast' => 'Circle of the Land',
        'Desert' => 'Circle of the Land',
        'Forest' => 'Circle of the Land',
        'Grassland' => 'Circle of the Land',
        'Mountain' => 'Circle of the Land',
        'Swamp' => 'Circle of the Land',
        'Underdark' => 'Circle of the Land',
        'Arctic' => 'Circle of the Land',

        // Common abbreviations
        'Ancients' => 'Oath of the Ancients',
        'Vengeance' => 'Oath of Vengeance',
    ];

    protected function importEntity(array $spellData): Spell
    {
        // Lookup spell school by code
        $spellSchool = SpellSchool::where('code', $spellData['school'])->firstOrFail();

        // Create or update spell using slug as unique key
        $spell = Spell::updateOrCreate(
            ['slug' => $this->generateSlug($spellData['name'])],
            [
                'name' => $spellData['name'],
                'level' => $spellData['level'],
                'spell_school_id' => $spellSchool->id,
                'casting_time' => $spellData['casting_time'],
                'range' => $spellData['range'],
                'components' => $spellData['components'],
                'material_components' => $spellData['material_components'],
                'duration' => $spellData['duration'],
                'needs_concentration' => $spellData['needs_concentration'],
                'is_ritual' => $spellData['is_ritual'],
                'description' => $spellData['description'],
                'higher_levels' => $spellData['higher_levels'],
            ]
        );

        // Delete existing effects (for re-imports)
        $spell->effects()->delete();

        // Import spell effects
        if (isset($spellData['effects'])) {
            foreach ($spellData['effects'] as $effectData) {
                // Lookup damage type if damage_type_name is present
                if (isset($effectData['damage_type_name']) && $effectData['damage_type_name']) {
                    $damageType = DamageType::where('name', $effectData['damage_type_name'])->first();
                    $effectData['damage_type_id'] = $damageType?->id;
                }

                // Remove damage_type_name (not a database column)
                unset($effectData['damage_type_name']);

                $spell->effects()->create($effectData);
            }
        }

        // Import sources - clear old sources and create new ones
        if (isset($spellData['sources']) && is_array($spellData['sources'])) {
            $this->importEntitySources($spell, $spellData['sources']);
        }

        // Import class associations
        if (isset($spellData['classes']) && is_array($spellData['classes'])) {
            $this->importClassAssociations($spell, $spellData['classes']);
        }

        // Import tags (Touch Spells, Ritual Caster, Mark of X, etc.)
        if (isset($spellData['tags']) && is_array($spellData['tags']) && ! empty($spellData['tags'])) {
            $spell->syncTags($spellData['tags']);
        }

        // Import saving throws
        if (isset($spellData['saving_throws']) && is_array($spellData['saving_throws'])) {
            $this->importSavingThrows($spell, $spellData['saving_throws']);
        }

        // Import random tables
        if (isset($spellData['random_tables']) && is_array($spellData['random_tables'])) {
            $this->importSpellRandomTables($spell, $spellData['random_tables']);
        }

        return $spell;
    }

    /**
     * Import class associations for a spell.
     *
     * Logic:
     * - "Fighter (Eldritch Knight)" → Use SUBCLASS (Eldritch Knight)
     * - "Wizard" → Use BASE CLASS (Wizard)
     *
     * @param  array  $classNames  Array of class names (may include subclasses in parentheses)
     */
    private function importClassAssociations(Spell $spell, array $classNames): void
    {
        $classIds = [];

        foreach ($classNames as $className) {
            $class = null;

            // Check if subclass is specified in parentheses: "Fighter (Eldritch Knight)"
            if (preg_match('/^(.+?)\s*\(([^)]+)\)$/', $className, $matches)) {
                $baseClassName = trim($matches[1]);
                $subclassName = trim($matches[2]);

                // Check if there's an alias mapping for this subclass name
                if (isset(self::SUBCLASS_ALIASES[$subclassName])) {
                    $subclassName = self::SUBCLASS_ALIASES[$subclassName];
                }

                // Try to find the SUBCLASS - try exact match first, then fuzzy match
                $class = CharacterClass::where('name', $subclassName)->first();

                // If exact match fails, try fuzzy match (e.g., "Archfey" -> "The Archfey")
                if (! $class) {
                    $class = CharacterClass::where('name', 'LIKE', "%{$subclassName}%")->first();
                }

                // If subclass still not found, skip (don't fallback to base class)
                if (! $class) {
                    // Could add logging here if needed
                    continue;
                }
            } else {
                // No parentheses = use base class
                $class = CharacterClass::where('name', $className)
                    ->whereNull('parent_class_id') // Only match base classes
                    ->first();
            }

            if ($class) {
                $classIds[] = $class->id;
            }
        }

        // Sync class associations (removes old associations, adds new ones)
        $spell->classes()->sync($classIds);
    }

    /**
     * Import random tables embedded in spell description.
     *
     * Similar to importTraitTables but for spells (Prismatic Spray, Confusion, etc.)
     *
     * @param  Spell  $spell  The spell entity
     * @param  array  $tablesData  Array of table data from parser
     */
    private function importSpellRandomTables(Spell $spell, array $tablesData): void
    {
        // Delete existing random tables (for re-imports)
        $spell->randomTables()->each(function ($table) {
            $table->entries()->delete();
            $table->delete();
        });

        foreach ($tablesData as $tableData) {
            if (empty($tableData['entries'])) {
                continue; // Skip tables with no entries
            }

            // Create random table linked to spell
            $table = RandomTable::create([
                'reference_type' => Spell::class,
                'reference_id' => $spell->id,
                'table_name' => $tableData['table_name'],
                'dice_type' => $tableData['dice_type'],
            ]);

            // Create table entries
            foreach ($tableData['entries'] as $index => $entry) {
                RandomTableEntry::create([
                    'random_table_id' => $table->id,
                    'roll_min' => $entry['roll_min'],
                    'roll_max' => $entry['roll_max'],
                    'result_text' => $entry['result_text'],
                    'sort_order' => $index,
                ]);
            }
        }
    }

    protected function getParser(): object
    {
        return new SpellXmlParser;
    }
}

<?php

namespace App\Services\Importers;

use App\Models\CharacterClass;
use App\Models\DamageType;
use App\Models\Spell;
use App\Models\SpellSchool;
use App\Services\Parsers\SpellXmlParser;

class SpellImporter extends BaseImporter
{
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

                // Try to find the SUBCLASS first
                $class = CharacterClass::where('name', $subclassName)->first();

                // If subclass not found, log warning and skip (don't fallback to base class)
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

    protected function getParser(): object
    {
        return new SpellXmlParser;
    }
}

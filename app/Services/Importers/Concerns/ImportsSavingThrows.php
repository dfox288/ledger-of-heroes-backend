<?php

namespace App\Services\Importers\Concerns;

use App\Models\AbilityScore;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing entity saving throw requirements.
 *
 * Handles the common pattern of:
 * 1. Clear existing saving throw associations
 * 2. Look up ability score by name
 * 3. Attach to entity via polymorphic entity_saving_throws table
 *
 * Used by: Spells, Items, Monsters, etc.
 */
trait ImportsSavingThrows
{
    /**
     * Import saving throw requirements for an entity.
     *
     * @param  Model  $entity  The entity (Spell, Item, Monster, etc.)
     * @param  array  $savingThrows  Array of saving throw data from parser
     */
    protected function importSavingThrows(Model $entity, array $savingThrows): void
    {
        // Clear existing saving throw associations (for re-imports)
        $entity->savingThrows()->detach();

        foreach ($savingThrows as $saveData) {
            // Lookup ability score by name (e.g., "Dexterity" -> DEX)
            $abilityScore = AbilityScore::where('name', $saveData['ability'])->first();

            if (! $abilityScore) {
                // Could add logging here if needed
                continue;
            }

            // Attach to entity with pivot data
            $entity->savingThrows()->attach($abilityScore->id, [
                'save_effect' => $saveData['effect'] ?? null,
                'is_initial_save' => ! ($saveData['recurring'] ?? false), // recurring=true means is_initial_save=false
                'save_modifier' => $saveData['modifier'] ?? 'none', // 'none', 'advantage', 'disadvantage' (defaults to 'none')
            ]);
        }
    }
}

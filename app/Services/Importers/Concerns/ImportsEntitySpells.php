<?php

namespace App\Services\Importers\Concerns;

use App\Models\Spell;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Trait for importing spell associations for polymorphic entities.
 *
 * Handles the common pattern of:
 * 1. Clear existing entity_spells records
 * 2. Look up spell by name (case-insensitive)
 * 3. Create entity_spell pivot record with entity-specific data
 *
 * Used by: ItemImporter, RaceImporter, (future: MonsterImporter)
 */
trait ImportsEntitySpells
{
    /**
     * Import spell associations for a polymorphic entity.
     *
     * @param  Model  $entity  The entity (Item, Race, Monster, etc.)
     * @param  array  $spellsData  Array of spell associations with pivot data
     *
     * Expected format:
     * [
     *     [
     *         'spell_name' => 'Cure Wounds',
     *         'pivot_data' => [
     *             'charges_cost_min' => 1,
     *             'charges_cost_max' => 4,
     *             // ... other entity-specific fields
     *         ]
     *     ],
     *     ...
     * ]
     */
    protected function importEntitySpells(Model $entity, array $spellsData): void
    {
        // Clear existing spell associations
        DB::table('entity_spells')
            ->where('reference_type', get_class($entity))
            ->where('reference_id', $entity->id)
            ->delete();

        foreach ($spellsData as $spellData) {
            // Look up spell by name (case-insensitive)
            $spell = Spell::whereRaw('LOWER(name) = ?', [strtolower($spellData['spell_name'])])
                ->first();

            if (! $spell) {
                Log::warning("Spell not found: {$spellData['spell_name']} (for {$entity->name})");

                continue;
            }

            // Merge common fields + entity-specific pivot data
            DB::table('entity_spells')->updateOrInsert(
                [
                    'reference_type' => get_class($entity),
                    'reference_id' => $entity->id,
                    'spell_id' => $spell->id,
                ],
                array_merge(
                    [
                        'reference_type' => get_class($entity),
                        'reference_id' => $entity->id,
                        'spell_id' => $spell->id,
                    ],
                    $spellData['pivot_data'] ?? []
                )
            );
        }
    }
}

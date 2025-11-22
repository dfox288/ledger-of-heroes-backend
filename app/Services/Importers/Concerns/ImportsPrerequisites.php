<?php

namespace App\Services\Importers\Concerns;

use App\Models\AbilityScore;
use App\Models\EntityPrerequisite;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing prerequisites for polymorphic entities.
 *
 * Handles the common pattern of:
 * 1. Clear existing prerequisites
 * 2. Create EntityPrerequisite records with proper polymorphic references
 *
 * Used by: ItemImporter, FeatImporter, (future: MonsterImporter)
 */
trait ImportsPrerequisites
{
    /**
     * Import prerequisites for a polymorphic entity.
     *
     * @param  Model  $entity  The entity (Item, Feat, Monster, etc.)
     * @param  array  $prerequisitesData  Array of prerequisite definitions
     *
     * Expected format:
     * [
     *     [
     *         'prerequisite_type' => AbilityScore::class,
     *         'prerequisite_id' => 1,
     *         'minimum_value' => 13,
     *         'description' => null,
     *         'group_id' => 1,
     *     ],
     *     ...
     * ]
     */
    protected function importEntityPrerequisites(Model $entity, array $prerequisitesData): void
    {
        // Clear existing prerequisites
        $entity->prerequisites()->delete();

        foreach ($prerequisitesData as $prereqData) {
            EntityPrerequisite::create([
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'prerequisite_type' => $prereqData['prerequisite_type'],
                'prerequisite_id' => $prereqData['prerequisite_id'],
                'minimum_value' => $prereqData['minimum_value'] ?? null,
                'description' => $prereqData['description'] ?? null,
                'group_id' => $prereqData['group_id'] ?? 1,
            ]);
        }
    }

    /**
     * Create a single strength prerequisite for an entity.
     *
     * Convenience method for the common pattern of requiring minimum STR.
     * Used by items with strength requirements (heavy armor, etc.)
     *
     * @param  Model  $entity  The entity (Item, Monster, etc.)
     * @param  int  $strengthRequirement  Minimum strength score required
     */
    protected function createStrengthPrerequisite(Model $entity, int $strengthRequirement): void
    {
        $strAbilityScore = AbilityScore::where('code', 'STR')->first();

        if (! $strAbilityScore) {
            return; // Should never happen, but fail gracefully
        }

        $this->importEntityPrerequisites($entity, [[
            'prerequisite_type' => AbilityScore::class,
            'prerequisite_id' => $strAbilityScore->id,
            'minimum_value' => $strengthRequirement,
            'description' => null,
            'group_id' => 1,
        ]]);
    }
}

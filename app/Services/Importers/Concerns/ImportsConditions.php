<?php

namespace App\Services\Importers\Concerns;

use App\Models\Condition;
use App\Models\EntityCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Trait for importing conditions (immunities, advantages, resistances, etc.).
 *
 * Handles the common pattern of:
 * 1. Clear existing conditions
 * 2. Look up conditions by name (slug match)
 * 3. Create EntityCondition records with polymorphic reference
 */
trait ImportsConditions
{
    /**
     * Import conditions for an entity.
     *
     * Clears existing conditions and creates new EntityCondition records.
     * Supports two data formats:
     * - With condition_name: Looks up condition by slug and sets condition_id
     * - With description only: Sets description without condition_id (free-form)
     *
     * @param  Model  $entity  The entity (Race, Feat, etc.)
     * @param  array  $conditionsData  Array of condition data
     */
    protected function importEntityConditions(Model $entity, array $conditionsData): void
    {
        // Clear existing conditions for this entity
        $entity->conditions()->delete();

        foreach ($conditionsData as $conditionData) {
            $conditionId = null;
            $description = $conditionData['description'] ?? null;

            // If condition_name is provided, look up the condition
            if (! empty($conditionData['condition_name'])) {
                $conditionSlug = Str::slug($conditionData['condition_name']);
                $condition = Condition::where('slug', $conditionSlug)->first();

                if ($condition) {
                    $conditionId = $condition->id;
                } else {
                    // Skip if condition lookup fails
                    continue;
                }
            }

            EntityCondition::create([
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'condition_id' => $conditionId,
                'effect_type' => $conditionData['effect_type'],
                'description' => $description,
            ]);
        }
    }
}

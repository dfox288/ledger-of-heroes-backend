<?php

namespace App\Services\Importers\Concerns;

use App\Models\AbilityScore;
use App\Models\DamageType;
use App\Models\Modifier;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for importing modifiers (ability scores, skills, damage resistances, etc.).
 *
 * Handles the common pattern of:
 * 1. Clear existing modifiers
 * 2. Create new modifiers with polymorphic reference
 * 3. Link to ability scores, skills, or damage types as needed
 */
trait ImportsModifiers
{
    /**
     * Import modifiers for an entity.
     *
     * Clears existing modifiers and creates new Modifier records.
     *
     * @param  Model  $entity  The entity (Race, Item, Feat, etc.)
     * @param  array  $modifiersData  Array of modifier data
     */
    protected function importEntityModifiers(Model $entity, array $modifiersData): void
    {
        // Clear existing modifiers for this entity
        $entity->modifiers()->delete();

        foreach ($modifiersData as $modData) {
            // Resolve skill_id from skill_name if needed
            $skillId = $modData['skill_id'] ?? null;
            if (! $skillId && isset($modData['skill_name'])) {
                $skill = Skill::where('name', $modData['skill_name'])->first();
                $skillId = $skill?->id;
            }

            // Resolve ability_score_id from ability_score_code if needed
            $abilityScoreId = $modData['ability_score_id'] ?? null;
            if (! $abilityScoreId && isset($modData['ability_score_code'])) {
                $ability = AbilityScore::where('code', $modData['ability_score_code'])->first();
                $abilityScoreId = $ability?->id;
            }

            // Resolve damage_type_id from damage_type_name or damage_type_code if needed
            $damageTypeId = $modData['damage_type_id'] ?? null;
            if (! $damageTypeId && isset($modData['damage_type_name'])) {
                // Prefer lookup by name (matches seeder data exactly)
                $damageType = DamageType::where('name', $modData['damage_type_name'])->first();
                $damageTypeId = $damageType?->id;
            } elseif (! $damageTypeId && isset($modData['damage_type_code'])) {
                // Fallback to lookup by code (for backward compatibility)
                $damageType = DamageType::where('code', $modData['damage_type_code'])->first();
                $damageTypeId = $damageType?->id;
            }

            $modifier = [
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'modifier_category' => $modData['modifier_category'] ?? $modData['category'],
                'value' => $modData['value'],
                'ability_score_id' => $abilityScoreId,
                'skill_id' => $skillId,
                'damage_type_id' => $damageTypeId,
                'is_choice' => $modData['is_choice'] ?? false,
                'choice_count' => $modData['choice_count'] ?? null,
                'choice_constraint' => $modData['choice_constraint'] ?? null,
                'condition' => $modData['condition'] ?? null,
            ];

            Modifier::create($modifier);
        }
    }
}

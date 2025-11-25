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
 * 1. Clear existing modifiers (via clearClassRelatedData in ClassImporter)
 * 2. Create/update modifiers with polymorphic reference using updateOrCreate
 * 3. Link to ability scores, skills, or damage types as needed
 *
 * Uses updateOrCreate to prevent duplicates on re-import.
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

            // Build unique keys for updateOrCreate
            $uniqueKeys = [
                'reference_type' => get_class($entity),
                'reference_id' => $entity->id,
                'modifier_category' => $modData['modifier_category'] ?? $modData['category'],
                'level' => $modData['level'] ?? null,
                'ability_score_id' => $abilityScoreId,
                'skill_id' => $skillId,
                'damage_type_id' => $damageTypeId,
            ];

            // Build values to set/update
            $values = [
                'value' => $modData['value'],
                'is_choice' => $modData['is_choice'] ?? false,
                'choice_count' => $modData['choice_count'] ?? null,
                'choice_constraint' => $modData['choice_constraint'] ?? null,
                'condition' => $modData['condition'] ?? null,
            ];

            // Use updateOrCreate to prevent duplicates on re-import
            Modifier::updateOrCreate($uniqueKeys, array_merge($uniqueKeys, $values));
        }
    }

    /**
     * Import a single modifier with deduplication.
     *
     * Convenience method for importing individual modifiers.
     *
     * @param  Model  $entity  The entity (Class, Race, etc.)
     * @param  string  $category  Modifier category (ability_score, skill, speed, etc.)
     * @param  array  $data  Modifier data (value, condition, level, etc.)
     */
    protected function importModifier(Model $entity, string $category, array $data): Modifier
    {
        $uniqueKeys = [
            'reference_type' => get_class($entity),
            'reference_id' => $entity->id,
            'modifier_category' => $category,
            'level' => $data['level'] ?? null,
            'ability_score_id' => $data['ability_score_id'] ?? null,
            'skill_id' => $data['skill_id'] ?? null,
            'damage_type_id' => $data['damage_type_id'] ?? null,
        ];

        return Modifier::updateOrCreate($uniqueKeys, array_merge($uniqueKeys, $data));
    }

    /**
     * Import ASI modifier specifically (common case).
     *
     * @param  string  $value  Default '+2'
     */
    protected function importAsiModifier(Model $entity, int $level, string $value = '+2'): Modifier
    {
        return $this->importModifier($entity, 'ability_score', [
            'level' => $level,
            'value' => $value,
            'ability_score_id' => null,
            'is_choice' => true,
            'choice_count' => 2,
            'condition' => 'Choose one ability score to increase by 2, or two ability scores to increase by 1 each',
        ]);
    }
}

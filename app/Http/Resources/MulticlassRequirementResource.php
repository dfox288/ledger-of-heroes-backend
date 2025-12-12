<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for multiclass ability score requirements.
 *
 * Transforms entity_proficiencies records with proficiency_type='multiclass_requirement'
 * into a structured format for the API.
 *
 * @property int $id
 * @property int|null $ability_score_id
 * @property string $proficiency_name Display name (e.g., "Strength 13")
 * @property string|null $proficiency_subcategory 'OR' = alternative, 'AND' = required together
 */
class MulticlassRequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Parse minimum score from proficiency_name (e.g., "Strength 13" -> 13)
        $minimumScore = null;
        if (preg_match('/(\d+)$/', $this->proficiency_name, $matches)) {
            $minimumScore = (int) $matches[1];
        }

        return [
            'ability' => $this->when(
                $this->relationLoaded('abilityScore') && $this->abilityScore,
                fn () => new AbilityScoreResource($this->abilityScore)
            ),
            'ability_name' => $this->proficiency_name, // "Strength 13"
            'minimum_score' => $minimumScore,
            // 'OR' = alternative (any one), 'AND' = required together
            'is_alternative' => $this->proficiency_subcategory === 'OR',
        ];
    }

    /**
     * Create a collection with type metadata for frontend.
     *
     * @param  \Illuminate\Support\Collection  $requirements
     * @return array{type: string, requirements: array}
     */
    public static function collectionWithType($requirements): array
    {
        if ($requirements->isEmpty()) {
            return [
                'type' => 'none',
                'requirements' => [],
            ];
        }

        // Determine type from proficiency_subcategory field
        // If ANY requirement has subcategory='OR', it's an OR condition
        $isOr = $requirements->contains('proficiency_subcategory', 'OR');

        $type = match (true) {
            $isOr => 'or',              // Need any one
            $requirements->count() > 1 => 'and', // Need all
            default => 'single',         // Just one requirement
        };

        return [
            'type' => $type,
            'requirements' => static::collection($requirements)->resolve(),
        ];
    }
}

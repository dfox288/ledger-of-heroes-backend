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
 * @property int $quantity Minimum ability score required
 * @property bool $is_choice true = OR condition, false = AND condition
 */
class MulticlassRequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ability' => $this->when(
                $this->relationLoaded('abilityScore') && $this->abilityScore,
                fn () => new AbilityScoreResource($this->abilityScore)
            ),
            'ability_name' => $this->proficiency_name, // "Strength 13"
            'minimum_score' => $this->quantity,
            'is_alternative' => (bool) $this->is_choice, // true = OR, false = AND
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

        // Determine type from is_choice flag
        // If ANY requirement has is_choice=true, it's an OR condition
        $isOr = $requirements->contains('is_choice', true);

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

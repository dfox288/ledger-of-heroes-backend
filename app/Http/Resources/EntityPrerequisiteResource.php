<?php

namespace App\Http\Resources;

use App\Models\AbilityScore;
use App\Models\ProficiencyType;
use App\Models\Race;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityPrerequisiteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prerequisite_type' => $this->prerequisite_type,
            'prerequisite_id' => $this->prerequisite_id,
            'minimum_value' => $this->minimum_value,
            'description' => $this->description,
            'group_id' => $this->group_id,

            // Generic prerequisite field (polymorphic relationship)
            'prerequisite' => $this->when(
                $this->relationLoaded('prerequisite'),
                fn () => $this->formatPrerequisite()
            ),

            // Conditionally include nested resources based on prerequisite type
            'ability_score' => $this->when(
                $this->prerequisite_type === AbilityScore::class,
                fn () => new AbilityScoreResource($this->whenLoaded('prerequisite'))
            ),
            'race' => $this->when(
                $this->prerequisite_type === Race::class,
                fn () => new RaceResource($this->whenLoaded('prerequisite'))
            ),
            'skill' => $this->when(
                $this->prerequisite_type === Skill::class,
                fn () => new SkillResource($this->whenLoaded('prerequisite'))
            ),
            'proficiency_type' => $this->when(
                $this->prerequisite_type === ProficiencyType::class,
                fn () => new ProficiencyTypeResource($this->whenLoaded('prerequisite'))
            ),
        ];
    }

    /**
     * Format the prerequisite based on its type.
     */
    private function formatPrerequisite()
    {
        if (! $this->prerequisite) {
            return null;
        }

        return match ($this->prerequisite_type) {
            AbilityScore::class => new AbilityScoreResource($this->prerequisite),
            Race::class => new RaceResource($this->prerequisite),
            Skill::class => new SkillResource($this->prerequisite),
            ProficiencyType::class => new ProficiencyTypeResource($this->prerequisite),
            default => null,
        };
    }
}

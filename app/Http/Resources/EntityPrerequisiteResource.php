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

            // Type-specific nested resources (only one will be present based on prerequisite_type)
            // Note: Removed generic 'prerequisite' field to avoid duplicate data (Issue #73)
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
}

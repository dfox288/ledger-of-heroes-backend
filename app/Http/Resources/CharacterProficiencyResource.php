<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterProficiencyResource extends JsonResource
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
            'source' => $this->source,
            'expertise' => $this->expertise,

            // Skill proficiency
            'skill' => $this->when($this->skill_slug, function () {
                return $this->skill ? [
                    'id' => $this->skill->id,
                    'name' => $this->skill->name,
                    'slug' => $this->skill->slug,
                    'ability_code' => $this->skill->abilityScore?->code,
                ] : null;
            }),
            'skill_slug' => $this->skill_slug,

            // Equipment/tool proficiency
            'proficiency_type' => $this->when($this->proficiency_type_slug, function () {
                return $this->proficiencyType ? [
                    'id' => $this->proficiencyType->id,
                    'name' => $this->proficiencyType->name,
                    'slug' => $this->proficiencyType->slug,
                    'category' => $this->proficiencyType->category,
                ] : null;
            }),
            'proficiency_type_slug' => $this->proficiency_type_slug,

            // Dangling reference detection
            'is_dangling' => ($this->skill_slug !== null && $this->skill === null)
                || ($this->proficiency_type_slug !== null && $this->proficiencyType === null),
        ];
    }
}

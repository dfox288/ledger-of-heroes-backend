<?php

namespace App\Http\Resources;

use App\Models\EntityChoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for entity choices (equipment, proficiency, spell, language, ability_score).
 *
 * @mixin EntityChoice
 */
class EntityChoiceResource extends JsonResource
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
            'choice_type' => $this->choice_type,
            'choice_group' => $this->choice_group,
            'choice_option' => $this->choice_option,
            'quantity' => $this->quantity,
            'constraint' => $this->constraint,
            'target_type' => $this->target_type,
            'target_slug' => $this->target_slug,
            'description' => $this->description,
            'level_granted' => $this->level_granted,
            'is_required' => $this->is_required,

            // Spell-specific fields
            'spell_max_level' => $this->when($this->choice_type === 'spell', $this->spell_max_level),
            'spell_list_slug' => $this->when($this->choice_type === 'spell', $this->spell_list_slug),
            'spell_school_slug' => $this->when($this->choice_type === 'spell', $this->spell_school_slug),

            // Proficiency-specific fields
            'proficiency_type' => $this->when($this->choice_type === 'proficiency', $this->proficiency_type),

            // Additional constraints
            'constraints' => $this->constraints,
        ];
    }

    /**
     * Group choices by choice_group for the API response.
     *
     * @param  \Illuminate\Support\Collection<int, EntityChoice>  $choices
     * @return array<int, array{choice_group: string, choice_type: string, quantity: int, level_granted: int, is_required: bool, options: array}>
     */
    public static function groupedByChoiceGroup($choices): array
    {
        if ($choices->isEmpty()) {
            return [];
        }

        $grouped = $choices->groupBy('choice_group');

        return $grouped->map(function ($groupChoices, $choiceGroup) {
            $first = $groupChoices->first();

            return [
                'choice_group' => $choiceGroup,
                'choice_type' => $first->choice_type,
                'quantity' => $first->quantity ?? 1,
                'level_granted' => $first->level_granted,
                'is_required' => $first->is_required ?? true,
                'options' => $groupChoices->map(function ($choice) {
                    return [
                        'option' => $choice->choice_option,
                        'description' => $choice->description,
                        'target_type' => $choice->target_type,
                        'target_slug' => $choice->target_slug,
                        'constraints' => $choice->constraints,
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();
    }
}

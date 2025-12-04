<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EntityLanguageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'language' => $this->when($this->language_id, new LanguageResource($this->whenLoaded('language'))),
            'is_choice' => $this->is_choice,
            'quantity' => $this->when($this->is_choice, $this->quantity),
            'choice_group' => $this->when($this->choice_group, $this->choice_group),
            'choice_option' => $this->when($this->choice_option, $this->choice_option),
            'condition' => $this->when($this->condition_type, fn () => [
                'type' => $this->condition_type,
                'language' => new LanguageResource($this->whenLoaded('conditionLanguage')),
            ]),
        ];
    }
}

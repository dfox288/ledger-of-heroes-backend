<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for fixed language grants.
 *
 * Note: Language choices are stored in entity_choices table and exposed via EntityChoiceResource.
 */
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
        ];
    }
}

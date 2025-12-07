<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterLanguageResource extends JsonResource
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
            'language' => $this->language ? [
                'id' => $this->language->id,
                'name' => $this->language->name,
                'slug' => $this->language->slug,
                'script' => $this->language->script,
            ] : null,
            'language_slug' => $this->language_slug,
            'is_dangling' => $this->language === null,
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Support\NoteCategories;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     category: string,
     *     category_label: string,
     *     title: string|null,
     *     content: string,
     *     sort_order: int,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'category_label' => NoteCategories::label($this->category),
            'title' => $this->title,
            'content' => $this->content,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

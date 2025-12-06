<?php

namespace App\Http\Resources;

use App\Enums\NoteCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Resource for grouped character notes by category.
 *
 * @property Collection $resource The character notes collection
 */
class CharacterNotesGroupedResource extends JsonResource
{
    /**
     * Disable data wrapping - we handle it explicitly.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array{data: array<string, array>}
     */
    public function toArray(Request $request): array
    {
        $grouped = [];

        foreach (NoteCategory::cases() as $category) {
            $categoryNotes = $this->resource->where('category', $category);
            if ($categoryNotes->isNotEmpty()) {
                $grouped[$category->value] = CharacterNoteResource::collection($categoryNotes)->resolve();
            }
        }

        return [
            /** @var array<string, array<array{id: int, category: string, category_label: string, title: string|null, content: string, sort_order: int, created_at: string, updated_at: string}>> Notes grouped by category */
            'data' => $grouped,
        ];
    }
}

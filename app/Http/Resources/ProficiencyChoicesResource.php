<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for proficiency choices data.
 *
 * Returns proficiency choices organized by source (class, race, background).
 * Each source contains choice groups with options and selection status.
 *
 * @property array $resource The choices data structure
 */
class ProficiencyChoicesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     class: array<string, array{
     *         proficiency_type: string,
     *         proficiency_subcategory: string|null,
     *         quantity: int,
     *         remaining: int,
     *         selected_skills: array<int>,
     *         selected_proficiency_types: array<int>,
     *         options: array<int, array{
     *             type: string,
     *             skill_id: int|null,
     *             skill: array{id: int, name: string, slug: string, ability_code: string}|null,
     *             proficiency_type_id: int|null,
     *             proficiency_type: array{id: int, name: string, slug: string, category: string}|null
     *         }>
     *     }>,
     *     race: array<string, array{
     *         proficiency_type: string,
     *         proficiency_subcategory: string|null,
     *         quantity: int,
     *         remaining: int,
     *         selected_skills: array<int>,
     *         selected_proficiency_types: array<int>,
     *         options: array<int, array{
     *             type: string,
     *             skill_id: int|null,
     *             skill: array{id: int, name: string, slug: string, ability_code: string}|null,
     *             proficiency_type_id: int|null,
     *             proficiency_type: array{id: int, name: string, slug: string, category: string}|null
     *         }>
     *     }>,
     *     background: array<string, array{
     *         proficiency_type: string,
     *         proficiency_subcategory: string|null,
     *         quantity: int,
     *         remaining: int,
     *         selected_skills: array<int>,
     *         selected_proficiency_types: array<int>,
     *         options: array<int, array{
     *             type: string,
     *             skill_id: int|null,
     *             skill: array{id: int, name: string, slug: string, ability_code: string}|null,
     *             proficiency_type_id: int|null,
     *             proficiency_type: array{id: int, name: string, slug: string, category: string}|null
     *         }>
     *     }>
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

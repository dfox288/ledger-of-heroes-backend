<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for character export data.
 */
class CharacterExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     format_version: string,
     *     exported_at: string,
     *     character: array{
     *         public_id: string,
     *         name: string,
     *         race: string|null,
     *         background: string|null,
     *         alignment: string|null,
     *         ability_scores: array{
     *             strength: int|null,
     *             dexterity: int|null,
     *             constitution: int|null,
     *             intelligence: int|null,
     *             wisdom: int|null,
     *             charisma: int|null
     *         },
     *         classes: array<array{class: string, subclass: string|null, level: int, is_primary: bool}>,
     *         spells: array<array{spell: string, source: string, preparation_status: string}>,
     *         equipment: array<array{item: string|null, custom_name: string|null, quantity: int, equipped: bool}>,
     *         languages: array<array{language: string, source: string}>,
     *         proficiencies: array{
     *             skills: array<array{skill: string, source: string, expertise: bool}>,
     *             types: array<array{type: string, source: string, expertise: bool}>
     *         },
     *         conditions: array<array{condition: string, level: int|null}>,
     *         feature_selections: array<array{feature: string, class: string}>,
     *         notes: array<array{category: string, title: string|null, content: string}>
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        // The resource is already an array from the export service
        return $this->resource;
    }
}

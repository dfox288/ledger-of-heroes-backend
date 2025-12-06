<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for language choices data.
 *
 * Returns language choices organized by source (race, background, feat).
 * Each source contains known languages and available choices.
 *
 * @property array $resource The choices data structure
 */
class LanguageChoicesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     race: array{
     *         known: array<int, array{id: int, name: string, slug: string, script: string}>,
     *         choices: array{
     *             quantity: int,
     *             remaining: int,
     *             selected: array<int>,
     *             options: array<int, array{id: int, name: string, slug: string, script: string}>
     *         }
     *     },
     *     background: array{
     *         known: array<int, array{id: int, name: string, slug: string, script: string}>,
     *         choices: array{
     *             quantity: int,
     *             remaining: int,
     *             selected: array<int>,
     *             options: array<int, array{id: int, name: string, slug: string, script: string}>
     *         }
     *     },
     *     feat: array{
     *         known: array<int, array{id: int, name: string, slug: string, script: string}>,
     *         choices: array{
     *             quantity: int,
     *             remaining: int,
     *             selected: array<int>,
     *             options: array<int, array{id: int, name: string, slug: string, script: string}>
     *         }
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

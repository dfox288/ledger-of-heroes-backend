<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for character currency modification results.
 *
 * Returns all currency types after modification.
 *
 * @property array{pp: int, gp: int, ep: int, sp: int, cp: int} $resource
 */
class CharacterCurrencyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        return [
            'pp' => $this->resource['pp'],
            'gp' => $this->resource['gp'],
            'ep' => $this->resource['ep'],
            'sp' => $this->resource['sp'],
            'cp' => $this->resource['cp'],
        ];
    }
}

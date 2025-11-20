<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchResource extends JsonResource
{
    /**
     * Transform the global search results into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => [
                'spells' => SpellResource::collection($this->resource['spells'] ?? collect()),
                'items' => ItemResource::collection($this->resource['items'] ?? collect()),
                'races' => RaceResource::collection($this->resource['races'] ?? collect()),
                'classes' => ClassResource::collection($this->resource['classes'] ?? collect()),
                'backgrounds' => BackgroundResource::collection($this->resource['backgrounds'] ?? collect()),
                'feats' => FeatResource::collection($this->resource['feats'] ?? collect()),
            ],
            'meta' => [
                'query' => $this->resource['query'],
                'types_searched' => $this->resource['types_searched'],
                'limit_per_type' => $this->resource['limit_per_type'],
                'total_results' => $this->resource['total_results'],
            ],
        ];
    }

    /**
     * Add debug information if requested.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        if (!isset($this->resource['debug'])) {
            return [];
        }

        return [
            'debug' => $this->resource['debug'],
        ];
    }
}

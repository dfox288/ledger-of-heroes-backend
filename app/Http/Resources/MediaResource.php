<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'collection' => $this->collection_name,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'urls' => [
                'original' => $this->getUrl(),
                'thumb' => $this->hasGeneratedConversion('thumb')
                    ? $this->getUrl('thumb')
                    : null,
                'medium' => $this->hasGeneratedConversion('medium')
                    ? $this->getUrl('medium')
                    : null,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

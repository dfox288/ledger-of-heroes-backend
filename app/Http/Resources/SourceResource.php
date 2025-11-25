<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SourceResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'publisher' => $this->publisher,
            'publication_year' => $this->publication_year,
            'url' => $this->url,
            'author' => $this->author,
            'artist' => $this->artist,
            'website' => $this->website,
            'category' => $this->category,
            'description' => $this->description,
        ];
    }
}

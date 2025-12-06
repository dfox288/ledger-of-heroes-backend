<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for choice resolution results.
 *
 * @property array{message: string, choice_id: string} $resource
 */
class ChoiceResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{message: string, choice_id: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string Success message */
            'message' => $this->resource['message'],
            /** @var string The choice ID that was resolved/undone */
            'choice_id' => $this->resource['choice_id'],
        ];
    }
}

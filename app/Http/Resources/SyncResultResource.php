<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Resource for sync operation results.
 *
 * Returns a message with associated data collection.
 *
 * @property array{message: string, data: ResourceCollection} $resource
 */
class SyncResultResource extends JsonResource
{
    /**
     * Create a new sync result resource.
     *
     * @param  string  $message  Success message
     * @param  ResourceCollection  $data  The synced data
     */
    public static function withMessage(string $message, ResourceCollection $data): self
    {
        return new self([
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array{message: string, data: array}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string Success message */
            'message' => $this->resource['message'],
            /** @var array The synced data */
            'data' => $this->resource['data'],
        ];
    }
}

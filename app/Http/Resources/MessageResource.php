<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for simple message responses.
 *
 * @property string $resource The message
 */
class MessageResource extends JsonResource
{
    /**
     * Disable wrapping for this resource.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array{message: string}
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var string Response message */
            'message' => $this->resource,
        ];
    }
}

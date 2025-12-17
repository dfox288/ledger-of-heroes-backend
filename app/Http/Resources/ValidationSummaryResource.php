<?php

namespace App\Http\Resources;

use App\DTOs\ValidationSummaryResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for bulk character validation summary.
 *
 * @mixin ValidationSummaryResult
 */
class ValidationSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'total' => $this->total,
            'valid' => $this->valid,
            'invalid' => $this->invalid,
            'characters' => $this->characters,
        ];
    }
}

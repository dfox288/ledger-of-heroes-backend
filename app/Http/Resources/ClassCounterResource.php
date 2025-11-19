<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassCounterResource extends JsonResource
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
            'level' => $this->level,
            'counter_name' => $this->counter_name,
            'counter_value' => $this->counter_value,
            'reset_timing' => match ($this->reset_timing) {
                'S' => 'Short Rest',
                'L' => 'Long Rest',
                default => 'Does Not Reset',
            },
        ];
    }
}

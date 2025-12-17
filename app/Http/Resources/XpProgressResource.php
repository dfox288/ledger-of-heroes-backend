<?php

namespace App\Http\Resources;

use App\DTOs\XpProgressResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for character XP progress responses.
 *
 * @mixin XpProgressResult
 */
class XpProgressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'experience_points' => $this->experiencePoints,
            'level' => $this->level,
            'next_level_xp' => $this->nextLevelXp,
            'xp_to_next_level' => $this->xpToNextLevel,
            'xp_progress_percent' => $this->xpProgressPercent,
            'is_max_level' => $this->isMaxLevel,
            'leveled_up' => $this->when($this->leveledUp !== null, $this->leveledUp),
        ];
    }
}

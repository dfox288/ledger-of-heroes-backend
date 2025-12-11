<?php

namespace App\Http\Resources;

use App\DTOs\LevelUpResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LevelUpResult
 */
class LevelUpResource extends JsonResource
{
    /**
     * The DTO to transform.
     */
    public LevelUpResult $dto;

    public function __construct(LevelUpResult $dto)
    {
        parent::__construct($dto);
        $this->dto = $dto;
    }

    public function toArray(Request $request): array
    {
        return [
            'previous_level' => $this->dto->previousLevel,
            'new_level' => $this->dto->newLevel,
            'hp_increase' => $this->dto->hpIncrease,
            'new_max_hp' => $this->dto->newMaxHp,
            'features_gained' => $this->dto->featuresGained,
            'spell_slots' => $this->formatSpellSlots($this->dto->spellSlots),
            'asi_pending' => $this->dto->asiPending,
            'hp_choice_pending' => $this->dto->hpChoicePending,
            'pending_choice_summary' => $this->dto->pendingChoiceSummary,
        ];
    }

    /**
     * Format spell slots to ensure consistent JSON structure.
     *
     * @param  array<int, int>  $spellSlots
     * @return array<string, int>
     */
    private function formatSpellSlots(array $spellSlots): array
    {
        // Convert integer keys to strings for consistent JSON output
        $formatted = [];
        foreach ($spellSlots as $level => $slots) {
            $formatted[(string) $level] = $slots;
        }

        return $formatted;
    }
}

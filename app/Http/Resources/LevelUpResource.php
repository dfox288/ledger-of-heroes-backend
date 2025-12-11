<?php

namespace App\Http\Resources;

use App\DTOs\LevelUpResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for level-up response.
 *
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

    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     previous_level: int,
     *     new_level: int,
     *     hp_increase: int,
     *     new_max_hp: int,
     *     features_gained: array<array{id: int, name: string, description: string|null}>,
     *     spell_slots: array<string, int>,
     *     asi_pending: bool,
     *     hp_choice_pending: bool,
     *     pending_choice_summary: array{
     *         total_pending: int,
     *         required_pending: int,
     *         optional_pending: int,
     *         by_type: array<string, int>,
     *         by_source: array<string, int>
     *     }
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            /** @var int Character level before level-up */
            'previous_level' => $this->dto->previousLevel,
            /** @var int Character level after level-up */
            'new_level' => $this->dto->newLevel,
            /** @var int HP gained (0 when using choice system) */
            'hp_increase' => $this->dto->hpIncrease,
            /** @var int Current max HP after level-up */
            'new_max_hp' => $this->dto->newMaxHp,
            /** @var array<array{id: int, name: string, description: string|null}> Class features granted at this level */
            'features_gained' => $this->dto->featuresGained,
            /** @var array<string, int> Spell slots by level (e.g., {"1": 4, "2": 3}) */
            'spell_slots' => $this->formatSpellSlots($this->dto->spellSlots),
            /** @var bool Whether an ASI/Feat choice is pending */
            'asi_pending' => $this->dto->asiPending,
            /** @var bool Whether an HP choice (roll/average) is pending */
            'hp_choice_pending' => $this->dto->hpChoicePending,
            /** @var array{total_pending: int, required_pending: int, optional_pending: int, by_type: array<string, int>, by_source: array<string, int>} Summary of all pending choices */
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

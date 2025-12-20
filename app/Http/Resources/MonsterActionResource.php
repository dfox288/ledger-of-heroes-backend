<?php

namespace App\Http\Resources;

use App\Models\MonsterAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MonsterAction
 */
class MonsterActionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $parsed = $this->parseAttackData($this->attack_data);

        return [
            'id' => (int) $this->id,
            'action_type' => $this->action_type,
            'name' => $this->name,
            'description' => $this->description,
            'attack_bonus' => $parsed['attack_bonus'],
            'damage' => $parsed['damage'],
            'recharge' => $this->recharge,
            'sort_order' => (int) $this->sort_order,
        ];
    }

    /**
     * Parse the attack_data field to extract attack bonus and damage.
     *
     * Format: ["DamageType Damage|+bonus|dice", ...]
     * Example: ["Slashing Damage|+4|1d6+2"]
     *
     * @return array{attack_bonus: int|null, damage: string|null}
     */
    private function parseAttackData(?string $attackData): array
    {
        if (empty($attackData)) {
            return ['attack_bonus' => null, 'damage' => null];
        }

        $entries = json_decode($attackData, true);
        if (! is_array($entries) || empty($entries)) {
            return ['attack_bonus' => null, 'damage' => null];
        }

        // Parse first entry (primary damage)
        $parts = explode('|', $entries[0]);
        if (count($parts) < 3) {
            return ['attack_bonus' => null, 'damage' => null];
        }

        // Extract damage type (e.g., "Slashing Damage" -> "slashing")
        $damageType = strtolower(str_replace(' Damage', '', $parts[0]));

        // Extract attack bonus (e.g., "+4" -> 4, "-2" -> -2)
        // Use trim() to preserve negative signs; return null for non-numeric values
        $bonusValue = trim($parts[1]);
        $attackBonus = is_numeric($bonusValue) ? (int) $bonusValue : null;

        // Extract damage dice (e.g., "1d6+2")
        $damageDice = $parts[2];

        // Format damage as "dice type" (e.g., "1d6+2 slashing")
        $damage = "{$damageDice} {$damageType}";

        return ['attack_bonus' => $attackBonus, 'damage' => $damage];
    }
}

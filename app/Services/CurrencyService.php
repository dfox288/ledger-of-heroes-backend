<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\InsufficientFundsException;
use App\Models\Character;
use App\Models\CharacterEquipment;
use Illuminate\Support\Facades\DB;
use LogicException;

class CurrencyService
{
    /**
     * Currency item slugs (PHB coins).
     */
    private const CURRENCY_SLUGS = [
        'pp' => 'phb:platinum-pp',
        'gp' => 'phb:gold-gp',
        'ep' => 'phb:electrum-ep',
        'sp' => 'phb:silver-sp',
        'cp' => 'phb:copper-cp',
    ];

    /**
     * Copper value of each currency type (D&D 5e exchange rates).
     */
    private const COPPER_VALUES = [
        'pp' => 1000,
        'gp' => 100,
        'ep' => 50,
        'sp' => 10,
        'cp' => 1,
    ];

    /**
     * Conversion order from highest to lowest denomination (skipping EP).
     */
    private const DENOMINATION_ORDER = ['pp', 'gp', 'sp', 'cp'];

    /**
     * How many of the next denomination each coin converts to.
     */
    private const NEXT_DENOMINATION = [
        'pp' => ['to' => 'gp', 'rate' => 10],
        'gp' => ['to' => 'sp', 'rate' => 10],
        'sp' => ['to' => 'cp', 'rate' => 10],
        'ep' => ['to' => 'sp', 'rate' => 5],
    ];

    /**
     * Modify character currency with auto-conversion support.
     *
     * @param  array<string, array{type: string, value: int}>  $changes
     * @return array{pp: int, gp: int, ep: int, sp: int, cp: int}
     *
     * @throws InsufficientFundsException
     */
    public function modifyCurrency(Character $character, array $changes): array
    {
        return DB::transaction(function () use ($character, $changes) {
            $currency = $this->getCurrentCurrency($character);

            $additions = [];
            $sets = [];
            $subtractions = [];

            foreach ($changes as $type => $change) {
                match ($change['type']) {
                    'add' => $additions[$type] = $change['value'],
                    'set' => $sets[$type] = $change['value'],
                    'subtract' => $subtractions[$type] = $change['value'],
                    default => throw new LogicException("Invalid operation type: {$change['type']}"),
                };
            }

            foreach ($additions as $type => $value) {
                $currency[$type] += $value;
            }

            foreach ($sets as $type => $value) {
                $currency[$type] = $value;
            }

            if (! empty($subtractions)) {
                $currency = $this->applySubtractions($currency, $subtractions);
            }

            $this->persistCurrency($character, $currency);

            return $currency;
        });
    }

    /**
     * @return array{pp: int, gp: int, ep: int, sp: int, cp: int}
     */
    private function getCurrentCurrency(Character $character): array
    {
        $character->load('equipment');

        return $character->currency;
    }

    /**
     * @param  array{pp: int, gp: int, ep: int, sp: int, cp: int}  $currency
     * @param  array<string, int>  $subtractions
     * @return array{pp: int, gp: int, ep: int, sp: int, cp: int}
     *
     * @throws InsufficientFundsException
     */
    private function applySubtractions(array $currency, array $subtractions): array
    {
        $totalCopperAvailable = $this->calculateCopperValue($currency);
        $totalCopperNeeded = 0;
        foreach ($subtractions as $type => $value) {
            $totalCopperNeeded += $value * self::COPPER_VALUES[$type];
        }

        if ($totalCopperNeeded > $totalCopperAvailable) {
            throw new InsufficientFundsException($totalCopperAvailable, $totalCopperNeeded);
        }

        foreach ($subtractions as $type => $needed) {
            $currency = $this->subtractCoin($currency, $type, $needed);
        }

        return $currency;
    }

    /**
     * @param  array{pp: int, gp: int, ep: int, sp: int, cp: int}  $currency
     * @return array{pp: int, gp: int, ep: int, sp: int, cp: int}
     */
    private function subtractCoin(array $currency, string $type, int $needed): array
    {
        $maxIterations = 1000;
        $iterations = 0;

        while ($needed > 0 && $iterations < $maxIterations) {
            $iterations++;

            if ($currency[$type] >= $needed) {
                $currency[$type] -= $needed;

                return $currency;
            }

            $needed -= $currency[$type];
            $currency[$type] = 0;

            $previousTotal = $this->calculateCopperValue($currency);
            $currency = $this->breakDownOneCoin($currency, $type);

            // Safety check: if no conversion happened, algorithm is stuck
            // This should never happen if applySubtractions validated funds correctly
            if ($currency[$type] === 0 && $previousTotal === $this->calculateCopperValue($currency)) {
                throw new LogicException(
                    "Currency conversion algorithm stuck: needed {$needed} {$type} but conversion produced no change"
                );
            }
        }

        if ($iterations >= $maxIterations) {
            throw new LogicException("Currency conversion exceeded maximum iterations ({$maxIterations})");
        }

        return $currency;
    }

    /**
     * @param  array{pp: int, gp: int, ep: int, sp: int, cp: int}  $currency
     * @return array{pp: int, gp: int, ep: int, sp: int, cp: int}
     */
    private function breakDownOneCoin(array $currency, string $targetType): array
    {
        $targetIndex = array_search($targetType, self::DENOMINATION_ORDER, true);

        // For SP, also check EP as a source
        if ($targetType === 'sp' && $currency['ep'] > 0) {
            $currency['ep']--;
            $currency['sp'] += 5;

            return $currency;
        }

        // For CP, check if we can convert SP first, otherwise EP
        if ($targetType === 'cp') {
            if ($currency['sp'] > 0) {
                $currency['sp']--;
                $currency['cp'] += 10;

                return $currency;
            }
            if ($currency['ep'] > 0) {
                $currency['ep']--;
                $currency['sp'] += 5;

                return $currency;
            }
        }

        if ($targetIndex === false || $targetIndex === 0) {
            return $currency;
        }

        // Check each higher denomination, closest first
        for ($i = $targetIndex - 1; $i >= 0; $i--) {
            $sourceType = self::DENOMINATION_ORDER[$i];

            if ($currency[$sourceType] > 0) {
                $currency[$sourceType]--;
                $conversion = self::NEXT_DENOMINATION[$sourceType];
                $currency[$conversion['to']] += $conversion['rate'];

                // If conversion didn't land on target, recurse
                if ($conversion['to'] !== $targetType) {
                    return $this->breakDownOneCoin($currency, $targetType);
                }

                return $currency;
            }
        }

        return $currency;
    }

    private function calculateCopperValue(array $currency): int
    {
        $total = 0;
        foreach (self::COPPER_VALUES as $type => $value) {
            $total += ($currency[$type] ?? 0) * $value;
        }

        return $total;
    }

    /**
     * @param  array{pp: int, gp: int, ep: int, sp: int, cp: int}  $currency
     */
    private function persistCurrency(Character $character, array $currency): void
    {
        foreach (self::CURRENCY_SLUGS as $type => $slug) {
            $quantity = $currency[$type];

            $equipment = CharacterEquipment::where('character_id', $character->id)
                ->where('item_slug', $slug)
                ->first();

            if ($quantity > 0) {
                if ($equipment) {
                    $equipment->update(['quantity' => $quantity]);
                } else {
                    CharacterEquipment::create([
                        'character_id' => $character->id,
                        'item_slug' => $slug,
                        'quantity' => $quantity,
                    ]);
                }
            } elseif ($equipment) {
                $equipment->delete();
            }
        }

        $character->unsetRelation('equipment');
    }
}

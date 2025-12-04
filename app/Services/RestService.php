<?php

namespace App\Services;

use App\Enums\ResetTiming;
use App\Enums\SpellSlotType;
use App\Models\Character;

class RestService
{
    public function __construct(
        private readonly SpellSlotService $spellSlotService,
        private readonly HitDiceService $hitDiceService
    ) {}

    /**
     * Perform a short rest.
     *
     * Effects:
     * - Reset pact magic spell slots (Warlock)
     * - Reset features with SHORT_REST reset timing
     * - (Character can spend hit dice - handled separately via HitDiceService)
     *
     * @return array{pact_magic_reset: bool, features_reset: array<string>}
     */
    public function shortRest(Character $character): array
    {
        $pactMagicReset = false;
        $featuresReset = [];

        // Reset pact magic slots
        $pactSlots = $character->spellSlots()
            ->where('slot_type', SpellSlotType::PACT_MAGIC)
            ->where('used_slots', '>', 0)
            ->exists();

        if ($pactSlots) {
            $this->spellSlotService->resetSlots($character, SpellSlotType::PACT_MAGIC);
            $pactMagicReset = true;
        }

        // Reset features with short_rest timing
        // TODO: Track feature usage when that system is implemented
        // For now, just identify which features WOULD reset
        $character->loadMissing('characterClasses.characterClass.features');
        foreach ($character->characterClasses as $classPivot) {
            $class = $classPivot->characterClass;
            $features = $class->features()
                ->where('level', '<=', $classPivot->level)
                ->where('resets_on', ResetTiming::SHORT_REST)
                ->get();

            foreach ($features as $feature) {
                $featuresReset[] = $feature->feature_name;
            }
        }

        return [
            'pact_magic_reset' => $pactMagicReset,
            'features_reset' => $featuresReset,
        ];
    }

    /**
     * Perform a long rest.
     *
     * Effects:
     * - Restore HP to max
     * - Recover half hit dice (minimum 1)
     * - Reset all spell slots (standard and pact magic)
     * - Clear death saves
     * - Reset features with LONG_REST, SHORT_REST, or DAWN timing
     *
     * @return array{hp_restored: int, hit_dice_recovered: int, spell_slots_reset: bool, death_saves_cleared: bool, features_reset: array<string>}
     */
    public function longRest(Character $character): array
    {
        $character->loadMissing('characterClasses.characterClass.features');

        $hpRestored = 0;
        $hitDiceRecovered = 0;
        $spellSlotsReset = false;
        $deathSavesCleared = false;
        $featuresReset = [];

        // Restore HP to max
        if ($character->current_hit_points < $character->max_hit_points) {
            $hpRestored = $character->max_hit_points - $character->current_hit_points;
            $character->current_hit_points = $character->max_hit_points;
        }

        // Recover half hit dice (minimum 1)
        $hitDiceResult = $this->hitDiceService->recover($character);
        $hitDiceRecovered = $hitDiceResult['recovered'];

        // Reset all spell slots
        $hasSpellSlots = $character->spellSlots()->where('used_slots', '>', 0)->exists();
        if ($hasSpellSlots) {
            $this->spellSlotService->resetAllSlots($character);
            $spellSlotsReset = true;
        }

        // Clear death saves
        if ($character->death_save_successes > 0 || $character->death_save_failures > 0) {
            $character->death_save_successes = 0;
            $character->death_save_failures = 0;
            $deathSavesCleared = true;
        }

        // Save character changes
        $character->save();

        // Reset features with long_rest, short_rest, or dawn timing
        // (Long rest encompasses all reset timings)
        foreach ($character->characterClasses as $classPivot) {
            $class = $classPivot->characterClass;
            $features = $class->features()
                ->where('level', '<=', $classPivot->level)
                ->whereIn('resets_on', [
                    ResetTiming::LONG_REST,
                    ResetTiming::SHORT_REST,
                    ResetTiming::DAWN,
                ])
                ->get();

            foreach ($features as $feature) {
                $featuresReset[] = $feature->feature_name;
            }
        }

        return [
            'hp_restored' => $hpRestored,
            'hit_dice_recovered' => $hitDiceRecovered,
            'spell_slots_reset' => $spellSlotsReset || ! $hasSpellSlots,
            'death_saves_cleared' => $deathSavesCleared,
            'features_reset' => array_values(array_unique($featuresReset)),
        ];
    }
}

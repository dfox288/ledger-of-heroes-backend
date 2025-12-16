<?php

namespace App\DTOs;

use App\Models\Character;
use App\Services\CharacterChoiceService;
use App\Services\CharacterLanguageService;
use App\Services\CharacterProficiencyService;
use App\Services\CounterService;
use App\Services\FeatChoiceService;
use App\Services\HitDiceService;
use App\Services\SpellSlotService;

/**
 * Data Transfer Object for character summary overview.
 *
 * Provides a comprehensive snapshot of character state including:
 * - Basic character info
 * - Pending choices (proficiencies, languages, spells, optional features, ASI, size, feats)
 * - Resources (HP, hit dice, spell slots, feature uses)
 * - Combat state (conditions, death saves, consciousness)
 * - Creation completeness status
 */
class CharacterSummaryDTO
{
    public function __construct(
        public readonly array $character,
        public readonly array $pendingChoices,
        public readonly array $resources,
        public readonly array $combatState,
        public readonly bool $creationComplete,
        public readonly array $missingRequired,
    ) {}

    /**
     * Build summary DTO from a Character model.
     */
    public static function fromCharacter(
        Character $character,
        CharacterProficiencyService $proficiencyService,
        CharacterLanguageService $languageService,
        SpellSlotService $spellSlotService,
        HitDiceService $hitDiceService,
        FeatChoiceService $featChoiceService,
        CounterService $counterService,
        CharacterChoiceService $choiceService
    ): self {
        // Ensure relationships are loaded
        $character->load([
            'characterClasses.characterClass',
            'race',
            'background',
            'conditions.condition',
            'spellSlots',
        ]);

        // Basic character info
        $characterInfo = [
            'id' => $character->id,
            'name' => $character->name,
            'total_level' => $character->total_level,
        ];

        // Calculate pending choices
        $pendingChoices = self::calculatePendingChoices(
            $character,
            $proficiencyService,
            $languageService,
            $featChoiceService,
            $choiceService
        );

        // Get resource states
        $resources = self::getResources(
            $character,
            $spellSlotService,
            $hitDiceService,
            $counterService
        );

        // Get combat state
        $combatState = self::getCombatState($character);

        // Determine creation completeness
        [$creationComplete, $missingRequired] = self::getCreationStatus(
            $character,
            $pendingChoices
        );

        return new self(
            character: $characterInfo,
            pendingChoices: $pendingChoices,
            resources: $resources,
            combatState: $combatState,
            creationComplete: $creationComplete,
            missingRequired: $missingRequired,
        );
    }

    /**
     * Calculate pending choices that require user input.
     */
    private static function calculatePendingChoices(
        Character $character,
        CharacterProficiencyService $proficiencyService,
        CharacterLanguageService $languageService,
        FeatChoiceService $featChoiceService,
        CharacterChoiceService $choiceService
    ): array {
        // Get proficiency choices (legacy - still using dedicated service)
        // These remain separate due to complex validation logic in CharacterProficiencyService
        // TODO: Consider migrating to CharacterChoiceService in future refactor
        $proficiencyChoices = $proficiencyService->getPendingChoices($character);
        $proficienciesRemaining = 0;
        foreach (['class', 'race', 'background', 'subclass_feature'] as $source) {
            if (isset($proficiencyChoices[$source])) {
                foreach ($proficiencyChoices[$source] as $choiceGroup) {
                    $proficienciesRemaining += $choiceGroup['remaining'];
                }
            }
        }

        // Get language choices (legacy - still using dedicated service)
        $languageChoices = $languageService->getPendingChoices($character);
        $languagesRemaining = 0;
        foreach (['race', 'background', 'feat'] as $source) {
            if (isset($languageChoices[$source]['choices'])) {
                $languagesRemaining += $languageChoices[$source]['choices']['remaining'];
            }
        }

        // Get size choices (races with has_size_choice like Custom Lineage)
        $sizeRemaining = 0;
        $race = $character->race;
        if ($race && $race->has_size_choice && $character->size_id === null) {
            $sizeRemaining = 1;
        }

        // Get feat choices (races/backgrounds with bonus_feat modifier)
        $featsRemaining = 0;
        $featChoices = $featChoiceService->getPendingChoices($character);
        foreach ($featChoices as $sourceData) {
            $featsRemaining += $sourceData['remaining'];
        }

        // Get all pending choices from CharacterChoiceService (Issue #490)
        // This provides dynamic counts for fighting_style, expertise, optional_feature, etc.
        $pendingChoicesCollection = $choiceService->getPendingChoices($character)
            ->filter(fn ($c) => $c->remaining > 0);

        // Count by type
        $byType = $pendingChoicesCollection->groupBy('type')->map->count()->toArray();

        // Count optional_features by subtype for fighting_style (Issue #490)
        // After removing FightingStyleChoiceHandler, fighting styles come via
        // OptionalFeatureChoiceHandler with type=optional_feature, subtype=fighting_style
        $fightingStyleCount = $pendingChoicesCollection
            ->filter(fn ($c) => $c->type === 'optional_feature' && $c->subtype === 'fighting_style')
            ->sum(fn ($c) => $c->remaining);

        // Count general optional_features (excluding fighting_style subtype to avoid double-counting)
        // Note: null subtypes (like invocations, metamagic) are included in optional_features
        $optionalFeaturesCount = $pendingChoicesCollection
            ->filter(fn ($c) => $c->type === 'optional_feature' && $c->subtype !== 'fighting_style')
            ->sum(fn ($c) => $c->remaining);

        return [
            'proficiencies' => $proficienciesRemaining,
            'languages' => $languagesRemaining,
            'spells' => $byType['spell'] ?? 0,
            'optional_features' => $optionalFeaturesCount,
            'asi' => $character->asi_choices_remaining ?? 0,
            'size' => $sizeRemaining,
            'feats' => $featsRemaining,
            // New fields from CharacterChoiceService (Issue #490)
            'fighting_style' => $fightingStyleCount,
            'expertise' => $byType['expertise'] ?? 0,
            'equipment' => $byType['equipment'] ?? 0,
            'subclass' => $byType['subclass'] ?? 0,
        ];
    }

    /**
     * Get current resource states.
     */
    private static function getResources(
        Character $character,
        SpellSlotService $spellSlotService,
        HitDiceService $hitDiceService,
        CounterService $counterService
    ): array {
        // Hit points
        $hitPoints = [
            'current' => $character->current_hit_points,
            'max' => $character->max_hit_points,
            'temp' => $character->temp_hit_points ?? 0,
        ];

        // Hit dice (use totals from service)
        $hitDiceData = $hitDiceService->getHitDice($character);
        $hitDice = [
            'available' => $hitDiceData['total']['available'],
            'max' => $hitDiceData['total']['max'],
        ];

        // Spell slots
        $spellSlots = $spellSlotService->getSlots($character);

        // Counters (limited-use resources: Rage, Ki, etc.)
        $counters = $counterService->getCountersForCharacter($character)
            ->values()
            ->all();

        return [
            'hit_points' => $hitPoints,
            'hit_dice' => $hitDice,
            'spell_slots' => $spellSlots,
            'counters' => $counters,
        ];
    }

    /**
     * Get combat state information.
     */
    private static function getCombatState(Character $character): array
    {
        // Active conditions (return slugs)
        $conditions = $character->conditions
            ->map(fn ($cc) => $cc->condition->slug)
            ->values()
            ->all();

        // Death saves
        $deathSaves = [
            'successes' => $character->death_save_successes ?? 0,
            'failures' => $character->death_save_failures ?? 0,
        ];

        // Consciousness (HP > 0)
        $isConscious = ($character->current_hit_points ?? 0) > 0;

        return [
            'conditions' => $conditions,
            'death_saves' => $deathSaves,
            'is_conscious' => $isConscious,
        ];
    }

    /**
     * Determine if character creation is complete and what's missing.
     *
     * @return array{bool, array<string>}
     */
    private static function getCreationStatus(
        Character $character,
        array $pendingChoices
    ): array {
        $missing = [];

        // Check base requirements from Character model
        $validation = $character->validation_status;
        $missing = array_merge($missing, $validation['missing']);

        // Check for pending proficiency choices
        if ($pendingChoices['proficiencies'] > 0) {
            $missing[] = 'proficiency_choices';
        }

        // Check for pending language choices
        if ($pendingChoices['languages'] > 0) {
            $missing[] = 'language_choices';
        }

        // Check for pending spell choices
        if ($pendingChoices['spells'] > 0) {
            $missing[] = 'spell_choices';
        }

        // Check for pending optional feature choices
        if ($pendingChoices['optional_features'] > 0) {
            $missing[] = 'optional_feature_choices';
        }

        // Check for pending size choice (races like Custom Lineage)
        if ($pendingChoices['size'] > 0) {
            $missing[] = 'size_choice';
        }

        // Check for pending feat choices (races like Variant Human)
        if ($pendingChoices['feats'] > 0) {
            $missing[] = 'feat_choices';
        }

        $isComplete = empty($missing);

        return [$isComplete, $missing];
    }
}

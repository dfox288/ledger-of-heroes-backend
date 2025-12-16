<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\AsiChoiceResult;
use App\Exceptions\AbilityScoreCapExceededException;
use App\Exceptions\FeatAlreadyTakenException;
use App\Exceptions\NoAsiChoicesRemainingException;
use App\Exceptions\PrerequisitesNotMetException;
use App\Models\Character;
use App\Models\CharacterFeature;
use App\Models\CharacterProficiency;
use App\Models\CharacterSpell;
use App\Models\Feat;
use App\Models\Modifier;
use Illuminate\Support\Facades\DB;

class AsiChoiceService
{
    private const ABILITY_SCORE_CAP = 20;

    private PrerequisiteCheckerService $prerequisiteChecker;

    private HitPointService $hitPointService;

    private FeatureUseService $featureUseService;

    public function __construct(
        ?PrerequisiteCheckerService $prerequisiteChecker = null,
        ?HitPointService $hitPointService = null,
        ?FeatureUseService $featureUseService = null
    ) {
        $this->prerequisiteChecker = $prerequisiteChecker ?? new PrerequisiteCheckerService;
        $this->hitPointService = $hitPointService ?? app(HitPointService::class);
        $this->featureUseService = $featureUseService ?? app(FeatureUseService::class);
    }

    /**
     * Apply a feat choice using an ASI slot.
     *
     * @throws NoAsiChoicesRemainingException
     * @throws FeatAlreadyTakenException
     * @throws PrerequisitesNotMetException
     * @throws AbilityScoreCapExceededException
     */
    public function applyFeatChoice(Character $character, Feat $feat): AsiChoiceResult
    {
        return DB::transaction(function () use ($character, $feat) {
            $this->validateAsiAvailable($character);
            $this->validateFeatNotTaken($character, $feat);
            $this->validatePrerequisitesMet($character, $feat);

            // Load feat relationships with nested relations to avoid N+1 queries
            $feat->loadMissing([
                'modifiers.abilityScore',
                'proficiencies.proficiencyType',
                'proficiencies.skill',
                'spells',
            ]);

            // Validate ability increases won't exceed cap
            $abilityIncreases = $this->getFeatAbilityIncreases($feat);
            $this->validateAbilityIncreases($character, $abilityIncreases);

            // Apply changes
            $character->asi_choices_remaining--;
            $this->applyAbilityChanges($character, $abilityIncreases);

            $this->createCharacterFeature($character, $feat);
            $proficienciesGained = $this->grantFeatProficiencies($character, $feat);
            $spellsGained = $this->grantFeatSpells($character, $feat);
            $hpBonus = $this->applyRetroactiveHpBonus($character, $feat);

            // Save all character changes once at end of transaction
            $character->save();

            return new AsiChoiceResult(
                choiceType: 'feat',
                asiChoicesRemaining: $character->asi_choices_remaining,
                abilityIncreases: $abilityIncreases,
                newAbilityScores: $this->getAbilityScores($character),
                feat: [
                    'slug' => $feat->slug,
                    'name' => $feat->name,
                ],
                proficienciesGained: $proficienciesGained,
                spellsGained: $spellsGained,
                hpBonus: $hpBonus,
            );
        });
    }

    /**
     * Apply an ability score increase using an ASI slot.
     *
     * @param  array<string, int>  $increases  Map of ability code to increase amount (e.g., ['STR' => 2])
     *
     * @throws NoAsiChoicesRemainingException
     * @throws AbilityScoreCapExceededException
     * @throws \InvalidArgumentException
     */
    public function applyAbilityIncrease(Character $character, array $increases): AsiChoiceResult
    {
        return DB::transaction(function () use ($character, $increases) {
            $this->validateAsiAvailable($character);
            $this->validateIncreaseTotal($increases);
            $this->validateAbilityIncreases($character, $increases);

            // Apply changes
            $character->asi_choices_remaining--;
            $this->applyAbilityChanges($character, $increases);
            $character->save();

            return new AsiChoiceResult(
                choiceType: 'ability_increase',
                asiChoicesRemaining: $character->asi_choices_remaining,
                abilityIncreases: $increases,
                newAbilityScores: $this->getAbilityScores($character),
                feat: null,
                proficienciesGained: [],
                spellsGained: [],
            );
        });
    }

    /**
     * Validate character has ASI choices remaining.
     *
     * @throws NoAsiChoicesRemainingException
     */
    private function validateAsiAvailable(Character $character): void
    {
        if ($character->asi_choices_remaining <= 0) {
            throw new NoAsiChoicesRemainingException($character);
        }
    }

    /**
     * Validate feat hasn't already been taken.
     *
     * @throws FeatAlreadyTakenException
     */
    private function validateFeatNotTaken(Character $character, Feat $feat): void
    {
        $exists = CharacterFeature::where('character_id', $character->id)
            ->where('feature_type', Feat::class)
            ->where('feature_slug', $feat->slug)
            ->exists();

        if ($exists) {
            throw new FeatAlreadyTakenException($character, $feat);
        }
    }

    /**
     * Validate feat prerequisites are met.
     *
     * @throws PrerequisitesNotMetException
     */
    private function validatePrerequisitesMet(Character $character, Feat $feat): void
    {
        $result = $this->prerequisiteChecker->checkFeatPrerequisites($character, $feat);

        if (! $result->met) {
            throw new PrerequisitesNotMetException($character, $feat, $result->unmet);
        }
    }

    /**
     * Validate ability increase total is exactly 2 points.
     *
     * @throws \InvalidArgumentException
     */
    private function validateIncreaseTotal(array $increases): void
    {
        $total = array_sum($increases);
        if ($total !== 2) {
            throw new \InvalidArgumentException("Ability score increases must total exactly 2 points, got {$total}.");
        }

        foreach ($increases as $code => $amount) {
            if ($amount < 1 || $amount > 2) {
                throw new \InvalidArgumentException("Each ability increase must be 1 or 2 points, got {$amount} for {$code}.");
            }
        }
    }

    /**
     * Validate no ability would exceed the cap.
     *
     * @throws AbilityScoreCapExceededException
     */
    private function validateAbilityIncreases(Character $character, array $increases): void
    {
        foreach ($increases as $code => $amount) {
            $current = $character->getAbilityScore($code) ?? 10;
            if ($current + $amount > self::ABILITY_SCORE_CAP) {
                throw new AbilityScoreCapExceededException($character, $code, $current, $amount);
            }
        }
    }

    /**
     * Get ability increases from a feat's modifiers.
     *
     * @return array<string, int>
     */
    private function getFeatAbilityIncreases(Feat $feat): array
    {
        $increases = [];

        foreach ($feat->modifiers as $modifier) {
            if ($modifier->modifier_category === 'ability_score' && $modifier->abilityScore) {
                $code = $modifier->abilityScore->code;
                $value = (int) $modifier->value;
                $increases[$code] = ($increases[$code] ?? 0) + $value;
            }
        }

        return $increases;
    }

    /**
     * Apply ability score changes to character.
     */
    private function applyAbilityChanges(Character $character, array $increases): void
    {
        foreach ($increases as $code => $amount) {
            $column = Character::ABILITY_SCORES[$code] ?? null;
            if ($column) {
                $character->{$column} += $amount;
            }
        }
    }

    /**
     * Create a character feature record for the feat.
     */
    private function createCharacterFeature(Character $character, Feat $feat): void
    {
        $characterFeature = CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_id' => $feat->id,
            'feature_slug' => $feat->slug,
            'source' => 'feat',
            'level_acquired' => $character->total_level ?: 1,
        ]);

        // Initialize max_uses for feats with limited uses (e.g., Lucky)
        $this->featureUseService->initializeUsesForFeature($characterFeature);
    }

    /**
     * Grant proficiencies from a feat.
     *
     * @return array<string>
     */
    private function grantFeatProficiencies(Character $character, Feat $feat): array
    {
        $granted = [];

        foreach ($feat->proficiencies as $proficiency) {
            // Relations already eager-loaded above
            $proficiencyTypeSlug = $proficiency->proficiencyType?->slug;
            $skillSlug = $proficiency->skill?->slug;

            // Skip if neither proficiency type nor skill is set (defensive check)
            if ($proficiencyTypeSlug === null && $skillSlug === null) {
                continue;
            }

            CharacterProficiency::create([
                'character_id' => $character->id,
                'proficiency_type_slug' => $proficiencyTypeSlug,
                'skill_slug' => $skillSlug,
                'source' => 'feat',
            ]);

            $granted[] = $proficiency->proficiency_name;
        }

        return $granted;
    }

    /**
     * Grant spells from a feat.
     *
     * @return array<array{slug: string, name: string}>
     */
    private function grantFeatSpells(Character $character, Feat $feat): array
    {
        $granted = [];

        foreach ($feat->spells as $spell) {
            CharacterSpell::firstOrCreate(
                [
                    'character_id' => $character->id,
                    'spell_slug' => $spell->slug,
                ],
                [
                    'source' => 'feat',
                    'preparation_status' => 'known',
                    'level_acquired' => $character->level,
                ]
            );

            $granted[] = [
                'slug' => $spell->slug,
                'name' => $spell->name,
            ];
        }

        return $granted;
    }

    /**
     * Apply retroactive HP bonus from feat (e.g., Tough).
     *
     * If the feat has a hit_points_per_level modifier, adds (value × total_level)
     * to both max HP and current HP.
     *
     * @return int The HP bonus applied (0 if no HP modifier)
     */
    private function applyRetroactiveHpBonus(Character $character, Feat $feat): int
    {
        // Check for hit_points_per_level modifier on this feat
        $hpModifier = Modifier::where('reference_type', Feat::class)
            ->where('reference_id', $feat->id)
            ->where('modifier_category', 'hit_points_per_level')
            ->first();

        if (! $hpModifier) {
            return 0;
        }

        $hpPerLevel = (int) $hpModifier->value;
        $totalLevel = $character->total_level ?: 1;
        $hpBonus = $hpPerLevel * $totalLevel;

        // Apply retroactive HP bonus (saved by caller)
        $character->max_hit_points = ($character->max_hit_points ?? 0) + $hpBonus;
        $character->current_hit_points = ($character->current_hit_points ?? 0) + $hpBonus;

        return $hpBonus;
    }

    /**
     * Get all ability scores as an associative array.
     *
     * @return array<string, int>
     */
    private function getAbilityScores(Character $character): array
    {
        return [
            'STR' => $character->strength ?? 10,
            'DEX' => $character->dexterity ?? 10,
            'CON' => $character->constitution ?? 10,
            'INT' => $character->intelligence ?? 10,
            'WIS' => $character->wisdom ?? 10,
            'CHA' => $character->charisma ?? 10,
        ];
    }
}

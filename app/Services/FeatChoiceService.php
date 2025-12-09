<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CharacterSource;
use App\Exceptions\FeatAlreadyTakenException;
use App\Exceptions\InvalidSelectionException;
use App\Exceptions\PrerequisitesNotMetException;
use App\Models\Character;
use App\Models\CharacterFeature;
use App\Models\CharacterProficiency;
use App\Models\CharacterSpell;
use App\Models\Feat;
use App\Models\Modifier;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for managing bonus feat choices from races/backgrounds.
 *
 * Detects bonus feats via modifiers with category 'bonus_feat' on races/backgrounds,
 * tracks which feats have been selected, and applies feat benefits when resolved.
 */
class FeatChoiceService
{
    public function __construct(
        private readonly PrerequisiteCheckerService $prerequisiteChecker,
        private readonly HitPointService $hitPointService,
    ) {}

    /**
     * Get pending feat choices for a character.
     *
     * @return array<string, array{quantity: int, remaining: int, selected: array}>
     */
    public function getPendingChoices(Character $character): array
    {
        $choices = [];

        // Check race for bonus feats
        if ($character->race) {
            $raceChoice = $this->getChoicesFromEntity($character->race, $character, 'race');
            if ($raceChoice['quantity'] > 0) {
                $choices['race'] = $raceChoice;
            }
        }

        // Check background for bonus feats
        if ($character->background) {
            $bgChoice = $this->getChoicesFromEntity($character->background, $character, 'background');
            if ($bgChoice['quantity'] > 0) {
                $choices['background'] = $bgChoice;
            }
        }

        return $choices;
    }

    /**
     * Get feat choices from an entity (race or background).
     *
     * @return array{quantity: int, remaining: int, selected: array<string>}
     */
    private function getChoicesFromEntity(mixed $entity, Character $character, string $source): array
    {
        // Check if entity has modifiers relationship (Background doesn't have HasModifiers trait)
        if (! method_exists($entity, 'modifiers')) {
            return ['quantity' => 0, 'remaining' => 0, 'selected' => []];
        }

        // Look for bonus_feat modifier
        $bonusFeatModifier = $entity->modifiers()
            ->where('modifier_category', 'bonus_feat')
            ->first();

        if (! $bonusFeatModifier) {
            return ['quantity' => 0, 'remaining' => 0, 'selected' => []];
        }

        $quantity = (int) $bonusFeatModifier->value;

        // Get feats already selected from this source
        $selectedFeats = CharacterFeature::where('character_id', $character->id)
            ->where('feature_type', Feat::class)
            ->where('source', $source)
            ->pluck('feature_slug')
            ->toArray();

        $remaining = max(0, $quantity - count($selectedFeats));

        return [
            'quantity' => $quantity,
            'remaining' => $remaining,
            'selected' => $selectedFeats,
        ];
    }

    /**
     * Make a feat choice for a character.
     *
     * @param  string  $featSlug  The feat full_slug or slug to select
     *
     * @throws InvalidArgumentException
     * @throws FeatAlreadyTakenException
     * @throws PrerequisitesNotMetException
     */
    public function makeChoice(Character $character, string $source, string $featSlug): array
    {
        // Validate source
        $sourceEnum = CharacterSource::tryFrom($source);
        if (! $sourceEnum || ! in_array($sourceEnum, [CharacterSource::RACE, CharacterSource::BACKGROUND])) {
            throw new InvalidArgumentException("Invalid source for feat choice: {$source}");
        }

        // Get the source entity
        $entity = match ($sourceEnum) {
            CharacterSource::RACE => $character->race,
            CharacterSource::BACKGROUND => $character->background,
            default => null,
        };

        if (! $entity) {
            throw new InvalidArgumentException("Character has no {$source} assigned");
        }

        // Check if user is replacing an existing selection
        $choiceData = $this->getChoicesFromEntity($entity, $character, $source);
        $isReplacement = ! empty($choiceData['selected']);

        // Verify there are remaining choices (unless this is a replacement)
        if (! $isReplacement && $choiceData['remaining'] <= 0) {
            throw new InvalidArgumentException("No remaining feat choices from {$source}");
        }

        // Find the feat
        $feat = Feat::where('full_slug', $featSlug)
            ->orWhere('slug', $featSlug)
            ->first();

        if (! $feat) {
            throw new InvalidSelectionException("feat:{$source}", $featSlug, "Feat not found: {$featSlug}");
        }

        return DB::transaction(function () use ($character, $feat, $featSlug, $source, $isReplacement, $choiceData) {
            // If replacing an existing selection, undo the old one first
            if ($isReplacement) {
                $oldFeatSlug = $choiceData['selected'][0];

                // If trying to select the same feat, throw an error
                if ($oldFeatSlug === $featSlug || $oldFeatSlug === $feat->full_slug) {
                    throw new FeatAlreadyTakenException($character, $feat);
                }

                $this->undoChoice($character, $source, $oldFeatSlug);
            }

            // Validate feat not already taken (from any source)
            $this->validateFeatNotTaken($character, $feat);

            // Validate prerequisites
            $this->validatePrerequisitesMet($character, $feat);

            // Load feat relationships
            $feat->loadMissing([
                'modifiers.abilityScore',
                'proficiencies.proficiencyType',
                'proficiencies.skill',
                'spells',
            ]);

            // Apply the feat
            $this->createCharacterFeature($character, $feat, $source);
            $proficienciesGained = $this->grantFeatProficiencies($character, $feat, $source);
            $spellsGained = $this->grantFeatSpells($character, $feat, $source);
            $hpBonus = $this->applyHpBonus($character, $feat);
            $abilityIncreases = $this->applyAbilityIncreases($character, $feat);

            $character->save();

            return [
                'feat' => [
                    'full_slug' => $feat->full_slug,
                    'slug' => $feat->slug,
                    'name' => $feat->name,
                ],
                'source' => $source,
                'proficiencies_gained' => $proficienciesGained,
                'spells_gained' => $spellsGained,
                'hp_bonus' => $hpBonus,
                'ability_increases' => $abilityIncreases,
            ];
        });
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
            ->where('feature_slug', $feat->full_slug)
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
     * Create a character feature record for the feat.
     */
    private function createCharacterFeature(Character $character, Feat $feat, string $source): void
    {
        CharacterFeature::create([
            'character_id' => $character->id,
            'feature_type' => Feat::class,
            'feature_slug' => $feat->full_slug,
            'source' => $source,
            // Bonus feats from race/background are always acquired at character creation (level 1)
            'level_acquired' => 1,
        ]);
    }

    /**
     * Grant proficiencies from a feat.
     *
     * @return array<string>
     */
    private function grantFeatProficiencies(Character $character, Feat $feat, string $source): array
    {
        $granted = [];

        foreach ($feat->proficiencies as $proficiency) {
            $proficiencyTypeSlug = $proficiency->proficiencyType?->full_slug;
            $skillSlug = $proficiency->skill?->full_slug;

            if ($proficiencyTypeSlug === null && $skillSlug === null) {
                continue;
            }

            CharacterProficiency::create([
                'character_id' => $character->id,
                'proficiency_type_slug' => $proficiencyTypeSlug,
                'skill_slug' => $skillSlug,
                'source' => $source,
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
    private function grantFeatSpells(Character $character, Feat $feat, string $source): array
    {
        $granted = [];

        foreach ($feat->spells as $spell) {
            CharacterSpell::firstOrCreate(
                [
                    'character_id' => $character->id,
                    'spell_slug' => $spell->full_slug,
                ],
                [
                    'source' => $source,
                    'preparation_status' => 'known',
                    'level_acquired' => $character->total_level ?: 1,
                ]
            );

            $granted[] = [
                'slug' => $spell->full_slug,
                'name' => $spell->name,
            ];
        }

        return $granted;
    }

    /**
     * Apply HP bonus from feat (e.g., Tough).
     */
    private function applyHpBonus(Character $character, Feat $feat): int
    {
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

        $character->max_hit_points = ($character->max_hit_points ?? 0) + $hpBonus;
        $character->current_hit_points = ($character->current_hit_points ?? 0) + $hpBonus;

        return $hpBonus;
    }

    /**
     * Apply ability score increases from feat.
     *
     * @return array<string, int>
     */
    private function applyAbilityIncreases(Character $character, Feat $feat): array
    {
        $increases = [];

        foreach ($feat->modifiers as $modifier) {
            if ($modifier->modifier_category === 'ability_score' && $modifier->abilityScore) {
                $code = $modifier->abilityScore->code;
                $value = (int) $modifier->value;
                $column = Character::ABILITY_SCORES[$code] ?? null;

                if ($column) {
                    $character->{$column} = min(20, ($character->{$column} ?? 10) + $value);
                    $increases[$code] = ($increases[$code] ?? 0) + $value;
                }
            }
        }

        return $increases;
    }

    /**
     * Undo a feat choice.
     *
     * Removes the feat and all granted resources (proficiencies, spells, ability scores).
     * Note: HP bonuses from feats like Tough are NOT automatically reversed here.
     * Those require recalculation via the HitPointService.
     */
    public function undoChoice(Character $character, string $source, string $featSlug): void
    {
        $feat = Feat::where('full_slug', $featSlug)->first();

        DB::transaction(function () use ($character, $source, $featSlug, $feat) {
            // Remove the character feature
            CharacterFeature::where('character_id', $character->id)
                ->where('feature_type', Feat::class)
                ->where('feature_slug', $featSlug)
                ->where('source', $source)
                ->delete();

            // Remove proficiencies granted by this source
            CharacterProficiency::where('character_id', $character->id)
                ->where('source', $source)
                ->delete();

            // Remove spells granted by this source
            CharacterSpell::where('character_id', $character->id)
                ->where('source', $source)
                ->delete();

            // Reverse ability score increases if feat exists
            if ($feat) {
                $this->reverseAbilityIncreases($character, $feat);
                $character->save();
            }
        });
    }

    /**
     * Reverse ability score increases from a feat.
     */
    private function reverseAbilityIncreases(Character $character, Feat $feat): void
    {
        foreach ($feat->modifiers as $modifier) {
            if ($modifier->modifier_category === 'ability_score' && $modifier->abilityScore) {
                $code = $modifier->abilityScore->code;
                $value = (int) $modifier->value;
                $column = Character::ABILITY_SCORES[$code] ?? null;

                if ($column) {
                    $character->{$column} = max(1, ($character->{$column} ?? 10) - $value);
                }
            }
        }
    }
}

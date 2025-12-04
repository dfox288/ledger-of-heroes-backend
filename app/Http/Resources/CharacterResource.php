<?php

namespace App\Http\Resources;

use App\Services\CharacterStatCalculator;
use App\Services\MulticlassSpellSlotCalculator;
use App\Services\ProficiencyCheckerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterResource extends JsonResource
{
    private CharacterStatCalculator $calculator;

    private ProficiencyCheckerService $proficiencyChecker;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->calculator = new CharacterStatCalculator;
        $this->proficiencyChecker = app(ProficiencyCheckerService::class);
    }

    public function toArray(Request $request): array
    {
        $abilityScores = $this->getAbilityScoresArray();
        $modifiers = $this->calculateModifiers($abilityScores);
        $level = $this->total_level;
        $proficiencyBonus = $this->calculator->proficiencyBonus($level);
        $primaryClass = $this->primary_class;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'level' => $level,
            'total_level' => $this->total_level,
            'is_multiclass' => $this->is_multiclass,
            'experience_points' => $this->experience_points ?? 0,

            // Completion status
            'is_complete' => $this->is_complete,
            'validation_status' => $this->validation_status,

            // Ability score method
            'ability_score_method' => $this->ability_score_method?->value,

            // Ability scores (as STR/DEX/CON/INT/WIS/CHA keyed array)
            'ability_scores' => $abilityScores,
            'modifiers' => $modifiers,

            // Calculated stats
            'proficiency_bonus' => $proficiencyBonus,

            // Combat stats (nullable until calculated)
            'max_hit_points' => $this->max_hit_points,
            'current_hit_points' => $this->current_hit_points,
            'temp_hit_points' => $this->temp_hit_points,
            'death_save_successes' => $this->death_save_successes,
            'death_save_failures' => $this->death_save_failures,
            'armor_class' => $this->armor_class,

            // Level-up tracking
            'asi_choices_remaining' => $this->asi_choices_remaining ?? 0,

            // Character attributes
            'alignment' => $this->alignment,
            'has_inspiration' => $this->has_inspiration ?? false,

            // Race-derived attributes
            'speed' => $this->speed,
            'speeds' => $this->speeds,
            'size' => $this->size,

            // Equipped items summary
            'equipped' => $this->getEquippedSummary(),

            // Proficiency penalties from equipped items
            'proficiency_penalties' => $this->getProficiencyPenalties(),

            // Relationships (conditionally loaded)
            'race' => $this->when($this->relationLoaded('race') || $this->race_id, function () {
                return $this->race ? [
                    'id' => $this->race->id,
                    'name' => $this->race->name,
                    'slug' => $this->race->slug,
                ] : null;
            }),
            // Primary class (for backwards compatibility - deprecated, use 'classes' array instead)
            // TODO: Remove in API v2.0 - clients should migrate to 'classes' array
            'class' => $this->when($this->relationLoaded('characterClasses') || $primaryClass, function () use ($primaryClass) {
                return $primaryClass ? [
                    'id' => $primaryClass->id,
                    'name' => $primaryClass->name,
                    'slug' => $primaryClass->slug,
                ] : null;
            }),
            'background' => $this->when($this->relationLoaded('background') || $this->background_id, function () {
                return $this->background ? [
                    'id' => $this->background->id,
                    'name' => $this->background->name,
                    'slug' => $this->background->slug,
                ] : null;
            }),

            // Multiclass support
            'classes' => CharacterClassPivotResource::collection(
                $this->whenLoaded('characterClasses', $this->characterClasses, collect())
            ),
            'spell_slots' => $this->getSpellSlots(),

            // Portrait
            'portrait' => $this->getPortraitData(),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get portrait data from uploaded media or external URL.
     */
    private function getPortraitData(): ?array
    {
        // Check for uploaded media first
        if ($this->relationLoaded('media')) {
            $media = $this->getFirstMedia('portrait');
            if ($media) {
                return [
                    'original' => $media->getUrl(),
                    'thumb' => $media->hasGeneratedConversion('thumb')
                        ? $media->getUrl('thumb')
                        : null,
                    'medium' => $media->hasGeneratedConversion('medium')
                        ? $media->getUrl('medium')
                        : null,
                    'is_uploaded' => true,
                ];
            }
        }

        // Fall back to external URL
        if ($this->portrait_url) {
            return [
                'original' => $this->portrait_url,
                'thumb' => null,
                'medium' => null,
                'is_uploaded' => false,
            ];
        }

        return null;
    }

    /**
     * Get ability scores as keyed array.
     */
    private function getAbilityScoresArray(): array
    {
        return [
            'STR' => $this->strength,
            'DEX' => $this->dexterity,
            'CON' => $this->constitution,
            'INT' => $this->intelligence,
            'WIS' => $this->wisdom,
            'CHA' => $this->charisma,
        ];
    }

    /**
     * Calculate modifiers for all ability scores.
     */
    private function calculateModifiers(array $scores): array
    {
        $modifiers = [];

        foreach ($scores as $code => $score) {
            $modifiers[$code] = $score !== null
                ? $this->calculator->abilityModifier($score)
                : null;
        }

        return $modifiers;
    }

    /**
     * Get summary of equipped items.
     */
    private function getEquippedSummary(): array
    {
        $summary = [];

        $armor = $this->resource->equippedArmor();
        if ($armor) {
            $summary['armor'] = [
                'id' => $armor->id,
                'name' => $armor->item->name,
                'armor_class' => $armor->item->armor_class,
            ];
        }

        $shield = $this->resource->equippedShield();
        if ($shield) {
            $summary['shield'] = [
                'id' => $shield->id,
                'name' => $shield->item->name,
                'armor_class' => $shield->item->armor_class,
            ];
        }

        return $summary;
    }

    /**
     * Get proficiency penalties from equipped items.
     *
     * @return array<string, mixed>
     */
    private function getProficiencyPenalties(): array
    {
        $penalties = [
            'has_armor_penalty' => false,
            'has_weapon_penalty' => false,
            'penalties' => [],
        ];

        // Check equipped armor
        $armor = $this->resource->equippedArmor();
        if ($armor) {
            $status = $this->proficiencyChecker->checkEquipmentProficiency(
                $this->resource,
                $armor->item
            );
            if (! $status->hasProficiency) {
                $penalties['has_armor_penalty'] = true;
                $penalties['penalties'] = array_merge(
                    $penalties['penalties'],
                    $status->penalties
                );
            }
        }

        // Check equipped shield
        $shield = $this->resource->equippedShield();
        if ($shield) {
            $status = $this->proficiencyChecker->checkEquipmentProficiency(
                $this->resource,
                $shield->item
            );
            if (! $status->hasProficiency) {
                $penalties['has_armor_penalty'] = true;
                $penalties['penalties'] = array_merge(
                    $penalties['penalties'],
                    $status->penalties
                );
            }
        }

        // Check equipped weapons
        $weapons = $this->resource->equippedWeapons();
        foreach ($weapons as $weapon) {
            $status = $this->proficiencyChecker->checkEquipmentProficiency(
                $this->resource,
                $weapon->item
            );
            if (! $status->hasProficiency) {
                $penalties['has_weapon_penalty'] = true;
                $penalties['penalties'] = array_merge(
                    $penalties['penalties'],
                    $status->penalties
                );
            }
        }

        // Remove duplicate penalties
        $penalties['penalties'] = array_values(array_unique($penalties['penalties']));

        return $penalties;
    }

    /**
     * Get spell slots for multiclass character.
     */
    private function getSpellSlots(): ?array
    {
        if (! $this->relationLoaded('characterClasses') || $this->characterClasses->isEmpty()) {
            return null;
        }

        $calculator = app(MulticlassSpellSlotCalculator::class);
        $result = $calculator->calculate($this->resource);

        if ($result->standardSlots === null && $result->pactSlots === null) {
            return null;
        }

        return [
            'standard' => $result->standardSlots,
            'pact' => $result->pactSlots ? [
                'count' => $result->pactSlots->count,
                'level' => $result->pactSlots->level,
            ] : null,
        ];
    }
}

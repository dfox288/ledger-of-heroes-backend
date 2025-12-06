<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsRelatedModels;
use App\Models\Character;
use App\Services\CharacterStatCalculator;
use App\Services\MulticlassSpellSlotCalculator;
use App\Services\ProficiencyCheckerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Character
 */
class CharacterResource extends JsonResource
{
    use FormatsRelatedModels;

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
        return [
            ...$this->getBaseInfo(),
            ...$this->getAbilityScoresData(),
            ...$this->getCombatStats(),
            ...$this->getCharacterAttributes(),
            ...$this->getRaceAttributes(),
            ...$this->getEquipmentData(),
            ...$this->getRelationships(),
            ...$this->getMulticlassData(),
            ...$this->getFeaturesAndConditions(),
            ...$this->getMediaData(),
            ...$this->getTimestamps(),
        ];
    }

    /**
     * Get base character information.
     */
    private function getBaseInfo(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            /** @var int Character level (total across all classes) */
            'level' => $this->total_level,
            /** @var int Total character level */
            'total_level' => $this->total_level,
            /** @var bool Whether character has multiple classes */
            'is_multiclass' => $this->is_multiclass,
            'experience_points' => $this->experience_points ?? 0,
            /** @var bool Whether character has all required fields */
            'is_complete' => $this->is_complete,
            /** @var array{is_complete: bool, missing: array<string>} Validation status with missing fields */
            'validation_status' => $this->validation_status,
        ];
    }

    /**
     * Get ability scores and modifiers.
     */
    private function getAbilityScoresData(): array
    {
        $abilityScores = $this->getAbilityScoresArray();
        $modifiers = $this->calculateModifiers($abilityScores);
        $proficiencyBonus = $this->calculator->proficiencyBonus($this->total_level);

        return [
            'ability_score_method' => $this->ability_score_method?->value,
            /** @var array{STR: int|null, DEX: int|null, CON: int|null, INT: int|null, WIS: int|null, CHA: int|null} */
            'ability_scores' => $abilityScores,
            /** @var array{STR: int|null, DEX: int|null, CON: int|null, INT: int|null, WIS: int|null, CHA: int|null} */
            'modifiers' => $modifiers,
            'proficiency_bonus' => $proficiencyBonus,
        ];
    }

    /**
     * Get combat statistics.
     */
    private function getCombatStats(): array
    {
        return [
            'max_hit_points' => $this->max_hit_points,
            'current_hit_points' => $this->current_hit_points,
            'temp_hit_points' => $this->temp_hit_points,
            'death_save_successes' => $this->death_save_successes,
            'death_save_failures' => $this->death_save_failures,
            /** @var int|null Calculated armor class */
            'armor_class' => $this->armor_class,
        ];
    }

    /**
     * Get character attributes.
     */
    private function getCharacterAttributes(): array
    {
        return [
            'asi_choices_remaining' => $this->asi_choices_remaining ?? 0,
            'alignment' => $this->alignment,
            'has_inspiration' => $this->has_inspiration ?? false,
        ];
    }

    /**
     * Get race-derived attributes.
     */
    private function getRaceAttributes(): array
    {
        return [
            /** @var int|null Walking speed from race */
            'speed' => $this->speed,
            /** @var array{walk: int|null, fly: int|null, swim: int|null, climb: int|null}|null All movement speeds */
            'speeds' => $this->speeds,
            /** @var string|null Character size (e.g., "Medium", "Small") */
            'size' => $this->size,
        ];
    }

    /**
     * Get equipment data.
     */
    private function getEquipmentData(): array
    {
        return [
            'equipped' => $this->getEquippedSummary(),
            'proficiency_penalties' => $this->getProficiencyPenalties(),
        ];
    }

    /**
     * Get character relationships.
     */
    private function getRelationships(): array
    {
        return [
            'race' => $this->when(
                $this->relationLoaded('race') || $this->race_id,
                fn () => $this->formatEntity($this->race)
            ),
            'background' => $this->when(
                $this->relationLoaded('background') || $this->background_id,
                fn () => $this->formatEntity($this->background)
            ),
            /** @var array{id: int, name: string, slug: string, equipment: array}|null Primary class with equipment */
            'class' => $this->getPrimaryClassData(),
        ];
    }

    /**
     * Get primary class data with equipment for wizard flow.
     */
    private function getPrimaryClassData(): ?array
    {
        $primaryClass = $this->primary_class;

        if (! $primaryClass) {
            return null;
        }

        return [
            'id' => $primaryClass->id,
            'name' => $primaryClass->name,
            'slug' => $primaryClass->slug,
            'equipment' => EntityItemResource::collection(
                $primaryClass->relationLoaded('equipment') ? $primaryClass->equipment : collect()
            ),
        ];
    }

    /**
     * Get multiclass data.
     */
    private function getMulticlassData(): array
    {
        return [
            'classes' => CharacterClassPivotResource::collection(
                $this->whenLoaded('characterClasses', $this->characterClasses, collect())
            ),
            /** @var array{standard: array<string, int>|null, pact: array{count: int, level: int}|null}|null Spell slots */
            'spell_slots' => $this->getSpellSlots(),
        ];
    }

    /**
     * Get features and conditions.
     */
    private function getFeaturesAndConditions(): array
    {
        return [
            'conditions' => $this->whenLoaded('conditions', function () {
                return $this->conditions->map(fn ($cc) => [
                    'id' => $cc->condition->id,
                    'name' => $cc->condition->name,
                    'slug' => $cc->condition->slug,
                    'level' => $cc->level,
                    'source' => $cc->source,
                    'duration' => $cc->duration,
                ]);
            }),
            'optional_features' => $this->whenLoaded('optionalFeatures', function () {
                return $this->optionalFeatures->map(fn ($cof) => [
                    'id' => $cof->optionalFeature->id,
                    'name' => $cof->optionalFeature->name,
                    'slug' => $cof->optionalFeature->slug,
                    'feature_type' => $cof->optionalFeature->feature_type?->value,
                    'level_acquired' => $cof->level_acquired,
                    'class_name' => $cof->characterClass?->name,
                    'subclass_name' => $cof->subclass_name,
                ]);
            }),
        ];
    }

    /**
     * Get media data.
     */
    private function getMediaData(): array
    {
        return [
            'portrait' => $this->getPortraitData(),
        ];
    }

    /**
     * Get timestamps.
     */
    private function getTimestamps(): array
    {
        return [
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

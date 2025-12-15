<?php

namespace App\Http\Resources;

use App\Enums\ItemTypeCode;
use App\Enums\ResetTiming;
use App\Models\Character;
use App\Models\ClassFeature;
use App\Services\CharacterStatCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Resource for party stats endpoint - provides DM dashboard data.
 *
 * @mixin Character
 */
class PartyCharacterStatsResource extends JsonResource
{
    private CharacterStatCalculator $calculator;

    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->calculator = new CharacterStatCalculator;
    }

    public function toArray(Request $request): array
    {
        $finalScores = $this->resource->getFinalAbilityScoresArray();
        $modifiers = $this->calculateModifiers($finalScores);
        $proficiencyBonus = $this->calculator->proficiencyBonus($this->total_level ?: 1);

        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'name' => $this->name,
            'level' => $this->total_level,
            'class_name' => $this->getPrimaryClassName(),
            'hit_points' => [
                'current' => $this->current_hit_points,
                'max' => $this->max_hit_points,
                'temp' => $this->temp_hit_points ?? 0,
            ],
            'armor_class' => $this->armor_class,
            'proficiency_bonus' => $proficiencyBonus,
            // Phase 1: Combat Quick Reference
            'combat' => [
                'initiative_modifier' => $modifiers['DEX'] ?? 0,
                'speeds' => $this->formatSpeeds(),
                'death_saves' => [
                    'successes' => $this->death_save_successes ?? 0,
                    'failures' => $this->death_save_failures ?? 0,
                ],
                'concentration' => [
                    'active' => false, // Placeholder for future implementation
                    'spell' => null,
                ],
            ],
            // Phase 2: Senses (includes passive skills + darkvision)
            'senses' => $this->formatSenses($modifiers, $proficiencyBonus),
            // Phase 2: Capabilities
            'capabilities' => [
                'languages' => $this->formatLanguages(),
                'size' => $this->size,
                'tool_proficiencies' => $this->formatToolProficiencies(),
            ],
            // Phase 4: Equipment
            'equipment' => $this->formatEquipment(),
            'saving_throws' => $this->calculateSavingThrows($modifiers, $proficiencyBonus),
            'conditions' => $this->formatConditions(),
            'spell_slots' => $this->formatSpellSlots(),
            'counters' => $this->formatCounters(),
        ];
    }

    /**
     * Get the primary class name.
     */
    private function getPrimaryClassName(): ?string
    {
        $primaryPivot = $this->characterClasses->firstWhere('is_primary', true);

        return $primaryPivot?->characterClass?->name;
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
                : 0;
        }

        return $modifiers;
    }

    /**
     * Format movement speeds from race.
     */
    private function formatSpeeds(): array
    {
        if (! $this->relationLoaded('race') || $this->race === null) {
            return [
                'walk' => null,
                'fly' => null,
                'swim' => null,
                'climb' => null,
            ];
        }

        return [
            'walk' => $this->race->speed,
            'fly' => $this->race->fly_speed,
            'swim' => $this->race->swim_speed,
            'climb' => $this->race->climb_speed,
        ];
    }

    /**
     * Format senses including passive skills and darkvision.
     */
    private function formatSenses(array $modifiers, int $proficiencyBonus): array
    {
        return [
            'passive_perception' => $this->calculatePassiveSkill('perception', 'WIS', $modifiers, $proficiencyBonus),
            'passive_investigation' => $this->calculatePassiveSkill('investigation', 'INT', $modifiers, $proficiencyBonus),
            'passive_insight' => $this->calculatePassiveSkill('insight', 'WIS', $modifiers, $proficiencyBonus),
            'darkvision' => $this->getDarkvisionRange(),
        ];
    }

    /**
     * Calculate a single passive skill score.
     */
    private function calculatePassiveSkill(string $skillName, string $abilityCode, array $modifiers, int $proficiencyBonus): int
    {
        $abilityModifier = $modifiers[$abilityCode] ?? 0;
        $proficient = $this->hasProficiencyInSkill($skillName);
        $expertise = $this->hasExpertiseInSkill($skillName);

        return $this->calculator->calculatePassiveSkill(
            $abilityModifier,
            $proficient,
            $expertise,
            $proficiencyBonus
        );
    }

    /**
     * Get darkvision range from race senses.
     */
    private function getDarkvisionRange(): ?int
    {
        if (! $this->relationLoaded('race') || $this->race === null) {
            return null;
        }

        if (! $this->race->relationLoaded('senses')) {
            return null;
        }

        $darkvision = $this->race->senses->first(function ($entitySense) {
            return $entitySense->sense?->slug === 'core:darkvision';
        });

        return $darkvision?->range_feet;
    }

    /**
     * Format character languages as array of names.
     */
    private function formatLanguages(): array
    {
        if (! $this->relationLoaded('languages')) {
            return [];
        }

        return $this->languages
            ->map(fn ($cl) => $cl->language?->name)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Format tool proficiencies as array of names.
     */
    private function formatToolProficiencies(): array
    {
        if (! $this->relationLoaded('proficiencies')) {
            return [];
        }

        return $this->proficiencies
            ->filter(function ($prof) {
                return $prof->proficiency_type_slug !== null
                    && $prof->proficiencyType?->category === 'tool';
            })
            ->map(fn ($prof) => $prof->proficiencyType?->name)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Format equipped gear (armor, weapons, shield).
     */
    private function formatEquipment(): array
    {
        return [
            'armor' => $this->formatEquippedArmor(),
            'weapons' => $this->formatEquippedWeapons(),
            'shield' => $this->hasEquippedShield(),
        ];
    }

    /**
     * Format equipped armor details.
     */
    private function formatEquippedArmor(): ?array
    {
        if (! $this->relationLoaded('equipment')) {
            return null;
        }

        $armorCodes = ItemTypeCode::armorCodes();

        $equippedArmor = $this->equipment
            ->where('equipped', true)
            ->first(function ($eq) use ($armorCodes) {
                return $eq->item && in_array($eq->item->itemType?->code, $armorCodes, true);
            });

        if (! $equippedArmor || ! $equippedArmor->item) {
            return null;
        }

        $item = $equippedArmor->item;
        $typeCode = $item->itemType?->code;

        return [
            'name' => $item->name,
            'type' => $this->getArmorTypeLabel($typeCode),
            'stealth_disadvantage' => $this->hasStealthDisadvantage($typeCode),
        ];
    }

    /**
     * Get armor type label from code.
     */
    private function getArmorTypeLabel(?string $code): string
    {
        return match ($code) {
            ItemTypeCode::LIGHT_ARMOR->value => 'light',
            ItemTypeCode::MEDIUM_ARMOR->value => 'medium',
            ItemTypeCode::HEAVY_ARMOR->value => 'heavy',
            default => 'unknown',
        };
    }

    /**
     * Check if armor type causes stealth disadvantage.
     *
     * Note: Simplified implementation. In D&D 5e, heavy armor always causes
     * stealth disadvantage, while only *some* medium armors do (e.g., half plate).
     * This method only checks for heavy armor. For precise per-item stealth
     * disadvantage, the item's stealth_disadvantage field should be checked.
     */
    private function hasStealthDisadvantage(?string $code): bool
    {
        return $code === ItemTypeCode::HEAVY_ARMOR->value;
    }

    /**
     * Format equipped weapons details.
     */
    private function formatEquippedWeapons(): array
    {
        if (! $this->relationLoaded('equipment')) {
            return [];
        }

        $weaponCodes = ItemTypeCode::weaponCodes();

        return $this->equipment
            ->where('equipped', true)
            ->filter(function ($eq) use ($weaponCodes) {
                return $eq->item && in_array($eq->item->itemType?->code, $weaponCodes, true);
            })
            ->map(function ($eq) {
                $item = $eq->item;

                // Build damage string from damage_dice and damage_type relationship
                $damage = null;
                if ($item->damage_dice) {
                    $damageType = $item->damageType?->name ?? '';
                    $damage = trim("{$item->damage_dice} {$damageType}");
                }

                // Build range string from range_normal/range_long
                $range = null;
                if ($item->range_normal) {
                    $range = $item->range_long
                        ? "{$item->range_normal}/{$item->range_long}"
                        : (string) $item->range_normal;
                }

                return [
                    'name' => $item->name,
                    'damage' => $damage ?: null,
                    'range' => $range,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Check if character has an equipped shield.
     */
    private function hasEquippedShield(): bool
    {
        if (! $this->relationLoaded('equipment')) {
            return false;
        }

        return $this->equipment
            ->where('equipped', true)
            ->contains(function ($eq) {
                return $eq->item?->itemType?->code === ItemTypeCode::SHIELD->value;
            });
    }

    /**
     * Check if character has proficiency in a skill.
     */
    private function hasProficiencyInSkill(string $skillName): bool
    {
        if (! $this->relationLoaded('proficiencies')) {
            return false;
        }

        return $this->proficiencies
            ->where('skill_slug', $skillName)
            ->isNotEmpty();
    }

    /**
     * Check if character has expertise in a skill.
     */
    private function hasExpertiseInSkill(string $skillName): bool
    {
        if (! $this->relationLoaded('proficiencies')) {
            return false;
        }

        return $this->proficiencies
            ->where('skill_slug', $skillName)
            ->where('expertise', true)
            ->isNotEmpty();
    }

    /**
     * Calculate saving throw modifiers for all abilities.
     *
     * Note: For now, returns base ability modifiers only.
     * Proficiency in saves would require checking class save proficiencies.
     */
    private function calculateSavingThrows(array $modifiers, int $proficiencyBonus): array
    {
        // For now, just return ability modifiers
        // Future: Check class save proficiencies and add proficiency bonus
        return [
            'STR' => $modifiers['STR'] ?? 0,
            'DEX' => $modifiers['DEX'] ?? 0,
            'CON' => $modifiers['CON'] ?? 0,
            'INT' => $modifiers['INT'] ?? 0,
            'WIS' => $modifiers['WIS'] ?? 0,
            'CHA' => $modifiers['CHA'] ?? 0,
        ];
    }

    /**
     * Format active conditions.
     */
    private function formatConditions(): array
    {
        if (! $this->relationLoaded('conditions')) {
            return [];
        }

        return $this->conditions->map(fn ($cc) => [
            'name' => $cc->condition?->name,
            'slug' => $cc->condition_slug,
            'level' => $cc->level,
        ])->values()->all();
    }

    /**
     * Format spell slots (current/max for each level).
     */
    private function formatSpellSlots(): ?array
    {
        if (! $this->relationLoaded('spellSlots')) {
            return null;
        }

        if ($this->spellSlots->isEmpty()) {
            return null;
        }

        $slots = [];
        foreach ($this->spellSlots as $slot) {
            $slots[$slot->level] = [
                'current' => $slot->current,
                'max' => $slot->max,
            ];
        }

        return $slots;
    }

    /**
     * Format class counters (Rage, Ki Points, Action Surge, etc.).
     *
     * Uses pre-loaded features to avoid N+1 queries in party context.
     */
    private function formatCounters(): array
    {
        if (! $this->relationLoaded('features')) {
            return [];
        }

        return $this->features
            ->filter(fn ($cf) => $cf->max_uses !== null)
            ->map(function ($characterFeature) {
                $feature = $characterFeature->feature;

                // Only ClassFeatures have counters (for now)
                if (! $feature instanceof ClassFeature) {
                    return null;
                }

                $resetOn = $this->mapResetTiming($feature->resets_on);
                $className = $feature->characterClass?->name ?? 'Unknown';
                $classSlug = $feature->characterClass?->slug ?? 'unknown';
                $counterNameSlug = Str::slug($feature->feature_name);

                return [
                    'id' => $characterFeature->id,
                    'slug' => "{$classSlug}:{$counterNameSlug}",
                    'name' => $feature->feature_name,
                    'current' => $characterFeature->uses_remaining,
                    'max' => $characterFeature->max_uses,
                    'reset_on' => $resetOn,
                    'source' => $className,
                    'source_type' => $characterFeature->source,
                    'unlimited' => $characterFeature->max_uses === -1,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Map ResetTiming enum to API string format.
     */
    private function mapResetTiming(?ResetTiming $timing): ?string
    {
        if ($timing === null) {
            return null;
        }

        return match ($timing) {
            ResetTiming::SHORT_REST => 'short_rest',
            ResetTiming::LONG_REST => 'long_rest',
            ResetTiming::DAWN => 'dawn',
            default => $timing->value,
        };
    }
}

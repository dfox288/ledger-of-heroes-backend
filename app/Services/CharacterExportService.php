<?php

namespace App\Services;

use App\Models\Character;

/**
 * Service for exporting characters as portable JSON.
 *
 * Produces a format that can be imported by any instance of the application,
 * using slugs instead of database IDs for portability.
 */
class CharacterExportService
{
    private const FORMAT_VERSION = '1.2';

    /**
     * Export a character as portable JSON.
     */
    public function export(Character $character): array
    {
        // Eager load relationships needed for export
        // Note: We use pivot slug columns directly, not the related models
        $character->loadMissing([
            'characterClasses',
            'spells',
            'equipment',
            'languages',
            'proficiencies',
            'conditions',
            'featureSelections',
            'notes',
            'abilityScores',
            'spellSlots',
            'features.feature.characterClass',
            'media',
        ]);

        return [
            'format_version' => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'character' => $this->buildCharacterData($character),
        ];
    }

    private function buildCharacterData(Character $character): array
    {
        return [
            'public_id' => $character->public_id,
            'name' => $character->name,
            'race' => $character->race_slug,
            'background' => $character->background_slug,
            'alignment' => $character->alignment,
            ...$this->buildAbilityScores($character),
            ...$this->buildCombatStats($character),
            ...$this->buildCharacterAttributes($character),
            ...$this->buildHpConfig($character),
            'classes' => $this->buildClasses($character),
            'spells' => $this->buildSpells($character),
            'equipment' => $this->buildEquipment($character),
            'languages' => $this->buildLanguages($character),
            'proficiencies' => $this->buildProficiencies($character),
            'conditions' => $this->buildConditions($character),
            'feature_selections' => $this->buildFeatureSelections($character),
            'notes' => $this->buildNotes($character),
            'ability_score_choices' => $this->buildAbilityScoreChoices($character),
            'spell_slots' => $this->buildSpellSlots($character),
            'features' => $this->buildFeatures($character),
            'portrait' => $this->buildPortrait($character),
        ];
    }

    private function buildAbilityScores(Character $character): array
    {
        return [
            'ability_scores' => [
                'strength' => $character->strength,
                'dexterity' => $character->dexterity,
                'constitution' => $character->constitution,
                'intelligence' => $character->intelligence,
                'wisdom' => $character->wisdom,
                'charisma' => $character->charisma,
            ],
        ];
    }

    private function buildCombatStats(Character $character): array
    {
        return [
            'max_hit_points' => $character->max_hit_points,
            'current_hit_points' => $character->current_hit_points,
            'temp_hit_points' => $character->temp_hit_points,
            'death_save_successes' => $character->death_save_successes,
            'death_save_failures' => $character->death_save_failures,
        ];
    }

    private function buildCharacterAttributes(Character $character): array
    {
        return [
            'experience_points' => $character->experience_points,
            'has_inspiration' => $character->has_inspiration,
            'ability_score_method' => $character->ability_score_method?->value,
            'equipment_mode' => $character->equipment_mode,
            'size_id' => $character->size_id,
            'asi_choices_remaining' => $character->asi_choices_remaining,
        ];
    }

    private function buildHpConfig(Character $character): array
    {
        return [
            'hp_levels_resolved' => $character->hp_levels_resolved ?? [],
            'hp_calculation_method' => $character->hp_calculation_method,
        ];
    }

    private function buildClasses(Character $character): array
    {
        return $character->characterClasses->map(fn ($pivot) => [
            'class' => $pivot->class_slug,
            'subclass' => $pivot->subclass_slug,
            'level' => $pivot->level,
            'is_primary' => $pivot->is_primary,
            'hit_dice_spent' => $pivot->hit_dice_spent,
        ])->toArray();
    }

    private function buildSpells(Character $character): array
    {
        return $character->spells->map(fn ($spell) => [
            'spell' => $spell->spell_slug,
            'source' => $spell->source,
            'preparation_status' => $spell->preparation_status,
            'level_acquired' => $spell->level_acquired,
        ])->toArray();
    }

    private function buildEquipment(Character $character): array
    {
        return $character->equipment->map(fn ($eq) => [
            'item' => $eq->item_slug,
            'custom_name' => $eq->custom_name,
            'custom_description' => $eq->custom_description,
            'quantity' => $eq->quantity,
            'equipped' => $eq->equipped,
            'location' => $eq->location,
        ])->toArray();
    }

    private function buildLanguages(Character $character): array
    {
        return $character->languages->map(fn ($lang) => [
            'language' => $lang->language_slug,
            'source' => $lang->source,
        ])->toArray();
    }

    private function buildProficiencies(Character $character): array
    {
        $skills = [];
        $types = [];

        foreach ($character->proficiencies as $prof) {
            if ($prof->skill_slug) {
                $skills[] = [
                    'skill' => $prof->skill_slug,
                    'source' => $prof->source,
                    'expertise' => $prof->expertise,
                    'choice_group' => $prof->choice_group,
                ];
            } elseif ($prof->proficiency_type_slug) {
                $types[] = [
                    'type' => $prof->proficiency_type_slug,
                    'source' => $prof->source,
                    'expertise' => $prof->expertise,
                    'choice_group' => $prof->choice_group,
                ];
            }
            // Skip proficiencies with neither skill_slug nor proficiency_type_slug
        }

        return [
            'skills' => $skills,
            'types' => $types,
        ];
    }

    private function buildConditions(Character $character): array
    {
        return $character->conditions->map(fn ($cond) => [
            'condition' => $cond->condition_slug,
            'level' => $cond->level,
            'source' => $cond->source,
            'duration' => $cond->duration,
        ])->toArray();
    }

    private function buildFeatureSelections(Character $character): array
    {
        return $character->featureSelections->map(fn ($fs) => [
            'feature' => $fs->optional_feature_slug,
            'class' => $fs->class_slug,
            'subclass_name' => $fs->subclass_name,
            'level_acquired' => $fs->level_acquired,
            'uses_remaining' => $fs->uses_remaining,
            'max_uses' => $fs->max_uses,
        ])->toArray();
    }

    private function buildNotes(Character $character): array
    {
        return $character->notes->map(fn ($note) => [
            'category' => $note->category,
            'title' => $note->title,
            'content' => $note->content,
            'sort_order' => $note->sort_order,
        ])->toArray();
    }

    private function buildAbilityScoreChoices(Character $character): array
    {
        return $character->abilityScores->map(fn ($as) => [
            'ability_score_code' => $as->ability_score_code,
            'bonus' => $as->bonus,
            'source' => $as->source,
            'choice_group' => $as->choice_group,
        ])->toArray();
    }

    private function buildSpellSlots(Character $character): array
    {
        return $character->spellSlots->map(fn ($slot) => [
            'spell_level' => $slot->spell_level,
            'max_slots' => $slot->max_slots,
            'used_slots' => $slot->used_slots,
            'slot_type' => $slot->slot_type,
        ])->toArray();
    }

    private function buildFeatures(Character $character): array
    {
        return $character->features->map(function ($cf) {
            $feature = $cf->feature;

            // Build portable identifier based on feature type
            $portableId = null;
            if ($feature) {
                $portableId = $this->buildPortableFeatureId($cf->feature_type, $feature);
            }

            return [
                'feature_type' => $cf->feature_type,
                'portable_id' => $portableId,
                'source' => $cf->source,
                'level_acquired' => $cf->level_acquired,
                'uses_remaining' => $cf->uses_remaining,
                'max_uses' => $cf->max_uses,
            ];
        })->toArray();
    }

    /**
     * Build a portable identifier for a feature that can be resolved across instances.
     *
     * Since features don't have slugs, we use composite keys based on feature type.
     */
    private function buildPortableFeatureId(string $featureType, mixed $feature): ?array
    {
        return match ($featureType) {
            'App\\Models\\ClassFeature' => [
                'type' => 'class_feature',
                'class_slug' => $feature->characterClass?->slug,
                'feature_name' => $feature->feature_name,
                'level' => $feature->level,
            ],
            'App\\Models\\RacialTrait' => [
                'type' => 'racial_trait',
                'name' => $feature->name ?? $feature->trait_name ?? null,
            ],
            default => null,
        };
    }

    /**
     * Build portrait data as base64 for export.
     *
     * Returns null if the character has no portrait.
     */
    private function buildPortrait(Character $character): ?array
    {
        $portrait = $character->getFirstMedia('portrait');

        if (! $portrait) {
            return null;
        }

        $path = $portrait->getPath();

        if (! file_exists($path)) {
            return null;
        }

        return [
            'filename' => $portrait->file_name,
            'mime_type' => $portrait->mime_type,
            'data' => base64_encode(file_get_contents($path)),
        ];
    }
}

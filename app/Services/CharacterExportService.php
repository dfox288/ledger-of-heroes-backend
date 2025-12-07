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
    private const FORMAT_VERSION = '1.0';

    /**
     * Export a character as portable JSON.
     */
    public function export(Character $character): array
    {
        // Eager load all relationships
        $character->loadMissing([
            'characterClasses.characterClass',
            'characterClasses.subclass',
            'spells',
            'equipment',
            'languages',
            'proficiencies',
            'conditions',
            'featureSelections',
            'notes',
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
            'classes' => $this->buildClasses($character),
            'spells' => $this->buildSpells($character),
            'equipment' => $this->buildEquipment($character),
            'languages' => $this->buildLanguages($character),
            'proficiencies' => $this->buildProficiencies($character),
            'conditions' => $this->buildConditions($character),
            'feature_selections' => $this->buildFeatureSelections($character),
            'notes' => $this->buildNotes($character),
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
                ];
            } else {
                $types[] = [
                    'type' => $prof->proficiency_type_slug,
                    'source' => $prof->source,
                    'expertise' => $prof->expertise,
                ];
            }
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
            'category' => $note->category->value,
            'title' => $note->title,
            'content' => $note->content,
            'sort_order' => $note->sort_order,
        ])->toArray();
    }
}

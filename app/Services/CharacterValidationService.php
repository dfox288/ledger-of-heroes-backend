<?php

namespace App\Services;

use App\DTOs\CharacterValidationResult;
use App\Models\Background;
use App\Models\Character;
use App\Models\CharacterClass;
use App\Models\Condition;
use App\Models\Item;
use App\Models\Language;
use App\Models\Race;
use App\Models\Spell;
use Illuminate\Support\Collection;

/**
 * Service for validating character reference integrity.
 *
 * Detects dangling references (slugs that don't resolve to entities)
 * which can occur when sourcebook data is reimported or removed.
 */
class CharacterValidationService
{
    /**
     * Validate a single character's references.
     */
    public function validate(Character $character): CharacterValidationResult
    {
        $dangling = [];
        $totalRefs = 0;

        // Race
        if ($character->race_slug) {
            $totalRefs++;
            if (! $character->race) {
                $dangling['race'] = $character->race_slug;
            }
        }

        // Background
        if ($character->background_slug) {
            $totalRefs++;
            if (! $character->background) {
                $dangling['background'] = $character->background_slug;
            }
        }

        // Classes
        $character->load('characterClasses');
        $classSlugs = $character->characterClasses->pluck('class_slug')->filter()->unique();
        $subclassSlugs = $character->characterClasses->pluck('subclass_slug')->filter()->unique();

        $totalRefs += $classSlugs->count() + $subclassSlugs->count();

        if ($classSlugs->isNotEmpty()) {
            $existingClasses = CharacterClass::whereIn('full_slug', $classSlugs)->pluck('full_slug');
            $missingClasses = $classSlugs->diff($existingClasses)->values()->all();
            if (! empty($missingClasses)) {
                $dangling['classes'] = $missingClasses;
            }
        }

        if ($subclassSlugs->isNotEmpty()) {
            $existingSubclasses = CharacterClass::whereIn('full_slug', $subclassSlugs)->pluck('full_slug');
            $missingSubclasses = $subclassSlugs->diff($existingSubclasses)->values()->all();
            if (! empty($missingSubclasses)) {
                $dangling['subclasses'] = $missingSubclasses;
            }
        }

        // Spells
        $character->load('spells');
        $spellSlugs = $character->spells->pluck('spell_slug')->filter()->unique();
        $totalRefs += $spellSlugs->count();

        if ($spellSlugs->isNotEmpty()) {
            $existingSpells = Spell::whereIn('full_slug', $spellSlugs)->pluck('full_slug');
            $missingSpells = $spellSlugs->diff($existingSpells)->values()->all();
            if (! empty($missingSpells)) {
                $dangling['spells'] = $missingSpells;
            }
        }

        // Equipment
        $character->load('equipment');
        $itemSlugs = $character->equipment->pluck('item_slug')->filter()->unique();
        $totalRefs += $itemSlugs->count();

        if ($itemSlugs->isNotEmpty()) {
            $existingItems = Item::whereIn('full_slug', $itemSlugs)->pluck('full_slug');
            $missingItems = $itemSlugs->diff($existingItems)->values()->all();
            if (! empty($missingItems)) {
                $dangling['items'] = $missingItems;
            }
        }

        // Languages
        $character->load('languages');
        $languageSlugs = $character->languages->pluck('language_slug')->filter()->unique();
        $totalRefs += $languageSlugs->count();

        if ($languageSlugs->isNotEmpty()) {
            $existingLanguages = Language::whereIn('full_slug', $languageSlugs)->pluck('full_slug');
            $missingLanguages = $languageSlugs->diff($existingLanguages)->values()->all();
            if (! empty($missingLanguages)) {
                $dangling['languages'] = $missingLanguages;
            }
        }

        // Conditions
        $character->load('conditions');
        $conditionSlugs = $character->conditions->pluck('condition_slug')->filter()->unique();
        $totalRefs += $conditionSlugs->count();

        if ($conditionSlugs->isNotEmpty()) {
            $existingConditions = Condition::whereIn('full_slug', $conditionSlugs)->pluck('full_slug');
            $missingConditions = $conditionSlugs->diff($existingConditions)->values()->all();
            if (! empty($missingConditions)) {
                $dangling['conditions'] = $missingConditions;
            }
        }

        return CharacterValidationResult::fromDanglingReferences($dangling, $totalRefs);
    }

    /**
     * Validate all characters and return summary.
     *
     * @return array{total: int, valid: int, invalid: int, characters: Collection}
     */
    public function validateAll(): array
    {
        $characters = Character::all();
        $invalidCharacters = collect();

        foreach ($characters as $character) {
            $result = $this->validate($character);
            if (! $result->valid) {
                $invalidCharacters->push([
                    'public_id' => $character->public_id,
                    'name' => $character->name,
                    'dangling_references' => $result->danglingReferences,
                ]);
            }
        }

        return [
            'total' => $characters->count(),
            'valid' => $characters->count() - $invalidCharacters->count(),
            'invalid' => $invalidCharacters->count(),
            'characters' => $invalidCharacters,
        ];
    }
}

<?php

namespace App\Services;

use App\DTOs\CharacterValidationResult;
use App\DTOs\DanglingReference;
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
        // Eager load all relationships needed for validation to avoid N+1
        $character->loadMissing([
            'race',
            'background',
            'characterClasses',
            'spells',
            'equipment',
            'languages',
            'conditions',
        ]);

        $dangling = [];
        $totalRefs = 0;

        // Race
        if ($character->race_slug) {
            $totalRefs++;
            if (! $character->race) {
                $dangling['race'] = [DanglingReference::create($character->race_slug, 'race')];
            }
        }

        // Background
        if ($character->background_slug) {
            $totalRefs++;
            if (! $character->background) {
                $dangling['background'] = [DanglingReference::create($character->background_slug, 'background')];
            }
        }

        // Classes (already loaded above)
        $classSlugs = $character->characterClasses->pluck('class_slug')->filter()->unique();
        $subclassSlugs = $character->characterClasses->pluck('subclass_slug')->filter()->unique();

        $totalRefs += $classSlugs->count() + $subclassSlugs->count();

        if ($classSlugs->isNotEmpty()) {
            $existingClasses = CharacterClass::whereIn('slug', $classSlugs)->pluck('slug');
            $missingClasses = $classSlugs->diff($existingClasses)->values()->all();
            if (! empty($missingClasses)) {
                $dangling['classes'] = array_map(
                    fn ($slug) => DanglingReference::create($slug, 'class'),
                    $missingClasses
                );
            }
        }

        if ($subclassSlugs->isNotEmpty()) {
            $existingSubclasses = CharacterClass::whereIn('slug', $subclassSlugs)->pluck('slug');
            $missingSubclasses = $subclassSlugs->diff($existingSubclasses)->values()->all();
            if (! empty($missingSubclasses)) {
                $dangling['subclasses'] = array_map(
                    fn ($slug) => DanglingReference::create($slug, 'subclass'),
                    $missingSubclasses
                );
            }
        }

        // Spells (already loaded above)
        $spellSlugs = $character->spells->pluck('spell_slug')->filter()->unique();
        $totalRefs += $spellSlugs->count();

        if ($spellSlugs->isNotEmpty()) {
            $existingSpells = Spell::whereIn('slug', $spellSlugs)->pluck('slug');
            $missingSpells = $spellSlugs->diff($existingSpells)->values()->all();
            if (! empty($missingSpells)) {
                $dangling['spells'] = array_map(
                    fn ($slug) => DanglingReference::create($slug, 'spell'),
                    $missingSpells
                );
            }
        }

        // Equipment (already loaded above)
        $itemSlugs = $character->equipment->pluck('item_slug')->filter()->unique();
        $totalRefs += $itemSlugs->count();

        if ($itemSlugs->isNotEmpty()) {
            $existingItems = Item::whereIn('slug', $itemSlugs)->pluck('slug');
            $missingItems = $itemSlugs->diff($existingItems)->values()->all();
            if (! empty($missingItems)) {
                $dangling['items'] = array_map(
                    fn ($slug) => DanglingReference::create($slug, 'item'),
                    $missingItems
                );
            }
        }

        // Languages (already loaded above)
        $languageSlugs = $character->languages->pluck('language_slug')->filter()->unique();
        $totalRefs += $languageSlugs->count();

        if ($languageSlugs->isNotEmpty()) {
            $existingLanguages = Language::whereIn('slug', $languageSlugs)->pluck('slug');
            $missingLanguages = $languageSlugs->diff($existingLanguages)->values()->all();
            if (! empty($missingLanguages)) {
                $dangling['languages'] = array_map(
                    fn ($slug) => DanglingReference::create($slug, 'language'),
                    $missingLanguages
                );
            }
        }

        // Conditions (already loaded above)
        $conditionSlugs = $character->conditions->pluck('condition_slug')->filter()->unique();
        $totalRefs += $conditionSlugs->count();

        if ($conditionSlugs->isNotEmpty()) {
            $existingConditions = Condition::whereIn('slug', $conditionSlugs)->pluck('slug');
            $missingConditions = $conditionSlugs->diff($existingConditions)->values()->all();
            if (! empty($missingConditions)) {
                $dangling['conditions'] = array_map(
                    fn ($slug) => DanglingReference::create($slug, 'condition'),
                    $missingConditions
                );
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
        // Eager load all relationships to avoid N+1 queries
        $characters = Character::with([
            'race',
            'background',
            'characterClasses',
            'spells',
            'equipment',
            'languages',
            'conditions',
        ])->get();

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

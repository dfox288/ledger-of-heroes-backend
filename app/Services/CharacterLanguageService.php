<?php

namespace App\Services;

use App\Enums\CharacterSource;
use App\Models\Character;
use App\Models\CharacterLanguage;
use App\Models\EntityLanguage;
use App\Models\Feat;
use App\Models\Language;
use App\Models\Race;
use App\Services\Concerns\PopulatesFromEntity;
use InvalidArgumentException;

class CharacterLanguageService
{
    use PopulatesFromEntity;

    /**
     * Get all languages known by a character.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, CharacterLanguage>
     */
    public function getCharacterLanguages(Character $character)
    {
        return $character->languages()
            ->with('language')
            ->get();
    }

    /**
     * Populate fixed (non-choice) languages from race, background, and feats.
     */
    public function populateFixed(Character $character): void
    {
        $this->populateFromRace($character);
        $this->populateFromBackground($character);
        $this->populateFromFeats($character);
    }

    /**
     * Get pending language choices that need user input.
     *
     * @return array{race: array, background: array, feat: array}
     */
    public function getPendingChoices(Character $character): array
    {
        // Get all languages the character already knows (by slug)
        $knownLanguageSlugs = $character->languages()->pluck('language_slug')->toArray();

        // Get all available languages for options
        $allLanguages = Language::orderBy('name')->get();

        $choices = [
            'race' => $this->getChoicesFromEntity($character->race, 'race', $character, $knownLanguageSlugs, $allLanguages),
            'background' => $this->getChoicesFromEntity($character->background, 'background', $character, $knownLanguageSlugs, $allLanguages),
            'feat' => $this->getChoicesFromFeats($character, $knownLanguageSlugs, $allLanguages),
        ];

        return $choices;
    }

    /**
     * Make a language choice for a character.
     *
     * @param  array<string>  $languageSlugs  The language full_slugs the user chose
     *
     * @throws InvalidArgumentException
     */
    public function makeChoice(Character $character, string $source, array $languageSlugs): void
    {
        // Validate source using enum
        $validSources = CharacterSource::forLanguages();
        $sourceEnum = CharacterSource::tryFrom($source);

        if (! $sourceEnum || ! in_array($sourceEnum, $validSources)) {
            throw new InvalidArgumentException("Invalid source: {$source}");
        }

        // Get expected quantity for this source
        $expectedQuantity = $this->getChoiceQuantity($character, $source);

        if ($expectedQuantity === 0) {
            throw new InvalidArgumentException("No language choices available for {$source}");
        }

        if (count($languageSlugs) !== $expectedQuantity) {
            throw new InvalidArgumentException(
                "Must choose exactly {$expectedQuantity} languages, got ".count($languageSlugs)
            );
        }

        // Validate languages exist
        $languages = Language::whereIn('full_slug', $languageSlugs)->get();
        if ($languages->count() !== count($languageSlugs)) {
            throw new InvalidArgumentException('One or more language slugs are invalid');
        }

        // Clear existing choices for this source before validation
        // This makes the endpoint idempotent - re-submitting choices is allowed
        $fixedLanguageSlugs = $this->getFixedLanguageSlugs($character, $source);
        $character->languages()
            ->where('source', $source)
            ->whereNotIn('language_slug', $fixedLanguageSlugs)
            ->delete();

        // Validate languages are not already known (excluding the ones we just deleted)
        $knownLanguageSlugs = $character->languages()->pluck('language_slug')->toArray();
        foreach ($languageSlugs as $languageSlug) {
            if (in_array($languageSlug, $knownLanguageSlugs)) {
                throw new InvalidArgumentException("Language {$languageSlug} is already known");
            }
        }

        // Create the language records
        foreach ($languageSlugs as $languageSlug) {
            CharacterLanguage::create([
                'character_id' => $character->id,
                'language_slug' => $languageSlug,
                'source' => $source,
            ]);
        }

        // Refresh the relationship
        $character->load('languages');
    }

    /**
     * Populate fixed languages from an entity.
     * Implementation of PopulatesFromEntity trait's abstract method.
     */
    protected function populateFromEntity(Character $character, $entity, string $source): void
    {
        if (! $entity) {
            return;
        }

        $fixedLanguages = $entity->languages()
            ->where('is_choice', false)
            ->whereNotNull('language_id')
            ->with('language')
            ->get();

        foreach ($fixedLanguages as $entityLanguage) {
            $languageSlug = $entityLanguage->language?->full_slug;
            if (! $languageSlug) {
                continue;
            }

            // Check if character already knows this language from ANY source
            $exists = $character->languages()
                ->where('language_slug', $languageSlug)
                ->exists();

            if ($exists) {
                continue;
            }

            CharacterLanguage::create([
                'character_id' => $character->id,
                'language_slug' => $languageSlug,
                'source' => $source,
            ]);
        }

        // Refresh the relationship
        $character->load('languages');
    }

    /**
     * Get choices from a single entity (race, background).
     * For subraces, includes inherited languages and choices from the parent race.
     */
    private function getChoicesFromEntity($entity, string $source, Character $character, array $knownLanguageSlugs, $allLanguages): array
    {
        if (! $entity) {
            return [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ];
        }

        // Get known languages from this source
        $knownFromSource = $character->languages()
            ->where('source', $source)
            ->with('language')
            ->get()
            ->filter(fn ($cl) => $cl->language !== null)
            ->map(fn ($cl) => [
                'full_slug' => $cl->language->full_slug,
                'name' => $cl->language->name,
                'slug' => $cl->language->slug,
                'script' => $cl->language->script,
            ])
            ->toArray();

        // Get choice info from entity
        $choiceRecord = $entity->languages()
            ->where('is_choice', true)
            ->first();

        $quantity = $choiceRecord?->quantity ?? 0;

        // For subraces, also check parent race for language choices
        if ($source === 'race' && $entity instanceof Race && $entity->is_subrace && $entity->parent) {
            $parentChoiceRecord = $entity->parent->languages()
                ->where('is_choice', true)
                ->first();

            $quantity += $parentChoiceRecord?->quantity ?? 0;
        }

        // Get selected choice languages (not fixed ones)
        $fixedLanguageSlugs = $this->getFixedLanguageSlugs($character, $source, $entity);
        $selectedFromSource = $character->languages()
            ->where('source', $source)
            ->whereNotIn('language_slug', $fixedLanguageSlugs)
            ->pluck('language_slug')
            ->toArray();

        $remaining = max(0, $quantity - count($selectedFromSource));

        // Build options (exclude already known languages and non-learnable languages)
        // Non-learnable languages (Thieves' Cant, Druidic) can only be granted by class features
        $options = $allLanguages
            ->whereNotIn('full_slug', $knownLanguageSlugs)
            ->where('is_learnable', true)
            ->map(fn ($lang) => [
                'full_slug' => $lang->full_slug,
                'name' => $lang->name,
                'slug' => $lang->slug,
                'script' => $lang->script,
                'is_learnable' => $lang->is_learnable,
            ])
            ->values()
            ->toArray();

        return [
            'known' => $knownFromSource,
            'choices' => [
                'quantity' => $quantity,
                'remaining' => $remaining,
                'selected' => $selectedFromSource,
                'options' => $options,
            ],
        ];
    }

    /**
     * Get choices from feats.
     */
    private function getChoicesFromFeats(Character $character, array $knownLanguageSlugs, $allLanguages): array
    {
        // Get feat IDs via feature_slug lookup
        $featIds = $this->getCharacterFeatIds($character);

        if (empty($featIds)) {
            return [
                'known' => [],
                'choices' => [
                    'quantity' => 0,
                    'remaining' => 0,
                    'selected' => [],
                    'options' => [],
                ],
            ];
        }

        // Get known languages from feat source
        $knownFromSource = $character->languages()
            ->where('source', 'feat')
            ->with('language')
            ->get()
            ->filter(fn ($cl) => $cl->language !== null)
            ->map(fn ($cl) => [
                'full_slug' => $cl->language->full_slug,
                'name' => $cl->language->name,
                'slug' => $cl->language->slug,
                'script' => $cl->language->script,
            ])
            ->toArray();

        // Sum up all feat language choices
        $totalQuantity = EntityLanguage::whereIn('reference_id', $featIds)
            ->where('reference_type', Feat::class)
            ->where('is_choice', true)
            ->sum('quantity');

        // Get fixed language slugs from feats
        $fixedLanguageSlugs = EntityLanguage::whereIn('reference_id', $featIds)
            ->where('reference_type', Feat::class)
            ->where('is_choice', false)
            ->whereNotNull('language_id')
            ->with('language')
            ->get()
            ->filter(fn ($el) => $el->language !== null)
            ->pluck('language.full_slug')
            ->toArray();

        // Get selected choice languages (not fixed ones)
        $selectedFromSource = $character->languages()
            ->where('source', 'feat')
            ->whereNotIn('language_slug', $fixedLanguageSlugs)
            ->pluck('language_slug')
            ->toArray();

        $remaining = max(0, $totalQuantity - count($selectedFromSource));

        // Build options (exclude already known languages and non-learnable languages)
        // Non-learnable languages (Thieves' Cant, Druidic) can only be granted by class features
        $options = $allLanguages
            ->whereNotIn('full_slug', $knownLanguageSlugs)
            ->where('is_learnable', true)
            ->map(fn ($lang) => [
                'full_slug' => $lang->full_slug,
                'name' => $lang->name,
                'slug' => $lang->slug,
                'script' => $lang->script,
                'is_learnable' => $lang->is_learnable,
            ])
            ->values()
            ->toArray();

        return [
            'known' => $knownFromSource,
            'choices' => [
                'quantity' => $totalQuantity,
                'remaining' => $remaining,
                'selected' => $selectedFromSource,
                'options' => $options,
            ],
        ];
    }

    /**
     * Get the number of language choices available for a source.
     * For subraces, includes inherited choices from the parent race.
     */
    private function getChoiceQuantity(Character $character, string $source): int
    {
        if ($source === 'feat') {
            $featIds = $this->getCharacterFeatIds($character);

            if (empty($featIds)) {
                return 0;
            }

            return EntityLanguage::whereIn('reference_id', $featIds)
                ->where('reference_type', Feat::class)
                ->where('is_choice', true)
                ->sum('quantity');
        }

        $entity = match ($source) {
            'race' => $character->race,
            'background' => $character->background,
            default => null,
        };

        if (! $entity) {
            return 0;
        }

        $quantity = $entity->languages()
            ->where('is_choice', true)
            ->sum('quantity');

        // For subraces, also include parent race language choices
        if ($source === 'race' && $entity instanceof Race && $entity->is_subrace && $entity->parent) {
            $quantity += $entity->parent->languages()
                ->where('is_choice', true)
                ->sum('quantity');
        }

        return $quantity;
    }

    /**
     * Get feat IDs for a character by looking up feature_slugs.
     *
     * This bridges the gap between slug-based CharacterFeature lookups
     * and ID-based EntityLanguage queries until EntityLanguage is migrated.
     *
     * @return array<int>
     */
    private function getCharacterFeatIds(Character $character): array
    {
        $featSlugs = $character->features()
            ->where('feature_type', Feat::class)
            ->whereNotNull('feature_slug')
            ->pluck('feature_slug')
            ->toArray();

        if (empty($featSlugs)) {
            return [];
        }

        return Feat::whereIn('full_slug', $featSlugs)->pluck('id')->toArray();
    }

    /**
     * Get fixed language slugs from an entity.
     * For subraces, includes inherited fixed languages from the parent race.
     */
    private function getFixedLanguageSlugs(Character $character, string $source, $entity = null): array
    {
        if ($source === 'feat') {
            $featIds = $this->getCharacterFeatIds($character);

            if (empty($featIds)) {
                return [];
            }

            return EntityLanguage::whereIn('reference_id', $featIds)
                ->where('reference_type', Feat::class)
                ->where('is_choice', false)
                ->whereNotNull('language_id')
                ->with('language')
                ->get()
                ->filter(fn ($el) => $el->language !== null)
                ->pluck('language.full_slug')
                ->toArray();
        }

        if (! $entity) {
            $entity = match ($source) {
                'race' => $character->race,
                'background' => $character->background,
                default => null,
            };
        }

        if (! $entity) {
            return [];
        }

        $fixedLanguageSlugs = $entity->languages()
            ->where('is_choice', false)
            ->whereNotNull('language_id')
            ->with('language')
            ->get()
            ->filter(fn ($el) => $el->language !== null)
            ->pluck('language.full_slug')
            ->toArray();

        // For subraces, also include parent race fixed languages
        if ($source === 'race' && $entity instanceof Race && $entity->is_subrace && $entity->parent) {
            $parentFixedLanguageSlugs = $entity->parent->languages()
                ->where('is_choice', false)
                ->whereNotNull('language_id')
                ->with('language')
                ->get()
                ->filter(fn ($el) => $el->language !== null)
                ->pluck('language.full_slug')
                ->toArray();

            $fixedLanguageSlugs = array_unique(array_merge($fixedLanguageSlugs, $parentFixedLanguageSlugs));
        }

        return $fixedLanguageSlugs;
    }
}

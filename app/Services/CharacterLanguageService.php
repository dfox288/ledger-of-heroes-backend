<?php

namespace App\Services;

use App\Enums\CharacterSource;
use App\Models\Character;
use App\Models\CharacterLanguage;
use App\Models\EntityLanguage;
use App\Models\Feat;
use App\Models\Language;
use App\Models\Race;
use InvalidArgumentException;

class CharacterLanguageService
{
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
     * Populate fixed languages from the character's race.
     * For subraces, also populates inherited languages from the parent race.
     */
    public function populateFromRace(Character $character): void
    {
        if (! $character->race_id) {
            return;
        }

        $race = $character->race;

        // For subraces, first populate languages from parent race
        if ($race->is_subrace && $race->parent) {
            $this->populateFixedLanguages($character, $race->parent, 'race');
        }

        // Then populate from the race itself
        $this->populateFixedLanguages($character, $race, 'race');
    }

    /**
     * Populate fixed languages from the character's background.
     */
    public function populateFromBackground(Character $character): void
    {
        if (! $character->background_id) {
            return;
        }

        $this->populateFixedLanguages($character, $character->background, 'background');
    }

    /**
     * Populate fixed languages from the character's feats.
     */
    public function populateFromFeats(Character $character): void
    {
        // Get feat IDs from character features (polymorphic)
        $featIds = $character->features()
            ->where('feature_type', Feat::class)
            ->pluck('feature_id')
            ->toArray();

        if (empty($featIds)) {
            return;
        }

        // Load all feats in one query to avoid N+1
        $feats = Feat::whereIn('id', $featIds)->get();

        foreach ($feats as $feat) {
            $this->populateFixedLanguages($character, $feat, 'feat');
        }
    }

    /**
     * Get pending language choices that need user input.
     *
     * @return array{race: array, background: array, feat: array}
     */
    public function getPendingChoices(Character $character): array
    {
        // Get all languages the character already knows
        $knownLanguageIds = $character->languages()->pluck('language_id')->toArray();

        // Get all available languages for options
        $allLanguages = Language::orderBy('name')->get();

        $choices = [
            'race' => $this->getChoicesFromEntity($character->race, 'race', $character, $knownLanguageIds, $allLanguages),
            'background' => $this->getChoicesFromEntity($character->background, 'background', $character, $knownLanguageIds, $allLanguages),
            'feat' => $this->getChoicesFromFeats($character, $knownLanguageIds, $allLanguages),
        ];

        return $choices;
    }

    /**
     * Make a language choice for a character.
     *
     * @param  array<int>  $languageIds  The language IDs the user chose
     *
     * @throws InvalidArgumentException
     */
    public function makeChoice(Character $character, string $source, array $languageIds): void
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

        if (count($languageIds) !== $expectedQuantity) {
            throw new InvalidArgumentException(
                "Must choose exactly {$expectedQuantity} languages, got ".count($languageIds)
            );
        }

        // Validate languages exist
        $languages = Language::whereIn('id', $languageIds)->get();
        if ($languages->count() !== count($languageIds)) {
            throw new InvalidArgumentException('One or more language IDs are invalid');
        }

        // Validate languages are not already known
        $knownLanguageIds = $character->languages()->pluck('language_id')->toArray();
        foreach ($languageIds as $languageId) {
            if (in_array($languageId, $knownLanguageIds)) {
                throw new InvalidArgumentException("Language ID {$languageId} is already known");
            }
        }

        // Clear existing choices for this source before adding new ones
        $character->languages()
            ->where('source', $source)
            ->whereNotIn('language_id', $this->getFixedLanguageIds($character, $source))
            ->delete();

        // Create the language records
        foreach ($languageIds as $languageId) {
            CharacterLanguage::create([
                'character_id' => $character->id,
                'language_id' => $languageId,
                'source' => $source,
            ]);
        }

        // Refresh the relationship
        $character->load('languages');
    }

    /**
     * Populate fixed languages from an entity.
     */
    private function populateFixedLanguages(Character $character, $entity, string $source): void
    {
        if (! $entity) {
            return;
        }

        $fixedLanguages = $entity->languages()
            ->where('is_choice', false)
            ->whereNotNull('language_id')
            ->get();

        foreach ($fixedLanguages as $entityLanguage) {
            // Check if character already knows this language from ANY source
            $exists = $character->languages()
                ->where('language_id', $entityLanguage->language_id)
                ->exists();

            if ($exists) {
                continue;
            }

            CharacterLanguage::create([
                'character_id' => $character->id,
                'language_id' => $entityLanguage->language_id,
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
    private function getChoicesFromEntity($entity, string $source, Character $character, array $knownLanguageIds, $allLanguages): array
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
            ->map(fn ($cl) => [
                'id' => $cl->language->id,
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
        $fixedLanguageIds = $this->getFixedLanguageIds($character, $source, $entity);
        $selectedFromSource = $character->languages()
            ->where('source', $source)
            ->whereNotIn('language_id', $fixedLanguageIds)
            ->pluck('language_id')
            ->toArray();

        $remaining = max(0, $quantity - count($selectedFromSource));

        // Build options (exclude already known languages)
        $options = $allLanguages
            ->whereNotIn('id', $knownLanguageIds)
            ->map(fn ($lang) => [
                'id' => $lang->id,
                'name' => $lang->name,
                'slug' => $lang->slug,
                'script' => $lang->script,
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
    private function getChoicesFromFeats(Character $character, array $knownLanguageIds, $allLanguages): array
    {
        // Get feat IDs from character features (polymorphic)
        $featIds = $character->features()
            ->where('feature_type', Feat::class)
            ->pluck('feature_id')
            ->toArray();

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
            ->map(fn ($cl) => [
                'id' => $cl->language->id,
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

        // Get fixed language IDs from feats
        $fixedLanguageIds = EntityLanguage::whereIn('reference_id', $featIds)
            ->where('reference_type', Feat::class)
            ->where('is_choice', false)
            ->whereNotNull('language_id')
            ->pluck('language_id')
            ->toArray();

        // Get selected choice languages (not fixed ones)
        $selectedFromSource = $character->languages()
            ->where('source', 'feat')
            ->whereNotIn('language_id', $fixedLanguageIds)
            ->pluck('language_id')
            ->toArray();

        $remaining = max(0, $totalQuantity - count($selectedFromSource));

        // Build options (exclude already known languages)
        $options = $allLanguages
            ->whereNotIn('id', $knownLanguageIds)
            ->map(fn ($lang) => [
                'id' => $lang->id,
                'name' => $lang->name,
                'slug' => $lang->slug,
                'script' => $lang->script,
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
            $featIds = $character->features()
                ->where('feature_type', Feat::class)
                ->pluck('feature_id')
                ->toArray();

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
     * Get fixed language IDs from an entity.
     * For subraces, includes inherited fixed languages from the parent race.
     */
    private function getFixedLanguageIds(Character $character, string $source, $entity = null): array
    {
        if ($source === 'feat') {
            $featIds = $character->features()
                ->where('feature_type', Feat::class)
                ->pluck('feature_id')
                ->toArray();

            return EntityLanguage::whereIn('reference_id', $featIds)
                ->where('reference_type', Feat::class)
                ->where('is_choice', false)
                ->whereNotNull('language_id')
                ->pluck('language_id')
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

        $fixedLanguageIds = $entity->languages()
            ->where('is_choice', false)
            ->whereNotNull('language_id')
            ->pluck('language_id')
            ->toArray();

        // For subraces, also include parent race fixed languages
        if ($source === 'race' && $entity instanceof Race && $entity->is_subrace && $entity->parent) {
            $parentFixedLanguageIds = $entity->parent->languages()
                ->where('is_choice', false)
                ->whereNotNull('language_id')
                ->pluck('language_id')
                ->toArray();

            $fixedLanguageIds = array_unique(array_merge($fixedLanguageIds, $parentFixedLanguageIds), SORT_NUMERIC);
        }

        return $fixedLanguageIds;
    }
}

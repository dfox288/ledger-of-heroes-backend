<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\EntityLanguage;
use App\Models\Feat;
use App\Models\Race;
use App\Services\CharacterLanguageService;
use Illuminate\Support\Collection;

class LanguageChoiceHandler extends AbstractChoiceHandler
{
    public function __construct(
        private readonly CharacterLanguageService $languageService
    ) {}

    public function getType(): string
    {
        return 'language';
    }

    public function getChoices(Character $character): Collection
    {
        $pendingChoices = $this->languageService->getPendingChoices($character);
        $choices = collect();

        foreach ($pendingChoices as $source => $sourceData) {
            $choiceData = $sourceData['choices'] ?? [];

            // Skip if no choices available
            if (($choiceData['quantity'] ?? 0) === 0) {
                continue;
            }

            $sourceSlug = $this->getSourceSlug($character, $source);
            if ($sourceSlug === '') {
                continue;
            }

            $sourceName = $this->getSourceName($character, $source);

            // Build options with consistent structure using full_slug
            // is_learnable is included for API documentation purposes (pre-filtered by service)
            $options = collect($choiceData['options'] ?? [])
                ->map(fn ($opt) => [
                    'full_slug' => $opt['full_slug'] ?? $opt['slug'],
                    'slug' => $opt['slug'],
                    'name' => $opt['name'],
                    'script' => $opt['script'] ?? null,
                    'is_learnable' => $opt['is_learnable'] ?? true,
                ])
                ->values()
                ->all();

            // Selected are language full_slugs
            $selected = $choiceData['selected'] ?? [];

            $choice = new PendingChoice(
                id: $this->generateChoiceId('language', $source, $sourceSlug, 1, 'language_choice'),
                type: 'language',
                subtype: null,
                source: $source,
                sourceName: $sourceName,
                levelGranted: 1,
                required: true,
                quantity: $choiceData['quantity'],
                remaining: $choiceData['remaining'],
                selected: $selected,
                options: $options,
                optionsEndpoint: null,
                metadata: [
                    'known_languages' => $sourceData['known'] ?? [],
                ],
            );

            $choices->push($choice);
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $source = $parsed['source'];

        $selected = $selection['selected'] ?? [];
        if (empty($selected)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Pass language slugs directly to the service (validation is done there)
        $this->languageService->makeChoice($character, $source, $selected);
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // Language choices can be undone/changed
        return true;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $source = $parsed['source'];

        // Get fixed language slugs to preserve them
        $fixedLanguageSlugs = $this->getFixedLanguageSlugs($character, $source);

        // Delete choice languages (not fixed ones)
        $character->languages()
            ->where('source', $source)
            ->whereNotIn('language_slug', $fixedLanguageSlugs)
            ->delete();

        $character->load('languages');
    }

    private function getSourceSlug(Character $character, string $source): string
    {
        return match ($source) {
            'race' => $character->getAttribute('race')?->full_slug ?? '',
            'background' => $character->getAttribute('background')?->full_slug ?? '',
            'feat' => 'feat', // Feats don't have a single slug, use 'feat' as placeholder
            default => '',
        };
    }

    private function getSourceName(Character $character, string $source): string
    {
        return match ($source) {
            'race' => $character->getAttribute('race')?->name ?? 'Unknown Race',
            'background' => $character->getAttribute('background')?->name ?? 'Unknown Background',
            'feat' => 'Feat',
            default => 'Unknown',
        };
    }

    private function getFixedLanguageSlugs(Character $character, string $source): array
    {
        // This mirrors the logic in CharacterLanguageService::getFixedLanguageSlugs
        if ($source === 'feat') {
            // Get feat IDs via feature_slug lookup
            $featSlugs = $character->features()
                ->where('feature_type', Feat::class)
                ->whereNotNull('feature_slug')
                ->pluck('feature_slug')
                ->toArray();

            if (empty($featSlugs)) {
                return [];
            }

            $featIds = Feat::whereIn('full_slug', $featSlugs)->pluck('id')->toArray();

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

        $entity = match ($source) {
            'race' => $character->race,
            'background' => $character->background,
            default => null,
        };

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
        if ($source === 'race' && $entity->is_subrace && $entity->parent) {
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

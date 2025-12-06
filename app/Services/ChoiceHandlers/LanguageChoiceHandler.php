<?php

declare(strict_types=1);

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\EntityLanguage;
use App\Models\Feat;
use App\Models\Language;
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

            $sourceId = $this->getSourceId($character, $source);
            if ($sourceId === 0) {
                continue;
            }

            $sourceName = $this->getSourceName($character, $source);

            // Build options with consistent structure
            $options = collect($choiceData['options'] ?? [])
                ->map(fn ($opt) => [
                    'id' => $opt['id'],
                    'slug' => $opt['slug'],
                    'name' => $opt['name'],
                    'script' => $opt['script'] ?? null,
                ])
                ->values()
                ->all();

            // Selected are language IDs
            $selected = array_map('strval', $choiceData['selected'] ?? []);

            $choice = new PendingChoice(
                id: $this->generateChoiceId('language', $source, $sourceId, 1, 'language_choice'),
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

        // Resolve language IDs (handle both IDs and slugs)
        $languageIds = $this->resolveLanguageIds($selected);

        $this->languageService->makeChoice($character, $source, $languageIds);
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

        // Get fixed language IDs to preserve them
        $fixedLanguageIds = $this->getFixedLanguageIds($character, $source);

        // Delete choice languages (not fixed ones)
        $character->languages()
            ->where('source', $source)
            ->whereNotIn('language_id', $fixedLanguageIds)
            ->delete();

        $character->load('languages');
    }

    private function getSourceId(Character $character, string $source): int
    {
        return match ($source) {
            'race' => $character->getAttribute('race_id') ?? 0,
            'background' => $character->getAttribute('background_id') ?? 0,
            'feat' => 1, // Feats don't have a single ID, use 1 as placeholder
            default => 0,
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

    private function resolveLanguageIds(array $selected): array
    {
        // If already integers, return as-is
        if (! empty($selected) && is_numeric($selected[0])) {
            return array_map('intval', $selected);
        }

        // Otherwise resolve slugs to IDs
        return Language::whereIn('slug', $selected)
            ->pluck('id')
            ->all();
    }

    private function getFixedLanguageIds(Character $character, string $source): array
    {
        // This mirrors the logic in CharacterLanguageService::getFixedLanguageIds
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

        $entity = match ($source) {
            'race' => $character->race,
            'background' => $character->background,
            default => null,
        };

        if (! $entity) {
            return [];
        }

        $fixedLanguageIds = $entity->languages()
            ->where('is_choice', false)
            ->whereNotNull('language_id')
            ->pluck('language_id')
            ->toArray();

        // For subraces, also include parent race fixed languages
        if ($source === 'race' && $entity->is_subrace && $entity->parent) {
            $parentFixedLanguageIds = $entity->parent->languages()
                ->where('is_choice', false)
                ->whereNotNull('language_id')
                ->pluck('language_id')
                ->toArray();

            $fixedLanguageIds = array_unique(array_merge($fixedLanguageIds, $parentFixedLanguageIds));
        }

        return $fixedLanguageIds;
    }
}

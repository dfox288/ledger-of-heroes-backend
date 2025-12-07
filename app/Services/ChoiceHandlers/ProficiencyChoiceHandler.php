<?php

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Services\CharacterProficiencyService;
use Illuminate\Support\Collection;

class ProficiencyChoiceHandler extends AbstractChoiceHandler
{
    public function __construct(
        private readonly CharacterProficiencyService $proficiencyService
    ) {}

    public function getType(): string
    {
        return 'proficiency';
    }

    public function getChoices(Character $character): Collection
    {
        $pendingChoices = $this->proficiencyService->getPendingChoices($character);
        $choices = collect();

        foreach ($pendingChoices as $source => $sourceChoices) {
            foreach ($sourceChoices as $choiceGroup => $choiceData) {
                // Get source entity slug
                $sourceSlug = $this->getSourceSlug($character, $source);
                if ($sourceSlug === '') {
                    continue;
                }

                $sourceName = $this->getSourceName($character, $source);

                // Determine subtype from proficiency_type
                $subtype = $choiceData['proficiency_type'] ?? 'skill';

                // Build options array
                $options = $this->buildOptions($choiceData);

                // Build selected array (skill/proficiency type slugs)
                $selected = $this->buildSelected($choiceData);

                $choice = new PendingChoice(
                    id: $this->generateChoiceId('proficiency', $source, $sourceSlug, 1, $choiceGroup),
                    type: 'proficiency',
                    subtype: $subtype,
                    source: $source,
                    sourceName: $sourceName,
                    levelGranted: 1,
                    required: true,
                    quantity: $choiceData['quantity'],
                    remaining: $choiceData['remaining'],
                    selected: $selected,
                    options: $options,
                    optionsEndpoint: $this->buildOptionsEndpoint($choiceData),
                    metadata: [
                        'choice_group' => $choiceGroup,
                        'proficiency_subcategory' => $choiceData['proficiency_subcategory'] ?? null,
                    ],
                );

                $choices->push($choice);
            }
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $parsed = $this->parseChoiceId($choice->id);
        $choiceGroup = $parsed['group'];
        $source = $parsed['source'];

        $selected = $selection['selected'] ?? [];
        if (empty($selected)) {
            throw new InvalidSelectionException($choice->id, 'empty', 'Selection cannot be empty');
        }

        // Determine if this is a skill choice or proficiency type choice
        if ($choice->subtype === 'skill') {
            // Pass skill slugs directly to the service
            $this->proficiencyService->makeSkillChoice($character, $source, $choiceGroup, $selected);
        } else {
            // Pass proficiency type slugs directly to the service
            $this->proficiencyService->makeProficiencyTypeChoice($character, $source, $choiceGroup, $selected);
        }
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // Proficiency choices can be undone/changed
        return true;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $parsed = $this->parseChoiceId($choice->id);

        // Clear proficiencies for this source + choice group
        $character->proficiencies()
            ->where('source', $parsed['source'])
            ->where('choice_group', $parsed['group'])
            ->delete();

        $character->load('proficiencies');
    }

    private function getSourceSlug(Character $character, string $source): string
    {
        return match ($source) {
            'class' => $character->primary_class?->full_slug ?? '',
            'race' => $character->race?->full_slug ?? '',
            'background' => $character->background?->full_slug ?? '',
            default => '',
        };
    }

    private function getSourceName(Character $character, string $source): string
    {
        return match ($source) {
            'class' => $character->primary_class?->name ?? 'Unknown Class',
            'race' => $character->race?->name ?? 'Unknown Race',
            'background' => $character->background?->name ?? 'Unknown Background',
            default => 'Unknown',
        };
    }

    private function buildOptions(array $choiceData): array
    {
        return collect($choiceData['options'] ?? [])
            ->map(function ($option) {
                if ($option['type'] === 'skill') {
                    return [
                        'type' => 'skill',
                        'full_slug' => $option['skill']['full_slug'] ?? $option['skill_slug'] ?? null,
                        'slug' => $option['skill']['slug'] ?? null,
                        'name' => $option['skill']['name'] ?? null,
                    ];
                } else {
                    return [
                        'type' => 'proficiency_type',
                        'full_slug' => $option['proficiency_type']['full_slug'] ?? $option['proficiency_type_slug'] ?? null,
                        'slug' => $option['proficiency_type']['slug'] ?? null,
                        'name' => $option['proficiency_type']['name'] ?? null,
                    ];
                }
            })
            ->values()
            ->all();
    }

    private function buildSelected(array $choiceData): array
    {
        // Return selected slugs
        $selectedSkillSlugs = $choiceData['selected_skill_slugs'] ?? [];
        $selectedProfTypeSlugs = $choiceData['selected_proficiency_type_slugs'] ?? [];

        return array_merge($selectedSkillSlugs, $selectedProfTypeSlugs);
    }

    private function buildOptionsEndpoint(array $choiceData): ?string
    {
        $subcategory = $choiceData['proficiency_subcategory'] ?? null;
        $profType = $choiceData['proficiency_type'] ?? null;

        if ($subcategory && $profType !== 'skill') {
            return "/api/v1/lookups/proficiency-types?category={$profType}&subcategory={$subcategory}";
        }

        return null;
    }
}

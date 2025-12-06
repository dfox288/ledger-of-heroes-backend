<?php

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\ProficiencyType;
use App\Models\Skill;
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
                // Get source entity ID
                $sourceId = $this->getSourceId($character, $source);
                if ($sourceId === 0) {
                    continue;
                }

                $sourceName = $this->getSourceName($character, $source);

                // Determine subtype from proficiency_type
                $subtype = $choiceData['proficiency_type'] ?? 'skill';

                // Build options array
                $options = $this->buildOptions($choiceData);

                // Build selected array (skill slugs or proficiency type IDs)
                $selected = $this->buildSelected($choiceData);

                $choice = new PendingChoice(
                    id: $this->generateChoiceId('proficiency', $source, $sourceId, 1, $choiceGroup),
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
            // Convert slugs to IDs if needed
            $skillIds = $this->resolveSkillIds($selected);
            $this->proficiencyService->makeSkillChoice($character, $source, $choiceGroup, $skillIds);
        } else {
            // Proficiency type choice (tools, weapons, etc.)
            $profTypeIds = $this->resolveProficiencyTypeIds($selected);
            $this->proficiencyService->makeProficiencyTypeChoice($character, $source, $choiceGroup, $profTypeIds);
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

    private function getSourceId(Character $character, string $source): int
    {
        return match ($source) {
            'class' => $character->primary_class?->id ?? 0,
            'race' => $character->race_id ?? 0,
            'background' => $character->background_id ?? 0,
            default => 0,
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
                        'id' => $option['skill_id'],
                        'slug' => $option['skill']['slug'] ?? null,
                        'name' => $option['skill']['name'] ?? null,
                    ];
                } else {
                    return [
                        'type' => 'proficiency_type',
                        'id' => $option['proficiency_type_id'],
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
        $selectedSkills = $choiceData['selected_skills'] ?? [];
        $selectedProfTypes = $choiceData['selected_proficiency_types'] ?? [];

        // Return IDs as strings for consistency
        return array_merge(
            array_map('strval', $selectedSkills),
            array_map('strval', $selectedProfTypes)
        );
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

    private function resolveSkillIds(array $selected): array
    {
        // If already integers, return as-is
        if (! empty($selected) && is_numeric($selected[0])) {
            return array_map('intval', $selected);
        }

        // Otherwise resolve slugs to IDs
        return Skill::whereIn('slug', $selected)
            ->pluck('id')
            ->all();
    }

    private function resolveProficiencyTypeIds(array $selected): array
    {
        // If already integers, return as-is
        if (! empty($selected) && is_numeric($selected[0])) {
            return array_map('intval', $selected);
        }

        // Otherwise resolve slugs to IDs
        return ProficiencyType::whereIn('slug', $selected)
            ->pluck('id')
            ->all();
    }
}

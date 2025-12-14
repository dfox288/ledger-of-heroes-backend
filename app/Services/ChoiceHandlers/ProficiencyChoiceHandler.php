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

                $sourceName = $this->getSourceName($character, $source, $choiceGroup);

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
            'class' => $character->primary_class?->slug ?? '',
            'race' => $character->race?->slug ?? '',
            'background' => $character->background?->slug ?? '',
            'subclass_feature' => $character->characterClasses->first()?->subclass?->slug ?? '',
            default => '',
        };
    }

    private function getSourceName(Character $character, string $source, string $choiceGroup = ''): string
    {
        return match ($source) {
            'class' => $character->primary_class?->name ?? 'Unknown Class',
            'race' => $character->race?->name ?? 'Unknown Race',
            'background' => $character->background?->name ?? 'Unknown Background',
            'subclass_feature' => $this->getSubclassFeatureSourceName($character, $choiceGroup),
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
                        'slug' => $option['skill']['slug'] ?? $option['skill_slug'] ?? null,
                        'name' => $option['skill']['name'] ?? null,
                    ];
                } else {
                    return [
                        'type' => 'proficiency_type',
                        'slug' => $option['proficiency_type']['slug'] ?? $option['proficiency_type_slug'] ?? null,
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
        $selectedSkillSlugs = $choiceData['selected_skills'] ?? [];
        $selectedProfTypeSlugs = $choiceData['selected_proficiency_types'] ?? [];

        return array_merge($selectedSkillSlugs, $selectedProfTypeSlugs);
    }

    private function buildOptionsEndpoint(array $choiceData): ?string
    {
        $subcategory = $choiceData['proficiency_subcategory'] ?? null;
        $profType = $choiceData['proficiency_type'] ?? null;

        if ($subcategory && $profType !== 'skill') {
            // Musical instruments and gaming sets are stored as top-level categories,
            // not as subcategories of 'tool'. Handle them specially.
            $standaloneCategories = ['musical_instrument', 'gaming_set'];
            if (in_array($subcategory, $standaloneCategories, true)) {
                return "/api/v1/lookups/proficiency-types?category={$subcategory}";
            }

            return "/api/v1/lookups/proficiency-types?category={$profType}&subcategory={$subcategory}";
        }

        return null;
    }

    private function getSubclassFeatureSourceName(Character $character, string $choiceGroup): string
    {
        // Choice group format for subclass_feature is "Feature Name (Subclass):base_choice_group"
        // Extract the feature name directly from the choice group
        if (str_contains($choiceGroup, ':')) {
            $featureName = substr($choiceGroup, 0, strrpos($choiceGroup, ':'));
            // Remove subclass suffix in parentheses if present, e.g., "Acolyte of Nature (Nature Domain)" -> "Acolyte of Nature"
            if (preg_match('/^(.+?)\s*\([^)]+\)$/', $featureName, $matches)) {
                return $matches[1];
            }

            return $featureName;
        }

        // Fallback to database lookup
        $subclass = $character->characterClasses->first()?->subclass;
        if (! $subclass) {
            return 'Unknown Feature';
        }

        $feature = $subclass->getFeatureByProficiencyChoiceGroup($choiceGroup);

        return $feature?->feature_name ?? 'Unknown Feature';
    }
}

<?php

namespace App\Services\ChoiceHandlers;

use App\DTOs\PendingChoice;
use App\Exceptions\InvalidSelectionException;
use App\Models\Character;
use App\Models\OptionalFeature;
use Illuminate\Support\Collection;

class OptionalFeatureChoiceHandler extends AbstractChoiceHandler
{
    /**
     * Map counter names to optional feature types.
     *
     * @var array<string, string>
     */
    private const COUNTER_TO_FEATURE_TYPE = [
        'Maneuvers Known' => 'maneuver',
        'Metamagic Known' => 'metamagic',
        'Infusions Known' => 'artificer_infusion',
        'Fighting Styles Known' => 'fighting_style',
        'Runes Known' => 'rune',
        'Arcane Shots Known' => 'arcane_shot',
        'Elemental Disciplines Known' => 'elemental_discipline',
        'Eldritch Invocations Known' => 'eldritch_invocation',
    ];

    public function getType(): string
    {
        return 'optional_feature';
    }

    public function getChoices(Character $character): Collection
    {
        $choices = collect();

        // Get character's classes with their counters
        $characterClasses = $character->characterClasses()
            ->with(['characterClass.counters', 'subclass.counters'])
            ->get();

        foreach ($characterClasses as $charClass) {
            $classLevel = $charClass->level;
            $className = $charClass->characterClass->name;
            $classId = $charClass->class_id;
            $subclassName = $charClass->subclass?->name;

            // Check base class counters
            $this->processClassCounters(
                $charClass->characterClass->counters ?? collect(),
                $classLevel,
                $className,
                $classId,
                null,
                $character,
                $choices
            );

            // Check subclass counters
            if ($charClass->subclass) {
                $this->processClassCounters(
                    $charClass->subclass->counters ?? collect(),
                    $classLevel,
                    $className,
                    $classId,
                    $subclassName,
                    $character,
                    $choices
                );
            }
        }

        return $choices;
    }

    public function resolve(Character $character, PendingChoice $choice, array $selection): void
    {
        $optionalFeatureId = $selection['optional_feature_id'] ?? null;

        if (! $optionalFeatureId) {
            throw new InvalidSelectionException(
                $choice->id,
                'missing',
                'optional_feature_id is required'
            );
        }

        // Validate optional feature exists
        $optionalFeature = OptionalFeature::find($optionalFeatureId);
        if (! $optionalFeature) {
            throw new InvalidSelectionException(
                $choice->id,
                'invalid',
                "Optional feature {$optionalFeatureId} not found"
            );
        }

        // Get class_id from metadata or parse from choice id
        $parsed = $this->parseChoiceId($choice->id);
        $classId = $choice->metadata['class_id'] ?? $parsed['sourceId'];

        // Create the feature selection record
        $character->featureSelections()->create([
            'optional_feature_id' => $optionalFeatureId,
            'class_id' => $classId,
            'subclass_name' => $choice->metadata['subclass_name'] ?? null,
            'level_acquired' => $parsed['level'] ?? $character->total_level,
        ]);
    }

    public function canUndo(Character $character, PendingChoice $choice): bool
    {
        // Optional features can be swapped/retrained
        return true;
    }

    public function undo(Character $character, PendingChoice $choice): void
    {
        $optionalFeatureId = $choice->metadata['optional_feature_id'] ?? null;

        if (! $optionalFeatureId) {
            return;
        }

        // Delete the feature selection record
        $character->featureSelections()
            ->where('optional_feature_id', $optionalFeatureId)
            ->delete();

        $character->load('featureSelections');
    }

    /**
     * Process counters from a class/subclass to find choice allowances.
     */
    private function processClassCounters(
        Collection $counters,
        int $classLevel,
        string $className,
        int $classId,
        ?string $subclassName,
        Character $character,
        Collection &$choices
    ): void {
        foreach (self::COUNTER_TO_FEATURE_TYPE as $counterName => $featureType) {
            // Find the highest counter value at or below the character's class level
            $relevantCounters = $counters
                ->filter(fn ($c) => $c->counter_name === $counterName && $c->level <= $classLevel)
                ->sortByDesc('level');

            if ($relevantCounters->isEmpty()) {
                continue;
            }

            $maxCounter = $relevantCounters->first();

            // Count how many of this feature type the character has already selected
            $selected = $this->countSelectedFeatures($character, $featureType);

            // Calculate remaining choices
            $allowed = $maxCounter->counter_value;
            $remaining = max(0, $allowed - $selected);

            // Build options endpoint
            $optionsEndpoint = $this->buildOptionsEndpoint($featureType, $className, $subclassName, $character->total_level);

            // Create unique choice ID
            $choiceId = $this->generateChoiceId(
                'optional_feature',
                'class',
                $classId,
                $maxCounter->level,
                "{$featureType}_1"
            );

            $choice = new PendingChoice(
                id: $choiceId,
                type: 'optional_feature',
                subtype: $featureType,
                source: 'class',
                sourceName: $className,
                levelGranted: $maxCounter->level,
                required: true,
                quantity: $allowed,
                remaining: $remaining,
                selected: [],
                options: [],
                optionsEndpoint: $optionsEndpoint,
                metadata: [
                    'class_id' => $classId,
                    'subclass_name' => $subclassName,
                    'counter_name' => $counterName,
                ],
            );

            $choices->push($choice);
        }
    }

    /**
     * Count how many features of a given type the character has selected.
     */
    private function countSelectedFeatures(Character $character, string $featureType): int
    {
        return $character->featureSelections()
            ->with('optionalFeature')
            ->get()
            ->filter(fn ($fs) => $fs->optionalFeature !== null)
            ->filter(fn ($fs) => $fs->optionalFeature->feature_type?->value === $featureType)
            ->count();
    }

    /**
     * Build the options endpoint URL for fetching available features.
     */
    private function buildOptionsEndpoint(
        string $featureType,
        string $className,
        ?string $subclassName,
        int $characterLevel
    ): string {
        $params = [
            'feature_type' => $featureType,
            'level_requirement' => '<='.$characterLevel,
        ];

        // Add class filter if applicable
        $classSlug = strtolower(str_replace(' ', '-', $className));
        $params['class'] = $classSlug;

        // Add subclass filter if applicable
        if ($subclassName) {
            $params['subclass'] = $subclassName;
        }

        return '/api/v1/optional-features?'.http_build_query($params);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\WizardFlowTesting;

use App\Models\Background;
use App\Models\CharacterClass;
use App\Models\Race;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Executes wizard flow steps via internal Laravel routing.
 * Uses app()->handle() to avoid network overhead and container connectivity issues.
 */
class FlowExecutor
{
    private StateSnapshot $snapshot;

    private SwitchValidator $validator;

    // Track current selections for switches
    private ?Race $currentRace = null;

    private ?Race $currentSubrace = null;

    private ?CharacterClass $currentClass = null;

    private ?CharacterClass $currentSubclass = null;

    private ?Background $currentBackground = null;

    private ?string $equipmentMode = null;

    private ?string $previousEquipmentMode = null;

    private EquipmentValidator $equipmentValidator;

    private SubclassValidator $subclassValidator;

    private CompletionValidator $completionValidator;

    /** @var array<string, array<string>> Equipment selections by choice_group */
    private array $equipmentSelections = [];

    public function __construct()
    {
        $this->snapshot = new StateSnapshot;
        $this->validator = new SwitchValidator;
        $this->equipmentValidator = new EquipmentValidator;
        $this->subclassValidator = new SubclassValidator;
        $this->completionValidator = new CompletionValidator;
    }

    /**
     * Execute a complete wizard flow.
     */
    public function execute(array $flow, CharacterRandomizer $randomizer, int $iteration = 1): FlowResult
    {
        $result = new FlowResult($iteration, $randomizer->getSeed());
        $characterId = null;

        // Reset state for new execution
        $this->resetState();

        foreach ($flow as $step) {
            try {
                // Capture state before if this is a switch, equipment-related, subclass, or validate step
                $snapshotBefore = null;
                $needsBeforeSnapshot = $this->isSwitch($step)
                    || $this->shouldValidateEquipment($step)
                    || $this->shouldValidateSubclass($step)
                    || $step['action'] === 'validate';

                if ($characterId && $needsBeforeSnapshot) {
                    $snapshotBefore = $this->snapshot->capture($characterId);
                }

                // Execute the step
                $response = $this->executeStep($step, $characterId, $randomizer);

                // Handle character creation
                if ($step['action'] === 'create' && isset($response['data']['id'])) {
                    $characterId = $response['data']['id'];
                    $publicId = $response['data']['public_id'];
                    $result->setCharacter($characterId, $publicId);
                }

                // Skip conditional steps that don't apply
                if ($response === null) {
                    $result->addStep($step, 'skipped');

                    continue;
                }

                // Check for HTTP errors
                if (isset($response['error']) && $response['error']) {
                    $result->addStep($step, 'http_error', null, ['status' => $response['status'] ?? 500]);
                    $result->addError($step, new \RuntimeException($response['message'] ?? 'HTTP error'));

                    break;
                }

                // Capture state after
                $snapshotAfter = $characterId ? $this->snapshot->capture($characterId) : null;

                // Validate switch behavior
                if ($this->isSwitch($step) && $snapshotBefore && $snapshotAfter) {
                    $validation = $this->validator->validate(
                        $step['action'],
                        $snapshotBefore,
                        $snapshotAfter,
                        $this->equipmentMode
                    );

                    if (! $validation->passed) {
                        $result->addStep($step, 'fail', $snapshotAfter, $response);
                        $result->addFailure($step, $validation, $snapshotBefore, $snapshotAfter);

                        continue; // Continue to find more issues
                    }
                }

                // Validate equipment after mode selection or equipment choices
                if ($snapshotAfter && $this->shouldValidateEquipment($step)) {
                    $equipmentValidation = $this->validateEquipmentAfterStep($step, $snapshotBefore, $snapshotAfter);

                    if ($equipmentValidation && ! $equipmentValidation->passed) {
                        $result->addStep($step, 'fail', $snapshotAfter, $response);
                        $result->addFailure($step, $equipmentValidation, $snapshotBefore, $snapshotAfter);

                        continue;
                    }
                }

                // Validate subclass features after subclass selection
                if ($snapshotAfter && $this->shouldValidateSubclass($step) && $this->currentSubclass) {
                    $subclassValidation = $this->subclassValidator->validateSubclassFeatures(
                        $snapshotAfter,
                        $this->currentSubclass,
                        1 // Level 1 for now
                    );

                    if (! $subclassValidation->passed) {
                        $result->addStep($step, 'fail', $snapshotAfter, $response);
                        $result->addFailure($step, $subclassValidation, $snapshotBefore, $snapshotAfter);

                        continue;
                    }
                }

                // Validate character completion after the validate step
                if ($step['action'] === 'validate' && $snapshotAfter && $snapshotBefore) {
                    $completionValidation = $this->completionValidator->validate($snapshotAfter);

                    if (! $completionValidation->passed) {
                        $result->addStep($step, 'fail', $snapshotAfter, $response);
                        $result->addFailure($step, $completionValidation, $snapshotBefore, $snapshotAfter);

                        continue;
                    }
                }

                $result->addStep($step, 'ok', $snapshotAfter, $response);

            } catch (\Throwable $e) {
                Log::error('Wizard flow step failed', [
                    'step' => $step,
                    'character_id' => $characterId,
                    'exception' => $e->getMessage(),
                ]);

                $result->addError($step, $e);

                break;
            }
        }

        return $result;
    }

    /**
     * Execute a single step.
     */
    private function executeStep(array $step, ?int $characterId, CharacterRandomizer $randomizer): ?array
    {
        return match ($step['action']) {
            'create' => $this->createCharacter($randomizer),
            'set_race' => $this->setRace($characterId, $randomizer, $step['force_race'] ?? null),
            'set_subrace' => $this->setSubrace($characterId, $randomizer),
            'set_class' => $this->setClass($characterId, $randomizer, $step['force_class'] ?? null),
            'set_subclass' => $this->setSubclass($characterId, $randomizer),
            'set_background' => $this->setBackground($characterId, $randomizer),
            'set_ability_scores' => $this->setAbilityScores($characterId, $randomizer),
            'resolve_proficiency_choices' => $this->resolveChoices($characterId, $randomizer, 'proficiency'),
            'resolve_language_choices' => $this->resolveChoices($characterId, $randomizer, 'language'),
            'set_equipment_mode' => $this->setEquipmentMode($characterId, $randomizer),
            'resolve_equipment_choices' => $this->resolveChoices($characterId, $randomizer, 'equipment'),
            'resolve_spell_choices' => $this->resolveChoices($characterId, $randomizer, 'spell'),
            'resolve_remaining_choices' => $this->resolveRemainingChoices($characterId, $randomizer),
            'resolve_all_required' => $this->resolveAllRequired($characterId, $randomizer),
            'set_details' => $this->setDetails($characterId, $randomizer),
            'validate' => $this->validateCharacter($characterId),
            'switch_race' => $this->switchRace($characterId, $randomizer),
            'switch_background' => $this->switchBackground($characterId, $randomizer),
            'switch_class' => $this->switchClass($characterId, $randomizer, $step),
            default => throw new \RuntimeException("Unknown action: {$step['action']}"),
        };
    }

    private function createCharacter(CharacterRandomizer $randomizer): array
    {
        return $this->makeRequest('POST', '/api/v1/characters', [
            'public_id' => $randomizer->generatePublicId(),
            'name' => $randomizer->randomName(),
        ]);
    }

    private function setRace(int $characterId, CharacterRandomizer $randomizer, ?string $forceRace = null): array
    {
        if ($forceRace) {
            $race = Race::where('slug', $forceRace)->firstOrFail();
        } else {
            $race = $randomizer->randomRace();
        }

        $this->currentRace = $race;
        $this->currentSubrace = null;

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'race_slug' => $race->slug,
        ]);
    }

    private function setSubrace(int $characterId, CharacterRandomizer $randomizer): ?array
    {
        if (! $this->currentRace || $this->currentRace->subraces->isEmpty()) {
            return null; // Skip - no subraces available
        }

        $subrace = $randomizer->randomSubrace($this->currentRace);
        if (! $subrace) {
            return null;
        }

        $this->currentSubrace = $subrace;

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'race_slug' => $subrace->slug,
        ]);
    }

    private function setClass(int $characterId, CharacterRandomizer $randomizer, ?string $forceClass = null): array
    {
        if ($forceClass) {
            $class = CharacterClass::where('slug', $forceClass)->firstOrFail();
        } else {
            $class = $randomizer->randomClass();
        }

        $this->currentClass = $class;

        // If already has a class, replace it
        if ($this->characterHasClass($characterId)) {
            $currentClassSlug = $this->getCurrentClassSlug($characterId);

            return $this->makeRequest('PUT', "/api/v1/characters/{$characterId}/classes/{$currentClassSlug}", [
                'class_slug' => $class->slug,
            ]);
        } else {
            return $this->makeRequest('POST', "/api/v1/characters/{$characterId}/classes", [
                'class_slug' => $class->slug,
                'force' => true,
            ]);
        }
    }

    private function setSubclass(int $characterId, CharacterRandomizer $randomizer): ?array
    {
        if (! $this->currentClass || $this->currentClass->subclass_level !== 1) {
            return null; // Skip - no subclass at level 1
        }

        $subclasses = $this->currentClass->subclasses;
        if ($subclasses->isEmpty()) {
            return null;
        }

        $subclass = $subclasses[$randomizer->randomInt(0, $subclasses->count() - 1)];
        $this->currentSubclass = $subclass;

        return $this->makeRequest(
            'PUT',
            "/api/v1/characters/{$characterId}/classes/{$this->currentClass->slug}/subclass",
            ['subclass_slug' => $subclass->slug]
        );
    }

    private function setBackground(int $characterId, CharacterRandomizer $randomizer): array
    {
        $background = $randomizer->randomBackground();
        $this->currentBackground = $background;

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'background_slug' => $background->slug,
        ]);
    }

    private function setAbilityScores(int $characterId, CharacterRandomizer $randomizer): array
    {
        $scores = $randomizer->randomAbilityScores();

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", $scores);
    }

    private function resolveChoices(int $characterId, CharacterRandomizer $randomizer, string $type): ?array
    {
        // Get pending choices of this type
        $choicesResponse = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/pending-choices");

        // Response structure is data.choices
        $allChoices = $choicesResponse['data']['choices'] ?? [];

        // Filter by type AND only include choices with remaining > 0
        $choices = array_filter($allChoices, function ($c) use ($type) {
            return ($c['type'] ?? '') === $type && ($c['remaining'] ?? 0) > 0;
        });

        if (empty($choices)) {
            return null; // Skip - no choices to resolve
        }

        $lastResponse = null;

        // Track already-selected values to avoid collisions between choices
        // (e.g., selecting the same language for both race and background)
        $alreadySelected = [];

        foreach ($choices as $choice) {
            $choiceId = $choice['id'];
            $options = $choice['options'] ?? [];
            $choiceType = $choice['type'] ?? '';

            // Skip if no options or if options need to be fetched from endpoint
            if (empty($options)) {
                // For spells, we need to fetch from endpoint
                if ($choiceType === 'spell' && ! empty($choice['options_endpoint'])) {
                    $spellsResponse = $this->makeRequest('GET', $choice['options_endpoint']);
                    $options = $spellsResponse['data'] ?? [];
                }

                if (empty($options)) {
                    continue;
                }
            }

            // Determine what to select based on choice type
            $count = $choice['quantity'] ?? $choice['remaining'] ?? 1;

            if ($choiceType === 'equipment') {
                // Equipment uses 'option' field (a, b, c, etc.)
                // Filter to only valid options (non-category, or category with selectable items)
                $validOptions = array_filter($options, function ($opt) {
                    if (! ($opt['is_category'] ?? false)) {
                        return true; // Non-category options are always valid
                    }
                    // Category options need at least one selectable item
                    $selectableItems = array_filter(
                        $opt['items'] ?? [],
                        fn ($item) => ! ($item['is_fixed'] ?? false)
                    );

                    return ! empty($selectableItems);
                });

                if (empty($validOptions)) {
                    // No valid options available - skip this choice
                    continue;
                }

                // Pick a random valid option
                $validOptionValues = array_column($validOptions, 'option');
                $selected = $randomizer->pickRandom($validOptionValues, 1);

                // Find the selected option
                $selectedOption = $selected[0] ?? null;
                $foundOption = null;
                foreach ($validOptions as $opt) {
                    if (($opt['option'] ?? '') === $selectedOption) {
                        $foundOption = $opt;
                        break;
                    }
                }

                // Track what items we expect to get from this choice
                $choiceGroup = $choice['metadata']['choice_group'] ?? $choiceId;
                $expectedItems = [];

                // If it's a category option, we need to provide item_selections
                $itemSelections = null;
                if ($foundOption && ($foundOption['is_category'] ?? false)) {
                    // Get non-fixed items (the ones user must choose from)
                    $selectableItems = array_filter(
                        $foundOption['items'] ?? [],
                        fn ($item) => ! ($item['is_fixed'] ?? false)
                    );

                    // Pick one random item from the category
                    $itemSlugs = array_column($selectableItems, 'slug');
                    $pickedItem = $randomizer->pickRandom($itemSlugs, 1);
                    $itemSelections = [$selectedOption => $pickedItem];
                    $expectedItems = array_merge($expectedItems, $pickedItem);

                    // Also include fixed items that come with this option
                    $fixedItems = array_filter(
                        $foundOption['items'] ?? [],
                        fn ($item) => ($item['is_fixed'] ?? false) && ! ($item['is_pack'] ?? false)
                    );
                    foreach ($fixedItems as $item) {
                        $expectedItems[] = $item['slug'];
                    }
                } else {
                    // Non-category option: all items are granted
                    foreach ($foundOption['items'] ?? [] as $item) {
                        if (! ($item['is_pack'] ?? false)) {
                            $expectedItems[] = $item['slug'];
                        }
                        // For packs, add the contents
                        if (($item['is_pack'] ?? false) && ! empty($item['contents'])) {
                            foreach ($item['contents'] as $content) {
                                $expectedItems[] = $content['slug'];
                            }
                        }
                    }
                }

                // Track the selections for validation
                $this->equipmentSelections[$choiceGroup] = $expectedItems;

                // Build the request payload
                $payload = ['selected' => $selected];
                if ($itemSelections !== null) {
                    $payload['item_selections'] = $itemSelections;
                }

                $lastResponse = $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choiceId}", $payload);

                if (isset($lastResponse['error']) && $lastResponse['error']) {
                    return $lastResponse;
                }

                continue; // Skip the generic POST below since we already made the request
            } elseif ($choiceType === 'proficiency' || $choiceType === 'language') {
                // Proficiencies and languages use 'slug'
                $slugs = array_map(fn ($o) => $o['slug'] ?? '', $options);
                $slugs = array_filter($slugs);
                // Exclude already-selected values to avoid duplicates across choices
                $slugs = array_diff($slugs, $alreadySelected);
                $selected = $randomizer->pickRandom(array_values($slugs), min($count, count($slugs)));
            } elseif ($choiceType === 'spell') {
                // Spells use 'slug'
                $slugs = array_map(fn ($o) => $o['slug'] ?? '', $options);
                $slugs = array_filter($slugs);
                // Exclude already-selected spells to avoid duplicates
                $slugs = array_diff($slugs, $alreadySelected);
                $selected = $randomizer->pickRandom(array_values($slugs), min($count, count($slugs)));
            } else {
                // Generic fallback
                $slugs = array_map(fn ($o) => $o['slug'] ?? $o['value'] ?? '', $options);
                $slugs = array_filter($slugs);
                $selected = $randomizer->pickRandom($slugs, min($count, count($slugs)));
            }

            if (empty($selected)) {
                continue;
            }

            // Track what we selected to avoid duplicates in subsequent choices
            foreach ((array) $selected as $sel) {
                $alreadySelected[] = $sel;
            }

            $lastResponse = $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choiceId}", [
                'selected' => $selected,
            ]);

            if (isset($lastResponse['error']) && $lastResponse['error']) {
                return $lastResponse;
            }
        }

        return $lastResponse ?? ['data' => [], 'message' => 'No choices resolved'];
    }

    /**
     * Resolve any remaining required choices that weren't handled by specific handlers.
     * This catches choice types like size, feat, optional_feature, ability_score, etc.
     */
    private function resolveRemainingChoices(int $characterId, CharacterRandomizer $randomizer): ?array
    {
        // Already-handled types
        $handledTypes = ['proficiency', 'language', 'equipment', 'equipment_mode', 'spell'];

        $choicesResponse = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/pending-choices");
        $allChoices = $choicesResponse['data']['choices'] ?? [];

        // Filter to required choices of types we haven't explicitly handled
        $remainingChoices = array_filter($allChoices, function ($c) use ($handledTypes) {
            $type = $c['type'] ?? '';
            $required = $c['required'] ?? false;

            return $required && ! in_array($type, $handledTypes, true);
        });

        if (empty($remainingChoices)) {
            return null;
        }

        $lastResponse = null;
        $alreadySelected = [];

        foreach ($remainingChoices as $choice) {
            $choiceId = $choice['id'];
            $choiceType = $choice['type'] ?? '';
            $options = $choice['options'] ?? [];
            $count = $choice['quantity'] ?? $choice['remaining'] ?? 1;

            // Fetch options from endpoint if not inline
            if (empty($options) && ! empty($choice['options_endpoint'])) {
                $optionsResponse = $this->makeRequest('GET', $choice['options_endpoint']);
                $options = $optionsResponse['data'] ?? [];
            }

            if (empty($options)) {
                continue;
            }

            // Handle by type
            $selected = match ($choiceType) {
                'size' => $this->selectSize($options, $randomizer),
                'feat' => $this->selectFeat($options, $randomizer, $alreadySelected),
                'optional_feature' => $this->selectOptionalFeature($options, $randomizer, $count, $alreadySelected),
                'ability_score' => $this->selectAbilityScore($options, $randomizer),
                default => $this->selectGeneric($options, $randomizer, $count, $alreadySelected),
            };

            if (empty($selected)) {
                continue;
            }

            // Track selections to avoid duplicates
            foreach ((array) $selected as $sel) {
                $alreadySelected[] = $sel;
            }

            $lastResponse = $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choiceId}", [
                'selected' => (array) $selected,
            ]);

            if (isset($lastResponse['error']) && $lastResponse['error']) {
                return $lastResponse;
            }
        }

        return $lastResponse ?? ['data' => [], 'message' => 'No remaining choices resolved'];
    }

    private function selectSize(array $options, CharacterRandomizer $randomizer): array
    {
        // Size options have 'id' field (e.g., 'small', 'medium')
        $sizeIds = array_column($options, 'id');
        if (empty($sizeIds)) {
            $sizeIds = array_column($options, 'slug');
        }

        return $randomizer->pickRandom(array_filter($sizeIds), 1);
    }

    private function selectFeat(array $options, CharacterRandomizer $randomizer, array $alreadySelected): array
    {
        // Feats use 'slug' field
        $slugs = array_column($options, 'slug');
        $slugs = array_diff(array_filter($slugs), $alreadySelected);

        return $randomizer->pickRandom(array_values($slugs), 1);
    }

    private function selectOptionalFeature(array $options, CharacterRandomizer $randomizer, int $count, array $alreadySelected): array
    {
        // Optional features use 'slug' field
        $slugs = array_column($options, 'slug');
        $slugs = array_diff(array_filter($slugs), $alreadySelected);

        return $randomizer->pickRandom(array_values($slugs), min($count, count($slugs)));
    }

    private function selectAbilityScore(array $options, CharacterRandomizer $randomizer): array
    {
        // Ability score choices - select from available options
        $values = array_column($options, 'value');
        if (empty($values)) {
            $values = array_column($options, 'slug');
        }
        if (empty($values)) {
            $values = array_column($options, 'id');
        }

        return $randomizer->pickRandom(array_filter($values), 1);
    }

    private function selectGeneric(array $options, CharacterRandomizer $randomizer, int $count, array $alreadySelected): array
    {
        // Generic fallback - try slug, then value, then id
        $values = array_column($options, 'slug');
        if (empty(array_filter($values))) {
            $values = array_column($options, 'value');
        }
        if (empty(array_filter($values))) {
            $values = array_column($options, 'id');
        }

        $values = array_diff(array_filter($values), $alreadySelected);

        return $randomizer->pickRandom(array_values($values), min($count, count($values)));
    }

    /**
     * Final pass: resolve ALL remaining required choices regardless of type.
     * Loops until no required choices remain or max iterations reached.
     */
    private function resolveAllRequired(int $characterId, CharacterRandomizer $randomizer): ?array
    {
        $maxIterations = 10;
        $lastResponse = null;
        $alreadySelected = [];

        for ($i = 0; $i < $maxIterations; $i++) {
            $choicesResponse = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/pending-choices");
            $allChoices = $choicesResponse['data']['choices'] ?? [];

            // Filter to required choices with remaining > 0
            $pending = array_filter($allChoices, function ($c) {
                return ($c['required'] ?? false) === true && ($c['remaining'] ?? 0) > 0;
            });

            if (empty($pending)) {
                break; // All required choices resolved
            }

            // Process each pending choice
            foreach ($pending as $choice) {
                $choiceId = $choice['id'];
                $choiceType = $choice['type'] ?? '';
                $options = $choice['options'] ?? [];
                $count = $choice['remaining'] ?? 1;

                // Fetch options from endpoint if not inline
                if (empty($options) && ! empty($choice['options_endpoint'])) {
                    $optionsResponse = $this->makeRequest('GET', $choice['options_endpoint']);
                    $options = $optionsResponse['data'] ?? [];
                }

                if (empty($options)) {
                    continue;
                }

                // Select based on type
                $selected = match ($choiceType) {
                    'equipment' => $this->selectEquipmentOption($options, $randomizer),
                    'proficiency' => $this->selectProficiency($options, $randomizer, $count, $alreadySelected),
                    'language' => $this->selectLanguage($options, $randomizer, $count, $alreadySelected),
                    'spell' => $this->selectSpells($options, $randomizer, $count, $alreadySelected),
                    'size' => $this->selectSize($options, $randomizer),
                    'feat' => $this->selectFeat($options, $randomizer, $alreadySelected),
                    'optional_feature' => $this->selectOptionalFeature($options, $randomizer, $count, $alreadySelected),
                    'ability_score' => $this->selectAbilityScore($options, $randomizer),
                    'subclass' => $this->selectSubclass($options, $randomizer),
                    default => $this->selectGeneric($options, $randomizer, $count, $alreadySelected),
                };

                if (empty($selected)) {
                    continue;
                }

                // Track selections
                foreach ((array) $selected as $sel) {
                    $alreadySelected[] = $sel;
                }

                $payload = ['selected' => (array) $selected];

                // Equipment choices need special handling for item_selections
                if ($choiceType === 'equipment') {
                    $selectedOption = $selected[0] ?? null;
                    $foundOption = null;
                    foreach ($options as $opt) {
                        if (($opt['option'] ?? '') === $selectedOption) {
                            $foundOption = $opt;
                            break;
                        }
                    }

                    if ($foundOption && ($foundOption['is_category'] ?? false)) {
                        $selectableItems = array_filter(
                            $foundOption['items'] ?? [],
                            fn ($item) => ! ($item['is_fixed'] ?? false)
                        );
                        if (! empty($selectableItems)) {
                            $itemSlugs = array_column($selectableItems, 'slug');
                            $pickedItem = $randomizer->pickRandom($itemSlugs, 1);
                            $payload['item_selections'] = [$selectedOption => $pickedItem];
                        }
                    }
                }

                $lastResponse = $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choiceId}", $payload);

                if (isset($lastResponse['error']) && $lastResponse['error']) {
                    return $lastResponse;
                }
            }
        }

        return $lastResponse ?? ['data' => [], 'message' => 'All required choices resolved'];
    }

    private function selectProficiency(array $options, CharacterRandomizer $randomizer, int $count, array $alreadySelected): array
    {
        $slugs = array_column($options, 'slug');
        $slugs = array_diff(array_filter($slugs), $alreadySelected);

        return $randomizer->pickRandom(array_values($slugs), min($count, count($slugs)));
    }

    private function selectLanguage(array $options, CharacterRandomizer $randomizer, int $count, array $alreadySelected): array
    {
        $slugs = array_column($options, 'slug');
        $slugs = array_diff(array_filter($slugs), $alreadySelected);

        return $randomizer->pickRandom(array_values($slugs), min($count, count($slugs)));
    }

    private function selectSpells(array $options, CharacterRandomizer $randomizer, int $count, array $alreadySelected): array
    {
        $slugs = array_column($options, 'slug');
        $slugs = array_diff(array_filter($slugs), $alreadySelected);

        return $randomizer->pickRandom(array_values($slugs), min($count, count($slugs)));
    }

    private function selectEquipmentOption(array $options, CharacterRandomizer $randomizer): array
    {
        // Filter to valid options only
        $validOptions = array_filter($options, function ($opt) {
            if (! ($opt['is_category'] ?? false)) {
                return true;
            }
            $selectableItems = array_filter(
                $opt['items'] ?? [],
                fn ($item) => ! ($item['is_fixed'] ?? false)
            );

            return ! empty($selectableItems);
        });

        if (empty($validOptions)) {
            return [];
        }

        $optionValues = array_column($validOptions, 'option');

        return $randomizer->pickRandom($optionValues, 1);
    }

    private function selectSubclass(array $options, CharacterRandomizer $randomizer): array
    {
        $slugs = array_column($options, 'slug');

        return $randomizer->pickRandom(array_filter($slugs), 1);
    }

    private function setEquipmentMode(int $characterId, CharacterRandomizer $randomizer): ?array
    {
        // Track previous mode for validation
        $this->previousEquipmentMode = $this->equipmentMode;
        $this->equipmentMode = $randomizer->randomEquipmentMode();

        // Check if there's an equipment mode choice pending
        $choicesResponse = $this->makeRequest('GET', "/api/v1/characters/{$characterId}/pending-choices");

        $allChoices = $choicesResponse['data']['choices'] ?? [];
        $choices = array_filter($allChoices, fn ($c) => ($c['type'] ?? '') === 'equipment_mode');

        if (empty($choices)) {
            return null;
        }

        $choice = array_values($choices)[0];

        // Clear equipment selections when switching modes (they'll be re-made if equipment mode)
        $this->equipmentSelections = [];

        return $this->makeRequest('POST', "/api/v1/characters/{$characterId}/choices/{$choice['id']}", [
            'selected' => [$this->equipmentMode],
        ]);
    }

    private function setDetails(int $characterId, CharacterRandomizer $randomizer): array
    {
        $alignments = [
            'Lawful Good', 'Neutral Good', 'Chaotic Good',
            'Lawful Neutral', 'True Neutral', 'Chaotic Neutral',
            'Lawful Evil', 'Neutral Evil', 'Chaotic Evil',
        ];

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'name' => $randomizer->randomName(),
            'alignment' => $alignments[$randomizer->randomInt(0, count($alignments) - 1)],
        ]);
    }

    private function validateCharacter(int $characterId): array
    {
        return $this->makeRequest('GET', "/api/v1/characters/{$characterId}/validate");
    }

    private function switchRace(int $characterId, CharacterRandomizer $randomizer): array
    {
        $newRace = $randomizer->differentRace($this->currentRace);
        $this->currentRace = $newRace;
        $this->currentSubrace = null;

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'race_slug' => $newRace->slug,
        ]);
    }

    private function switchBackground(int $characterId, CharacterRandomizer $randomizer): array
    {
        $newBackground = $randomizer->differentBackground($this->currentBackground);
        $this->currentBackground = $newBackground;

        return $this->makeRequest('PATCH', "/api/v1/characters/{$characterId}", [
            'background_slug' => $newBackground->slug,
        ]);
    }

    private function switchClass(int $characterId, CharacterRandomizer $randomizer, array $step): array
    {
        $spellcaster = null;
        if (isset($step['class_type_to'])) {
            $spellcaster = $step['class_type_to'] === 'spellcaster';
        }

        $newClass = $randomizer->differentClass($this->currentClass, $spellcaster);
        $oldClassSlug = $this->currentClass->slug;
        $this->currentClass = $newClass;

        return $this->makeRequest('PUT', "/api/v1/characters/{$characterId}/classes/{$oldClassSlug}", [
            'class_slug' => $newClass->slug,
        ]);
    }

    /**
     * Make an internal request to the Laravel application.
     */
    private function makeRequest(string $method, string $uri, array $data = []): array
    {
        // Build the request
        $request = Request::create(
            $uri,
            $method,
            $method === 'GET' ? $data : [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $method !== 'GET' ? json_encode($data) : null
        );

        // Handle the request through the application
        $response = app()->handle($request);

        // Get the response content
        $content = $response->getContent();
        $statusCode = $response->getStatusCode();

        // Parse JSON response
        $decoded = json_decode($content, true) ?? [];

        // Check for error status codes
        if ($statusCode >= 400) {
            return [
                'error' => true,
                'status' => $statusCode,
                'message' => $decoded['message'] ?? 'Request failed',
                'errors' => $decoded['errors'] ?? [],
            ];
        }

        return $decoded;
    }

    private function isSwitch(array $step): bool
    {
        return ($step['is_switch'] ?? false) || str_starts_with($step['action'], 'switch_');
    }

    private function characterHasClass(int $characterId): bool
    {
        $response = $this->makeRequest('GET', "/api/v1/characters/{$characterId}");
        $classes = $response['data']['classes'] ?? [];

        return ! empty($classes);
    }

    private function getCurrentClassSlug(int $characterId): ?string
    {
        $response = $this->makeRequest('GET', "/api/v1/characters/{$characterId}");
        $classes = $response['data']['classes'] ?? [];

        return $classes[0]['class']['slug'] ?? null;
    }

    private function resetState(): void
    {
        $this->currentRace = null;
        $this->currentSubrace = null;
        $this->currentClass = null;
        $this->currentSubclass = null;
        $this->currentBackground = null;
        $this->equipmentMode = null;
        $this->previousEquipmentMode = null;
        $this->equipmentSelections = [];
    }

    /**
     * Determine if equipment should be validated after this step.
     */
    private function shouldValidateEquipment(array $step): bool
    {
        $action = $step['action'];

        return in_array($action, [
            'set_equipment_mode',
            'resolve_equipment_choices',
        ], true);
    }

    /**
     * Determine if subclass features should be validated after this step.
     */
    private function shouldValidateSubclass(array $step): bool
    {
        return $step['action'] === 'set_subclass';
    }

    /**
     * Validate equipment state after a step.
     */
    private function validateEquipmentAfterStep(array $step, ?array $before, array $after): ?ValidationResult
    {
        $action = $step['action'];

        // Get class and background slugs
        $classSlug = $this->currentClass?->slug;
        $backgroundSlug = $this->currentBackground?->slug;

        if ($action === 'set_equipment_mode') {
            // Validate mode switch if switching between modes
            if ($this->previousEquipmentMode !== null && $this->previousEquipmentMode !== $this->equipmentMode && $before) {
                $modeSwitchValidation = $this->equipmentValidator->validateModeSwitch(
                    $before,
                    $after,
                    $this->previousEquipmentMode,
                    $this->equipmentMode
                );

                if (! $modeSwitchValidation->passed) {
                    return $modeSwitchValidation;
                }
            }

            // If switching to equipment mode, validate choices are available
            if ($this->equipmentMode === 'equipment') {
                $pendingChoices = $after['pending_choices']['data']['choices'] ?? [];

                return $this->equipmentValidator->validateEquipmentChoicesAvailable(
                    $pendingChoices,
                    $classSlug
                );
            }

            // If gold mode, validate gold state
            if ($this->equipmentMode === 'gold') {
                return $this->equipmentValidator->validateEquipmentState(
                    $after,
                    'gold',
                    [],
                    $classSlug,
                    $backgroundSlug
                );
            }
        }

        if ($action === 'resolve_equipment_choices') {
            // Check if all equipment choices are resolved (no more pending)
            $pendingChoices = $after['pending_choices']['data']['choices'] ?? [];
            $pendingEquipmentChoices = array_filter(
                $pendingChoices,
                fn ($c) => ($c['type'] ?? '') === 'equipment'
            );
            $allChoicesResolved = empty($pendingEquipmentChoices);

            // Validate equipment state after resolving choices
            return $this->equipmentValidator->validateEquipmentState(
                $after,
                $this->equipmentMode ?? 'equipment',
                $this->equipmentSelections,
                $classSlug,
                $backgroundSlug,
                $allChoicesResolved
            );
        }

        return null;
    }
}
